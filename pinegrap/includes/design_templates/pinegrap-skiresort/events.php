<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort", its events: the staff page that adds an
 * event (the Events form, a collection, with the sample events written in
 * when it is made), the events page from today on, the event's page and
 * the next featured events the home page places. Included after parts.php,
 * cards.php and slopes.php in the same scope (it uses the list pieces of
 * slopes.php: $skr_record_list, $skr_filter, $skr_featured_choices);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * Every sample event is dated from the day the template opens ($event_on),
 * and the lists keep only the events from today on (the current date
 * filter), nearest first. The events page draws each event in the
 * structure of the palette's events-list block (the date in a tile, the
 * title, the time and place, the summary, the way to the event), bound to
 * the record; the home's cards are the events-cards structure of cards.php.
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

// ── Pieces ──────────────────────────────────────────────────────────────

// The sample events as records of the Events form, each dated from the
// day the template opens.
$sample_events = array();
foreach ($events as $key => $e) {
    $sample_events[] = array(
        'title'      => $e['title'],
        'event_date' => $event_on($e['weekday'], $e['weeks']),
        'event_time' => $e['time'],
        'kind'       => $event_kinds[$e['kind']],
        'place'      => $e['place'],
        'price'      => $e['price'],
        'summary'    => $e['summary'],
        'photo_1'    => $photo('event-' . $key, 1200, 675),
        'featured'   => $e['featured'] ? $skr_featured_choices[0] : $skr_featured_choices[1],
    );
}

// The events from today on, nearest first.
$event_list = function ($empty, $more) use ($skr_record_list, $skr_filter) {
    return $skr_record_list('event_new', 'event', $empty, array_merge(array(
        'filters' => array(array_merge($skr_filter('event_date', 'is greater than or equal to', ''), array('dynamic_value' => 'current date'))),
    ), $more), 'event_date', 'ASC');
};

