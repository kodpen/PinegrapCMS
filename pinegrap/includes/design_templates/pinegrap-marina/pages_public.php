<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marina", its public pages: home, the berths and one
 * page per kind of berth, the rates, the harbour services, the facilities
 * ashore, arriving by sea and by land, the harbour rules, about and the
 * questions page. Included after parts.php in the same scope (its
 * builders and the harbour's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($harbour, $kinds, $seasons, $rates,
 * $extras, $services, $facilities, $approach, $directions, $rules,
 * $why_us, $figures, $testimonials, $pontoons), so a change made there
 * reaches every page here.
 *
 * The home, the berths and the arriving page place the Marina blocks
 * (assets/js/style_designer_blocks_marina.js): the pontoon plan and the
 * arrival by sea, drawn here with the blocks' structure and classes; their
 * pg-marina-* rules ship with the template's User Styles.
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
        $para($eyebrow, 'pmn-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pmn-display h2 mb-3'),
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

// A node with an interaction (props._fx, played by pg_fx; reduced motion
// is honoured there).
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// The Marina blocks' entrance: the children rise one after another while
// the row scrolls into view.
$rise = function ($node, $stagger = 90) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger));
};
// A length or a depth in metres.
$metres = function ($value) {
    return str_replace('{var}', $value, lang('{var} m'));
};

// The pontoon plan (the marina-pontoons block): a card per pontoon with its
// letter, its use, its berths and what each berth has, as a search section
// under $id.
$pontoon_plan = function ($id, $title, $text, $more_text, $more_href, $topics, $class = 'py-5 bg-body-tertiary')
    use ($el, $heading, $para, $link, $span, $icon, $container, $row, $col, $rise, $metres, $pontoons, $pontoon_amenities, $searchable) {
    $fact = function ($label, $value) use ($el, $heading) {
        return $el('div', 'd-flex justify-content-between gap-3 py-2 border-top', $label, array(
            $heading('dt', $label, 'fw-normal text-body-secondary'),
            $heading('dd', $value, 'fw-semibold text-end mb-0'),
        ));
    };
    $cols = array();
    $n = 0;
    foreach ($pontoons as $letter => $pt) {
        $n++;
        $pills = array();
        foreach ($pt['amenities'] as $a) {
            $pills[] = $el('li', 'd-inline-flex align-items-center gap-1 badge rounded-pill text-bg-secondary fw-normal', $pontoon_amenities[$a]['title'], array(
                $icon($pontoon_amenities[$a]['icon']),
                $span($pontoon_amenities[$a]['title'], '', lang('Text')),
            ));
        }
        $cols[] = $col(array(
            $el('div', 'card h-100 border-0 shadow-sm', lang('Pontoon Card'), array(
                $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                    $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Card Head'), array(
                        $span($letter, 'pg-marina-pontoon flex-shrink-0', lang('Pontoon Letter')),
                        $el('div', '', lang('Pontoon Name'), array(
                            $heading('h3', $pt['name'], 'pmn-display h5 fw-bold mb-0'),
                            $span($pt['use'], 'small text-body-secondary', lang('Text')),
                        )),
                    )),
                    $el('dl', 'small mb-3', lang('Pontoon Facts'), array(
                        $fact(lang('Berths'), $pt['berths']),
                        $fact(lang('Berth length'), lang(array('string' => '{var:1} – {var:2} m', 'vars' => array($pt['from'], $pt['to'])))),
                        $fact(lang('Largest boat'), $metres($pt['largest'])),
                        $fact(lang('Depth alongside'), $metres($pt['depth'])),
                    )),
                    $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0 mt-auto', lang('On Every Berth'), $pills),
                )),
            )),
        ), '6', '3', '', '12', '');
    }
    return $el('section', $class . ' pg-marina-figures', lang('Pontoon Plan'), array(
        $container(array(
            $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                $el('div', '', lang('Section Title'), array(
                    $heading('h2', $title, 'pmn-display h2 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link($more_text, $more_href, 'btn btn-primary rounded-pill px-4'),
            )),
            $rise($row($cols, '4')),
            $para(lang('The length overall of your boat decides the pontoon; the harbour office gives you the berth number on arrival.'), 'small text-body-secondary text-center mt-4 mb-0'),
        )),
    ), $searchable($id, $topics));
};

