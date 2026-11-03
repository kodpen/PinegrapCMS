<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Hotel", its events: the Weddings and Meetings page with
 * the event spaces, the event enquiry, the enquiry's own page with its
 * messages, My Enquiries and the staff's list, the two e-mails of the
 * enquiry form, and the widgets that draw them. Included after parts.php
 * and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An enquiry is a record of the Event Enquiries form, as a booking request
 * is a record of its own form: a request, never a booking. A visitor
 * without an account gets one (auto-registration), so the enquiry's page
 * and My Enquiries show it at once. Its Status is an office-use-only field
 * the events team changes in the control panel; the messages under it are
 * the page's comments, mailed to the guest and to the hotel.
 *
 * The enquiry's Space and Occasion start with the choice of the card that
 * sent the visitor (?space=, ?kind=, each the answer's own words). The
 * agenda, seating plan or mood board a guest sends is kept in the
 * template's private folder: the enquiry's page links it for whoever may
 * read the record.
 *
 * The helpers of this file are named ev_…, so they never take the place of
 * a helper another page file of the folder defines in the same scope.
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

// What the hotel hosts, each with the enquiry opened for it (?kind=, one of
// $event_kinds) and what the events team arranges.
$ev_occasions = array(
    'wedding' => array('icon' => 'bi-heart', 'kind' => $event_kinds[0], 'title' => lang('Weddings by the Sea'),
        'text'   => lang('A ceremony under the olive trees, dinner on the lawn and dancing until late, with the house kept for your guests.'),
        'points' => array(lang('One wedding a day, never two'), lang('A menu tasting for the two of you'), lang('Rooms held for your guests at a group rate'))),
    'meeting' => array('icon' => 'bi-easel', 'kind' => $event_kinds[1], 'title' => lang('Meetings and Workshops'),
        'text'   => lang('A day in the Stone Hall with daylight and good sound, coffee in the garden and lunch on the terrace.'),
        'points' => array(lang('Screen, sound and fast Wi-Fi included'), lang('Coffee breaks and lunch for the group'), lang('Rooms for those who stay the night'))),
    'dinner'  => array('icon' => 'bi-cup-straw', 'kind' => $event_kinds[2], 'title' => lang('Private Dinners'),
        'text'   => lang('A long table in the library or on the sea terrace, with a menu written for the evening by our chef.'),
        'points' => array(lang('A menu of your own'), lang('Wines from the hills behind the bay'), lang('Flowers and candles from the garden'))),
);

// The space's card on the Weddings and Meetings page: the photo, the name,
// how many guests it seats and its size, a line, and the enquiry opened
// with the space chosen (?space=).
$ev_space_card = function ($key) use ($el, $heading, $para, $span, $link, $image, $col, $icon, $photo, $attr, $event_spaces, $query) {
    $s = $event_spaces[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pht-lift', lang('Space Card'), array(
            $el('span', 'd-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('space-' . $key, 800, 600), $s['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center gap-2 mb-2', lang('Space Heading'), array(
                    $icon($s['icon'], 'fs-5 text-primary'),
                    $heading('h3', $s['title'], 'pht-display h4 mb-0'),
                )),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-3', lang('Space Facts'), array(
                    $el('li', 'd-inline-flex align-items-center gap-1', lang('Capacity'), array(
                        $icon('bi-people', 'text-primary'),
                        $span(sprintf(lang('Up to %d guests seated'), $s['capacity']), '', lang('Value')),
                    )),
                    $el('li', 'd-inline-flex align-items-center gap-1', lang('Size'), array(
                        $icon('bi-arrows-angle-expand', 'text-primary'),
                        $span($s['size'] . ' m²', '', lang('Value')),
                    )),
                )),
                $para($s['text'], 'small text-body-secondary mb-4'),
                $link(lang('Ask about this space'), '{{page:event_enquiry}}?' . $query(array('space' => $s['title'])), 'btn btn-outline-primary rounded-pill mt-auto'),
            )),
        )),
    ), '6', '3');
};

