<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Private School", its events: the staff page that adds
 * an event (the Events form, a collection, with the sample events written
 * in when it is made), the list of every event with its programme filter,
 * the event's own page and the list the home page places. Included after
 * parts.php in the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * An event is a record of the Events form. Its programme is a pick list
 * whose answers are the words parts.php gives (the whole school first), so
 * a link with ?programme= (the programme pages, the footer, the filter
 * row) filters the list by the words a visitor sees. When and where are
 * words: the school writes the real date in its own record, the sample
 * never names a year. Featured is the school's choice (office use only):
 * the home page shows the featured events. The newest event added comes
 * first.
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

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$sh_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks an event for the home.
$sh_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$sh_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of events: the Events form, the event page, the newest first,
// and the words shown when nothing matches.
$sh_event_list = function ($empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'detail_page_id'      => '{{tab:event}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};
// The event cards of a list, in rows of three.
$event_grid = function () use ($widget_root, $row, $loop, $event_card) {
    return $widget_root(array($row(array($loop(array($event_card()))), '4')));
};

// The links above the list that narrow it: one per programme, each
// opening the list with the filter set.
$sh_filter_bar = function ($label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:events}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:events}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every event, narrowed by programme from the address.
$event_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('School Events'),
    'meta_description' => lang('Open mornings, fairs, concerts and class events at our school, for families and new families.'),
    'tree'             => $page(array(
        $page_header(lang('School Events'), lang('Open mornings, fairs, concerts and the events of each programme. Choose a programme to narrow the list.')),
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $sh_filter_bar(lang('Programme'), 'programme', $event_programme_titles),
                $widget('events_list'),
            )),
        ), $searchable('sh-events', lang('open day, fair, concert, parents evening, calendar'))),
    )),
);

// One event: its record.
$event_pages[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event'),
    'meta_description' => lang('An event at our school: when and where it is, what happens and who it is for.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

// The staff page that adds an event. The form is the Events collection;
// the sample events are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
$sample_events = array();
foreach ($events as $key => $e) {
    $sample_events[] = array(
        'title'     => $e['title'],
        'programme' => $event_programme($key),
        'when'      => $e['when'],
        'place'     => $e['place'],
        'summary'   => $e['summary'],
        'details'   => $details_text($key),
        'photo_1'   => $photo('event-' . $key, 1200, 675),
        'featured'  => $e['featured'] ? $sh_featured_choices[0] : $sh_featured_choices[1],
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
        $page_header(lang('New Event'), lang('Add the event with its date, place and a photo; it appears in the list as soon as you send it. Mark it featured from its Edit button.')),
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

// ── Widgets ─────────────────────────────────────────────────────────────

$event_widgets = array(

    // The staff's Events form: one event. The programme starts as the
    // whole school, featured as No.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('sh-event-title', lang('Event Title'), pg_cf_with_role($input('sh-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('sh-event-programme', lang('Programme'), pg_cf_with_role($select('sh-event-programme', 'programme', '', $event_programme_titles, true, $event_programme_titles[0]), 'category'))), '6'),
                $col(array($field('sh-event-place', lang('Place'), $input('sh-event-place', 'text', 'place', 'off'))), '6'),
            ), '3'),
            $field('sh-event-when', lang('When'), $input('sh-event-when', 'text', 'when', 'off'), 'mb-3',
                sprintf(lang('The day and the time, for example %s.'), $events['spring_fair']['when'])),
            $form_step(2, lang('Words')),
            $field('sh-event-summary', lang('Summary'), pg_cf_with_role($textarea('sh-event-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('sh-event-details', lang('What Happens'), $textarea('sh-event-details', 'details', 5, false), 'mb-3', lang('One item of the programme on each line.')),
            $form_step(3, lang('Photo')),
            $field('sh-event-photo', lang('Photo'), pg_cf_with_role($file('sh-event-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image. Choose one that shows no child\'s face.')),
            $field('sh-event-featured', lang('Featured'), $select('sh-event-featured', 'featured', '', $sh_featured_choices, false, $sh_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured event is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every event, filtered by the address (programme), the newest first,
    // with a search over the words of the events.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $sh_event_list(lang('No event for this programme yet. Choose another programme, or see all events.'), array(
            'items_per_page' => 12,
            'filters'        => array($sh_filter('programme', 'is equal to', '', 'programme')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'programme', 'when', 'place', 'summary', 'details'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the events: a concert, a fair, an open morning...'),
        )),
        'tree'   => $event_grid(),
    ),

    // The home page's featured events, three at most.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $sh_event_list(lang('No featured events yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($sh_filter('featured', 'is equal to', $sh_featured_choices[0])),
        )),
        'tree'   => $event_grid(),
    ),
);

// One event: the programme, the title, the summary, when and where, the
// photo, what happens and beside them the way to book a visit or see the
// other events of the same programme.
$event_sample_key = 'open_morning';
$event_sample = $events[$event_sample_key];
$event_not_found = lang('This event could not be found. It may have taken place already or been taken off the list.');
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
                $icon('bi-calendar-x', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Event not found'), 'h3 fw-semibold mt-3'),
                $para($event_not_found, 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('All Events'), '{{page:events}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Event'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Events'), '', lang('Link Text')),
                    ), array('href' => '{{page:events}}')),
                    $edit_button(),
                )),
                $span($event_programme($event_sample_key), 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Programme'), $bind('programme')),
                $heading('h1', $event_sample['title'], 'psh-display display-6 mb-2', $bind('title')),
                $para($event_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $event_facts($event_sample_key, 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-4'),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                            $photo('event-' . $event_sample_key, 1200, 675), lang('Photo')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('What Happens'), array(
                            $heading('h2', lang('What happens on the day'), 'psh-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $details_text($event_sample_key)), $bind('details'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-4', lang('Event Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('New to the school?'), 'psh-display h5 mb-2'),
                                $para(lang('Come and see us on an ordinary school day, or send an application and we will call you within a week.'), 'small text-body-secondary mb-3'),
                                $link(lang('Book a School Visit'), '{{page:visit}}', 'btn btn-primary rounded-pill w-100 mb-2'),
                                // Opens the list on this event's programme
                                // (?programme=).
                                $link(lang('More events of this programme'), '#', 'btn btn-outline-secondary rounded-pill w-100', array(),
                                    $sh_page_link('events', array('programme' => 'programme'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $event_pages, 'widgets' => $event_widgets);
