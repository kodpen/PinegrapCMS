<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Craft Brewery", its taproom events: the staff page that
 * adds an event (the Taproom Events form, a collection, with the sample
 * events written in when it is made), the events from today on with their
 * kind filter, the events already held, the event page with the start
 * time, the entry and the ticket link, and the list the home page places.
 * Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * An event is a record of the Taproom Events form. Its date is a date
 * field, so the events page lists those from today on (nearest first) and
 * the past events the rest (latest first); the sample dates are counted
 * from the day the template opens ($event_on()). Kind is a pick list whose
 * answers are the words parts.php gives, so a link with ?kind= filters the
 * list by the words a visitor sees. Tickets, when an event has any, are
 * sold elsewhere: the event links that page (ticket_url), and the button
 * leaves when an event has none. Featured is the brewery's own choice
 * (office use only): the home page shows the featured events to come.
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

// The rows of a list, one event under the other.
$event_list = function () use ($widget_root, $loop, $event_row) {
    return $widget_root(array($loop(array($event_row()))));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The events to come, narrowed by kind from the address.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events'),
    'meta_description' => lang('Live music, quiz nights, tap takeovers and beer releases in the taproom, with the start time and the entry.'),
    'tree'             => $page(array(
        $page_header(lang('Events'), lang('What is on in the taproom, nearest first. Choose a kind of event to narrow the list.')),
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $bw_filter_bar('events', lang('Event Kind'), 'kind', $event_kind_titles),
                $widget('events_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the events already held'), '{{page:past_events}}', 'fw-semibold link-primary'),
                )),
            )),
        )),
    )),
);

// The events already held, latest first.
$event_pages[] = array(
    'key'              => 'past_events',
    'name'             => lang('past-events'),
    'folder'           => 'public',
    'title'            => lang('Past Events'),
    'meta_description' => lang('The events the taproom has already held, latest first.'),
    'tree'             => $page(array(
        $page_header(lang('Past Events'), lang('What the taproom has already held, latest first. Thank you to everyone who came.')),
        $el('section', 'py-5', lang('Past Events'), array(
            $container(array(
                $widget('past_events_list'),
                $el('p', 'mt-4 mb-0', lang('Text'), array(
                    $link(lang('See the events to come'), '{{page:events}}', 'fw-semibold link-primary'),
                )),
            )),
        )),
    )),
);

// One event.
$event_pages[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event Details'),
    'meta_description' => lang('An event in the taproom: the date, the start time, what entry costs and what is on.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

// The staff page that adds an event. The form is the Taproom Events
// collection; the sample events are written in when the form is made,
// their dates counted from today. All of them are free to come in, so
// none links a ticket page.
$sample_events = array();
foreach ($events as $key => $e) {
    $sample_events[] = array(
        'title'      => $e['title'],
        'event_date' => $event_on($e['weekday'], $e['weeks']),
        'starts'     => $e['starts'],
        'kind'       => $event_kinds[$e['kind']]['title'],
        'entry'      => $e['entry'],
        'summary'    => $e['text'],
        'featured'   => $e['featured'] ? $bw_featured_choices[0] : $bw_featured_choices[1],
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
        'form_name'            => lang('Taproom Events'),
        'collection'           => 1,
        'confirmation_message' => lang('The event is published.'),
        'confirmation_page_id' => '{{tab:events}}',
        'sample_records'       => $sample_events,
    ),
    'tree'    => $bw_form_page(lang('New Event'),
        lang('Add the event with its date, start time and entry; it appears in the events as soon as you send it and moves to the past events the day after.'),
        'event_form'),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$event_widgets = array(

    // The staff's Taproom Events form: one event. Featured starts as No.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('bw-event-title', lang('Event Title'), pg_cf_with_role($input('bw-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('bw-event-date', lang('Event Date'), $input('bw-event-date', 'date', 'event_date', 'off'))), '6'),
                $col(array($field('bw-event-starts', lang('Starts at'), $input('bw-event-starts', 'text', 'starts', 'off'), 'mb-3',
                    lang('The time the event starts, for example 19:30.'))), '6'),
            ), '3'),
            $field('bw-event-kind', lang('Event Kind'), pg_cf_with_role($select('bw-event-kind', 'kind', lang('Choose a kind of event…'), $event_kind_titles), 'category')),
            $field('bw-event-entry', lang('Admission'), $input('bw-event-entry', 'text', 'entry', 'off'), 'mb-3',
                lang('What it costs to come in, for example Free entry.')),
            $field('bw-event-summary', lang('Summary'), pg_cf_with_role($textarea('bw-event-summary', 'summary', 3), 'description')),
            $form_step(2, lang('Tickets')),
            $field('bw-event-tickets', lang('Ticket address'), $input('bw-event-tickets', 'url', 'ticket_url', 'url', false), 'mb-3',
                lang('The page where tickets are sold, when the event has any. Leave empty to hide the button.')),
            $field('bw-event-featured', lang('Featured'), $select('bw-event-featured', 'featured', '', $bw_featured_choices, false, $bw_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured event to come is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The events to come, filtered by the address (kind), nearest first.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $bw_list_config('event_new', 'event', 'event_date', 'ASC',
            lang('Nothing of this kind is planned right now. Join the newsletter to hear about the next event first.'), array(
            'items_per_page' => 20,
            'filters'        => array(
                $bw_date_filter('event_date', 'is greater than or equal to'),
                $bw_filter('kind', 'is equal to', '', 'kind'),
            ),
        )),
        'tree'   => $event_list(),
    ),

    // The events already held, latest first.
    'past_events_list' => array(
        'page'   => 'past_events',
        'slug'   => lang('form-list'),
        'config' => $bw_list_config('event_new', 'event', 'event_date', 'DESC', lang('No events held yet.'), array(
            'items_per_page' => 20,
            'filters'        => array($bw_date_filter('event_date', 'is less than')),
        )),
        'tree'   => $event_list(),
    ),

    // The home page's featured events to come, nearest first, three at most.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $bw_list_config('event_new', 'event', 'event_date', 'ASC', lang('No featured events to come yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array(
                $bw_date_filter('event_date', 'is greater than or equal to'),
                $bw_filter('featured', 'is equal to', $bw_featured_choices[0]),
            ),
        )),
        'tree'   => $event_list(),
    ),
);