// An occasion's card: the icon, the name, a line, what is arranged and the
// enquiry opened with the occasion chosen (?kind=).
$ev_occasion_card = function ($key) use ($el, $heading, $para, $link, $col, $check_list, $icon_tile, $ev_occasions, $query) {
    $o = $ev_occasions[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Occasion Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($o['icon'], 'align-self-start mb-3'),
                $heading('h3', $o['title'], 'pht-display h4 mb-2'),
                $para($o['text'], 'small text-body-secondary mb-3'),
                $check_list($o['points'], 'list-unstyled small mb-4'),
                $link(lang('Start an enquiry'), '{{page:event_enquiry}}?' . $query(array('kind' => $o['kind'])), 'btn btn-primary rounded-pill mt-auto'),
            )),
        )),
    ), '', '4');
};

// The side column of the enquiry page: what happens next, then the events
// team's direct line.
$ev_aside = function () use ($el, $heading, $para, $link, $icon, $span, $hotel) {
    $steps = array(
        lang('Your enquiry opens its own page, and an account for you if you have none.'),
        lang('The events team answers within two working days with a proposal and the price.'),
        lang('We hold the date for you while you decide; questions and changes go under your enquiry.'),
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
        $el('div', 'p-4 rounded-4 border', lang('Events Team'), array(
            $heading('h2', lang('Rather talk it through?'), 'h5 fw-semibold mb-2'),
            $para(lang('The events team shows you the spaces, by video call or on a visit to the hotel.'), 'small text-body-secondary mb-3'),
            $el('p', 'd-flex align-items-center gap-2 small mb-0', lang('Phone'), array(
                $icon('bi-telephone', 'text-primary'),
                $link($hotel['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $hotel['phone']), 'link-body-emphasis text-decoration-none'),
            )),
        )),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

// Weddings and Meetings: the occasions, the spaces, how it works and the
// questions asked before an enquiry.
$ev_space_cards = array();
foreach (array_keys($event_spaces) as $key) $ev_space_cards[] = $ev_space_card($key);
$ev_occasion_cards = array();
foreach (array_keys($ev_occasions) as $key) $ev_occasion_cards[] = $ev_occasion_card($key);

$event_pages[] = array(
    'key'              => 'meetings',
    'name'             => lang('weddings-and-meetings'),
    'folder'           => 'public',
    'title'            => lang('Weddings and Meetings'),
    'meta_description' => lang('Weddings under the olive trees, meetings in the Stone Hall and private dinners by the sea: the spaces, how many they seat and how to ask.'),
    'tree'             => $page(array(
        $page_header(lang('Weddings and Meetings'),
            lang('A wedding under the olive trees, a meeting in the old stone hall or a long dinner by the sea: tell us the day and the guests, and the events team plans it with you.'),
            array($el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Start an Enquiry'), '{{page:event_enquiry}}', 'btn btn-primary rounded-pill px-4'),
                $link(lang('My Enquiries'), '{{page:my_enquiries}}', 'btn btn-outline-primary rounded-pill px-4'),
            )))),
        $el('section', 'py-5', lang('Occasions'), array(
            $container(array(
                $section_header(lang('What we host'), lang('One celebration at a time')),
                $row($ev_occasion_cards, '4'),
            )),
        ), $searchable('occasions', lang('wedding, meeting, workshop, private dinner, party, celebration'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Event Spaces'), array(
            $container(array(
                $section_header(lang('The spaces'), lang('Four places for your event')),
                $row($ev_space_cards, '4'),
            )),
        ), $searchable('spaces', lang('venue, hall, garden, terrace, capacity, seats'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('How it works'), lang('From the first question to the day')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Tell us about it'), lang('The occasion, the date, how many guests and the space you like, in a short enquiry.')),
                            $step(2, lang('Get a proposal'), lang('Within two working days: the space, the menu, the rooms for your guests and the price, on your enquiry\'s page.')),
                            $step(3, lang('Plan it together'), lang('We hold the date while you decide, then plan every detail with you under your enquiry.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $section_header(lang('Good to know'), lang('Before you ask')),
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Can our guests stay at the hotel?'),
                                lang('Yes. With an event we hold rooms for your guests at a group rate; each guest books their own room with the code we send you.'), '', 'ht-events-faq'),
                            $faq(2, lang('Can we bring our own music or decoration?'),
                                lang('Of course. The garden and the terrace have power for a band or a DJ until midnight; the events team knows decorators and florists of the region.'), '', 'ht-events-faq'),
                            $faq(3, lang('What if it rains?'),
                                lang('Every garden event has the Stone Hall held as its rain plan, at no extra cost.'), '', 'ht-events-faq'),
                        ), array('id' => 'ht-events-faq')),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('how-events-work', lang('proposal, price, rain plan, group rate, guest rooms'))),
    )),
);

// The event enquiry. What is sent opens the enquiry's own page.
$event_pages[] = array(
    'key'              => 'event_enquiry',
    'name'             => lang('event-enquiry'),
    'folder'           => 'public',
    'title'            => lang('Event Enquiry'),
    'meta_description' => lang('Ask about a wedding, a meeting or a private dinner at the hotel: the events team answers within two working days with a proposal.'),
    'form'             => array(
        'form_name'            => lang('Event Enquiries'),
        'confirmation_message' => lang('Thank you, your enquiry has reached the events team. We answer within two working days with a proposal.'),
        'confirmation_page_id' => '{{tab:enquiry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New event enquiry: ^^event_kind^^'),
        'notify_page_id'       => '{{tab:email_enquiry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your event enquiry'),
        'confirm_page_id'      => '{{tab:email_enquiry_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Event Enquiry'),
            lang('Tell us about the occasion, the date and your guests. The events team answers within two working days with a proposal.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('enquiry_form'))),
                        )),
                    ), '', '8'),
                    $col($ev_aside(), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One enquiry: what was asked, its status and the messages under it.
$event_pages[] = array(
    'key'      => 'enquiry_view',
    'name'     => lang('enquiry'),
    'folder'   => 'registration',
    'title'    => lang('Event Enquiry'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'attachments' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your event'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('enquiry_detail'),
        $comments_section(lang('Messages'), 'event_messages'),
    ), false),
);

