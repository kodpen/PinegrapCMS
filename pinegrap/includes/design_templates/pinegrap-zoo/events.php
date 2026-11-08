<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Zoo", its events: the staff page that adds an event (the
 * Events form, a collection, with the sample events written in when it is
 * made), the events from today on with the kinds that narrow the list
 * (?kind=), the event's page, and the featured events the home page
 * places. Included after parts.php and cards.php in the same scope;
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * An event's date is a date field, so the lists show those from today on,
 * nearest first; the sample dates are counted from the day the template
 * opens ($event_on). Kind is a pick list whose answers are its own words,
 * so the kind pills filter the list in the words the record keeps. The
 * price is words: an event is paid with a day ticket or at the gate, never
 * booked here.
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

// A filter row of a list: a fixed value, today's date, or the value of the
// address parameter $param when one is named.
$zev_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
$zev_from_today = array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date');

// A list of the Events collection: the event's page opens a record, the
// list runs by date, nearest first.
$zev_record_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'detail_page_id'      => '{{tab:event}}',
        'order_by_field'      => 'event_date',
        'order_by_direction'  => 'ASC',
        'empty_message'       => $empty,
    ), $more);
};

// The event cards of a list, two to a row on wide screens.
$event_grid = function () use ($widget_root, $row, $loop, $event_card) {
    return $widget_root(array($row(array($loop(array($event_card()))), '4')));
};

// The kinds of event as pills: All, then each kind opening the list on it.
$zev_kind_pills = function () use ($el, $link, $event_kind_titles, $query) {
    $items = array($el('li', '', lang('List Item'), array($link(lang('All'), '{{page:events}}', 'btn btn-sm btn-success rounded-pill'))));
    foreach ($event_kind_titles as $title) {
        $items[] = $el('li', '', lang('List Item'), array(
            $link($title, '{{page:events}}?' . $query(array('kind' => $title)), 'btn btn-sm btn-outline-success rounded-pill'),
        ));
    }
    return $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Event Kinds'), $items);
};

$zev_featured_choices = array(lang('Yes'), lang('No'));

// ── Pages ───────────────────────────────────────────────────────────────

// The events to come, narrowed by kind from the address.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events'),
    'meta_description' => lang('Night tours, family days, talks and workshops at the zoo and the aquarium, nearest first, with the time, the place and the price.'),
    'tree'             => $page(array(
        $page_header(lang('Events'), lang('What is on at the zoo, nearest first. Choose a kind of event to narrow the list.')),
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $el('nav', 'mb-4', lang('Event Kinds'), array($zev_kind_pills()), array('_attrs' => array($attr('aria-label', lang('Event kind'))))),
                $widget('events_list'),
            )),
        ), $searchable('zoo-event-list', lang('night tour, family day, talk, workshop, what is on, calendar'))),
    )),
);

