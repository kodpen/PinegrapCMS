<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marina", its events and event entries: the staff page
 * that adds an event (the Events form, a collection, with the sample
 * events written in when it is made), the list of every event with its
 * kind filter, the event's own page and the list the home page places;
 * the entry request, the entry's own page with its messages, My Event
 * Entries and the staff's list, and the e-mails of the entry form.
 * Included after parts.php, mail.php, charters.php and requests.php in
 * the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * An event is a record of the Events form. Its kind is a pick list whose
 * answers are the words parts.php gives, so a link with ?kind= (the
 * filter bar, the event page) filters the list by the words a visitor
 * sees. When, where and the entry fee are words: the marina writes the
 * dates of each season itself, and no fixed date ages in the sample.
 * Featured is the marina's choice (office use only): the home page shows
 * the featured events.
 *
 * An entry is a request, never a booked place: a record of the Event
 * Entries form, the event named by ?event= from the event page (a text
 * field, so an event the staff add later is taken as well). Its Status
 * is the harbour office's; the answer follows as messages.
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

// ── The sample events ───────────────────────────────────────────────────

// One event of each kind. When is words, never a date.
$events = array(
    'regatta' => array('title' => lang('Autumn Bay Regatta'), 'kind' => $event_kinds[0], 'featured' => true,
        'when'    => lang('A weekend in early autumn, two races a day'),
        'where'   => lang('Start line off the breakwater, prize giving on the quay'),
        'fee'     => lang('Entry fee per boat, crew dinner included'),
        'summary' => lang('Two days of racing round the islands for cruisers and racers, with a crew dinner on the quay.'),
        'text'    => lang('The regatta is open to cruising and racing yachts of every size, sailed in classes by length. The race committee sets courses round the islands of the bay each morning; the evenings belong to the crews, with music on the quay and the prize giving on the last night.')),
    'open_day' => array('title' => lang('Marina Open Day'), 'kind' => $event_kinds[1], 'featured' => true,
        'when'    => lang('A Saturday in late spring, from ten to five'),
        'where'   => lang('Across the marina, starting at the harbour office'),
        'fee'     => lang('Free, no entry needed'),
        'summary' => lang('Boats open to visit, short trips on the bay, the boatyard at work and the lifeboat crew on the quay.'),
        'text'    => lang('Once a year the marina opens its pontoons to everyone. Owners show their boats, the boatyard lifts a yacht with the crane, the lifeboat crew explains its work and the sailing school takes visitors out for a short trip on the bay.')),
    'course' => array('title' => lang('Start Sailing Weekend'), 'kind' => $event_kinds[2], 'featured' => true,
        'when'    => lang('Every second weekend from spring to autumn'),
        'where'   => lang('Sailing school pontoon and the sheltered inner bay'),
        'fee'     => lang('Course fee per person, life jackets included'),
        'summary' => lang('Two days with an instructor for complete beginners: knots, ropes, steering and the first sail on your own.'),
        'text'    => lang('Our instructors take groups of four in small keelboats, first in the shelter of the inner bay, then out to the islands. By the end of the weekend you can rig the boat, sail a course and come back alongside under sail.')),
    'social' => array('title' => lang('Sundowner on the Quay'), 'kind' => $event_kinds[3], 'featured' => false,
        'when'    => lang('Every Friday in summer, from seven in the evening'),
        'where'   => lang('The quay in front of the café'),
        'fee'     => lang('Free for berth holders and visiting crews'),
        'summary' => lang('Meet the other crews over a drink as the sun goes down behind the islands.'),
        'text'    => lang('The marina\'s weekly get-together for berth holders and visiting crews: a drink, something to eat from the café and the latest news from the harbour master about the weather and the week ahead.')),
    'show' => array('title' => lang('Harbour Boat Show'), 'kind' => $event_kinds[4], 'featured' => false,
        'when'    => lang('Three days in late autumn'),
        'where'   => lang('Show pontoons and the boatyard hall'),
        'fee'     => lang('Free to visit; exhibitors book a stand'),
        'summary' => lang('New and used boats afloat, chandlers and sailmakers ashore, and talks on cruising the coast.'),
        'text'    => lang('Brokers and builders bring new and used boats to the show pontoons, while chandlers, sailmakers and engine dealers fill the boatyard hall. Talks each afternoon cover cruising the coast, keeping a boat and the season\'s new rules.')),
);
$event_titles = array();
foreach ($events as $e) $event_titles[] = $e['title'];
$event_sample_key = 'regatta';
$event_sample = $events[$event_sample_key];
$ev_missing = lang('This event entry could not be found, or it is not yours to see.');
$ev_sample_note = lang('A crew of five on a ten-metre cruiser, our first regatta here; we would like a berth for the two nights as well.');