// An event of the events page, in the events-list block's structure: the
// date in a tile, the kind, the title linking the event's page, the time
// and the place, the summary and the price. Bound to the Events form's
// fields; the sample's date is counted from today.
$event_line = function ($sample_key = 'torchlight') use ($el, $heading, $para, $span, $link, $icon, $bind, $bind_date, $events, $event_kinds, $event_on) {
    $e = $events[$sample_key];
    $date = $event_on($e['weekday'], $e['weeks']);
    return $el('article', 'card border-0 shadow-sm psk-lift', lang('Event'), array(
        $el('div', 'card-body d-flex flex-column flex-sm-row align-items-sm-center gap-3 gap-sm-4 p-4', lang('Event Body'), array(
            // A tile, not a <time>: the list cannot bind the datetime
            // attribute to each record's date.
            $el('div', 'flex-shrink-0 align-self-start align-self-sm-center text-center rounded-3 bg-primary-subtle text-primary-emphasis px-3 py-2', lang('Event Date'), array(
                $span(date('m.Y', strtotime($date)), 'd-block small fw-semibold', lang('Month'), $bind_date('event_date', 'm.Y')),
                $span(date('d', strtotime($date)), 'psk-display d-block fs-3 fw-bold lh-1', lang('Day'), $bind_date('event_date', 'd')),
            )),
            $el('div', 'flex-grow-1', lang('Event Copy'), array(
                $span($event_kinds[$e['kind']], 'psk-eyebrow d-block small text-uppercase fw-semibold text-primary mb-1', lang('Kind'), $bind('kind')),
                $el('h3', 'psk-display h5 position-static mb-1', lang('Event Title'), array(
                    $link($e['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 small text-body-secondary mb-2', lang('Time and Place'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Time'), array(
                        $icon('bi-clock', 'text-primary'), $span($e['time'], '', lang('Time'), $bind('event_time')),
                    )),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Place'), array(
                        $icon('bi-geo-alt', 'text-primary'), $span($e['place'], '', lang('Place'), $bind('place')),
                    )),
                )),
                $para($e['summary'], 'mb-0', $bind('summary')),
            )),
            $el('div', 'flex-shrink-0', lang('Event Action'), array(
                $el('span', 'd-inline-flex align-items-center gap-1 small fw-semibold', lang('Price'), array(
                    $icon('bi-ticket', 'text-primary'), $span($e['price'], '', lang('Price'), $bind('price')),
                )),
            )),
        )),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// The events from today on, nearest first; the lifts' hours beside them,
// since the night events follow the night skiing.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events'),
    'meta_description' => lang('Races, torchlight descents, night skiing, festivals and family days at the resort, from today on.'),
    'tree'             => $page(array(
        $page_header(lang('Events'), lang('Races, torchlight descents, night skiing and festivals in the village. Every event of the season, the nearest first.')),
        $el('section', 'py-5', lang('Upcoming Events'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Coming up at the resort'), 'psk-display h3 mb-4'),
                        $widget('events_list'),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Opening Hours'), array(
                            $heading('h2', lang('Lifts and night skiing'), 'psk-display h5 mb-3'),
                            $hours_list('mb-3'),
                            $link(lang('Today\'s Conditions'), '{{page:conditions}}', 'fw-semibold link-primary text-decoration-none small'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Newsletter'), array(
                            $heading('h2', lang('Never miss an event'), 'psk-display h5 mb-2'),
                            $para(lang('The snow news and the next events, once a week in winter.'), 'small text-body-secondary mb-3'),
                            $link(lang('Subscribe'), '{{page:newsletter}}', 'btn btn-sm btn-outline-primary px-3'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('skr-events', lang('event, race, torchlight, night skiing, festival, family day, live music, calendar'))),
    )),
);

// One event.
$event_pages[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event'),
    'meta_description' => lang('An event at the resort: its date and time, where it happens, what it costs and what to expect.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

// The staff page that adds an event. The form is the Events collection;
// the sample events are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
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
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Event'), lang('Add an event with its date, time, place and photo; it appears in the list until its day has passed.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm p-4', lang('Form Card'), array($widget('event_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$event_widgets = array(

    // The staff's Events form: one event, its day, its place and whether
    // the home shows it.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('skr-event-title', lang('Name'), pg_cf_with_role($input('skr-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('skr-event-date', lang('Date'), $input('skr-event-date', 'date', 'event_date', 'off'))), '4'),
                $col(array($field('skr-event-time', lang('Starts at'), $input('skr-event-time', 'text', 'event_time', 'off', true, array(), array($attr('placeholder', '18:30'))))), '4'),
                $col(array($field('skr-event-kind', lang('Kind'), pg_cf_with_role($select('skr-event-kind', 'kind', lang('Choose a kind'), $event_kind_titles), 'category'))), '4'),
            ), '3'),
            $row(array(
                $col(array($field('skr-event-place', lang('Place'), $input('skr-event-place', 'text', 'place', 'off'), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $events['torchlight']['place']))), '6'),
                $col(array($field('skr-event-price', lang('Price'), $input('skr-event-price', 'text', 'price', 'off', false), 'mb-3',
                    sprintf(lang('In words, for example %s.'), $events['club_race']['price']))), '6'),
            ), '3'),
            $field('skr-event-summary', lang('Summary'), pg_cf_with_role($textarea('skr-event-summary', 'summary', 3), 'description')),
            $form_step(2, lang('Photo')),
            $field('skr-event-photo', lang('Photo'), pg_cf_with_role($file('skr-event-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the cards and the share image.')),
            $field('skr-event-featured', lang('Featured'), $select('skr-event-featured', 'featured', '', $skr_featured_choices, false, $skr_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured event is shown on the home page until its day.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The events from today on, nearest first.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $event_list(lang('No events planned right now. Subscribe to the newsletter to hear about the next ones.'), array('items_per_page' => 12)),
        'tree'   => $widget_root(array(
            $el('div', 'd-flex flex-column gap-3', lang('Event List'), array($loop(array($event_line())))),
        )),
    ),

    // The home page's events: the next featured ones, three at most.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('coming-events'),
        'config' => $event_list(lang('No events planned right now.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array(
                array_merge($skr_filter('event_date', 'is greater than or equal to', ''), array('dynamic_value' => 'current date')),
                $skr_filter('featured', 'is equal to', $skr_featured_choices[0]),
            ),
        )),
        'tree'   => $widget_root(array($row(array($loop(array($event_card()))), '4'))),
    ),
);

// One event: the photo, the kind, the title, when and where, the price and
// the summary, with the ways to the other events and to the conditions.
$event_sample_key = 'torchlight';
$event_sample = $events[$event_sample_key];
$event_sample_date = $event_on($event_sample['weekday'], $event_sample['weeks']);
$event_not_found = lang('This event could not be found. It may have passed, or the page was renamed.');
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
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-calendar-x', 'display-5 text-body-secondary'),
                $heading('h2', lang('Event not found'), 'psk-display h3 mt-3'),
                $para($event_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the events'), '{{page:events}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Event'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('Events'), '', lang('Link Text')),
                    ), array('href' => '{{page:events}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-16x9 rounded-4 overflow-hidden bg-body-tertiary',
                            $photo('event-' . $event_sample_key, 1200, 675), lang('Photo')),
                    ), '', '7'),
                    $col(array(
                        $span($event_kinds[$event_sample['kind']], 'psk-eyebrow d-block small text-uppercase fw-semibold text-primary mb-2', lang('Kind'), $bind('kind')),
                        $heading('h1', $event_sample['title'], 'psk-display display-5 fw-bold mb-3', $bind('title')),
                        $el('ul', 'list-unstyled mb-4', lang('When and Where'), array(
                            $el('li', 'd-flex align-items-center gap-2 mb-2', lang('Date'), array(
                                $icon('bi-calendar3', 'text-primary'),
                                $span($event_sample_date, 'fw-semibold', lang('Date'), $bind('event_date')),
                            )),
                            $el('li', 'd-flex align-items-center gap-2 mb-2', lang('Time'), array(
                                $icon('bi-clock', 'text-primary'),
                                $span($event_sample['time'], '', lang('Time'), $bind('event_time')),
                            )),
                            $el('li', 'd-flex align-items-center gap-2 mb-2', lang('Place'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span($event_sample['place'], '', lang('Place'), $bind('place')),
                            )),
                            $el('li', 'pg-hide-if-empty d-flex align-items-center gap-2 mb-0', lang('Price'), array(
                                $icon('bi-ticket', 'text-primary'),
                                $span($event_sample['price'], 'pg-field-value psk-price', lang('Price'), $bind('price')),
                            )),
                        )),
                        $para($event_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('All events'), '{{page:events}}', 'btn btn-primary px-4'),
                            $link(lang('Today\'s Conditions'), '{{page:conditions}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