// A fact of the event page: an icon, the label and the bound value.
$event_fact = function ($icon_name, $label, $token, $sample) use ($el, $icon, $span, $bind) {
    return $el('p', 'pg-hide-if-empty d-flex align-items-center gap-2 mb-2', $label, array(
        $icon($icon_name, 'text-primary'),
        $span($label, 'text-body-secondary', lang('Label')),
        $span($sample, 'pg-field-value fw-semibold', lang('Value'), $bind($token)),
    ));
};

// One event: the date, the kind, the title, the start time, the entry, what
// is on, and the ticket link when the event has one.
$event_sample_key = 'release';
$event_sample = $events[$event_sample_key];
$event_not_found = lang('This event could not be found. It may have been moved or called off.');
$event_widgets['event_detail'] = array(
    'page'   => 'event',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $event_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $bw_not_found('bi-calendar-x', lang('Event not found'), $event_not_found, lang('See the events to come'), 'events'),
            $el('article', '', lang('Event Details'), array(
                $bw_toolbar('events', lang('All Events')),
                $row(array(
                    $col(array(
                        $el('div', 'pbw-date fs-4 fw-bold text-primary mb-2', lang('Event Date'), array(), array_merge(
                            array('text' => $event_on($event_sample['weekday'], $event_sample['weeks'])), $bind('event_date'))),
                        $span($event_kinds[$event_sample['kind']]['title'], 'pbw-eyebrow d-block small text-uppercase fw-semibold text-primary mb-1', lang('Event Kind'), $bind('kind')),
                        $heading('h1', $event_sample['title'], 'pbw-display display-5 mb-3', $bind('title')),
                        $event_fact('bi-clock', lang('Starts at'), 'starts', $event_sample['starts']),
                        $event_fact('bi-door-open', lang('Admission'), 'entry', $event_sample['entry']),
                        $para($event_sample['text'], 'lead text-body-secondary mt-3 mb-0', $bind('summary')),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Tickets'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Coming along'), 'pbw-display h5 mb-2'),
                                $para(lang('Most events are free to come in. When an event sells tickets, the button opens the seller\'s page.'), 'small text-body-secondary mb-3'),
                                $bw_record_link(lang('Tickets'), 'ticket_url', 'btn btn-primary rounded-pill w-100 mb-2'),
                                $link(lang('Opening Hours and Directions'), '{{page:taproom}}', 'btn btn-outline-primary rounded-pill w-100 mb-3'),
                                $para($brewery['adults'], 'small text-body-secondary mb-0'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