// ── Pieces ──────────────────────────────────────────────────────────────

// An event's when, where and fee in one list, each bound to the Events
// form's field of the same name.
$event_facts = function ($class = 'list-unstyled d-flex flex-column gap-1 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $event_sample) {
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-flex align-items-start gap-2', $name, array(
            $icon($icon_name, 'text-primary mt-1'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Event Facts'), array(
        $fact('bi-calendar-event', $event_sample['when'], 'when', lang('When')),
        $fact('bi-geo-alt', $event_sample['where'], 'where', lang('Where')),
        $fact('bi-ticket', $event_sample['fee'], 'fee', lang('Entry fee')),
    ));
};

// An event's card of the lists: the photo, the kind, the title linking the
// event page, the facts and the summary.
$event_card = function () use ($el, $para, $span, $link, $image, $col, $bind, $photo, $attr, $event_sample, $event_sample_key, $event_facts) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pmn-lift', lang('Event Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('event-' . $event_sample_key, 800, 450), $event_sample['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_sample['kind'], 'pmn-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Kind'), $bind('kind')),
                $el('h3', 'pmn-display h5 position-static mb-2', lang('Event Title'), array(
                    $link($event_sample['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($event_sample['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $event_facts('list-unstyled d-flex flex-column gap-1 small text-body-secondary mt-auto mb-0'),
            )),
        )),
    ), '6', '4');
};
$event_grid = function () use ($widget_root, $row, $loop, $event_card) {
    return $widget_root(array($row(array($loop(array($event_card()))), '4')));
};
// A list of events: the Events form, the event page, newest added first,
// and the words shown when nothing matches.
$mn_event_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'detail_page_id'      => '{{tab:event}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};

// ── Pages: events ───────────────────────────────────────────────────────

// Every event, narrowed by kind from the address.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events'),
    'meta_description' => lang('Regattas, open days, sailing courses, evenings on the quay and the boat show at the marina, and how to take part.'),
    'tree'             => $page(array(
        $page_header(lang('Events'), lang('Racing, learning to sail or an evening on the quay: choose a kind of event, then ask to take part.')),
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $mn_filter_bar('events', array(
                    array('label' => lang('Kind'), 'param' => 'kind', 'values' => $event_kinds),
                )),
                $widget('events_list'),
            )),
        ), $searchable('mn-events', lang('event, regatta, race, open day, sailing course, learn to sail, social, boat show'))),
    )),
);

