<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "City Hall", its two collections: the Notices (public
 * notices, tenders, council decisions, vacancies and zoning notices) and
 * the Events. For each: the staff page that adds a record (the form, a
 * collection, with the samples written in when it is made), the list of
 * every record with its filter, the record's own page and the list the
 * home page places. Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A notice's type and an event's category are pick lists whose answers are
 * the words parts.php gives, so a link with ?type= or ?category= (the
 * footer, the council page, the filter row) filters the list by the words
 * a visitor sees. Dates are words: the city writes the real date in its
 * own record, the sample never names a year. Featured is the staff's
 * choice (office use only): the home page shows the featured records. The
 * newest record added comes first.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$notice_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$cy_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a record for the home.
$cy_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$cy_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list of a collection: its form, its record page, the newest first, and
// the words shown when nothing matches.
$cy_list = function ($form_key, $detail_key, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_key . '}}',
        'detail_page_id'      => '{{tab:' . $detail_key . '}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'DESC',
        'empty_message'       => $empty,
    ), $more);
};
// The cards of a list, in rows of three.
$notice_grid = function () use ($widget_root, $row, $loop, $notice_card) {
    return $widget_root(array($row(array($loop(array($notice_card()))), '4')));
};
$event_grid = function () use ($widget_root, $row, $loop, $event_card) {
    return $widget_root(array($row(array($loop(array($event_card()))), '4')));
};

// The links above a list that narrow it: one per answer, each opening the
// list with the filter set.
$cy_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The staff page of a collection's form: the staff bar, the header and the
// form in a card.
$cy_form_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget($widget_key))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    ));
};

// The not-found note of a record page.
$cy_not_found = function ($icon_name, $title, $text, $back_text, $back_key) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_key . '}}', 'btn btn-primary px-4'),
    ), $show_when('record_not_found'));
};
// The line above a record: back to the list, and the staff's Edit button.
$cy_toolbar = function ($back_text, $back_key) use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($back_text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $back_key . '}}')),
        $edit_button(),
    ));
};

// ── Notices ─────────────────────────────────────────────────────────────

// Every notice, narrowed by type from the address.
$notice_pages[] = array(
    'key'              => 'notices',
    'name'             => lang('notices'),
    'folder'           => 'public',
    'title'            => lang('Public Notices'),
    'meta_description' => lang('Public notices, tenders, council decisions, vacancies and zoning notices of the city hall, with their deadlines.'),
    'tree'             => $page(array(
        $page_header(lang('Public Notices'), lang('Notices, tenders, council decisions, vacancies and zoning notices. Choose a type to narrow the list.')),
        $el('section', 'py-5', lang('Public Notices'), array(
            $container(array(
                $cy_filter_bar('notices', lang('Type'), 'type', $notice_type_titles),
                $widget('notices_list'),
            )),
        ), $searchable('cy-notices', lang('announcement, tender, bid, council decision, job, vacancy, zoning plan, objection'))),
    )),
);

// One notice: its record.
$notice_pages[] = array(
    'key'              => 'notice',
    'name'             => lang('notice'),
    'folder'           => 'public',
    'title'            => lang('Public Notice'),
    'meta_description' => lang('A public notice of the city hall: its reference, its deadline, the department and the documents.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('notice_detail'),
    )),
);

// The staff page that adds a notice. The form is the Notices collection;
// the sample notices are written in when the form is made, so the lists do
// not open empty (pg_cf_seed_sample_records()).
$sample_notices = array();
foreach ($notices as $key => $n) {
    $sample_notices[] = array(
        'title'      => $n['title'],
        'type'       => $notice_types[$n['type']],
        'reference'  => $n['ref'],
        'department' => $notice_department($key),
        'published'  => $n['published'],
        'deadline'   => $n['deadline'],
        'summary'    => $n['summary'],
        'details'    => $notice_details_text($key),
        'featured'   => $n['featured'] ? $cy_featured_choices[0] : $cy_featured_choices[1],
    );
}
$notice_pages[] = array(
    'key'     => 'notice_new',
    'name'    => lang('new-notice'),
    'folder'  => 'private',
    'title'   => lang('New Notice'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Public Notices'),
        'collection'           => 1,
        'confirmation_message' => lang('The notice is published.'),
        'confirmation_page_id' => '{{tab:notices}}',
        'sample_records'       => $sample_notices,
    ),
    'tree'    => $cy_form_page(lang('New Notice'),
        lang('Publish a notice, a tender, a council decision or a vacancy with its reference and deadline; it appears in the list as soon as you send it. Mark it featured from its Edit button.'),
        'notice_form'),
);

// ── Events ──────────────────────────────────────────────────────────────

// Every event, narrowed by category from the address.
$notice_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('City Events'),
    'meta_description' => lang('Festivals, concerts, sport, markets, children\'s events and ceremonies in the city, free and open to everyone.'),
    'tree'             => $page(array(
        $page_header(lang('City Events'), lang('Festivals, concerts, markets and ceremonies across the city. Choose a category to narrow the list.')),
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $cy_filter_bar('events', lang('Category'), 'category', $event_category_titles),
                $widget('events_list'),
            )),
        ), $searchable('cy-events', lang('festival, concert, market, run, children, ceremony, what\'s on, calendar'))),
    )),
);

