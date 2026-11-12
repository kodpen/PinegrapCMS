<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", its club events and the sponsorship
 * enquiry: the list of every club event with its kind filter, the event's
 * own page, the staff page that adds an event (the Club Events form, a
 * collection, with the sample events written in when it is made) and the
 * list the home page places; the sponsorship enquiry with the package a
 * link chooses, the staff's list of enquiries and the two e-mails of the
 * enquiry. Included after parts.php, squad.php and mail.php in the same
 * scope (the $sp_* list helpers come from squad.php); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An event is a record of the Club Events form. Its kind is a pick list
 * whose answers are the words parts.php gives, so a link with ?kind=
 * filters the list by the words a visitor sees. When and where are words:
 * the club writes the real day in its own record, the sample never names
 * a date. Featured is the club's choice (office use only): the home page
 * shows the featured events.
 *
 * A sponsorship enquiry is a request: a record of the Sponsorship
 * Enquiries form, read by the staff only. The package starts with what a
 * link sends (?package=: the package's title, as the sponsors page writes
 * it); the status is the club's.
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

// The event cards of a list, in rows of three; $sample is the event the
// canvas shows.
$ev_grid = function ($sample = 'awards') use ($widget_root, $row, $loop, $event_card) {
    return $widget_root(array($row(array($loop(array($event_card($sample)))), '4')));
};
// The long words of a sample event's page: what happens, one item a
// line.
$ev_details = array(
    'awards' => implode("\n", array(lang('Welcome drinks in the clubhouse'), lang('The awards of every team, from the Under-8s to the first team'), lang('Buffet dinner'), lang('Music and dancing until late'))),
    'quiz' => implode("\n", array(lang('Eight rounds of questions, a picture round and a music round'), lang('A raffle with prizes from our sponsors'), lang('The bar and the kitchen stay open all evening'))),
    'open' => implode("\n", array(lang('Football and basketball sessions for every age, every hour'), lang('Meet the coaches of every team'), lang('A tour of the ground and the clubhouse'), lang('Barbecue at lunchtime'))),
    'camp' => implode("\n", array(lang('Skills sessions every morning'), lang('Small-sided matches every afternoon'), lang('A tournament and a medal for everyone on the last day'), lang('Bring a packed lunch and a water bottle'))),
);

// ── Pages ───────────────────────────────────────────────────────────────

// Every club event, narrowed by kind from the address.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('club-events'),
    'folder'           => 'public',
    'title'            => lang('Club Events'),
    'meta_description' => lang('Social evenings, fundraisers, open days and holiday camps at the club, for members, families and new faces.'),
    'tree'             => $page(array(
        $page_header(lang('Club Events'), lang('Social evenings, fundraisers, open days and holiday camps. Choose a kind to narrow the list.')),
        $el('section', 'py-5', lang('Club Events'), array(
            $container(array(
                $sp_filter_bar('events', lang('By kind'), 'kind', $event_kind_titles),
                $widget('events_list'),
            )),
        ), $searchable('sp-events', lang('events, social, quiz night, fundraiser, open day, camp, awards'))),
    )),
);

