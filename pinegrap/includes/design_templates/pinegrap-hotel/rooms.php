<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Hotel", its rooms: the staff page that adds a room (the
 * Rooms form, a collection, with the sample rooms written in when it is
 * made), the list of every room with its kind and view filters, the room's
 * own page, and the list the home page places. Included after parts.php in
 * the same scope; returns array('pages' => ..., 'widgets' => ...).
 *
 * A room is a record of the Rooms form. Its kind and view are pick lists
 * whose answers are the words parts.php gives, so a link with ?type= or
 * ?room_view= filters the list by the words a visitor sees (?view= is a
 * reserved name a list filter cannot read). Guests and size are number
 * fields, so a list can sort on them; the rate is the words the cards show
 * ("From $190 a night"). The photos are image fields kept in the public
 * folder. Featured is the hotel's own choice (office use only): the home
 * page shows the featured rooms.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$room_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address).
$room_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// The answers of the Featured list: the first marks a room for the home.
$room_featured_choices = array(lang('Yes'), lang('No'));

// A link to a template page that sends values of the record it is drawn
// in: $params maps the address name to the field it takes (the renderer's
// __page_link).
$room_page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A list's settings: the Rooms form, the room page, smallest room first
// (size is a number field, so it sorts as a number).
$room_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:room_new}}',
        'detail_page_id'      => '{{tab:room}}',
        'order_by_field'      => 'size',
        'order_by_direction'  => 'ASC',
        'empty_message'       => lang('No room matches these choices. Try another kind of room or view, or ask us: we will find you one.'),
    ), $more);
};
// The cards of a list, in rows of three.
$room_grid = function () use ($widget_root, $row, $loop, $room_card) {
    return $widget_root(array($row(array($loop(array($room_card()))), '4')));
};

// The links above the list that narrow it: one per kind of room and one per
// view, each opening the room list with the filter set.
$room_filter_bar = function () use ($el, $link, $span, $query, $room_type_titles, $view_titles) {
    $chips = function ($label, $param, $values) use ($el, $link, $span, $query) {
        $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
        $children[] = $link(lang('All'), '{{page:rooms}}', 'btn btn-sm btn-outline-secondary rounded-pill');
        foreach ($values as $v) {
            $children[] = $link($v, '{{page:rooms}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
        }
        return $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', $label, $children);
    };
    return $el('nav', 'mb-4', lang('Filters'), array(
        $chips(lang('Kind of room'), 'type', $room_type_titles),
        $chips(lang('View'), 'room_view', $view_titles),
    ));
};

// ── Pages ───────────────────────────────────────────────────────────────

// Every room, narrowed by kind and view from the address.
$room_pages[] = array(
    'key'              => 'rooms',
    'name'             => lang('rooms'),
    'folder'           => 'public',
    'title'            => lang('Rooms and Suites'),
    'meta_description' => lang('Guest rooms, suites and family rooms by the sea or over the olive garden, with photos, sizes and the rate for a night.'),
    'tree'             => $page(array(
        $page_header(lang('Rooms and Suites'), lang('Every room looks at the sea or the garden. Choose a kind of room or a view to narrow the list.')),
        // The top of the house in depth before the list (Hotel: Room
        // Showcase); its second button goes to the rates, the list is
        // under it.
        $room_showcase('terrace', 'ht-rooms-showcase', 'h2', 'py-5', array(
            $link(lang('Ask for This Room'), '{{page:book}}?' . $query(array('room' => $rooms['terrace']['name'])), 'btn btn-primary rounded-pill px-4'),
            $link(lang('See the rates'), '{{page:rates}}', 'btn btn-outline-secondary rounded-pill px-4'),
        )),
        $el('section', 'py-5 border-top', lang('Rooms'), array(
            $container(array($room_filter_bar(), $widget('rooms_list'))),
        )),
    )),
);

// One room: the photos, the name, the facts, the rate, the description, the
// amenities, the board plans and the way to ask for it.
$room_pages[] = array(
    'key'              => 'room',
    'name'             => lang('room'),
    'folder'           => 'public',
    'title'            => lang('Room'),
    'meta_description' => lang('A room of the hotel: photos, beds, size, view, what it has and the rate for a night.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('room_detail'),
    )),
);

