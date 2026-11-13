<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Hotel", its public pages: home, about, dining, spa,
 * getting here, the offers, the rates and the questions page. Included
 * after parts.php in the same scope (its builders and the hotel's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($hotel, $rooms, $room_rates,
 * $rate_plans, $offers, $facilities, $dining, $treatments, $nearby), so a
 * change made there reaches every page here. No page here links a shop
 * page: the template stands on a site without the shop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$public_pages = array();

// A picture in a rounded frame of the given ratio, loaded lazily unless it
// is the first thing a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pht-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pht-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line: the facilities, the dining
// places and what is near the hotel.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pht-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The hotel in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pht-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The cards of the shared data, in their order.
$facility_tiles = array();
foreach ($facilities as $f) $facility_tiles[] = $feature_tile($f['icon'], $f['title'], $f['text']);
$offer_cards_row = array();
foreach (array_keys($offers) as $key) $offer_cards_row[] = $offer_card($key);
$plan_cards_row = array();
foreach (array_keys($rate_plans) as $key) $plan_cards_row[] = $plan_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A small family hotel by the sea: rooms and suites over the bay, breakfast on the terrace, a garden pool, a hammam and a private beach.'),
    'tree'             => $page(array(
        // The way in: the hotel in a sentence, the two ways on and a photo
        // of the house over the bay.
        $el('section', 'py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $fx($col(array(
                        $para($hotel['region'], 'pht-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Wake up to the sea'), 'pht-display display-3 mb-3'),
                        $para($hotel['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Ask for a Room'), '{{page:book}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('See the rooms and suites'), '{{page:rooms}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                        $para(sprintf(lang('Check-in from %1$s, check-out until %2$s'), $hotel['check_in'], $hotel['check_out']), 'small text-body-secondary mt-4 mb-0'),
                    ), '', '5'), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700)),
                    $fx($col(array(
                        $framed($photo('hero', 1400, 1050), lang('The stone house of the hotel above the bay at sunset'), '4x3', false),
                    ), '', '7'), array('effect' => 'fade', 'trigger' => 'load', 'duration' => 1000, 'delay' => 200)),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // One suite in depth, its pictures, facts and rate (Hotel: Room
        // Showcase); the figures band is on the about page.
        $room_showcase('junior', 'ht-home-room', 'h2', 'py-5 border-top'),

        // The featured rooms, read from the Rooms collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Featured Rooms'), array(
            $container(array(
                $section_header(lang('Stay with us'), lang('Rooms and suites over the bay'), lang('All rooms and suites'), '{{page:rooms}}'),
                $widget('home_rooms'),
            )),
        )),

        $el('section', 'py-5', lang('Facilities'), array(
            $container(array(
                $section_header(lang('At the hotel'), lang('Everything a slow day needs'), lang('About the hotel'), '{{page:about}}'),
                $rise($row($facility_tiles, '4')),
            )),
        ), $searchable('ht-facilities', lang('pool, beach, parking, transfer, facilities, amenities'))),

        // Arrival and departure on one line, the stay between them (Hotel:
        // Stay Clock).
        $stay_clock('ht-home-stay'),

        $el('section', 'py-5', lang('Special Offers'), array(
            $container(array(
                $section_header(lang('Good to know'), lang('Special offers'), lang('All offers'), '{{page:offers}}'),
                $rise($row($offer_cards_row, '4'), 120),
            )),
        )),

        $story_band('ht-home-dining', lang('Dining'), lang('From the harbour and the garden'),
            array($dining['restaurant']['text'], $dining['terrace']['text']),
            $photo('home-dining', 1200, 900), lang('Dinner on the terrace by candlelight with the bay behind'), false,
            array($link(lang('See the restaurant'), '{{page:dining}}', 'btn btn-outline-primary rounded-pill px-4')),
            '', 'py-5 bg-body-tertiary'),

        $story_band('ht-home-spa', lang('Spa'), lang('A hammam under the olive trees'),
            array($facilities['spa']['text'], lang('Book a treatment at the reception or ask for one with your room.')),
            $photo('home-spa', 1200, 900), lang('Warm marble in the stone hammam'), true,
            array($link(lang('See the spa'), '{{page:spa}}', 'btn btn-outline-primary rounded-pill px-4'))),

        // What guests say: sample words for the hotel to replace, and beside
        // them its own Google reviews, which show once Google Reviews is set
        // up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our guests'), lang('What guests say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From the Journal'), array(
            $container(array(
                $section_header(lang('Hotel Journal'), lang('Stories from the coast'), lang('All stories'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-house-heart', 'title' => lang('One family'), 'text' => lang('The same family has run the house for three generations; one of us is always at the reception.')),
    array('icon' => 'bi-basket', 'title' => lang('From the region'), 'text' => lang('Bread from our own oven, fish from the harbour, olive oil from our trees and wine from the hills.')),
    array('icon' => 'bi-recycle', 'title' => lang('Light on the coast'), 'text' => lang('Solar water heating, no plastic bottles in the rooms and the garden watered from the rain.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('An old stone house above the bay, run as a hotel by the same family for three generations.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $hotel['tagline'] . '.'),
        $story_band('ht-our-story', lang('Our story'), lang('An old stone house above the bay'),
            array($hotel['story'], lang('We kept the thick walls, the vaulted hall and the olive trees, and added what a guest needs today: quiet rooms, good beds and a pool in the garden.')),
            $photo('about-house', 1200, 900), lang('The hotel\'s stone front with blue shutters and bougainvillea'), false, array(),
            lang('history, family, owners, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we keep the house')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('ht-values', lang('sustainability, local food, environment'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Facilities'), array(
            $container(array(
                $section_header(lang('At the hotel'), lang('Everything a slow day needs')),
                $row($facility_tiles, '4'),
            )),
        ), $searchable('ht-about-facilities', lang('pool, beach, parking, transfer, facilities, amenities'))),
        $el('section', 'py-5', lang('Write to Us'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('A question before you come?'), 'pht-display h4 mb-1'),
                        $para(lang('Write to us and someone from the family answers, usually the same day.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── Dining ──────────────────────────────────────────────────────────────

$dining_tiles = array();
foreach ($dining as $key => $d) {
    $dining_tiles[] = $feature_tile($d['icon'], $d['title'], $d['text'], '4', array(
        $el('p', 'd-flex align-items-center gap-2 small fw-semibold mt-3 mb-0', lang('Opening Hours'), array(
            $icon('bi-clock', 'text-primary'),
            $span($d['hours'], '', lang('Text')),
        )),
    ));
}
$menu_samples = array(
    array(lang('Grilled fish of the day'), lang('With wild greens, lemon and the house\'s olive oil')),
    array(lang('Slow-cooked lamb'), lang('With smoked aubergine and thyme from the hill')),
    array(lang('Garden mezze'), lang('Six small plates of what the garden gives that week')),
    array(lang('Fig and walnut tart'), lang('With honey from the village and cream')),
);
$menu_items = array();
foreach ($menu_samples as $m) {
    $menu_items[] = $el('li', 'list-group-item px-0 py-3 bg-transparent', $m[0], array(
        $heading('h3', $m[0], 'h6 fw-semibold mb-1'),
        $para($m[1], 'small text-body-secondary mb-0'),
    ));
}

$public_pages[] = array(
    'key'              => 'dining',
    'name'             => lang('dining'),
    'folder'           => 'public',
    'title'            => lang('Dining'),
    'meta_description' => lang('Breakfast on the terrace, dinners from the harbour and the garden, and a pool bar until midnight.'),
    'tree'             => $page(array(
        $page_header(lang('Dining'), lang('Breakfast on the terrace, dinner by the sea and a glass of wine as the sun goes down.')),
        $el('section', 'py-5', lang('Where to Eat'), array(
            $container(array(
                $row($dining_tiles, '4'),
            )),
        ), $searchable('ht-dining-places', lang('restaurant, breakfast, bar, opening hours, meals'))),
        $el('section', 'py-5 bg-body-tertiary', lang('From the Menu'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('From the menu'), 'pht-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('A short menu that changes with the market'), 'pht-display h2 mb-3'),
                        $el('ul', 'list-group list-group-flush mb-3', lang('Menu'), $menu_items),
                        $para(lang('Tell us about an allergy or a diet with your booking and the kitchen prepares for it.'), 'small text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array($framed($photo('dining-plate', 1200, 900), lang('A plate of grilled fish with lemon and greens'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('ht-menu', lang('menu, food, vegetarian, allergy, gluten, diet'))),
        $el('section', 'py-5', lang('Board Plans'), array(
            $container(array(
                $section_header(lang('With your room'), lang('Breakfast or half board'), lang('Rates and Board'), '{{page:rates}}'),
                $row($plan_cards_row, '4'),
            )),
        )),
        $story_band('ht-private-dining', lang('Private dining'), lang('A table of your own'),
            array(lang('A dinner for two on the sea terrace, a family table in the olive garden or a long table in the library: tell us the occasion and we plan the evening with you.')),
            $photo('dining-private', 1200, 900), lang('A long table set under the olive trees with lanterns'), true,
            array($link(lang('Plan an event'), '{{page:meetings}}', 'btn btn-outline-primary rounded-pill px-4')),
            lang('private dinner, anniversary, celebration'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Spa ─────────────────────────────────────────────────────────────────

$treatment_cols = array();
foreach ($treatments as $t) {
    $treatment_cols[] = $col(array(
        $el('article', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $t['title'], array(
            $el('div', 'd-flex align-items-start justify-content-between gap-2 mb-2', lang('Treatment Heading'), array(
                $heading('h3', $t['title'], 'pht-display h5 mb-0'),
                $span(sprintf(lang('%d minutes'), $t['minutes']), 'badge rounded-pill bg-body text-body-emphasis border text-nowrap', lang('Duration')),
            )),
            $para($t['text'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '6');
}
$spa_rules = array(
    lang('The hammam and the sauna are free for every guest of the hotel'),
    lang('Treatments are booked at the reception, a day ahead in summer'),
    lang('Robes, slippers and towels are waiting in the changing rooms'),
    lang('Guests under 16 are welcome in the hammam with a parent until 17:00'),
);

$public_pages[] = array(
    'key'              => 'spa',
    'name'             => lang('spa'),
    'folder'           => 'public',
    'title'            => lang('Spa'),
    'meta_description' => lang('A stone hammam, a sauna and massages with olive oil from the hotel\'s own trees.'),
    'tree'             => $page(array(
        $page_header(lang('Spa'), $facilities['spa']['text']),
        $story_band('ht-hammam', lang('The hammam'), lang('Warm stone and slow hours'),
            array(lang('Under a dome of old stone, the hammam is warm from morning to evening; after it, the sauna, a cold shower and tea in the garden.'), lang('The spa is open from 9:00 to 20:00 every day.')),
            $photo('spa-hammam', 1200, 900), lang('Light falling through the dome of the stone hammam'), false, array(),
            lang('hammam, turkish bath, sauna, wellness, opening hours')),
        $el('section', 'py-5 bg-body-tertiary', lang('Treatments'), array(
            $container(array(
                $section_header(lang('Treatments'), lang('Rituals of the coast')),
                $row($treatment_cols, '4'),
            )),
        ), $searchable('ht-treatments', lang('massage, scrub, treatment, couples'))),
        $el('section', 'py-5', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Before your visit'), 'pht-display h3 mb-3'),
                        $check_list($spa_rules),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('A treatment with your stay'), 'pht-display h4 mb-2'),
                            $para(lang('Name the treatment in your booking request and we keep the hour for you.'), 'mb-3'),
                            $link(lang('Ask for a Room'), '{{page:book}}', 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
    )),
);

// ── Getting here ────────────────────────────────────────────────────────

$nearby_items = array();
foreach ($nearby as $n) {
    $nearby_items[] = $el('li', 'd-flex align-items-center gap-3 py-3 border-bottom', $n['title'], array(
        $icon_tile($n['icon']),
        $el('div', '', lang('Text'), array(
            $heading('h3', $n['title'], 'h6 fw-semibold mb-1'),
            $para($n['distance'], 'small text-body-secondary mb-0'),
        )),
    ));
}
$ways_here = array(
    array('icon' => 'bi-airplane', 'title' => lang('By air'), 'text' => lang('Fly to the nearest airport; our driver meets you at arrivals if you ask for the transfer with your booking.')),
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('Follow the coast road to the Old Harbour and turn up Cove Road; the hotel\'s parking is under the pines.')),
    array('icon' => 'bi-bus-front', 'title' => lang('By bus'), 'text' => lang('Buses from the airport stop at the Old Harbour; tell us the time and we collect you from the stop.')),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'location',
    'name'             => lang('getting-here'),
    'folder'           => 'public',
    'title'            => lang('Getting Here'),
    'meta_description' => lang('Where the hotel is, how to reach it by air, car or bus, and what is near: the harbour, the beach and the coastal path.'),
    'tree'             => $page(array(
        $page_header(lang('Getting Here'), $hotel['region'] . '.'),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Find us'), 'pht-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $hotel['address'], 'pht-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($hotel['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $hotel['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-2', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($hotel['email'], 'mailto:' . $hotel['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Opening Hours'), array(
                                $icon('bi-clock', 'text-primary'),
                                $span($hotel['reception'], '', lang('Text')),
                            )),
                        )),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($hotel['address']), 'btn btn-outline-primary rounded-pill px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('location-bay', 1400, 1050), lang('The bay and the Old Harbour seen from the hotel\'s garden'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('ht-address', lang('address, map, directions, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('ht-travel', lang('airport, transfer, taxi, parking, bus, car'))),
        $el('section', 'py-5', lang('Nearby'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('What is near'), 'pht-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-0', lang('Nearby Places'), $nearby_items),
                    ), '', '6'),
                    $col(array($framed($photo('location-path', 1200, 900), lang('The coastal walking path above the sea'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('ht-nearby', lang('nearby, things to do, harbour, beach, walking'))),
    )),
);

// ── Special offers ──────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'offers',
    'name'             => lang('special-offers'),
    'folder'           => 'public',
    'title'            => lang('Special Offers'),
    'meta_description' => lang('Early booking, a free fourth night in spring and autumn, and a honeymoon by the sea.'),
    'tree'             => $page(array(
        $page_header(lang('Special Offers'), lang('Ask for your room with an offer chosen; the reception confirms the price with your room.')),
        $el('section', 'py-5', lang('Offers'), array(
            $container(array(
                $row($offer_cards_row, '4'),
            )),
        ), $searchable('ht-offers', lang('offer, discount, deal, promotion, honeymoon, early booking'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How an offer works'), 'pht-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose the offer'), lang('Open the request from the offer, or choose it in the form.')),
                            $step(2, lang('Tell us your dates'), lang('With the room you like and how many you are.')),
                            $step(3, lang('We confirm the price'), lang('The reception answers within a day with the room held and the saving taken off.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('One offer per stay; offers are not added to each other and depend on the rooms that are free.'), 'small text-body-secondary mb-0'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
    )),
);

// ── Rates and board ─────────────────────────────────────────────────────
// A row for each sample room with its kind, guests, size and the lowest
// rate, and the board plans under it. The table is the hotel's own text,
// not the Rooms collection: the hotel keeps it beside its rooms.

$rate_rows = array();
foreach ($rooms as $key => $r) {
    $rate_rows[] = $el('tr', '', $r['name'], array(
        $el('th', 'fw-semibold', lang('Room Name'), array(), array('text' => $r['name'], '_attrs' => array($attr('scope', 'row')))),
        $el('td', '', lang('Room Type'), array(), array('text' => $room_types[$r['type']]['title'])),
        $el('td', 'text-nowrap', lang('Guests'), array(), array('text' => sprintf(lang('Up to %d guests'), $r['guests']))),
        $el('td', 'text-nowrap', lang('Size'), array(), array('text' => $r['size'] . ' m²')),
        $el('td', 'pht-price fw-semibold text-nowrap', lang('Price'), array(), array('text' => $rate_text($key))),
        $el('td', 'text-end', lang('Action'), array(
            $link(lang('Ask for it'), '{{page:book}}?' . $query(array('room' => $r['name'])), 'btn btn-sm btn-outline-primary rounded-pill px-3'),
        )),
    ));
}
$rate_policies = array(
    sprintf(lang('Check-in from %1$s, check-out until %2$s'), $hotel['check_in'], $hotel['check_out']),
    lang('Free cancellation up to three days before arrival'),
    lang('Children under 6 stay free in their parents\' room'),
    lang('Rates are per room and night and include taxes'),
);

$public_pages[] = array(
    'key'              => 'rates',
    'name'             => lang('rates'),
    'folder'           => 'public',
    'title'            => lang('Rates and Board'),
    'meta_description' => lang('The lowest rate of every room and suite for a night, breakfast and half board, and what every stay includes.'),
    'tree'             => $page(array(
        $page_header(lang('Rates and Board'), lang('The lowest rate of each room for a night, room only. The reception confirms the price for your dates.')),
        $el('section', 'py-5', lang('Room Rates'), array(
            $container(array(
                $section_header(lang('Rooms and suites'), lang('Rates for a night'), lang('All rooms and suites'), '{{page:rooms}}'),
                $el('div', 'table-responsive', lang('Table'), array(
                    $el('table', 'table align-middle mb-0', lang('Rates Table'), array(
                        $el('thead', '', lang('Table Head'), array(
                            $el('tr', '', lang('Row'), array(
                                $el('th', '', lang('Room Name'), array(), array('text' => lang('Room or suite'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Room Type'), array(), array('text' => lang('Kind of room'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Guests'), array(), array('text' => lang('Guests'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Size'), array(), array('text' => lang('Room size'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Price'), array(), array('text' => lang('Lowest rate a night'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Action'), array($span(lang('Ask for this room'), 'visually-hidden', lang('Text'))), array('_attrs' => array($attr('scope', 'col')))),
                            )),
                        )),
                        $el('tbody', '', lang('Table Body'), $rate_rows),
                    )),
                )),
            )),
        ), $searchable('ht-rates', lang('price, prices, cost, rate, per night, tariff'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Board Plans'), array(
            $container(array(
                $section_header(lang('With your room'), lang('Room only, breakfast or half board')),
                $rise($row($plan_cards_row, '4'), 120),
            )),
        ), $searchable('ht-board', lang('breakfast, half board, dinner, meals included'))),
        // The times of the stay the rates count by (Hotel: Stay Clock).
        $stay_clock('ht-rates-stay', 'py-5'),
        $el('section', 'py-5', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Every stay includes'), 'pht-display h3 mb-3'),
                        $check_list($rate_policies),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('Looking for a better price?'), 'pht-display h4 mb-2'),
                            $para(lang('Book early or stay four nights in spring or autumn: the offers take a part of the rate off.'), 'mb-3'),
                            $link(lang('See the offers'), '{{page:offers}}', 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('ht-policies', lang('cancellation, children, taxes, policy, check-in, check-out'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: booking, the stay, dining and the spa. Each question
// is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pht-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about asking for a room, check-in and check-out, children, pets, breakfast, the spa and the transfer.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Asking for a room, the stay, dining and the spa. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('ht-faq-booking', lang('Asking for a room'), array(
                            array(lang('Is my request a confirmed reservation?'), lang('Not yet: the reception checks the room for your dates and answers within a day. The stay is confirmed when you read "Confirmed" on your request.'), lang('confirmation, reservation, request, availability')),
                            array(lang('How do I follow my request?'), lang('Sending the form opens an account for you. Under My Account you see the state of each request and can write to the reception there.'), lang('my account, status, follow, message')),
                            array(lang('Can I cancel or change my dates?'), lang('Yes, free of charge up to three days before arrival. Write to us on your request and we change it.'), lang('cancel, cancellation, change dates, refund')),
                            array(lang('Do you take a deposit?'), lang('No deposit is taken online. The stay is paid at the hotel, by card or in cash.'), lang('deposit, payment, pay, card')),
                        )),
                        $faq_group('ht-faq-stay', lang('Your stay'), array(
                            array(lang('What time are check-in and check-out?'), sprintf(lang('Check-in is from %1$s and check-out until %2$s. If the room is free, we are glad to give it earlier or keep it longer.'), $hotel['check_in'], $hotel['check_out']), lang('check-in, check-out, early, late, time')),
                            array(lang('Are children welcome?'), lang('Yes. Children under 6 stay free in their parents\' room, a cot is ready on request and the family rooms have a corner of their own.'), lang('children, kids, family, cot, baby')),
                            array(lang('Can I bring my dog?'), lang('Small dogs are welcome in the garden rooms; tell us in your request so we prepare the room.'), lang('pets, dog, cat, animals')),
                            array(lang('Can you collect us from the airport?'), lang('Yes, our driver meets you at arrivals. Ask for the transfer in your request and give us your flight.'), lang('airport, transfer, taxi, pick up')),
                        )),
                        $faq_group('ht-faq-dining', lang('Dining and the spa'), array(
                            array(lang('Is breakfast included?'), lang('With Bed and Breakfast and Half Board, yes. With Room Only you can add breakfast at the hotel any morning.'), lang('breakfast, included, board')),
                            array(lang('Can the kitchen cook for a diet or an allergy?'), lang('Yes: tell us with your request and the kitchen prepares for it every day of your stay.'), lang('allergy, vegetarian, vegan, gluten, diet')),
                            array(lang('Is the spa free for guests?'), lang('The hammam and the sauna are free for every guest; treatments are booked at the reception.'), lang('spa, hammam, sauna, massage, free')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured rooms (home_rooms) come with the
// rooms, its journal (home_posts) and the Google reviews (google_reviews)
// with the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