// One event: its record.
$event_pages[] = array(
    'key'              => 'event',
    'name'             => lang('club-event'),
    'folder'           => 'public',
    'title'            => lang('Club Event'),
    'meta_description' => lang('An event at the club: when and where it is, what happens and who it is for.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

// The staff page that adds an event. The form is the Club Events
// collection; the sample events are written in when the form is made.
$sample_events = array();
foreach ($events as $key => $e) {
    $sample_events[] = array(
        'title'      => $e['title'],
        'kind'       => $event_kinds[$e['kind']],
        'event_when' => $e['when'],
        'place'      => $e['place'],
        'summary'    => $e['summary'],
        'details'    => $ev_details[$key],
        'featured'   => $e['featured'] ? $sp_featured_choices[0] : $sp_featured_choices[1],
    );
}
$event_pages[] = array(
    'key'     => 'event_new',
    'name'    => lang('new-club-event'),
    'folder'  => 'private',
    'title'   => lang('New Club Event'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Club Events'),
        'collection'           => 1,
        'confirmation_message' => lang('The event is published.'),
        'confirmation_page_id' => '{{tab:events}}',
        'sample_records'       => $sample_events,
    ),
    'tree'    => $sp_form_page(lang('New Club Event'),
        lang('Add the event with its day, time and place; it appears in the list as soon as you send it. Mark it featured to show it on the home page.'),
        'event_form'),
);

// The sponsorship enquiry. What is sent shows the form's thank-you note.
$event_pages[] = array(
    'key'              => 'sponsor_enquiry',
    'name'             => lang('sponsorship-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Sponsorship Enquiry'),
    'meta_description' => lang('Ask the club about sponsorship: a shirt, a match day, the academy or a pitchside board. The committee answers within a week.'),
    'form'             => array(
        'form_name'            => lang('Sponsorship Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached the committee. We answer within a week with the details of the package.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New sponsorship enquiry: ^^package^^'),
        'notify_page_id'       => '{{tab:email_sponsor_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your sponsorship enquiry'),
        'confirm_page_id'      => '{{tab:email_sponsor_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Sponsorship Enquiry'),
            lang('Tell us about your business and how you would like to support the club. Every package is agreed with the committee.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('sponsor_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary', lang('Packages'), array(
                            $heading('h2', lang('Ways to support us'), 'psp-display h5 mb-3'),
                            $check_list($package_titles, 'list-unstyled small mb-3'),
                            $link(lang('See the packages'), '{{page:sponsors}}', 'small fw-semibold link-primary'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every enquiry, newest first, with a search.
$event_pages[] = array(
    'key'     => 'staff_sponsors',
    'name'    => lang('all-sponsorship-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Sponsorship Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Sponsorship Enquiries'), lang('Every sponsorship enquiry, newest first: call the business, agree the package and set the status.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_sponsors_list'))),
        )),
    )),
);

// The e-mails of the enquiry: what the club receives and the reply.
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_sponsor_new',
    'name'  => lang('email-new-sponsorship-enquiry'),
    'title' => lang('New Sponsorship Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_sponsor_admin'))),
));
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_sponsor_received',
    'name'  => lang('email-sponsorship-enquiry-received'),
    'title' => lang('Sponsorship Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_sponsor_sender'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

$ev_sample_package = $sponsor_packages['matchday']['title'];
$ev_sample_business = lang('Riverside Bakery');
$ev_sample_message = lang('We would like our name on a home match next season and to bring our staff to watch.');

// A bound value.
$ev_value = function ($sample, $name, $field) use ($span, $bind) {
    return array($span($sample, '', $name, $bind($field)));
};
// The contact's name.
$ev_contact_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};
// The lines of an enquiry, for its e-mails; the staff's adds the contact.
$ev_mail_enquiry = function ($staff) use ($mail_box, $mail_line, $ev_value, $ev_contact_name, $ev_sample_package, $ev_sample_business, $ev_sample_message) {
    $lines = array(
        $mail_line(lang('Business'), $ev_value($ev_sample_business, lang('Business'), 'business')),
        $mail_line(lang('Package'), $ev_value($ev_sample_package, lang('Package'), 'package')),
        $mail_line(lang('Message'), $ev_value($ev_sample_message, lang('Message'), 'message')),
    );
    if ($staff) {
        $lines[] = $mail_line(lang('Contact'), $ev_contact_name());
        $lines[] = $mail_line(lang('Email'), $ev_value('jane.cooper@example.com', lang('Email'), 'email'));
        $lines[] = $mail_line(lang('Phone'), $ev_value('+90 555 000 00 00', lang('Phone'), 'phone'));
    }
    return $mail_box($lines, lang('Sponsorship Enquiry'));
};
// The form detail settings of an e-mail page's widget: drawn for the
// submission just sent.
$ev_mail_widget = function ($page_key, $tree) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:sponsor_enquiry}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('This enquiry was not found.'),
        ),
        'tree'   => $tree,
    );
};