// The staff page that adds a room. The form is the Rooms collection; the
// sample rooms are written in when the form is made, so the lists do not
// open empty (pg_cf_seed_sample_records()).
$sample_rooms = array();
foreach ($rooms as $key => $r) {
    $sample_rooms[] = array(
        'name'        => $r['name'],
        'type'        => $room_types[$r['type']]['title'],
        'view'        => $views[$r['view']]['title'],
        'guests'      => (string)$r['guests'],
        'size'        => (string)$r['size'],
        'beds'        => $r['beds'],
        'rate'        => $rate_text($key),
        'summary'     => $r['summary'],
        'description' => $r['description'],
        'amenities'   => implode("\n", $r['amenities']),
        'photo_1'     => $photo('room-' . $key, 1200, 900),
        'photo_2'     => $photo('room-' . $key . '-2', 1200, 900),
        'photo_3'     => $photo('room-' . $key . '-3', 1200, 900),
        'featured'    => $r['featured'] ? $room_featured_choices[0] : $room_featured_choices[1],
    );
}
$room_pages[] = array(
    'key'     => 'room_new',
    'name'    => lang('new-room'),
    'folder'  => 'private',
    'title'   => lang('New Room'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Rooms'),
        'collection'           => 1,
        'confirmation_message' => lang('The room is published.'),
        'confirmation_page_id' => '{{tab:rooms}}',
        'sample_records'       => $sample_rooms,
        // The first photo is the room's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo_1',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('New Room'), lang('Add the room with its photos and rate; it appears in the list as soon as you send it. Mark it featured from the room\'s Edit button.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget('room_form'))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    )),
);

// ── Widget pieces ───────────────────────────────────────────────────────

// A number field of the Rooms form: a number kind, so the lists sort it as
// a number.
$room_number = function ($id, $name, $min) use ($input, $attr) {
    return $input($id, 'number', $name, 'off', true, array('kind' => 'number'), array($attr('min', (string)$min), $attr('step', '1')));
};

// A photo of the room page, in a frame that leaves when the record has
// none.
$room_photo = function ($field, $seed) use ($optional_image, $photo) {
    return $optional_image($field, 'name', 'rounded-4 object-fit-cover', 'ratio ratio-4x3', $photo($seed, 1200, 900), lang('Photo'));
};

// Beside the room's words: the rate, the times of the stay and the way to
// ask for this room or call.
$room_aside = function ($sample_key) use ($el, $heading, $para, $link, $span, $icon, $bind, $room_page_link, $rate_text, $hotel) {
    return $el('aside', 'card border-0 shadow-sm rounded-4', lang('Booking Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $para(lang('The lowest rate, room only'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
            $heading('h2', $rate_text($sample_key), 'pht-price h4 fw-semibold mb-3', $bind('rate')),
            // Opens the booking request with this room chosen (?room=).
            $link(lang('Ask for this room'), '#', 'btn btn-primary rounded-pill w-100 mb-2', array(),
                $room_page_link('book', array('room' => 'name'))),
            $link(lang('Call Us'), 'tel:' . preg_replace('/[^0-9+]/', '', $hotel['phone']), 'btn btn-outline-primary rounded-pill w-100 mb-3'),
            $el('ul', 'list-unstyled small mb-3', lang('Stay Times'), array(
                $el('li', 'd-flex gap-2 mb-1', lang('Check-in'), array(
                    $icon('bi-box-arrow-in-right', 'text-primary'),
                    $span(sprintf(lang('Check-in from %s'), $hotel['check_in']), '', lang('Text')),
                )),
                $el('li', 'd-flex gap-2', lang('Check-out'), array(
                    $icon('bi-box-arrow-right', 'text-primary'),
                    $span(sprintf(lang('Check-out until %s'), $hotel['check_out']), '', lang('Text')),
                )),
            )),
            $el('p', 'd-flex gap-2 small text-body-secondary mb-0', lang('Note'), array(
                $icon('bi-info-circle', 'mt-1'),
                $span(lang('A request is not yet a booking: the reception answers within a day with the price for your dates.'), '', lang('Text')),
            )),
        )),
    ));
};

