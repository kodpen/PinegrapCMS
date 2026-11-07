<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Coworking", its spaces and meeting room bookings: the
 * staff page that adds a space (the Spaces form, a collection, with the
 * sample spaces written in when it is made), the list of every space with
 * its kind filter, the space's own page, the list the home page places,
 * and the page that books a meeting room at a free time. Included after
 * parts.php in the same scope; returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * A space is a record of the Spaces form. Its kind and floor are pick
 * lists whose answers are the words parts.php gives, so a link with ?kind=
 * filters the list by the words a visitor sees. Seats is a number field,
 * so a list can sort on it; the rate is the price as the visitor reads it
 * ("… an hour"). The photo is an image field kept in the public folder.
 *
 * Meeting rooms are booked with the booking widget in the editor's
 * starter layout ('tree' => 'starter'): the rooms (as services), their
 * hours and prices are the site's own, set under Settings › Bookings. A
 * new site has none yet, so the widget shows its no-services words until
 * the space adds one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$space_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$space_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};

// A list's settings: the Spaces form, the space page, the largest space
// first (seats is a number field, so it sorts as a number).
$space_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:space_new}}',
        'detail_page_id'      => '{{tab:space}}',
        'order_by_field'      => 'seats',
        'order_by_direction'  => 'DESC',
        'empty_message'       => lang('No space of this kind yet. Choose another, or ask the front desk.'),
    ), $more);
};
// The cards of a list, in rows of three.
$space_grid = function () use ($widget_root, $row, $loop, $space_card) {
    return $widget_root(array($row(array($loop(array($space_card()))), '4')));
};

// The links above the list that narrow it: one per kind, each opening the
// space list with the filter set.
$space_filter_bar = function () use ($el, $link, $span, $query, $kind_titles) {
    $children = array($span(lang('Kind'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:spaces}}', 'btn btn-sm btn-outline-secondary');
    foreach ($kind_titles as $v) {
        $children[] = $link($v, '{{page:spaces}}?' . $query(array('kind' => $v)), 'btn btn-sm btn-outline-secondary');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The staffed hours in a short list, beside the booking widget.
$space_hours = function () use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-1', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'pcw-price fw-semibold', lang('Hours')),
        ));
    }
    return $el('ul', 'list-unstyled small mb-0', lang('Opening Hours'), $items);
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every space, narrowed by kind from the address.
$space_pages[] = array(
    'key'              => 'spaces',
    'name'             => lang('spaces'),
    'folder'           => 'public',
    'title'            => lang('Spaces'),
    'meta_description' => lang('Hot desks, fixed desks, private offices, meeting rooms and an event hall: every space with its seats, floor and price.'),
    'tree'             => $page(array(
        $page_header(lang('Spaces'), lang('Desks, offices and rooms on three floors of an old warehouse. Choose a kind to narrow the list.')),
        $el('section', 'py-5', lang('Spaces'), array(
            $container(array($space_filter_bar(), $widget('spaces_list'))),
        ), $searchable('cw-spaces', lang('desk, office, room, hall, workspace, seats'))),
    )),
);

// One space: the photo, the kind, the name, the seats, the floor, the
// price, the words, what it has and the ways on.
$space_pages[] = array(
    'key'              => 'space',
    'name'             => lang('space'),
    'folder'           => 'public',
    'title'            => lang('Workspace'),
    'meta_description' => lang('A space of the coworking: its seats, its floor, its price and what it has, with the way to book a tour or a room.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('space_detail'),
    )),
);

// Meeting rooms by the hour: the booking widget in the editor's starter
// layout, beside the desks and the staffed hours.
$space_pages[] = array(
    'key'              => 'book_online',
    'name'             => lang('book-online'),
    'folder'           => 'public',
    'title'            => lang('Meeting Rooms'),
    'meta_description' => lang('Book a meeting room online by the hour: choose the room, the day and one of the free times.'),
    'tree'             => $page(array(
        $page_header(lang('Meeting Rooms'), lang('Choose the room, the day and one of the free times. The room is yours as soon as you confirm.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Booking Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('booking_slots'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body-tertiary mb-4', lang('Desks'), array(
                            $heading('h2', lang('Need a desk for the day?'), 'h5 fw-semibold mb-2'),
                            $para(lang('Book a tour and your first day at a hot desk is on us; after that, a day pass or a plan.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Book a Tour'), '{{page:tour}}', 'btn btn-sm btn-outline-primary px-3'),
                                $link(lang('See the Plans'), '{{page:plans}}', 'btn btn-sm btn-outline-primary px-3'),
                            )),
                        )),
                        $el('div', 'p-4 rounded-3 border', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'h5 fw-semibold mb-3'),
                            $space_hours(),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('cw-room-booking', lang('meeting room, conference room, book, hourly, reserve'))),
    ), false),
);