// One event: its record.
$notice_pages[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event'),
    'meta_description' => lang('An event in the city: when and where it is, what happens and who it is for.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

$sample_events = array();
foreach ($events as $key => $e) {
    $sample_events[] = array(
        'title'    => $e['title'],
        'category' => $event_categories[$e['category']],
        'when'     => $e['when'],
        'place'    => $e['place'],
        'summary'  => $e['summary'],
        'details'  => $event_details_text($key),
        'photo_1'  => $photo('event-' . $key, 1200, 675),
        'featured' => $e['featured'] ? $cy_featured_choices[0] : $cy_featured_choices[1],
    );
}
$notice_pages[] = array(
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
    'tree'    => $cy_form_page(lang('New Event'),
        lang('Add the event with its date, place and a photo; it appears in the list as soon as you send it. Mark it featured from its Edit button.'),
        'event_form'),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$notice_widgets = array(

    // The staff's Notices form: one notice. The type starts as a public
    // notice, featured as No. The document (tender papers, the full
    // decision) is a file any visitor may download.
    'notice_form' => array(
        'page'   => 'notice_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The notice')),
            $field('cy-notice-title', lang('Notice Title'), pg_cf_with_role($input('cy-notice-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('cy-notice-type', lang('Type'), pg_cf_with_role($select('cy-notice-type', 'type', '', $notice_type_titles, true, $notice_type_titles[0]), 'category'))), '6'),
                $col(array($field('cy-notice-reference', lang('Reference Number'), $input('cy-notice-reference', 'text', 'reference', 'off'))), '6'),
            ), '3'),
            $field('cy-notice-department', lang('Responsible Department'), $select('cy-notice-department', 'department', lang('Choose the department'), $notice_department_titles)),
            $row(array(
                $col(array($field('cy-notice-published', lang('Publication Date'), $input('cy-notice-published', 'text', 'published', 'off'), 'mb-3',
                    lang('The day it is published, as you want it read.'))), '6'),
                $col(array($field('cy-notice-deadline', lang('Deadline'), $input('cy-notice-deadline', 'text', 'deadline', 'off'), 'mb-3',
                    sprintf(lang('The closing day and hour, for example %s.'), $notices['park_tender']['deadline']))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('cy-notice-summary', lang('Summary'), pg_cf_with_role($textarea('cy-notice-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('cy-notice-details', lang('Details'), $textarea('cy-notice-details', 'details', 6, false), 'mb-3', lang('One point on each line.')),
            $form_step(3, lang('Document')),
            $field('cy-notice-document', lang('Document'), $file('cy-notice-document', 'document', 'public', '.pdf,.doc,.docx,.odt,.xls,.xlsx,.zip'), 'mb-3',
                lang('The tender papers, the full decision or the plan, for visitors to download.')),
            $field('cy-notice-featured', lang('Featured'), $select('cy-notice-featured', 'featured', '', $cy_featured_choices, false, $cy_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured notice is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every notice, filtered by the address (type), the newest first, with
    // a search over the words and reference numbers of the notices.
    'notices_list' => array(
        'page'   => 'notices',
        'slug'   => lang('form-list'),
        'config' => $cy_list('notice_new', 'notice', lang('No notice of this type yet. Choose another type, or see all notices.'), array(
            'items_per_page' => 12,
            'filters'        => array($cy_filter('type', 'is equal to', '', 'type')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'type', 'reference', 'department', 'summary', 'details'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the notices: a tender, a reference number, a street...'),
        )),
        'tree'   => $notice_grid(),
    ),

    // The home page's featured notices, three at most.
    'home_notices' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $cy_list('notice_new', 'notice', lang('No featured notices yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($cy_filter('featured', 'is equal to', $cy_featured_choices[0])),
        )),
        'tree'   => $notice_grid(),
    ),

    // The staff's Events form: one event. The category starts as culture,
    // featured as No.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The event')),
            $field('cy-event-title', lang('Event Title'), pg_cf_with_role($input('cy-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('cy-event-category', lang('Category'), pg_cf_with_role($select('cy-event-category', 'category', '', $event_category_titles, true, $event_category_titles[0]), 'category'))), '6'),
                $col(array($field('cy-event-place', lang('Place'), $input('cy-event-place', 'text', 'place', 'off'))), '6'),
            ), '3'),
            $field('cy-event-when', lang('When'), $input('cy-event-when', 'text', 'when', 'off'), 'mb-3',
                sprintf(lang('The day and the time, for example %s.'), $events['city_run']['when'])),
            $form_step(2, lang('Words')),
            $field('cy-event-summary', lang('Summary'), pg_cf_with_role($textarea('cy-event-summary', 'summary', 2), 'description'), 'mb-3',
                lang('One or two lines for the cards of the lists.')),
            $field('cy-event-details', lang('What Happens'), $textarea('cy-event-details', 'details', 5, false), 'mb-3', lang('One item of the programme on each line.')),
            $form_step(3, lang('Photo')),
            $field('cy-event-photo', lang('Photo'), pg_cf_with_role($file('cy-event-photo', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $field('cy-event-featured', lang('Featured'), $select('cy-event-featured', 'featured', '', $cy_featured_choices, false, $cy_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured event is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every event, filtered by the address (category), the newest first,
    // with a search over the words of the events.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => $cy_list('event_new', 'event', lang('No event in this category yet. Choose another category, or see all events.'), array(
            'items_per_page' => 12,
            'filters'        => array($cy_filter('category', 'is equal to', '', 'category')),
            'search_enabled' => true,
            'search_fields'  => array('title', 'category', 'when', 'place', 'summary', 'details'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the events: a concert, a market, a run...'),
        )),
        'tree'   => $event_grid(),
    ),

    // The home page's featured events, three at most.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $cy_list('event_new', 'event', lang('No featured events yet.'), array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($cy_filter('featured', 'is equal to', $cy_featured_choices[0])),
        )),
        'tree'   => $event_grid(),
    ),
);

// One notice: the type and reference, the title, the summary, the facts,
// the details, the document and beside them the way to ask about it or to
// see the other notices of the same type.
$notice_sample_key = 'park_tender';
$notice_sample = $notices[$notice_sample_key];
$notice_not_found = lang('This notice could not be found. It may have expired or been taken off the list.');
$notice_widgets['notice_detail'] = array(
    'page'   => 'notice',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:notice_new}}',
        'access_control'      => 'public',
        'not_found_message'   => $notice_not_found,
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $cy_not_found('bi-file-earmark-x', lang('Notice not found'), $notice_not_found, lang('All Notices'), 'notices'),
            $el('article', '', lang('Public Notice'), array(
                $cy_toolbar(lang('All Notices'), 'notices'),
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Type and Reference'), array(
                    $span($notice_types[$notice_sample['type']], 'badge text-bg-primary', lang('Type'), $bind('type')),
                    $span($notice_sample['ref'], 'pcy-ref small text-body-secondary', lang('Reference'), $bind('reference')),
                )),
                $heading('h1', $notice_sample['title'], 'pcy-display display-6 mb-2', $bind('title')),
                $para($notice_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $notice_facts($notice_sample_key, 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-4'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Details'), array(
                            $heading('h2', lang('Details'), 'pcy-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $notice_details_text($notice_sample_key)), $bind('details'))),
                        )),
                        $el('div', 'pg-hide-if-empty mb-4', lang('Document'), array(
                            $el('span', 'pg-field-value', lang('Value'), array(
                                $link(lang('Download the document'), '#', 'btn btn-outline-primary px-4', array(),
                                    array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => 'document'))),
                            )),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-3', lang('Notice Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('A question about this notice?'), 'pcy-display h5 mb-2'),
                                $para(lang('Write to us with the reference number, or call the department named above.'), 'small text-body-secondary mb-3'),
                                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary w-100 mb-2'),
                                // Opens the list on this notice's type (?type=).
                                $link(lang('More notices of this type'), '#', 'btn btn-outline-secondary w-100', array(),
                                    $cy_page_link('notices', array('type' => 'type'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

// One event: the category, the title, the summary, when and where, the
// photo, what happens and beside them the other events of the category.
$event_sample_key = 'summer_festival';
$event_sample = $events[$event_sample_key];
$event_not_found = lang('This event could not be found. It may have taken place already or been taken off the list.');
$notice_widgets['event_detail'] = array(
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
            $cy_not_found('bi-calendar-x', lang('Event not found'), $event_not_found, lang('All Events'), 'events'),
            $el('article', '', lang('Event'), array(
                $cy_toolbar(lang('All Events'), 'events'),
                $span($event_categories[$event_sample['category']], 'badge bg-primary-subtle text-primary-emphasis mb-2', lang('Category'), $bind('category')),
                $heading('h1', $event_sample['title'], 'pcy-display display-6 mb-2', $bind('title')),
                $para($event_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $event_facts($event_sample_key, 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-4'),
                $row(array(
                    $col(array(
                        $optional_image('photo_1', 'title', 'rounded-3 object-fit-cover', 'ratio ratio-16x9 mb-4',
                            $photo('event-' . $event_sample_key, 1200, 675), lang('Photo')),
                        $el('div', 'pg-hide-if-empty mb-4', lang('What Happens'), array(
                            $heading('h2', lang('What Happens'), 'pcy-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $event_details_text($event_sample_key)), $bind('details'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm rounded-3', lang('Event Card'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Free and open to everyone'), 'pcy-display h5 mb-2'),
                                $para(lang('City events are free unless the page says otherwise. Questions about access or seating? Write to us.'), 'small text-body-secondary mb-3'),
                                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary w-100 mb-2'),
                                // Opens the list on this event's category
                                // (?category=).
                                $link(lang('More events of this category'), '#', 'btn btn-outline-secondary w-100', array(),
                                    $cy_page_link('events', array('category' => 'category'))),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5 mb-4'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $notice_pages, 'widgets' => $notice_widgets);