$room_widgets = array(

    // The staff's Rooms form: one room. Featured starts as No.
    'room_form' => array(
        'page'   => 'room_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('The room')),
            $field('ht-room-name', lang('Name'), pg_cf_with_role($input('ht-room-name', 'text', 'name', 'off'), 'title')),
            $row(array(
                $col(array($field('ht-room-type', lang('Kind of room'), pg_cf_with_role($select('ht-room-type', 'type', lang('Choose a kind of room…'), $room_type_titles), 'category'))), '6'),
                $col(array($field('ht-room-view', lang('View'), $select('ht-room-view', 'view', lang('Choose a view…'), $view_titles))), '6'),
                $col(array($field('ht-room-guests', lang('Guests'), $room_number('ht-room-guests', 'guests', 1), 'mb-3', lang('The most guests the room sleeps.'))), '6'),
                $col(array($field('ht-room-size', lang('Size (m²)'), $room_number('ht-room-size', 'size', 1))), '6'),
            ), '3'),
            $field('ht-room-beds', lang('Beds'), $input('ht-room-beds', 'text', 'beds', 'off'), 'mb-3',
                lang('As the guest reads it, for example One king-size bed or two single beds.')),
            $field('ht-room-rate', lang('Rate'), $input('ht-room-rate', 'text', 'rate', 'off'), 'mb-3',
                sprintf(lang('As the cards show it, for example %s.'), $rate_text('superior'))),
            $form_step(2, lang('Words')),
            $field('ht-room-summary', lang('Summary'), pg_cf_with_role($textarea('ht-room-summary', 'summary', 2), 'description'), 'mb-3',
                lang('Two lines for the cards of the lists.')),
            $field('ht-room-description', lang('Description'), $textarea('ht-room-description', 'description', 6)),
            $field('ht-room-amenities', lang('Amenities'), $textarea('ht-room-amenities', 'amenities', 4, false), 'mb-3', lang('One amenity on each line.')),
            $form_step(3, lang('Photos')),
            $field('ht-room-photo-1', lang('First Photo'), pg_cf_with_role($file('ht-room-photo-1', 'photo_1', 'public', 'image/*'), 'media'), 'mb-3',
                lang('The photo of the cards and the share image.')),
            $row(array(
                $col(array($field('ht-room-photo-2', lang('Second Photo'), $file('ht-room-photo-2', 'photo_2', 'public', 'image/*'))), '6'),
                $col(array($field('ht-room-photo-3', lang('Third Photo'), $file('ht-room-photo-3', 'photo_3', 'public', 'image/*'))), '6'),
            ), '3'),
            $field('ht-room-featured', lang('Featured'), $select('ht-room-featured', 'featured', '', $room_featured_choices, false, $room_featured_choices[1], array('office_use_only' => true)),
                'mb-3', lang('A featured room is shown on the home page.')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // Every room, filtered by the address (kind, view), with a search over
    // the words of the rooms.
    'rooms_list' => array(
        'page'   => 'rooms',
        'slug'   => lang('form-list'),
        'config' => $room_list_config(array(
            'items_per_page' => 12,
            'filters'        => array(
                $room_filter('type', 'is equal to', '', 'type'),
                $room_filter('view', 'is equal to', '', 'room_view'),
            ),
            'search_enabled' => true,
            'search_fields'  => array('name', 'type', 'view', 'beds', 'summary', 'amenities', 'description'),
            'search_width'   => 'full',
            'search_label'   => lang('Search the rooms: a balcony, a bath, a bed for a child...'),
        )),
        'tree'   => $room_grid(),
    ),

    // The home page's featured rooms, three at most.
    'home_rooms' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => $room_list_config(array(
            'items_per_page' => 3,
            'max_results'    => 3,
            'filters'        => array($room_filter('featured', 'is equal to', $room_featured_choices[0])),
            'empty_message'  => lang('No featured rooms yet.'),
        )),
        'tree'   => $room_grid(),
    ),
);

// One room: the photos, the kind and view, the name, the facts, the
// summary, the description, the amenities, the rate with the way to ask
// for it, and the board plans.
$room_sample_key = 'superior';
$room_sample = $rooms[$room_sample_key];
$room_plan_cards = array();
foreach (array_keys($rate_plans) as $plan_key) $room_plan_cards[] = $plan_card($plan_key);
$room_widgets['room_detail'] = array(
    'page'   => 'room',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:room_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This room could not be found. It may have been renamed or closed for the season.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $el('div', 'text-center py-5', lang('Not Found'), array(
                $icon('bi-door-closed', 'display-5 text-body-tertiary'),
                $heading('h2', lang('Room not found'), 'h3 fw-semibold mt-3'),
                $para(lang('This room could not be found. It may have been renamed or closed for the season.'), 'text-body-secondary mb-4', $bind('not_found')),
                $link(lang('See the rooms'), '{{page:rooms}}', 'btn btn-primary rounded-pill px-4'),
            ), $show_when('record_not_found')),
            $el('article', '', lang('Room'), array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
                    $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
                        $icon('bi-arrow-left'),
                        $span(lang('All Rooms'), '', lang('Link Text')),
                    ), array('href' => '{{page:rooms}}')),
                    $edit_button(),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array(
                    $span($room_types[$room_sample['type']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Room Type'), $bind('type')),
                    $span($views[$room_sample['view']]['title'], 'badge rounded-pill text-bg-light border', lang('Room View'), $bind('view')),
                )),
                $heading('h1', $room_sample['name'], 'pht-display display-6 mb-2', $bind('name')),
                $para($room_sample['summary'], 'lead text-body-secondary mb-3', $bind('summary')),
                $room_facts($room_sample_key, 'd-flex flex-wrap gap-3 text-body-secondary mb-4'),
                // The photos: the first large, the other two beside it.
                $row(array(
                    $col(array($room_photo('photo_1', 'room-' . $room_sample_key)), '', '8'),
                    $col(array(
                        $el('div', 'd-flex flex-column gap-3 h-100', lang('Photos'), array(
                            $room_photo('photo_2', 'room-' . $room_sample_key . '-2'),
                            $room_photo('photo_3', 'room-' . $room_sample_key . '-3'),
                        )),
                    ), '', '4'),
                ), '3', '', 'mb-5'),
                $row(array(
                    $col(array(
                        $el('div', 'pg-hide-if-empty mb-4', lang('Description'), array(
                            $heading('h2', lang('About the room'), 'pht-display h4 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $room_sample['description']), $bind('description'))),
                        )),
                        $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', lang('Amenities'), array(
                            $heading('h2', lang('In the room'), 'pht-display h5 mb-2'),
                            $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => implode("\n", $room_sample['amenities'])), $bind('amenities'))),
                        )),
                    ), '', '8'),
                    $col(array($room_aside($room_sample_key)), '', '4'),
                ), '4', '', 'g-lg-5 mb-5'),
                // The board plans: each opens the booking request with the
                // plan chosen.
                $el('section', '', lang('Rates and Board'), array(
                    $section_header(lang('Rates and Board'), lang('Choose how you eat'), lang('See the rates'), '{{page:rates}}'),
                    $row($room_plan_cards, '4'),
                )),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $room_pages, 'widgets' => $room_widgets);