// The arrival by sea (the marina-arrival block): a dark band with the
// radio call, the phone, the approach position and the entrance as tiles,
// beside the steps from the breakwater to the office. $more is put under
// the tiles (the arriving page's map link).
$arrival_band = function ($id, $topics, $more = array())
    use ($el, $heading, $para, $span, $icon, $container, $row, $col, $attr, $rise, $harbour, $searchable) {
    $tile = function ($icon_name, $label, $value, $note) use ($el, $span, $icon, $col) {
        return $col(array(
            $el('div', 'h-100 p-3 rounded-3 border', lang('Fact Tile'), array(
                $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mb-1', lang('Fact Label'), array(
                    $icon($icon_name, 'text-primary'),
                    $span($label, '', lang('Text')),
                )),
                $span($value, 'd-block h5 fw-bold mb-1', lang('Value')),
                $span($note, 'd-block small text-body-secondary', lang('Note')),
            )),
        ), '6', '6');
    };
    $leg = function ($n, $title, $text) use ($el, $heading, $para, $span) {
        return $el('li', 'd-flex gap-3 pb-3', $title, array(
            $span((string)$n, 'badge rounded-pill text-bg-primary align-self-start', lang('Step Number')),
            $el('div', '', lang('Text'), array(
                $heading('h4', $title, 'h6 fw-bold mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ));
    };
    return $el('section', 'py-5 bg-body text-body pg-marina-figures', lang('Arrival by Sea'), array(
        $container(array(
            $row(array(
                $col(array_merge(array(
                    $heading('h2', lang('Arriving by sea'), 'pmn-display h2 mb-2'),
                    $para($harbour['arrival'] . '.', 'text-body-secondary mb-4'),
                    $rise($row(array(
                        $tile('bi-broadcast', lang('VHF radio'), str_replace('{var}', $harbour['channel'], lang('Channel {var}')), lang('Call when you are a mile out')),
                        $tile('bi-telephone', lang('Phone'), $harbour['phone'], lang('Day and night')),
                        $tile('bi-geo-alt', lang('Approach'), $harbour['coordinates'], lang('Half a mile off the entrance')),
                        $tile('bi-arrows-collapse', lang('Entrance'), str_replace('{var}', '3.5', lang('{var} m deep')), lang('Green light to starboard')),
                    ), '3'), 70),
                ), $more), '', '6'),
                $col(array(
                    $el('div', 'h-100 p-4 rounded-4 border', lang('Approach Card'), array(
                        $heading('h3', lang('From the breakwater to your berth'), 'pmn-display h5 fw-bold mb-3'),
                        $el('ol', 'list-unstyled mb-3', lang('Approach Steps'), array(
                            $leg(1, lang('Call on the radio'), lang('Give the name, length and draught of the boat, and how many nights you stay.')),
                            $leg(2, lang('Enter slowly'), lang('Three knots inside the breakwater, keeping the green light to starboard.')),
                            $leg(3, lang('Tie up at reception'), lang('The fuel and reception pontoon is straight ahead; the marina team takes your lines.')),
                            $leg(4, lang('Check in at the office'), lang('Bring the papers and insurance of the boat; we give you your berth and the gate code.')),
                        )),
                        $el('div', 'd-flex gap-2 small', lang('Night Note'), array(
                            $icon('bi-moon-stars', 'text-primary flex-shrink-0'),
                            $span(lang('Arriving after dark? The harbour master on watch answers the radio all night.'), '', lang('Text')),
                        )),
                    )),
                ), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), array_merge($searchable($id, $topics), array('_attrs' => array($attr('data-bs-theme', 'dark')))));
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pmn-display h4 mb-1'),
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

// A section of feature cards under a section header, as a search section.
$feature_section = function ($id, $name, $header, $items, $lg, $topics, $class = 'py-5') use ($el, $container, $row, $feature_card, $searchable) {
    $cols = array();
    foreach ($items as $item) $cols[] = $feature_card($item, '6', $lg);
    $children = $header ? array($header) : array();
    $children[] = $row($cols, '4');
    return $el('section', $class, $name, array($container($children)), $searchable($id, $topics));
};

// What a berth may add, a line each: the extra, its price and per what.
$extras_list = function () use ($el, $span, $extras, $money) {
    $items = array();
    foreach ($extras as $x) {
        $items[] = $el('li', 'list-group-item d-flex justify-content-between align-items-center gap-3 px-0', lang('List Item'), array(
            $span($x['title'], '', lang('Text')),
            $span(sprintf('%s %s', $money($x['price']), $x['unit']), 'pmn-price fw-semibold text-nowrap', lang('Price')),
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
    'meta_description' => lang('A sheltered marina with visitor, seasonal and annual berths, moorings, dry stack, charter boats and a full boatyard.'),
    'tree'             => $page(array(
        // The way in: the harbour in a sentence, the two ways on and a
        // picture of the pontoons; the words rise in on load.
        $el('section', 'pmn-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $fx($el('div', '', lang('Hero Text'), array(
                            $para(lang('Marina and yacht harbour'), 'pmn-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                            $heading('h1', lang('A safe berth at the head of the bay'), 'pmn-display display-3 mb-3'),
                            $para($harbour['tagline'] . '.', 'lead text-body-secondary mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Request a Berth'), '{{page:berth_request}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                                $link(lang('Charter a Boat'), '{{page:charters}}', 'btn btn-outline-secondary btn-lg rounded-pill px-4'),
                            )),
                        )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800, 'stagger' => 120)),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('Yachts moored along a pontoon in a calm harbour at sunset'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The harbour as a skipper reads it: the pontoons behind the
        // breakwater, then the way in from the sea.
        $pontoon_plan('mn-home-berths', lang('Find your pontoon'),
            lang('Four pontoons behind the breakwater, from small day boats to yachts of thirty metres.'),
            lang('All Berths'), '{{page:berths}}',
            lang('berth, pontoon, mooring, buoy, dry stack, visitor, seasonal, annual, marina, length, depth')),
        $arrival_band('mn-home-arriving', lang('vhf, radio, channel, coordinates, entrance, night, harbour office')),

        // The featured charter boats, read from the collection.
        $el('section', 'py-5', lang('Charters'), array(
            $container(array(
                $section_header(lang('Charter boats'), lang('Take a boat out of the harbour'), lang('All Charter Boats'), '{{page:charters}}'),
                $widget('home_charters'),
            )),
        )),

        $feature_section('mn-home-services', lang('Services'),
            $section_header(lang('In the harbour'), lang('Harbour services'), lang('All Services'), '{{page:services}}'),
            array_slice($services, 0, 4), '3', lang('fuel, diesel, pump-out, boatyard, crane, travel lift, chandlery'), 'py-5 bg-body-tertiary'),

        // The coming events, read from the collection.
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $section_header(lang('On the water and ashore'), lang('Coming events'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // What crews say: sample words for the marina to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our crews'), lang('What crews say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from the harbour'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Berths ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'berths',
    'name'             => lang('berths'),
    'folder'           => 'public',
    'title'            => lang('Berths'),
    'meta_description' => lang('Visitor, seasonal and annual pontoon berths, swinging moorings and a dry stack: find the berth that suits your boat.'),
    'tree'             => $page(array(
        $page_header(lang('Berths'), lang('From a night on the visitor pontoon to a home berth for the whole year.')),
        $el('section', 'py-5', lang('Berths'), array(
            $container(array(
                $rise($row($kind_cols, '4')),
            )),
        ), $searchable('mn-berths', lang('berth, pontoon, mooring, buoy, dry stack, visitor, seasonal, annual'))),
        $pontoon_plan('mn-berths-pontoons', lang('Find your pontoon'),
            lang('Not sure which berth suits your boat? Its length and draught decide the pontoon.'),
            lang('Request a Berth'), '{{page:berth_request}}',
            lang('pontoon, length, depth, draught, shore power, water, wifi')),
        $el('section', 'py-5', lang('Rates'), array(
            $container(array(
                $section_header(lang('What a berth costs'), lang('Rates by season'), lang('All Rates'), '{{page:rates}}'),
                $rate_table(),
            )),
        ), $searchable('mn-berths-rates', lang('price, rate, cost, per metre, per night, season'))),
    )),
);

// One page per kind of berth: what it is, the boats it takes, what it
// includes, its rates by season, and the way to the berth request with the
// kind chosen. Its address is a translated slug, so a Turkish site reads
// Turkish addresses.
$kind_slugs = array(
    'visitor'  => lang('visitor-berths'),
    'seasonal' => lang('seasonal-berths'),
    'annual'   => lang('annual-berths'),
    'mooring'  => lang('moorings'),
    'drystack' => lang('dry-stack'),
);
foreach ($kinds as $key => $k) {
    $public_pages[] = array(
        'key'              => 'berth_' . $key,
        'name'             => $kind_slugs[$key],
        'folder'           => 'public',
        'title'            => $k['title'],
        'meta_description' => $k['summary'],
        'tree'             => $page(array(
            $page_header($k['title'], $k['summary'], array($kind_facts($key, 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mt-3 mb-0'))),
            $el('section', 'py-5', $k['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($k['icon'], 'mb-3'),
                            $para($k['text'], 'lead mb-4'),
                            $heading('h2', lang('What the berth includes'), 'pmn-display h4 mb-3'),
                            $rise($check_list($k['points'], 'list-unstyled mb-4'), 70),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Request a Berth'), '{{page:berth_request}}?' . $query(array('kind' => $k['title'])), 'btn btn-primary rounded-pill px-4'),
                                $link(lang('Arriving by Sea'), '{{page:arriving}}', 'btn btn-outline-secondary rounded-pill px-4'),
                            )),
                        ), '', '6'),
                        $col(array($framed($photo('berth-' . $key, 1200, 900), $k['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('mn-berth-' . $key)),
            $el('section', 'py-5 bg-body-tertiary', lang('Rates'), array(
                $container(array(
                    $section_header(lang('What a berth costs'), lang('Rates by season'), lang('All Rates'), '{{page:rates}}'),
                    $rate_table($key),
                    $para($rate_note, 'small text-body-secondary mt-3 mb-0'),
                )),
            ), $searchable('mn-berth-' . $key . '-rates', lang('price, rate, cost, per metre, per night, season'))),
        )),
    );
}

// ── Rates ───────────────────────────────────────────────────────────────
// Rates as content: the season table, the extras and what every rate
// includes. A berth request asks for a berth; nothing is charged online.

$public_pages[] = array(
    'key'              => 'rates',
    'name'             => lang('rates'),
    'folder'           => 'public',
    'title'            => lang('Rates'),
    'meta_description' => lang('Berth and mooring rates in the low and high season, the extras and what every rate includes.'),
    'tree'             => $page(array(
        $page_header(lang('Rates'), $rate_note),
        $el('section', 'py-5', lang('Rates'), array(
            $container(array(
                $section_header(lang('What a berth costs'), lang('Rates by season')),
                $rate_table(),
            )),
        ), $searchable('mn-rates', lang('price, rate, cost, per metre, per night, season, low season, high season'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Extras'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Extras'), 'pmn-display h3 mb-3'),
                        $extras_list(),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Every rate includes'), 'pmn-display h3 mb-3'),
                        $check_list($rate_includes, 'list-unstyled mb-4'),
                        $para($harbour['arrival'] . '.', 'small text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Request a Berth'), '{{page:berth_request}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Harbour Services'), '{{page:services}}', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('mn-rates-extras', lang('electricity, shore power, crane, lift out, storage, pump-out, parking, included'))),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Harbour Services'),
    'meta_description' => lang('Fuel dock, pump-out, boatyard and travel lift, chandlery, sail loft and provisioning, all inside the marina.'),
    'tree'             => $page(array(
        $page_header(lang('Harbour Services'), lang('Fuel, repairs and supplies without leaving the harbour.')),
        $feature_section('mn-services', lang('Services'), null, $services, '4',
            lang('fuel, diesel, petrol, pump-out, waste, boatyard, crane, travel lift, repairs, engineer, chandlery, sails, provisioning')),
        $story_band('mn-services-yard', lang('The boatyard'), lang('Lift-out, repairs and winter storage'),
            array($services[2]['text'], lang('Many berth holders lift out with us each autumn: the boat is washed, stored ashore and launched again in spring, ready to sail.')),
            $photo('services-yard', 1200, 900), lang('A sailing yacht held in the slings of a travel lift above the water'), false,
            array($link(lang('See the Rates'), '{{page:rates}}', 'btn btn-outline-secondary rounded-pill px-4')),
            lang('lift out, haul out, antifouling, winter storage, repairs'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Facilities ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'facilities',
    'name'             => lang('facilities'),
    'folder'           => 'public',
    'title'            => lang('Facilities'),
    'meta_description' => lang('Showers, laundry, Wi-Fi, a café on the quay, a provisions shop, parking and security around the clock.'),
    'tree'             => $page(array(
        $page_header(lang('Facilities'), lang('Everything a crew needs ashore, a short walk from every pontoon.')),
        $feature_section('mn-facilities', lang('Facilities'), null, $facilities, '3',
            lang('showers, toilets, laundry, wifi, cafe, restaurant, shop, parking, security, charging')),
        $story_band('mn-facilities-quay', lang('Ashore'), lang('The café on the quay'),
            array($facilities[3]['text'], $facilities[4]['text']),
            $photo('facilities-quay', 1200, 900), lang('Tables on a stone quay beside moored boats'), true, array(),
            lang('cafe, breakfast, fish, shop, bread, ice'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Arriving ────────────────────────────────────────────────────────────
// By sea: the radio call, the approach position, the entrance, the steps
// to the berth, the pontoons and the pilotage; by land: the office hours,
// a link to the map and the ways to come. The map opens on a marker at the
// harbour's coordinates.
list($map_lat, $map_lon) = array_map('trim', explode(',', $harbour['coordinates']));
$map_url = 'https://www.openstreetmap.org/?' . $query(array('mlat' => $map_lat, 'mlon' => $map_lon)) . '#map=15/' . $map_lat . '/' . $map_lon;

$public_pages[] = array(
    'key'              => 'arriving',
    'name'             => lang('arriving'),
    'folder'           => 'public',
    'title'            => lang('Arriving'),
    'meta_description' => lang('How to call the harbour, the approach, the depths and arriving at night, and the way to the marina by land.'),
    'tree'             => $page(array(
        $page_header(lang('Arriving'), lang('By sea or by land: the radio call, the approach, your pontoon and the road to the harbour gate.')),
        // By sea: the radio call and the steps to the berth on the dark
        // band, the map under its tiles; then the pontoons and the pilotage.
        $arrival_band('mn-arriving', lang('vhf, radio, channel, coordinates, gps, chart, harbour office'), array(
            $el('p', 'd-flex align-items-start gap-2 small mt-4 mb-3', lang('Office Hours'), array($icon('bi-clock', 'text-primary'), $span($harbour['hours'], '', lang('Text')))),
            $link(lang('Open in maps'), $map_url, 'btn btn-outline-primary rounded-pill px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
        )),
        $pontoon_plan('mn-arriving-pontoons', lang('Find your pontoon'),
            lang('Four pontoons behind the breakwater, from small day boats to yachts of thirty metres.'),
            lang('Request a Berth'), '{{page:berth_request}}',
            lang('pontoon, berth number, length, depth, draught, shore power')),
        $feature_section('mn-approach', lang('The Approach'),
            $section_header(lang('Pilotage'), lang('Entering the harbour')),
            $approach, '3', lang('approach, entrance, buoy, light, depth, draught, night, tide')),
        $feature_section('mn-directions', lang('Directions'),
            $section_header(lang('By land'), lang('How to get here'), $harbour['address'], '{{page:contact}}'),
            $directions, '4', lang('address, car, parking, airport, transfer, bus, taxi'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Harbour rules ───────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'rules',
    'name'             => lang('harbour-rules'),
    'folder'           => 'public',
    'title'            => lang('Harbour Rules'),
    'meta_description' => lang('Speed, arrival and departure, quiet hours, clean water, fire safety, children and pets: how the harbour stays safe.'),
    'tree'             => $page(array(
        $page_header(lang('Harbour Rules'), lang('A few simple rules keep the harbour safe, clean and quiet for every crew.')),
        $feature_section('mn-rules', lang('Harbour Rules'), null, $rules, '4',
            lang('rules, speed limit, wash, quiet hours, holding tank, pollution, fire, refuelling, life jacket, dogs')),
        $ask_band(lang('A question about the rules?'), lang('Ask the harbour office before you arrive: we will gladly help.')),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A fishing quay that became a sheltered marina with pontoons, a boatyard and a café on the old net store.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $harbour['tagline'] . '.'),
        $story_band('mn-our-story', lang('Our story'), lang('From a fishing quay'),
            array($harbour['story'], $harbour['opening'] . '.'),
            $photo('about-quay', 1200, 900), lang('Fishing boats and yachts side by side at an old stone quay'), false, array(),
            lang('history, owners, harbour master, who we are')),
        $figures_band(),
        $feature_section('mn-about-why', lang('Why Crews Come Back'),
            $section_header(lang('Our promise'), lang('Why crews come back')),
            $why_us, '3', lang('safe, shelter, security, staff, town')),
        $ask_band(lang('Would you like to see the marina first?'), lang('Write to us, or call in at the harbour office when you are passing.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: berths and life in the harbour. Each question is a
// search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pmn-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about berth requests, rates, arriving, shore power, fuel, charters and staying aboard.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Berths, charters and life in the harbour. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('mn-faq-berths', lang('Berths and moorings'), array(
                            array(lang('How do I ask for a berth?'), lang('Send a berth request with your boat and your dates. It opens an account for you, where you follow the request and read our answer.'), lang('book, reserve, booking, reservation')),
                            array(lang('Is my berth confirmed when I send the request?'), lang('Not yet: the harbour office checks what is free for your boat\'s length and draught and confirms the berth it allocates.'), lang('confirmation, availability')),
                            array(lang('How are the rates counted?'), lang('On the boat\'s length overall, including the bowsprit and the bathing platform, by the night, the season or the year.'), lang('length, metre, loa, price')),
                            array(lang('Can I keep my boat ashore for the winter?'), lang('Yes: the boatyard lifts boats out each autumn and keeps them on stands or cradles until spring.'), lang('winter, storage, lift out, haul out')),
                        )),
                        $faq_group('mn-faq-harbour', lang('Life in the harbour'), array(
                            array(lang('Can I arrive at night?'), lang('Yes, the entrance is lit and the night watch answers the radio. Tie up on the reception pontoon and come to the office in the morning.'), lang('night, late, arrival')),
                            array(lang('Is there shore power on every berth?'), lang('Every pontoon berth has water and shore power; bring your own cable with the right plug, or borrow one from the office.'), lang('electric, power, plug, socket, cable')),
                            array(lang('Can we stay aboard?'), lang('Yes, crews may live aboard on every berth; the showers, the laundry and the café are a short walk away.'), lang('live aboard, sleep, stay')),
                            array(lang('Do you have boats to charter?'), lang('Yes: sailing yachts, a catamaran, a motor yacht and day boats, with or without a skipper. Send a charter enquiry from the boat\'s page.'), lang('charter, rent, hire, skipper')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured charter boats (home_charters) come
// with the charters, its coming events (home_events) with the events, its
// news (home_posts) and the Google reviews (google_reviews) with the
// standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