// My Enquiries: the guest's own enquiries, and a card that starts another.
$event_pages[] = array(
    'key'     => 'my_enquiries',
    'name'    => lang('my-enquiries'),
    'folder'  => 'registration',
    'title'   => lang('My Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Enquiries'), lang('Your event enquiries, their status and the events team\'s messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_enquiries_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('New Enquiry'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-heart', 'text-primary'),
                                $span(lang('Another occasion?'), '', lang('Text')),
                            )),
                            $para(lang('Start a new enquiry; your details are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Start an Enquiry'), '{{page:event_enquiry}}', 'btn btn-sm btn-primary rounded-pill px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// The staff's list of every enquiry, newest first, with a search.
$event_pages[] = array(
    'key'     => 'staff_enquiries',
    'name'    => lang('all-event-enquiries'),
    'folder'  => 'private',
    'title'   => lang('Event Enquiries'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $staff_page(array(
        $page_header(lang('Event Enquiries'), lang('Every event enquiry, newest first: send the proposal, set the status and answer under each.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array($widget('staff_enquiries_list'))),
        )),
    )),
);

// The e-mails of the enquiry form: what the hotel receives and the reply to
// the guest. A message under an enquiry goes out with the new message
// e-mail (email_reply) the record page names.
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_new',
    'name'  => lang('email-new-event-enquiry'),
    'title' => lang('New Event Enquiry E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_admin'))),
));
$event_pages[] = array_merge($mail_settings, array(
    'key'   => 'email_enquiry_received',
    'name'  => lang('email-event-enquiry-received'),
    'title' => lang('Event Enquiry Received E-mail'),
    'tree'  => $mail_page(array($widget('email_enquiry_client'))),
));

// ── Widget pieces ───────────────────────────────────────────────────────

$ev_sample_space = $event_space_choices[0];
$ev_sample_note = lang('A wedding for about ninety guests in early autumn: the ceremony at sunset in the garden, then dinner and dancing on the terrace.');
$ev_missing = lang('This enquiry could not be found, or it is not yours to see.');

