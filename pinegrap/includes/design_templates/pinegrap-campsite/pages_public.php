<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Campsite", its public pages: home, the places to stay
 * and one page per kind of stay, the facilities, the activities and the
 * area, the seasons and prices, getting here, the park rules, about and
 * the questions page. Included after parts.php in the same scope (its
 * builders and the park's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($park, $kinds, $seasons,
 * $season_prices, $extras, $facilities, $activities, $area, $directions,
 * $rules, $why_us, $figures, $testimonials), so a change made there
 * reaches every page here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// A picture in a framed ratio, loaded lazily unless it is the first thing
// a page shows.
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
        $para($eyebrow, 'pcp-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pcp-display h2 mb-3'),
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

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pcp-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
            )),
        )),
    ));
};

// The band of figures under a page's opening.
$figures_band = function () use ($el, $container, $figure_row) {
    return $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
        $container(array($figure_row())),
    ));
};

// A section of feature cards under a section header, as a search section;
// with $moving the cards rise in one after another on view.
$feature_section = function ($id, $name, $header, $items, $lg, $topics, $class = 'py-5', $moving = false) use ($el, $container, $row, $feature_card, $searchable, $rise) {
    $cols = array();
    foreach ($items as $item) $cols[] = $feature_card($item, '6', $lg);
    $children = $header ? array($header) : array();
    $children[] = $moving ? $rise($row($cols, '4')) : $row($cols, '4');
    return $el('section', $class, $name, array($container($children)), $searchable($id, $topics));
};

// What a stay may add, a line each: the extra, its price and per what.
$extras_list = function () use ($el, $span, $extras, $money) {
    $items = array();
    foreach ($extras as $x) {
        $items[] = $el('li', 'list-group-item d-flex justify-content-between align-items-center gap-3 px-0', lang('List Item'), array(
            $span($x['title'], '', lang('Text')),
            $span(sprintf('%s %s', $money($x['price']), $x['unit']), 'pcp-price fw-semibold text-nowrap', lang('Price')),
        ));
    }
    return $el('ul', 'list-group list-group-flush mb-0', lang('Extras'), $items);
};

// The cards of the shared data, in their order.
$kind_cols = array();
foreach (array_keys($kinds) as $key) $kind_cols[] = $kind_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages = array();
$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A family-run campsite by a lake and a forest: tent and touring pitches, glamping tents and wooden cabins.'),
    'tree'             => $page(array(
        // The way in: the park over a wide picture of the lake, the stay
        // request and the prices, and a way into each kind of stay.
        $camp_hero(lang('Campsite and holiday park'), lang('Wake up by the lake'), $park['tagline'] . '.',
            $photo('hero', 1800, 770), lang('Tents in a meadow by a lake at sunset, with a forest behind')),

        // The kinds of stay from the wildest to the cosiest.
        $comfort_scale('cp-home-stays', 'py-5'),

        // The featured pitches and cabins, read from the collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Pitches and Cabins'), array(
            $container(array(
                $section_header(lang('Our favourites'), lang('Pitches and cabins guests ask for'), lang('All Pitches and Cabins'), '{{page:units}}'),
                $widget('home_units'),
            )),
        )),

        $feature_section('cp-home-why', lang('Why Guests Come Back'),
            $section_header(lang('Our promise'), lang('Why guests come back'), lang('About Us'), '{{page:about}}'),
            $why_us, '3', lang('quiet, spacious, lake, family, nature'), 'py-5', true),

        $story_band('cp-home-activities', lang('Things to do'), lang('Days on the water, evenings by the fire'),
            array(lang('Canoes at the jetty, trails from the gate, bikes for all ages and a campfire every weekend.'), $activities[4]['text']),
            $photo('home-canoes', 1200, 900), lang('Two canoes pulled up on a sandy lake shore'), true,
            array($link(lang('See the Activities'), '{{page:activities}}', 'btn btn-outline-secondary rounded-pill px-4')),
            lang('activities, canoe, kayak, hiking, bikes, kids club, campfire'), 'py-5 bg-body-tertiary'),

        // What guests say: sample words for the park to replace, and beside
        // them its own Google reviews, which show once Google Reviews is set
        // up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our guests'), lang('What guests say')),
                $rise($row($testimonial_cols, '4'), 120),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from the campsite'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Places to stay ──────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'stays',
    'name'             => lang('places-to-stay'),
    'folder'           => 'public',
    'title'            => lang('Places to Stay'),
    'meta_description' => lang('Tent pitches, touring pitches for caravans and motorhomes, glamping tents and cabins: find the stay that suits you.'),
    'tree'             => $page(array(
        $page_header(lang('Places to Stay'), lang('Bring your own tent or caravan, or let us make the bed for you.')),
        $el('section', 'py-5', lang('Places to Stay'), array(
            $container(array(
                $rise($row($kind_cols, '4')),
            )),
        ), $searchable('cp-stays', lang('tent, caravan, motorhome, campervan, glamping, cabin, lodge, bungalow'))),
        // What each kind asks of a guest and what it has ready, on the
        // scale from the wildest to the cosiest.
        $comfort_scale('cp-stays-comfort'),
        $el('section', 'py-5', lang('Prices'), array(
            $container(array(
                $section_header(lang('A night\'s price'), lang('Prices by season'), lang('All Prices'), '{{page:prices}}'),
                $season_table(),
            )),
        ), $searchable('cp-stays-prices', lang('price, rate, cost, per night, season'))),
        $ask_band(lang('Not sure which stay suits you?'), lang('Tell us how many you are and how you camp, and we will suggest a place.')),
    )),
);