// One event.
$event_pages[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event'),
    'meta_description' => lang('An event at the zoo: the date, the time, where in the park it meets, what it costs and what happens.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

// The staff page that adds an event. The form is the Events collection;
// the sample events are written in when the form is made, their dates
// counted from today, so the lists do not open empty.
$sample_events = array();
foreach ($events as $zev_key => $zev_event) {
    $sample_events[] = array(
        'title'      => $zev_event['title'],
        'event_date' => $event_on($zev_event['weekday'], $zev_event['weeks']),
        'event_time' => $zev_event['time'],
        'kind'       => $event_kinds[$zev_event['kind']],
        'place'      => $zev_event['place'],
        'price'      => $zev_event['price'],
        'summary'    => $zev_event['summary'],
        'photo_1'    => $photo('event-' . $zev_key, 800, 600),
        'featured'   => $zev_event['featured'] ? $zev_featured_choices[0] : $zev_featured_choices[1],
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
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Event'), lang('Add an event with its date, time, place and price; it appears in the events as soon as you send it and leaves the list the day after.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm rounded-4 p-4', lang('Form Card'), array($widget('event_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$event_widgets = array(

    // The staff's Events form: one event, when and where, its price in
    // words, its photo and whether the home shows it. Featured starts as No.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('zoo-event-title', lang('Event title'), pg_cf_with_role($input('zoo-event-title', 'text', 'title', 'off'), 'title')),
            $field('zoo-event-kind', lang('Event kind'), pg_cf_with_role($select('zoo-event-kind', 'kind', lang('Choose a kind'), $event_kind_titles), 'category')),
            $field('zoo-event-summary', lang('Summary'), pg_cf_with_role($textarea('zoo-event-summary', 'summary', 3), 'description'), 'mb-3',
                lang('Two sentences for the cards and the event\'s page.')),
            $form_step(2, lang('When and where')),
            $row(array(
                $col(array($field('zoo-event-date', lang('Date'), $input('zoo-event-date', 'date', 'event_date', 'off'))), '6'),
                $col(array($field('zoo-event-time', lang('Time'), $input('zoo-event-time', 'text', 'event_time', 'off'), 'mb-3',
                    sprintf(lang('The time it starts, for example %s.'), $events['night_walk']['time']))), '6'),
            ), '3'),
            $field('zoo-event-place', lang('Place'), $input('zoo-event-place', 'text', 'place', 'off'), 'mb-3',
                sprintf(lang('Where in the park it meets, for example %s.'), $events['night_walk']['place'])),
            $field('zoo-event-price', lang('Price'), $input('zoo-event-price', 'text', 'price', 'off'), 'mb-3',
                sprintf(lang('In words, for example %s.'), $events['penguin_day']['price'])),
            $form_step(3, lang('Photo and home page')),
            $field('zoo-event-photo', lang('Photo'), pg_cf_with_role($file('zoo-event-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The picture of the event\'s page and the share image.')),
            $field('zoo-event-featured', lang('Featured'), $select('zoo-event-featured', 'featured', '', $zev_featured_choices, false, $zev_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured event to come is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The events from today on, filtered by the address (kind), nearest
    // first.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $zev_record_list(lang('Nothing of this kind is planned right now. Join the newsletter to hear about the next event first.'), array(
            'items_per_page' => 20,
            'filters'        => array(
                $zev_from_today,
                $zev_filter('kind', 'is equal to', '', 'kind'),
            ),
        )),
        'tree'   => $event_grid(),
    ),

    // The home page's featured events to come, nearest first, two at most.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $zev_record_list(lang('No featured event is planned yet.'), array(
            'items_per_page' => 2,
            'max_results'    => 2,
            'filters'        => array(
                $zev_from_today,
                $zev_filter('featured', 'is equal to', $zev_featured_choices[0]),
            ),
        )),
        'tree'   => $event_grid(),
    ),
);

// A fact of the event page: an icon, the label and the bound value.
$zev_fact = function ($icon_name, $label, $token, $sample, $extra = array()) use ($el, $icon, $span, $bind) {
    return $el('div', 'd-flex align-items-start gap-3', $label, array(
        $icon($icon_name, 'fs-4 text-success'),
        $el('div', '', lang('Text'), array(
            $span($label, 'd-block small text-body-secondary', lang('Label')),
            $span($sample, 'd-block fw-semibold', lang('Value'), array_merge($bind($token), $extra)),
        )),
    ));
};

// One event: the photo, the kind, the title, the date, time, place and
// price, what happens, and the ways to come (a ticket, the map).
$event_sample_key = 'night_walk';
$event_sample = $events[$event_sample_key];
$event_sample_date = $event_on($event_sample['weekday'], $event_sample['weeks']);
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
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-calendar-x', 'display-5 text-body-secondary'),
                $heading('h2', lang('Event not found'), 'pzo-display h3 mt-3'),
                $para($event_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the events to come'), '{{page:events}}', 'btn btn-success rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Event'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-success text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Events'), '', lang('Link Text')),
                    ), array('href' => '{{page:events}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $span($event_kinds[$event_sample['kind']], 'badge rounded-pill bg-success-subtle text-success-emphasis mb-3', lang('Kind'), $bind('kind')),
                        $heading('h1', $event_sample['title'], 'pzo-display display-5 fw-bold mb-3', $bind('title')),
                        $para($event_sample['summary'], 'lead mb-4', $bind('summary')),
                        $el('div', 'd-grid gap-3 p-4 rounded-4 bg-body-tertiary mb-4', lang('Fact Sheet'), array(
                            $zev_fact('bi-calendar-event', lang('Date'), 'event_date', date('d.m.Y', strtotime($event_sample_date)), array('_bindFormats' => array('text' => 'd.m.Y'))),
                            $zev_fact('bi-clock', lang('Time'), 'event_time', $event_sample['time']),
                            $zev_fact('bi-geo-alt', lang('Place'), 'place', $event_sample['place']),
                            $zev_fact('bi-ticket', lang('Price'), 'price', $event_sample['price']),
                        )),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Actions'), array(
                            $link(lang('Tickets and Prices'), '{{page:tickets}}', 'btn btn-success rounded-pill px-4'),
                            $link(lang('Park Map'), '{{page:map}}#zoo-map', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $optional_image('photo_1', 'title', 'w-100 h-100 object-fit-cover', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary',
                            $photo('event-' . $event_sample_key, 800, 600), lang('Photo')),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