// The guest's name.
$ev_person = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// An enquiry in a list: the occasion, the status, the date, the space and
// the guests, linked to its page. The staff's row names the guest and the
// phone; the guest's own shows the number of messages.
$ev_row = function ($staff = false) use ($el, $span, $icon, $bind, $ev_person, $event_kinds, $event_statuses, $event_size_choices, $ev_sample_space) {
    $item = function ($icon_name, $label, $sample, $field) use ($el, $span, $icon, $bind) {
        return $el('span', 'd-inline-flex align-items-center gap-1', $label, array($icon($icon_name), $span($sample, '', $label, $bind($field))));
    };
    $items = array(
        $item('bi-calendar-event', lang('Event Date'), lang('Date'), 'event_date'),
        $item('bi-geo-alt', lang('Venue'), $ev_sample_space, 'space'),
        $item('bi-people', lang('Guests'), $event_size_choices[2], 'guest_count'),
        $item('bi-send', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
    );
    if ($staff) {
        $items[] = $item('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
        $title = $el('span', 'fw-semibold', lang('Guest'), array_merge($ev_person(), array(
            $span(' · ', 'text-body-secondary', lang('Text')),
            $span($event_kinds[0], 'text-body-secondary fw-normal', lang('Type of Event'), $bind('event_kind')),
        )));
    } else {
        $items[] = $item('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
        $title = $span($event_kinds[0], 'fw-semibold', lang('Type of Event'), $bind('event_kind'));
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Enquiry'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $title,
            $span($event_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Status'), $bind('status')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $items),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A detail of the enquiry's card: the label, then the value. $value is
// either the value's props (its sample and binding) or, with $children,
// the nodes it is made of.
$ev_detail = function ($label, $class, $name, $value, $children = false) use ($el) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 ' . $class), $name, $children ? $value : array(), $children ? array() : $value),
    );
};
$ev_bound = function ($sample, $field) use ($bind) {
    return array_merge(array('text' => $sample), $bind($field));
};

// The list settings: the guest's own enquiries (the staff see them all), or
// the staff's with a search.
$ev_list = function ($page_key, $row_node, $empty, $search_fields = array(), $search_label = '') use ($widget_root, $el, $loop) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_enquiry}}',
        'detail_page_id'      => '{{tab:enquiry_view}}',
        'items_per_page'      => 20,
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
    );
    if ($search_fields) {
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
            $el('div', 'list-group', lang('Enquiry List'), array($loop(array($row_node)))),
        )),
    );
};

// The lines of an enquiry, for its e-mails.
$ev_mail_enquiry = function () use ($mail_box, $mail_line, $span, $bind, $event_kinds, $event_size_choices, $ev_sample_space) {
    return $mail_box(array(
        $mail_line(lang('Type of Event'), array($span($event_kinds[0], '', lang('Type of Event'), $bind('event_kind')))),
        $mail_line(lang('Event Date'), array($span(lang('Date'), '', lang('Event Date'), $bind('event_date')))),
        $mail_line(lang('Other dates that suit you'), array($span(lang('Any Saturday in September'), '', lang('Other Dates'), $bind('other_dates')))),
        $mail_line(lang('Venue'), array($span($ev_sample_space, '', lang('Venue'), $bind('space')))),
        $mail_line(lang('Guests'), array($span($event_size_choices[2], '', lang('Guests'), $bind('guest_count')))),
        $mail_line(lang('Guest rooms'), array($span('20', '', lang('Guest rooms'), $bind('guest_rooms')))),
        $mail_line(lang('Contact by'), array($span(lang('Phone'), '', lang('Contact by'), $bind('contact_method')))),
    ), lang('Enquiry'));
};
// The guest's lines of the staff e-mail.
$ev_mail_guest = function () use ($mail_box, $mail_line, $span, $bind, $ev_person, $ev_sample_note) {
    return $mail_box(array(
        $mail_line(lang('Guest'), $ev_person()),
        $mail_line(lang('Company'), array($span(lang('Sample Company'), '', lang('Company'), $bind('company')))),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
        $mail_line(lang('About the event'), array($span($ev_sample_note, '', lang('Text'), $bind('details')))),
    ), lang('Guest'));
};
// The reference code under the staff e-mail.
$ev_mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4b5563;margin:24px 0 0'));
};
// The account the form opened for a guest who had none, in the reply.
$ev_mail_new_account = function () use ($el, $span, $link, $bind, $show_when, $mail_style) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #ede0d4;padding-top:16px'), $show_when('has_new_account')));
};
// The form detail settings of a widget: the e-mail pages read the record
// they are sent for (public), the enquiry's page only the guest's own.
$ev_detail_config = function ($page_key, $access) use ($ev_missing) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:event_enquiry}}',
            'access_control'      => $access,
            'not_found_message'   => $ev_missing,
        ),
    );
};