// One page per kind of stay: what it is, who it sleeps, what it has, its
// prices by season, and the ways to its pitches or cabins and to the stay
// request with the kind chosen. Its address is a translated slug, so a
// Turkish site reads Turkish addresses.
$kind_slugs = array(
    'tent'     => lang('tent-pitches'),
    'touring'  => lang('touring-pitches'),
    'glamping' => lang('glamping'),
    'cabin'    => lang('cabins'),
);
foreach ($kinds as $key => $k) {
    $chosen = $query(array('kind' => $k['title']));
    $public_pages[] = array(
        'key'              => 'stay_' . $key,
        'name'             => $kind_slugs[$key],
        'folder'           => 'public',
        'title'            => $k['title'],
        'meta_description' => $k['summary'],
        'tree'             => $page(array_values(array_filter(array(
            $page_header($k['title'], $k['summary'], array($kind_facts($key, 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mt-3 mb-0'))),
            $el('section', 'py-5', $k['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($k['icon'], 'mb-3'),
                            $para($k['text'], 'lead mb-4'),
                            $heading('h2', lang('What you will find'), 'pcp-display h4 mb-3'),
                            $check_list($k['points'], 'list-unstyled mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Request a Stay'), '{{page:book}}?' . $chosen, 'btn btn-primary rounded-pill px-4'),
                                $link(lang('See the places'), '{{page:units}}?' . $chosen, 'btn btn-outline-secondary rounded-pill px-4'),
                            )),
                        ), '', '6'),
                        $col(array($framed($photo('kind-' . $key, 1200, 900), $k['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('cp-stay-' . $key)),
            // A tent pitch drawn from above, its points numbered (the other
            // kinds leave the null out).
            $key === 'tent' ? $pitch_drawing('cp-stay-tent-pitch', array(), 'py-5 border-top') : null,
            $el('section', 'py-5 bg-body-tertiary', lang('Prices'), array(
                $container(array(
                    $section_header(lang('A night\'s price'), lang('Prices by season'), lang('All Prices'), '{{page:prices}}'),
                    $season_table($key),
                    $para($price_note, 'small text-body-secondary mt-3 mb-0'),
                )),
            ), $searchable('cp-stay-' . $key . '-prices', lang('price, rate, cost, per night, season'))),
        )))),
    );
}

// ── Facilities ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'facilities',
    'name'             => lang('facilities'),
    'folder'           => 'public',
    'title'            => lang('Facilities'),
    'meta_description' => lang('Heated washing blocks, a camp shop, a café, a swimming lake, playgrounds, laundry and motorhome services.'),
    'tree'             => $page(array(
        $page_header(lang('Facilities'), lang('Everything you need for a stay outdoors, a short walk from every pitch.')),
        $feature_section('cp-facilities', lang('Facilities'), null, $facilities, '3',
            lang('showers, toilets, shop, cafe, restaurant, lake, swimming, playground, wifi, laundry, motorhome, electric car')),
        $story_band('cp-facilities-barn', lang('The heart of the park'), lang('The old barn'),
            array($facilities[1]['text'], $facilities[2]['text']),
            $photo('facilities-barn', 1200, 900), lang('An old wooden barn with tables on a terrace in front'), false, array(),
            lang('shop, bread, cafe, pizza, breakfast'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Activities and the area ─────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'activities',
    'name'             => lang('activities'),
    'folder'           => 'public',
    'title'            => lang('Activities'),
    'meta_description' => lang('Canoes, forest walks, bikes, campfire evenings, a nature club for children and the sights of the valley.'),
    'tree'             => $page(array(
        $page_header(lang('Activities'), lang('On the lake, in the forest and around the fire: there is always something to do.')),
        $feature_section('cp-activities', lang('Activities'), null, $activities, '4',
            lang('canoe, kayak, paddle board, hiking, walking, bikes, campfire, kids club, stars')),
        $feature_section('cp-area', lang('The Area'),
            $section_header(lang('Around the campsite'), lang('The valley around us'), lang('Getting Here'), '{{page:location}}'),
            $area, '3', lang('village, castle, national park, town, day trips, nearby'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Prices ──────────────────────────────────────────────────────────────
// Prices as content: the season table, the extras and what every price
// includes. A stay request asks for an offer; nothing is charged online.

$public_pages[] = array(
    'key'              => 'prices',
    'name'             => lang('prices'),
    'folder'           => 'public',
    'title'            => lang('Prices'),
    'meta_description' => lang('A night\'s price for every kind of stay in the low, mid and high season, the extras and what every price includes.'),
    'tree'             => $page(array(
        $page_header(lang('Prices'), $price_note),
        $el('section', 'py-5', lang('Prices'), array(
            $container(array(
                $section_header(lang('A night\'s price'), lang('Prices by season')),
                $season_table(),
            )),
        ), $searchable('cp-prices', lang('price, rate, cost, per night, season, low season, high season'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Extras'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Extras'), 'pcp-display h3 mb-3'),
                        $extras_list(),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Every price includes'), 'pcp-display h3 mb-3'),
                        $check_list($price_includes, 'list-unstyled mb-4'),
                        $para($park['arrival'] . '.', 'small text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Request a Stay'), '{{page:book}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Group Stays'), '{{page:groups}}', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('cp-prices-extras', lang('extra person, child, dog, pet, car, firewood, included'))),
    )),
);

// ── Getting here ────────────────────────────────────────────────────────
// The address, the coordinates and a link to the map, the ways to come and
// a plan of the park as a picture. The map opens on a marker at the
// park's coordinates.
list($map_lat, $map_lon) = array_map('trim', explode(',', $park['coordinates']));
$map_url = 'https://www.openstreetmap.org/?' . $query(array('mlat' => $map_lat, 'mlon' => $map_lon)) . '#map=14/' . $map_lat . '/' . $map_lon;

$public_pages[] = array(
    'key'              => 'location',
    'name'             => lang('getting-here'),
    'folder'           => 'public',
    'title'            => lang('Getting Here'),
    'meta_description' => lang('Where the campsite is, how to come by car, train or bus, and a plan of the park.'),
    'tree'             => $page(array(
        $page_header(lang('Getting Here'), $park['address']),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Find us'), 'pcp-display h3 mb-3'),
                        $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array($icon('bi-geo-alt', 'text-primary'), $span($park['address'], '', lang('Text')))),
                        $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Coordinates'), array($icon('bi-crosshair', 'text-primary'), $span($park['coordinates'], 'pcp-price', lang('Text')))),
                        $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Reception Hours'), array($icon('bi-clock', 'text-primary'), $span($park['hours'], '', lang('Text')))),
                        $el('p', 'd-flex align-items-start gap-2 mb-4', lang('Arrival and departure'), array($icon('bi-door-open', 'text-primary'), $span($park['arrival'], '', lang('Text')))),
                        $link(lang('Open in maps'), $map_url, 'btn btn-outline-primary rounded-pill px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('park-plan', 1200, 900), lang('A drawn plan of the campsite with the lake, the meadow, the forest and the barn'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('cp-address', lang('address, map, directions, where, coordinates, gps'))),
        $feature_section('cp-directions', lang('Directions'),
            $section_header(lang('On the way'), lang('How to get here')),
            $directions, '4', lang('car, motorway, train, station, bus, taxi'), 'py-5 bg-body-tertiary'),
        $feature_section('cp-location-area', lang('The Area'),
            $section_header(lang('Around the campsite'), lang('The valley around us'), lang('Activities'), '{{page:activities}}'),
            $area, '3', lang('village, castle, national park, town, nearby')),
    )),
);

// ── Park rules ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'rules',
    'name'             => lang('park-rules'),
    'folder'           => 'public',
    'title'            => lang('Park Rules'),
    'meta_description' => lang('Quiet hours, arrival and departure, dogs, fires and barbecues, cars and waste: how we keep the park pleasant for everyone.'),
    'tree'             => $page(array(
        $page_header(lang('Park Rules'), lang('A few simple rules keep the park quiet, safe and pleasant for every guest.')),
        $feature_section('cp-rules', lang('Park Rules'), null, $rules, '4',
            lang('rules, quiet hours, check-in, check-out, dogs, pets, fire, barbecue, cars, waste, recycling')),
        $ask_band(lang('A question about the rules?'), lang('Ask the reception before you come: we will gladly help.')),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A farmer\'s meadow by the lake that became a family-run campsite with pitches, glamping tents and cabins.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $park['tagline'] . '.'),
        $story_band('cp-our-story', lang('Our story'), lang('From a meadow by the lake'),
            array($park['story'], $park['opening'] . '.'),
            $photo('about-meadow', 1200, 900), lang('A wide meadow sloping down to a lake, with a few tents'), false, array(),
            lang('history, family, owners, who we are')),
        $figures_band(),
        $feature_section('cp-about-why', lang('Why Guests Come Back'),
            $section_header(lang('Our promise'), lang('Why guests come back')),
            $why_us, '3', lang('quiet, spacious, lake, family, nature')),
        $ask_band(lang('Would you like to see the park first?'), lang('Write to us, or simply drop in at the reception when you are nearby.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: booking and the stay. Each question is a search section
// of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pcp-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about stay requests, prices, arrival, dogs, electricity, campfires and what to bring.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Planning your stay and life on the campsite. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('cp-faq-booking', lang('Planning your stay'), array(
                            array(lang('How do I book a pitch or a cabin?'), lang('Send a stay request with your dates. It opens an account for you, where you follow the request and read our offer.'), lang('book, reserve, booking, reservation')),
                            array(lang('Is my stay confirmed when I send the request?'), lang('Not yet: the reception checks what is free, sends you an offer and confirms the stay once you accept it.'), lang('confirmation, availability, offer')),
                            array(lang('Can I choose a particular pitch?'), lang('Yes, name it in your request. If it is taken we offer the nearest one like it.'), lang('pitch number, choose pitch, favourite pitch')),
                            array(lang('Do you take groups?'), lang('Yes: schools, clubs, rallies and family celebrations send a group enquiry and we plan the stay with them.'), lang('group, school trip, rally, party')),
                        )),
                        $faq_group('cp-faq-stay', lang('During your stay'), array(
                            array(lang('Can I bring my dog?'), lang('Dogs are welcome on the pitches and in some cabins; they stay on a lead in the park.'), lang('dog, pet, animal')),
                            array(lang('Is there electricity on the pitches?'), lang('Most pitches have an electric hook-up; the lakeside tent pitches are kept without.'), lang('electric, hook-up, power, socket')),
                            array(lang('Can we light a campfire?'), lang('Yes, in the fire pits and the fire bowls we lend. Firewood is sold at the shop.'), lang('fire, campfire, firewood, barbecue')),
                            array(lang('What should I bring to a cabin?'), lang('Only your clothes: bed linen, towels and a full kitchen are there.'), lang('bring, linen, towels, kitchen, packing')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured pitches and cabins (home_units) come
// with the units, its news (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