// ── Widgets ─────────────────────────────────────────────────────────────

$event_widgets = array(

    // The staff's Club Events form: one event. Featured starts as No.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('sp-event-title', lang('Event Title'), pg_cf_with_role($input('sp-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('sp-event-kind', lang('Kind'), pg_cf_with_role($select('sp-event-kind', 'kind', lang('Choose a kind…'), $event_kind_titles), 'category'))), '6'),
                $col(array($field('sp-event-place', lang('Place'), $input('sp-event-place', 'text', 'place', 'off'))), '6'),
            ), '3'),
            $field('sp-event-when', lang('When'), $input('sp-event-when', 'text', 'event_when', 'off'), 'mb-3',
                sprintf(lang('The day and the time, for example %s.'), $events['quiz']['when'])),
            $form_step(2, lang('Words')),
            $field('sp-event-summary', lang('Summary'), pg_cf_with_role($textarea('sp-event-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('sp-event-details', lang('What Happens'), $textarea('sp-event-details', 'details', 5, false), 'mb-3', lang('One item of the programme on each line.')),
            $field('sp-event-featured', lang('Featured'), $select('sp-event-featured', 'featured', '', $sp_featured_choices, false, $sp_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured event is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every event, filtered by the address (kind), the newest first.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $sp_list_config('event_new', 'event', 'submitted_date_and_time', 'DESC',
            lang('No event of this kind yet. Choose another kind, or see all events.'), array(
            'items_per_page' => 12,
            'filters'        => array($sp_filter('kind', 'is equal to', '', 'kind')),
        )),
        'tree'   => $ev_grid(),
    ),

    // The home page's featured events, three at most.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $sp_list_config('event_new', 'event', 'submitted_date_and_time', 'DESC', lang('No featured events yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($sp_filter('featured', 'is equal to', $sp_featured_choices[0])),
        )),
        'tree'   => $ev_grid(),
    ),

    // The sponsorship enquiry: the package starts with what a link sends
    // (?package=). The Status is the club's (office use only).
    'sponsor_form' => array(
        'page'   => 'sponsor_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your business')),
            $row(array(
                $col(array($field('sp-sp-business', lang('Business'), $input('sp-sp-business', 'text', 'business', 'organization'))), '6'),
                $col(array($field('sp-sp-package', lang('Package'), $select('sp-sp-package', 'package', lang('Choose a package…'), $package_titles, true, '', array('url_param' => 'package')))), '6'),
            ), '3'),
            $field('sp-sp-message', lang('Message'), $textarea('sp-sp-message', 'message', 4, false), 'mb-3',
                lang('What you would like to support, a season or a match in mind, and anything we should know.')),
            $form_step(2, lang('How to reach you')),
            $row(array(
                $col(array($field('sp-sp-first-name', lang('First Name'), $input('sp-sp-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('sp-sp-last-name', lang('Last Name'), $input('sp-sp-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('sp-sp-email', lang('Email'), $input('sp-sp-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('sp-sp-phone', lang('Phone'), $input('sp-sp-phone', 'tel', 'phone', 'tel', false, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('sp-sp-status', lang('Status'), $select('sp-sp-status', 'status', '', $enquiry_statuses, false, $enquiry_statuses[0], array('office_use_only' => true))),
            $agree('sp-sp-consent', 'consent', lang('I agree that the club keeps these details to answer the enquiry, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The committee answers within a week.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // The staff's list of enquiries, newest first, with a search. An
    // enquiry has no page: the record's pencil opens it in the control
    // panel.
    'staff_sponsors_list' => array(
        'page'   => 'staff_sponsors',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:sponsor_enquiry}}',
            'items_per_page'      => 20,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'search_enabled'      => true,
            'search_fields'       => array('business', 'package', 'first_name', 'last_name', 'email', 'status'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by business, package, name or status...'),
            'empty_message'       => lang('No sponsorship enquiries yet.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Request List'), array($loop(array(
                $el('div', 'list-group-item p-3', lang('Sponsorship Enquiry'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
                        $span($ev_sample_business, 'fw-semibold', lang('Business'), $bind('business')),
                        $span($enquiry_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
                    )),
                    $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
                        $el('span', 'd-inline-flex align-items-center gap-1', lang('Package'), array_merge(array($icon('bi-tags')), $ev_value($ev_sample_package, lang('Package'), 'package'))),
                        $el('span', 'd-inline-flex align-items-center gap-1', lang('Contact'), array_merge(array($icon('bi-person')), $ev_contact_name())),
                        $el('span', 'd-inline-flex align-items-center gap-1', lang('Phone'), array_merge(array($icon('bi-telephone')), $ev_value('+90 555 000 00 00', lang('Phone'), 'phone'))),
                    )),
                    $el('p', 'small mt-2 mb-0 pg-hide-if-empty', lang('Message'), array(), array_merge(array('text' => $ev_sample_message), $bind('message'))),
                )),
            )))),
        )),
    ),

    // The e-mail pages' widgets: drawn for the enquiry just sent.
    'email_sponsor_admin' => $ev_mail_widget('email_sponsor_new', $widget_root(array(
        $mail_heading(lang('A new sponsorship enquiry')),
        $mail_para(lang('It came from the sponsorship form. Call the business to agree the package, then set the status in the list of enquiries.')),
        $ev_mail_enquiry(true),
        $mail_button(lang('Open the enquiries'), '{{page:staff_sponsors}}'),
    ))),
    'email_sponsor_sender' => $ev_mail_widget('email_sponsor_received', $widget_root(array(
        $mail_heading(lang('We received your sponsorship enquiry')),
        $mail_greeting(),
        $mail_para(lang('Thank you for wanting to support the club. The committee answers within a week with the details of the package.')),
        $ev_mail_enquiry(false),
        $mail_button(lang('See our sponsors'), '{{page:sponsors}}'),
    ))),
);

// A fact of the event page: an icon, the label and the bound value.
$ev_fact = function ($icon_name, $label, $token, $sample) use ($el, $icon, $span, $bind) {
    return $el('p', 'pg-hide-if-empty d-flex align-items-center gap-2 mb-2', $label, array(
        $icon($icon_name, 'text-primary'),
        $span($label, 'text-body-secondary', lang('Label')),
        $span($sample, 'pg-field-value fw-semibold', lang('Value'), $bind($token)),
    ));
};

// One event: the kind, the title, the summary, what happens and beside
// them when and where, and the way to the other events.
$ev_sample_key = 'open';
$ev_sample = $events[$ev_sample_key];
$ev_not_found = lang('This event could not be found. It may have taken place already or been taken off the list.');
$event_widgets['event_detail'] = array(
    'page'   => 'event',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $ev_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $sp_not_found('bi-calendar-x', lang('Event not found'), $ev_not_found, lang('All Club Events'), 'events'),
            $el('article', '', lang('Club Event'), array(
                $sp_toolbar('events', lang('All Club Events')),
                $row(array(
                    $col(array(
                        $span($event_kinds[$ev_sample['kind']], 'psp-eyebrow small text-uppercase fw-semibold text-primary', lang('Kind'), $bind('kind')),
                        $heading('h1', $ev_sample['title'], 'psp-display display-6 mb-2', $bind('title')),
                        $para($ev_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $sp_long_field(lang('What happens on the day'), 'details', $ev_details[$ev_sample_key]),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm', lang('Event Facts'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $ev_fact('bi-calendar-event', lang('When'), 'event_when', $ev_sample['when']),
                                $ev_fact('bi-geo-alt', lang('Place'), 'place', $ev_sample['place']),
                                $el('div', 'd-flex flex-wrap gap-2 mt-3', lang('Buttons'), array(
                                    $link(lang('Getting to the ground'), '{{page:ground}}', 'btn btn-sm btn-outline-primary'),
                                    $link(lang('All Club Events'), '{{page:events}}', 'btn btn-sm btn-outline-primary'),
                                )),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