// One event: its record.
$event_pages[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event'),
    'meta_description' => lang('An event of the marina: when and where it is, the entry fee, what happens and how to take part.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

// The staff page that adds an event. The form is the Events collection;
// the sample events are written in when the form is made, so the lists
// do not open empty (pg_cf_seed_sample_records()).
$sample_events = array();
foreach ($events as $key => $e) {
    $sample_events[] = array(
        'title'       => $e['title'],
        'kind'        => $e['kind'],
        'when'        => $e['when'],
        'where'       => $e['where'],
        'fee'         => $e['fee'],
        'summary'     => $e['summary'],
        'description' => $e['text'],
        'photo_1'     => $photo('event-' . $key, 1200, 675),
        'featured'    => $e['featured'] ? $mn_featured_choices[0] : $mn_featured_choices[1],
    );
}
$event_pages[] = array(
    'key'     => 'event_new',
    'name'    => lang('new-event'),
    'folder'  => 'private',
    'title'   => lang('New Event'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Events'),
        'collection'           => 1,
        'confirmation_message' => lang('The event is published.'),
        'confirmation_page_id' => '{{tab:events}}',
        'sample_records'       => $sample_events,
        // The photo is the event's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Event'), lang('Add the event with its dates in words and a photo; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('event_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Pages: event entries ────────────────────────────────────────────────

// The entry request. What is sent opens the entry's own page.
$event_pages[] = array(
    'key'              => 'event_entry',
    'name'             => lang('enter-an-event'),
    'folder'           => 'public',
    'title'            => lang('Enter an Event'),
    'meta_description' => lang('Ask to take part in a regatta, a course or another event of the marina: the harbour office confirms your entry.'),
    'form'             => array(
        'form_name'            => lang('Event Entries'),
        'confirmation_message' => lang('Thank you, your entry has reached us. The harbour office confirms it within two working days.'),
        'confirmation_page_id' => '{{tab:entry_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New event entry: ^^event^^'),
        'notify_page_id'       => '{{tab:email_entry_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your event entry'),
        'confirm_page_id'      => '{{tab:email_entry_received}}',
    ),
    'tree'             => $mn_form_page(lang('Enter an Event'),
        lang('Tell us the event, your boat and your crew. The harbour office confirms your entry within two working days; any fee is paid at the harbour office.'),
        'entry_form', $mn_aside(array(
            lang('Your entry opens its own page, and an account for you if you have none.'),
            lang('The harbour office answers there within two working days.'),
            lang('The status shows Entry confirmed once your place is held.'),
            lang('Nothing is paid now: any entry fee is paid at the harbour office.'),
        ), array($harbour['hours'], $harbour['vhf']), lang('All Events'), 'events')),
);

$event_pages[] = $mn_record_page('entry_view', lang('event-entry'), lang('Event Entry'), 'entry_detail', 'entry_messages',
    lang('There is a new message about your event entry'), false);

$event_pages[] = $mn_mine_page('my_entries', lang('my-event-entries'), lang('My Event Entries'),
    lang('Your event entries, their status and the harbour office\'s answers, newest first.'), 'my_entries_list',
    $mn_new_card('bi-flag', lang('Another event?'),
        lang('Choose an event and ask to take part; your details are filled in for you.'), lang('All Events'), 'events'));

$event_pages[] = $mn_staff_page('staff_entries', lang('all-event-entries'), lang('Event Entries'),
    lang('Every event entry, newest first: confirm the place, set the status and write to the skipper under each.'), 'staff_entries_list');

$event_pages[] = $mn_mail_page('email_entry_new', lang('email-new-event-entry'), lang('New Event Entry E-mail'), 'email_entry_admin');
$event_pages[] = $mn_mail_page('email_entry_received', lang('email-event-entry-received'), lang('Event Entry Received E-mail'), 'email_entry_client');

// ── Widget pieces ───────────────────────────────────────────────────────

$ev_row = function ($staff = false) use ($mn_row, $event_sample, $entry_statuses) {
    return $mn_row(lang('Event Entry'), $event_sample['title'], lang('Event'), 'event', $entry_statuses[0], array(
        array('bi-water', lang('Boat'), lang('Sea Swallow'), 'boat_name'),
        array('bi-people', lang('Crew'), '4', 'crew'),
        array('bi-send', lang('Sent'), lang('Date'), 'submitted_date_and_time'),
    ), $staff);
};
// The lines of an entry, for its e-mails.
$ev_mail_entry = function () use ($mail_box, $mail_line, $span, $bind, $event_sample, $vessel_choices) {
    return $mail_box(array(
        $mail_line(lang('Event'), array($span($event_sample['title'], '', lang('Event'), $bind('event')))),
        $mail_line(lang('Boat'), array($span(lang('Sea Swallow'), '', lang('Boat'), $bind('boat_name')))),
        $mail_line(lang('Type of boat'), array($span($vessel_choices[0], '', lang('Type of boat'), $bind('vessel')))),
        $mail_line(lang('Length overall'), array($span(lang('12 metres'), '', lang('Length overall'), $bind('boat_length')))),
        $mail_line(lang('Sail number or class'), array($span('TUR 1234', '', lang('Sail number or class'), $bind('sail_number')))),
        $mail_line(lang('Crew'), array($span('4', '', lang('Crew'), $bind('crew')))),
    ), lang('Event Entry'));
};
$ev_not_found = lang('This event could not be found. It may have passed or been taken off the list.');

// ── Widgets ─────────────────────────────────────────────────────────────

$event_widgets = array(

    // The staff's Events form: one event. Featured starts as No.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('mn-event-title', lang('Title'), pg_cf_with_role($input('mn-event-title', 'text', 'title', 'off'), 'title')),
            $field('mn-event-kind', lang('Kind'), pg_cf_with_role($select('mn-event-kind', 'kind', lang('Choose a kind…'), $event_kinds), 'category')),
            $row(array(
                $col(array($field('mn-event-when', lang('When'), $input('mn-event-when', 'text', 'when', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $event_sample['when']))), '6'),
                $col(array($field('mn-event-where', lang('Where'), $input('mn-event-where', 'text', 'where', 'off'))), '6'),
            ), '3'),
            $field('mn-event-fee', lang('Entry fee'), $input('mn-event-fee', 'text', 'fee', 'off', false), 'mb-3',
                lang('In words: free, a fee per boat or per person.')),
            $form_step(2, lang('Words and photo')),
            $field('mn-event-summary', lang('Summary'), pg_cf_with_role($textarea('mn-event-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('mn-event-description', lang('Description'), $textarea('mn-event-description', 'description', 5, false)),
            $field('mn-event-photo-1', lang('Photo'), pg_cf_with_role($file('mn-event-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $field('mn-event-featured', lang('Featured'), $select('mn-event-featured', 'featured', '', $mn_featured_choices, false, $mn_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured event is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every event, filtered by the address (kind), with a search.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $mn_event_list(lang('No event of this kind is on the list yet. Try another kind, or ask the harbour office.'), array(
            'items_per_page' => 12,
            'filters'        => array($mn_filter('kind', 'is equal to', '', 'kind')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'kind', 'when', 'where', 'summary', 'description'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the events: regatta, course, open day...'),
        )),
        'tree'   => $event_grid(),
    ),

    // The home page's featured events, three at most.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $mn_event_list(lang('No featured events yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($mn_filter('featured', 'is equal to', $mn_featured_choices[0])),
        )),
        'tree'   => $event_grid(),
    ),

    // One event: the kind, the title, the summary, the photo and the
    // description, and beside them the facts and the ways to take part or
    // see more of its kind.
    'event_detail' => array(
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
                $el('div', 'text-center py-5', lang('Not Found'), array(
                    $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                    $heading('h2', lang('Event not found'), 'h3 fw-semibold mt-3'),
                    $para($ev_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('All Events'), '{{page:events}}', 'btn btn-primary px-4'),
                ), $show_when('record_not_found')),
                $el('article', '', lang('Event'), array(
                    $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                            $icon('bi-arrow-left'),
                            $span(lang('All Events'), '', lang('Link Text')),
                        ), array('href' => '{{page:events}}')),
                        $edit_button(),
                    )),
                    $span($event_sample['kind'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Kind'), $bind('kind')),
                    $heading('h1', $event_sample['title'], 'pmn-display display-6 mb-2', $bind('title')),
                    $para($event_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                    $row(array(
                        $col(array(
                            $optional_image('photo_1', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                $photo('event-' . $event_sample_key, 1200, 675), lang('Photo')),
                            $el('div', 'pg-hide-if-empty mb-4', lang('Description'), array(
                                $heading('h2', lang('About this event'), 'pmn-display h4 mb-2'),
                                $el('div', 'pg-field-value', lang('Value'), array(), array_merge(array('text' => $event_sample['text']), $bind('description'))),
                            )),
                        ), '', '8'),
                        $col(array(
                            $el('aside', 'card border-0 shadow-sm rounded-4', lang('Event Card'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $event_facts('list-unstyled d-flex flex-column gap-2 mb-4'),
                                    // Opens the entry request with this event
                                    // named (?event=).
                                    $link(lang('Take part'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                                        $mn_page_link('event_entry', array('event' => 'title'))),
                                    // Opens the list on this event's kind (?kind=).
                                    $link(lang('More events of this kind'), '#', 'btn btn-outline-secondary rounded-pill w-100', array(),
                                        $mn_page_link('events', array('kind' => 'kind'))),
                                )),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5 mb-4'),
                ), $show_when('record_found')),
            ), 'py-5'),
        )),
    ),

    // The entry request: the fields are the page's form. The event starts
    // with the event page that sent the visitor (?event=). The Status is
    // the harbour office's (office use only): every entry starts as Entry
    // received.
    'entry_form' => array(
        'page'   => 'event_entry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array_merge(array(
            $form_step(1, lang('The event')),
            $field('mn-ev-event', lang('Event'), $input('mn-ev-event', 'text', 'event', 'off', true, array('url_param' => 'event')), 'mb-3',
                lang('The event as it is named on its page.')),
            $form_step(2, lang('Your boat and crew')),
            $row(array(
                $col(array($field('mn-ev-boat', lang('Boat name'), $input('mn-ev-boat', 'text', 'boat_name', 'off', false), 'mb-3',
                    lang('Leave it empty for a course or an evening without a boat.'))), '6'),
                $col(array($field('mn-ev-vessel', lang('Type of boat'), $select('mn-ev-vessel', 'vessel', lang('Choose a type of boat…'), $vessel_choices, false))), '6'),
                $col(array($rq_measure('mn-ev-length', 'boat_length', lang('Length overall'), lang('12 metres'), false)), '6'),
                $col(array($field('mn-ev-sail', lang('Sail number or class'), $input('mn-ev-sail', 'text', 'sail_number', 'off', false))), '6'),
                $col(array($field('mn-ev-crew', lang('Crew'), $mn_count('mn-ev-crew', 'crew', 1, 20, 1), 'mb-3', lang('Everyone taking part, the skipper included.'))), '6'),
            ), '3'),
            $field('mn-ev-notes', lang('Notes'), $textarea('mn-ev-notes', 'notes', 3, false), 'mb-3',
                lang('Experience, a berth for the event nights, dietary needs for the crew dinner.')),
            $form_step(3, lang('How to reach you')),
            $mn_contact_fields('mn-ev'),
            $field('mn-ev-status', lang('Status'), $select('mn-ev-status', 'status', '', $entry_statuses, false, $entry_statuses[0], array('office_use_only' => true))),
        ), $mn_form_end('mn-ev-consent',
            lang('I agree that the marina keeps these details to handle my entry, as its privacy notice explains.'),
            lang('Send the Entry'), lang('The harbour office answers within two working days.')))),
    ),

    'entry_detail' => $mn_detail_config('entry_view', 'event_entry', $ev_missing, 'submitter_only') + array(
        'tree' => $mn_record(array(
            'not_found_title' => lang('Event entry not found'),
            'missing'         => $ev_missing,
            'list_title'      => lang('My Event Entries'),
            'list_key'        => 'my_entries',
            'sent'            => lang('Thank you, your entry has reached us. The harbour office answers here within two working days; this page shows the status, and you can write to us below.'),
            'card'            => lang('Entry Card'),
            'eyebrow'         => lang('Event Entry'),
            'title_sample'    => $event_sample['title'],
            'title_field'     => 'event',
            'status'          => $entry_statuses[0],
            'details'         => array_merge(
                $mn_detail(lang('Boat name'), 'pg-hide-if-empty', lang('Boat name'), $mn_bound(lang('Sea Swallow'), 'boat_name')),
                $mn_detail(lang('Type of boat'), 'pg-hide-if-empty', lang('Type of boat'), $mn_bound($vessel_choices[0], 'vessel')),
                $mn_detail(lang('Length overall'), 'pg-hide-if-empty', lang('Length overall'), $mn_bound(lang('12 metres'), 'boat_length')),
                $mn_detail(lang('Sail number or class'), 'pg-hide-if-empty', lang('Sail number or class'), $mn_bound('TUR 1234', 'sail_number')),
                $mn_detail(lang('Crew'), '', lang('Crew'), $mn_bound('4', 'crew')),
                $mn_detail(lang('Skipper'), '', lang('Skipper'), $mn_person(), true),
                $mn_detail(lang('Reference Code'), 'mb-0 font-monospace', lang('Reference Code'), $mn_bound('ABC123', 'reference_code'))
            ),
            'words_label'     => lang('Notes'),
            'words_sample'    => $ev_sample_note,
            'words_field'     => 'notes',
            'note'            => lang('A change to your crew or a question about the event? Write to us below; we answer here and by e-mail.'),
        )),
    ),

    'entry_messages' => $mn_messages('entry_view',
        lang('No messages yet. The harbour office\'s answer comes here; you can write to us about your entry too.')),

    'my_entries_list' => $mn_list('my_entries', 'event_entry', 'entry_view', $ev_row(),
        lang('No event entries yet. Choose an event and ask to take part.')),

    'staff_entries_list' => $mn_list('staff_entries', 'event_entry', 'entry_view', $ev_row(true),
        lang('No event entries yet.'),
        array('first_name', 'last_name', 'email', 'event', 'boat_name', 'sail_number', 'status'),
        lang('Search by skipper, e-mail, event, boat, sail number or status...')),

    'email_entry_admin' => $mn_detail_config('email_entry_new', 'event_entry', lang('The event entry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('A new event entry')),
            $mail_para(lang('It came from the event entry form. Confirm the place under the entry on its page and set its status; what you write there reaches the skipper by e-mail.')),
            $ev_mail_entry(),
            $mn_mail_person(lang('Notes'), $ev_sample_note, 'notes'),
            $mail_button(lang('Open the entry'), '{{page:entry_view}}?r=^^reference_code^^'),
            $mn_mail_reference(),
        )),
    ),
    'email_entry_client' => $mn_detail_config('email_entry_received', 'event_entry', lang('The event entry could not be found.'), 'public') + array(
        'tree' => $widget_root(array(
            $mail_heading(lang('We received your event entry')),
            $mail_greeting(),
            $mail_para(lang('Thank you. The harbour office confirms your entry within two working days, on the entry\'s page and by e-mail. Any entry fee is paid at the harbour office.')),
            $ev_mail_entry(),
            $mail_para(lang('A change or a question? Write to us on the entry\'s page; everything about your entry stays there.')),
            $mail_button(lang('Open my entry'), '{{page:entry_view}}?r=^^reference_code^^'),
            $mn_mail_new_account(),
        )),
    ),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