$event_widgets = array(

    // The event enquiry: the fields are the page's form. The Occasion and
    // the Space start with the choice of the card that sent the visitor
    // (?kind=, ?space=). The Status is the events team's (office use only):
    // every enquiry starts as Enquiry received.
    'enquiry_form' => array(
        'page'   => 'event_enquiry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The occasion')),
            $row(array(
                $col(array($field('ht-ev-kind', lang('Type of Event'), $select('ht-ev-kind', 'event_kind', lang('Choose a type of event…'), $event_kinds, true, '', array('url_param' => 'kind')))), '6'),
                $col(array($field('ht-ev-space', lang('Venue'), $select('ht-ev-space', 'space', '', $event_space_choices, true, lang('Not sure yet'), array('url_param' => 'space')))), '6'),
                $col(array($field('ht-ev-date', lang('Event Date'), $input('ht-ev-date', 'date', 'event_date', 'off', true, array('rules' => array('min_date' => 'today'))))), '6'),
                $col(array($field('ht-ev-guests', lang('Guests'), $select('ht-ev-guests', 'guest_count', lang('Choose a number of guests…'), $event_size_choices))), '6'),
            ), '3'),
            $field('ht-ev-other-dates', lang('Other dates that suit you'), $input('ht-ev-other-dates', 'text', 'other_dates', 'off', false), 'mb-3',
                lang('If the day can move, the dates or the season that would also work.')),
            $field('ht-ev-rooms', lang('Guest rooms'), $input('ht-ev-rooms', 'number', 'guest_rooms', 'off', false, array('kind' => 'number'), array($attr('min', '0'), $attr('step', '1'))), 'mb-3',
                lang('How many rooms to hold for your guests, if any stay the night.')),
            $field('ht-ev-details', lang('About the event'), $textarea('ht-ev-details', 'details', 4, false), 'mb-3',
                lang('The programme of the day, the menu you have in mind, music, flowers or anything we should know.')),
            $field('ht-ev-documents', lang('Agenda or plans'), $file('ht-ev-documents', 'documents', 'private'), 'mb-3',
                lang('An agenda, a seating plan or a mood board (PDF, Word or a picture). More can follow under your enquiry.')),
            $form_step(2, lang('How to reach you')),
            $radios('ht-ev-contact', 'contact_method', lang('Best way to reach you'), $contact_choices, lang('E-mail')),
            $row(array(
                $col(array($field('ht-ev-first-name', lang('First Name'), $input('ht-ev-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
                $col(array($field('ht-ev-last-name', lang('Last Name'), $input('ht-ev-last-name', 'text', 'last_name', 'family-name', true, array('contact_field' => 'last_name')))), '6'),
                $col(array($field('ht-ev-email', lang('Email'), $input('ht-ev-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
                $col(array($field('ht-ev-phone', lang('Phone'), $input('ht-ev-phone', 'tel', 'phone', 'tel', true, array('contact_field' => 'mobile_phone')))), '6'),
            ), '3'),
            $field('ht-ev-company', lang('Company'), $input('ht-ev-company', 'text', 'company', 'organization', false, array('contact_field' => 'company')), 'mb-3',
                lang('For a meeting or a company event; it goes on the proposal.')),
            $field('ht-ev-status', lang('Status'), $select('ht-ev-status', 'status', '', $event_statuses, false, $event_statuses[0], array('office_use_only' => true))),
            $agree('ht-ev-consent', 'consent', lang('I agree that the hotel keeps these details to plan the event and answer me, as its privacy notice explains.'), true),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Send the Enquiry'), 'btn btn-primary btn-lg rounded-pill px-4'),
                $para(lang('The events team answers within two working days.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One enquiry: what was asked, its status and what the guest wrote;
    // the proposal and the messages follow as comments.
    'enquiry_detail' => $ev_detail_config('enquiry_view', 'submitter_only') + array(
        'tree' => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Enquiry not found'), 'h3 fw-semibold mt-3'),
                            $para($ev_missing, 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Enquiries'), '{{page:my_enquiries}}', 'btn btn-primary rounded-pill px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Record'), array(
                            $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
                                $icon('bi-check-circle', 'mt-1'),
                                $span(lang('Thank you, your enquiry has reached the events team. We answer within two working days with a proposal; this page shows the status, and you can write to us below.'), '', lang('Text')),
                            ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
                            $el('div', 'alert alert-info', lang('New Account'), array(
                                $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
                                $el('p', 'mb-1', lang('Sign-in Email'), array(
                                    $span(lang('Email') . ': ', '', lang('Label')),
                                    $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
                                )),
                                // The single-use set password link, for the guest who sent the
                                // form while it is live; the sentence below covers a used or
                                // expired link.
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
                                            $para(lang('Event Enquiry'), 'pht-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
                                            $heading('h1', $event_kinds[0], 'pht-display h3 mb-0', $bind('event_kind')),
                                        )),
                                        $span($event_statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
                                    )),
                                    $el('dl', 'row small mb-0', lang('Details'), array_merge(
                                        $ev_detail(lang('Event Date'), '', lang('Event Date'), $ev_bound(lang('Date'), 'event_date')),
                                        $ev_detail(lang('Other dates that suit you'), 'pg-hide-if-empty', lang('Other Dates'), $ev_bound(lang('Any Saturday in September'), 'other_dates')),
                                        $ev_detail(lang('Venue'), '', lang('Venue'), $ev_bound($ev_sample_space, 'space')),
                                        $ev_detail(lang('Guests'), '', lang('Guests'), $ev_bound($event_size_choices[2], 'guest_count')),
                                        $ev_detail(lang('Guest rooms'), 'pg-hide-if-empty', lang('Guest rooms'), $ev_bound('20', 'guest_rooms')),
                                        $ev_detail(lang('Best way to reach you'), '', lang('Contact by'), $ev_bound(lang('E-mail'), 'contact_method')),
                                        $ev_detail(lang('Guest'), '', lang('Guest'), $ev_person(), true),
                                        $ev_detail(lang('Company'), 'pg-hide-if-empty', lang('Company'), $ev_bound(lang('Sample Company'), 'company')),
                                        $ev_detail(lang('Agenda or plans'), 'pg-hide-if-empty text-break', lang('Documents'), array(
                                            $link('seating-plan.pdf', '#', '', array(), array('target' => '_blank', 'rel' => 'noopener',
                                                '_bindings' => array('href' => 'documents', 'text' => 'documents'))),
                                        ), true),
                                        $ev_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $ev_bound('ABC123', 'reference_code'))
                                    )),
                                )),
                            )),
                            $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('About the event'), array(
                                $para(lang('About the event'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pg-field-value lh-lg', lang('Value'), array(), $ev_bound($ev_sample_note, 'details')),
                            )),
                            $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                $icon('bi-chat-left-text', 'text-primary mt-1'),
                                $span(lang('A change of date, more guests or a question? Write to the events team below; the proposal comes here too, and every answer by e-mail.'), '', lang('Text')),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The messages under an enquiry: the page's own Comments widget, shown
    // only to whoever may read the enquiry above it. Oldest first, all on
    // one page, with a file each. No Reply, Share or follow box: the guest
    // is e-mailed every answer and the hotel every message.
    'event_messages' => array(
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
            'empty'     => lang('No messages yet. Write to the events team here about your event.'),
            'heading'   => lang('Write to the events team'),
            'label'     => lang('Your message'),
            'sign_in'   => lang('Sign in to write to the events team.'),
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

    'my_enquiries_list' => $ev_list('my_enquiries', $ev_row(),
        lang('No event enquiries yet. Tell us about your wedding, meeting or dinner and the events team answers within two working days.')),

    'staff_enquiries_list' => $ev_list('staff_enquiries', $ev_row(true),
        lang('No event enquiries yet.'),
        array('first_name', 'last_name', 'company', 'event_kind', 'space', 'event_date', 'status'),
        lang('Search by guest, company, occasion, space, date or status...')),

    // The e-mail pages' widgets: drawn for the enquiry just sent.
    'email_enquiry_admin' => $ev_detail_config('email_enquiry_new', 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new event enquiry')),
            $mail_para(lang('It came from the event enquiry form. Check the date and the space, send the proposal under the enquiry and set its status; what you write there reaches the guest by e-mail.')),
            $ev_mail_enquiry(),
            $ev_mail_guest(),
            $mail_button(lang('Open the enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $ev_mail_reference(),
        )),
    ),
    'email_enquiry_client' => $ev_detail_config('email_enquiry_received', 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your event enquiry')),
            $mail_greeting(),
            $mail_para(lang('Thank you for thinking of us for your event. The events team answers within two working days with a proposal and the price, and holds the date for you while you decide.')),
            $ev_mail_enquiry(),
            $mail_para(lang('A change or a question? Write to us on the enquiry\'s page; everything about your event stays there.')),
            $mail_button(lang('Open my enquiry'), '{{page:enquiry_view}}?r=^^reference_code^^'),
            $ev_mail_new_account(),
        )),
    ),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