// The staff page that adds a space. The form is the Spaces collection; the
// sample spaces are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_spaces = array();
foreach ($spaces as $key => $s) {
    $sample_spaces[] = array(
        'name'     => $s['name'],
        'kind'     => $kinds[$s['kind']]['title'],
        'floor'    => $floors[$s['floor']],
        'seats'    => (string)$s['seats'],
        'rate'     => $space_price($key),
        'summary'  => $s['summary'],
        'features' => implode("\n", $s['features']),
        'photo'    => $photo('space-' . $key, 1200, 900),
    );
}
$space_pages[] = array(
    'key'     => 'space_new',
    'name'    => lang('new-space'),
    'folder'  => 'private',
    'title'   => lang('New Space'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Spaces'),
        'collection'           => 1,
        'confirmation_message' => lang('The space is published.'),
        'confirmation_page_id' => '{{tab:spaces}}',
        'sample_records'       => $sample_spaces,
        // The photo is the space's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Space'), lang('Add the desk, office or room with a photo and a few words; it appears in the list as soon as you send it.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget('space_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────

$space_widgets = array(

    // The staff's Spaces form: one desk area, office or room.
    'space_form' => array(
        'page'   => 'space_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The space')),
            $field('cw-space-name', lang('Name'), pg_cf_with_role($input('cw-space-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('cw-space-kind', lang('Kind'), pg_cf_with_role($select('cw-space-kind', 'kind', lang('Choose a kind…'), $kind_titles), 'category'))), '6'),
                $col(array($field('cw-space-floor', lang('Floor'), $select('cw-space-floor', 'floor', lang('Choose a floor…'), $floor_titles))), '6'),
            ), '3'),
            $row(array(
                $col(array($field('cw-space-seats', lang('Number of Seats'),
                    $input('cw-space-seats', 'number', 'seats', 'off', true, array('kind' => 'number'), array($attr('min', '1'), $attr('step', '1'))))), '6'),
                $col(array($field('cw-space-rate', lang('Price'), $input('cw-space-rate', 'text', 'rate', 'off'), 'mb-3',
                    lang('As visitors read it, for example 25 a day or 40 an hour.'))), '6'),
            ), '3'),
            $form_step(2, lang('Words')),
            $field('cw-space-summary', lang('Summary'), pg_cf_with_role($textarea('cw-space-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('cw-space-features', lang('What it has'), $textarea('cw-space-features', 'features', 4, false), 'mb-3',
                lang('One thing on each line, for example a screen for calls.')),
            $form_step(3, lang('Photo')),
            $field('cw-space-photo', lang('Photo'), pg_cf_with_role($file('cw-space-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
                lang('A wide photo works best; it is also the share image.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every space, filtered by the address (kind), with a search over the
    // words of the spaces.
    'spaces_list' => array(
        'page'   => 'spaces',
        'slug'   => lang('form-list'),
        'config' => $space_list_config(array(
            'items_per_page' => 12,
            'filters'        => array($space_filter('kind', 'is equal to', '', 'kind')),
            'search_enabled' => true,
            'search_fields'  => array('name', 'kind', 'floor', 'summary', 'features'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the spaces: a quiet desk, a room for ten, a screen...'),
        )),
        'tree'   => $space_grid(),
    ),

    // The home page's spaces, three at most.
    'home_spaces' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $space_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'empty_message'  => lang('No spaces yet.'),
        )),
        'tree'   => $space_grid(),
    ),

    // Meeting rooms at a free time: the booking widget in the editor's
    // starter layout (services, day and time, details, confirmation).
    'booking_slots' => array(
        'page'   => 'book_online',
        'slug'   => lang('booking'),
        'config' => array('regionType' => 'booking'),
        'tree'   => 'starter',
    ),
);

// One space: the photo, the kind, the name, the seats, the floor, the
// price, the words, what it has and the ways on.
$space_sample_key = 'boardroom';
$space_sample = $spaces[$space_sample_key];
$space_widgets['space_detail'] = array(
    'page'   => 'space',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:space_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This space could not be found. It may have been let, or the page was renamed.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-door-closed', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Space not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This space could not be found. It may have been let, or the page was renamed.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the spaces'), '{{page:spaces}}', 'btn btn-primary px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Workspace'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Spaces'), '', lang('Link Text')),
                    ), array('href' => '{{page:spaces}}')),
                    $edit_button(),
                )),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'rounded-3 object-fit-cover', 'ratio ratio-4x3',
                            $photo('space-' . $space_sample_key, 1200, 900), lang('Photo')),
                    ), '', '7'),
                    $col(array(
                        $span($kinds[$space_sample['kind']]['title'], 'badge bg-primary-subtle text-primary-emphasis mb-2', lang('Kind'), $bind('kind')),
                        $heading('h1', $space_sample['name'], 'pcw-display display-6 mb-3', $bind('name')),
                        $el('ul', 'list-unstyled d-flex flex-wrap gap-3 text-body-secondary mb-3', lang('Space Facts'), array(
                            $el('li', 'd-inline-flex align-items-center gap-1', lang('Seats'), array(
                                $icon('bi-people', 'text-primary'),
                                $span((string)$space_sample['seats'], 'pcw-price', lang('Value'), $bind('seats')),
                                $span(lang('seats'), '', lang('Unit')),
                            )),
                            $el('li', 'd-inline-flex align-items-center gap-1', lang('Floor'), array(
                                $icon('bi-building', 'text-primary'),
                                $span($floors[$space_sample['floor']], '', lang('Value'), $bind('floor')),
                            )),
                        )),
                        $para($space_price($space_sample_key), 'pcw-price h4 fw-bold text-primary mb-3', $bind('rate')),
                        $para($space_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'pg-hide-if-empty p-4 rounded-3 bg-body-tertiary mb-4', lang('What it has'), array(
                            $heading('h2', lang('What it has'), 'pcw-display h6 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $space_sample['features'])), $bind('features'))),
                        )),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book a Tour'), '{{page:tour}}', 'btn btn-primary px-4'),
                            $link(lang('Book a meeting room'), '{{page:book_online}}', 'btn btn-outline-primary px-4'),
                            $link(lang('Ask About an Office'), '{{page:office_enquiry}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $space_pages, 'widgets' => $space_widgets);
