<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Travel Agency", its public pages: home, about, travel
 * information, the deals and the questions page. Included after parts.php
 * in the same scope (its builders and the agency's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($agency, $tours, $tour_prices,
 * $deposit, $deals, $why_us, $included, $not_included, $figures,
 * $testimonials), so a change made there reaches every page here. No page
 * here links a shop page: the template stands on a site without the shop.
 *
 * The home is drawn on the general Hero (Image Background) block and the
 * two Travel blocks, Day by Day and Destination Guide: their pg- classes
 * are the block rules pinegrap-travel.php ships. What moves does so by the
 * interactions on the nodes (props _fx), never by a script of the
 * template's own.
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
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable, $fx) {
    $words = array(
        $para($eyebrow, 'ptv-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'ptv-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($fx($framed($picture, $alt), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 900))), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line: why travellers choose the
// agency, its values and what to know before a journey.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'ptv-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The agency in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'ptv-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// An icon and a few words on one line: a day's night and meals.
$note = function ($icon_name, $text, $name) use ($el, $icon, $span) {
    return $el('div', 'd-flex align-items-center gap-2', $name, array(
        $icon($icon_name, 'text-primary flex-shrink-0'),
        $span($text, '', lang('Text')),
    ));
};
// A fact of a trip or a place: its label over its value.
$fact = function ($label, $value) use ($el) {
    return $el('div', '', $label, array(
        $el('dt', '', lang('Label'), array(), array('text' => $label)),
        $el('dd', '', lang('Value'), array(), array('text' => $value)),
    ));
};

// The home's way in, on the general Hero (Image Background) block: a photo
// of the road under the dark shade, the agency in a sentence, the two ways
// on, and the four regions as the stops of a route, each opening the
// destinations of its region. The words come in on load, the stops after
// them, and a stop rises under the pointer.
$home_hero = function () use ($el, $heading, $para, $link, $span, $icon, $container, $attr, $fx, $photo, $query, $regions, $agency) {
    $stops = array();
    foreach ($regions as $r) {
        $stops[] = $el('li', '', $r['title'], array(
            $fx($el('a', 'btn btn-outline-light rounded-pill d-inline-flex align-items-center gap-2 px-3', lang('Link'), array(
                $icon($r['icon']),
                $span($r['title'], '', lang('Link Text')),
            ), array('href' => '{{page:destinations}}?' . $query(array('region' => $r['title'])))),
            array('effect' => 'slide-up', 'trigger' => 'hover', 'duration' => 200)),
        ));
    }
    return $el('section', 'd-flex overflow-hidden pg-hero-tall', lang('Hero'), array(
        $el('div', 'd-flex flex-column justify-content-end w-100 py-5 text-body pg-shade-bottom', lang('Overlay'), array(
            $container(array(
                $fx($el('div', 'col-lg-8 mb-5', lang('Hero Body'), array(
                    $para(lang('Small groups, local guides'), 'ptv-eyebrow text-uppercase fw-semibold mb-3'),
                    $heading('h1', lang('See the world slowly'), 'ptv-display display-2 mb-3'),
                    $para($agency['tagline'] . '.', 'lead mb-4'),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $link(lang('Find a tour'), '{{page:tours}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                        $link(lang('Plan a Tailor-made Trip'), '{{page:enquiry}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                    )),
                )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 900, 'stagger' => 120)),
                $fx($el('ol', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Regions'), $stops),
                    array('effect' => 'slide-up', 'trigger' => 'load', 'delay' => 500, 'stagger' => 100)),
            ), 'py-5'),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),
    ), array('_attrs' => array($attr('style', 'background-image: url(' . $photo('hero', 1920, 1080) . '); background-size: cover; background-position: center;'))));
};

// A tour day by day, on the Travel block Day by Day: each day with its
// marker, where the night is spent and the meals ($notes, one pair per
// day), and beside them the trip at a glance with the price and the ways
// to book it. The days rise one after another as they scroll into view.
$day_by_day = function ($key, $notes, $start, $id, $topics) use ($el, $heading, $para, $link, $span, $container, $row, $col, $fx, $rise, $fade_in, $note, $fact, $tours, $tour_prices, $money, $length_text, $query, $searchable) {
    $t = $tours[$key];
    $days = array();
    foreach ($t['itinerary'] as $n => $d) {
        $days[] = $el('li', '', $d[0], array(
            $el('div', 'pg-travel-day', lang('Day'), array(
                $span(lang('Day'), 'small text-uppercase fw-semibold', lang('Text')),
                $span((string)($n + 1), 'fs-5 fw-bold', lang('Number')),
            )),
            $el('div', 'flex-grow-1 pt-1', lang('Text'), array(
                $heading('h3', $d[0], 'h5 fw-bold mb-1'),
                $para($d[1], 'text-body-secondary mb-2'),
                $el('div', 'd-flex flex-wrap gap-3 small', lang('Notes'), array(
                    $note('bi-moon', $notes[$n][0], lang('Overnight')),
                    $note('bi-cup-hot', $notes[$n][1], lang('Meals')),
                )),
            )),
        ));
    }
    $glance = $el('aside', 'card border-0 shadow-sm rounded-4 sticky-lg-top', lang('Trip at a Glance'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $heading('h3', lang('Trip at a glance'), 'h5 fw-bold mb-3'),
            $el('dl', 'pg-travel-glance mb-4', lang('Facts'), array(
                $fact(lang('Duration'), $length_text($key)),
                $fact(lang('Group size'), implode(' ', array(lang('Up to'), (string)$t['group'], lang('travellers')))),
                $fact(lang('Starts and ends'), $start),
                $fact(lang('Departures'), $t['departures']),
            )),
            $el('div', 'd-flex align-items-baseline flex-wrap gap-2 mb-3', lang('Price'), array(
                $span(lang('Starting from'), 'small text-body-secondary', lang('Label')),
                $span($money($tour_prices[$key]), 'pg-travel-figure fs-2 fw-bold', lang('Price')),
                $span(lang('per traveller'), 'text-body-secondary', lang('Unit')),
            )),
            // Opens the booking request with this tour chosen (?tour=).
            $link(lang('Book this tour'), '{{page:book}}?' . $query(array('tour' => $t['name'])), 'btn btn-primary rounded-pill w-100 mb-2'),
            $link(lang('Plan a Tailor-made Trip'), '{{page:enquiry}}', 'btn btn-outline-primary rounded-pill w-100'),
        )),
    ));
    return $el('section', 'py-5 bg-body-tertiary', lang('Travel (Day by Day)'), array(
        $container(array(
            $fx($el('div', 'col-lg-8 mb-5', lang('Section Header'), array(
                $para(lang('Itinerary'), 'ptv-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                $heading('h2', $t['name'], 'ptv-display h2 mb-2'),
                $para($t['summary'], 'lead text-body-secondary mb-0'),
            )), $fade_in),
            $row(array(
                $col(array($rise($el('ol', 'pg-travel-days', lang('Itinerary'), $days), 100)), '', '8'),
                $col(array($glance), '', '4'),
            ), '4', '', 'g-lg-5 align-items-start'),
        )),
    ), $searchable($id, $topics));
};

// A destination as a guide, on the Travel block Destination Guide: the
// picture with the region, the country, the name and the summary, the
// best time to go season by season ($seasons), the facts a traveller asks
// first ($facts, label => value) and the ways to its tours. The picture
// fades in and the seasons rise as they scroll into view.
$destination_guide = function ($key, $alt, $seasons, $facts, $id, $topics) use ($el, $heading, $para, $link, $span, $icon, $image, $attr, $container, $row, $col, $fx, $rise, $fact, $photo, $query, $destinations, $regions, $searchable) {
    $d = $destinations[$key];
    $tiles = array();
    foreach ($seasons as $s) {
        $verdict = array($span($s['verdict'], '', lang('Text')));
        if ($s['best']) array_unshift($verdict, $icon('bi-check2-circle', 'flex-shrink-0'));
        $tiles[] = $el('li', $s['best'] ? 'pg-travel-best' : '', $s['title'], array(
            $icon($s['icon'], 'fs-4 d-block mb-1'),
            $span($s['title'], 'd-block fw-semibold', lang('Name')),
            $el('div', 'd-flex align-items-center justify-content-center gap-1 small', lang('Verdict'), $verdict),
        ));
    }
    $fact_nodes = array();
    foreach ($facts as $label => $value) $fact_nodes[] = $fact($label, $value);
    $picture = $el('figure', 'position-relative m-0', lang('Picture'), array(
        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
            $image($photo('place-' . $key, 1200, 900), $alt, 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
        )),
        $el('div', 'badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1 position-absolute top-0 start-0 m-3', lang('Region'), array(
            $icon($regions[$d['region']]['icon']),
            $span($regions[$d['region']]['title'], '', lang('Text')),
        )),
    ));
    return $el('section', 'py-5', lang('Travel (Destination Guide)'), array(
        $container(array(
            $row(array(
                $col(array($fx($picture, array('effect' => 'fade', 'trigger' => 'view', 'duration' => 900))), '', '6'),
                $col(array(
                    $para($d['country'], 'ptv-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', $d['name'], 'ptv-display h2 mb-2'),
                    $para($d['summary'], 'lead text-body-secondary mb-4'),
                    $heading('h3', lang('Best time to go'), 'h6 fw-bold mb-2'),
                    $rise($el('ul', 'pg-travel-seasons mb-4', lang('Seasons'), $tiles), 80),
                    $el('dl', 'pg-travel-glance row row-cols-1 row-cols-sm-2 g-0 mb-4', lang('Facts'), $fact_nodes),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        // The tour list and the enquiry, each with this
                        // destination chosen (?destination=).
                        $link(lang('See the tours here'), '{{page:tours}}?' . $query(array('destination' => $d['name'])), 'btn btn-primary rounded-pill px-4'),
                        $link(lang('Plan a Tailor-made Trip'), '{{page:enquiry}}?' . $query(array('destination' => $d['name'])), 'btn btn-outline-primary rounded-pill px-4'),
                    )),
                ), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// The cards of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$deal_cards_row = array();
foreach (array_keys($deals) as $key) $deal_cards_row[] = $deal_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$deposit_text = $money($deposit);

// ── Home ────────────────────────────────────────────────────────────────

// The Cappadocia sample on the home: where each of its nights is spent and
// its meals, one pair per day of its itinerary, and the destination's
// seasons and facts. The tour and destination records have no such
// fields, so only the home shows them.
$home_day_notes = array(
    array(lang('Cave hotel in Göreme'), lang('Dinner')),
    array(lang('Cave hotel in Göreme'), lang('Breakfast and lunch')),
    array(lang('Stone house in Ürgüp'), lang('Breakfast and dinner')),
    array(lang('The journey home'), lang('Breakfast')),
);
$home_seasons = array(
    array('icon' => 'bi-flower1', 'title' => lang('Spring'), 'verdict' => lang('Best time'), 'best' => true),
    array('icon' => 'bi-sun', 'title' => lang('Summer'), 'verdict' => lang('Hot days'), 'best' => false),
    array('icon' => 'bi-tree', 'title' => lang('Autumn'), 'verdict' => lang('Best time'), 'best' => true),
    array('icon' => 'bi-snow', 'title' => lang('Winter'), 'verdict' => lang('Snow and quiet'), 'best' => false),
);
$home_place_facts = array(
    lang('Getting there') => lang('A short flight to Kayseri or Nevşehir'),
    lang('Language')      => lang('Turkish'),
    lang('Currency')      => lang('Turkish lira'),
    lang('Entry visa')    => lang('Many travellers apply online before they go'),
);

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Small-group tours with local guides on four continents, and journeys planned around you.'),
    'tree'             => $page(array(
        $home_hero(),

        // The featured tours, read from the Tours collection.
        $el('section', 'py-5', lang('Featured Tours'), array(
            $container(array(
                $fx($section_header(lang('Where we go'), lang('Tours our travellers love'), lang('All Tours'), '{{page:tours}}'), $fade_in),
                $widget('home_tours'),
            )),
        )),

        // One of them day by day, then one place in depth.
        $day_by_day('cappadocia', $home_day_notes, lang('Kayseri airport'), 'tv-home-itinerary',
            lang('itinerary, day by day, overnight, hotel, meals, programme')),
        $destination_guide('cappadocia', lang('Balloons over the fairy chimneys of Cappadocia at sunrise'), $home_seasons, $home_place_facts,
            'tv-home-destination', lang('best time to go, season, weather, visa, currency, language')),

        // The featured destinations, read from the Destinations collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Featured Destinations'), array(
            $container(array(
                $fx($section_header(lang('Four continents'), lang('Destinations to dream of'), lang('All Destinations'), '{{page:destinations}}'), $fade_in),
                $widget('home_destinations'),
            )),
        )),

        $el('section', 'py-5', lang('Deals'), array(
            $container(array(
                $fx($section_header(lang('Good to know'), lang('Deals on every tour'), lang('All deals'), '{{page:deals}}'), $fade_in),
                $rise($row($deal_cards_row, '4')),
            )),
        )),

        // What travellers say: sample words for the agency to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings. Each tour page carries its own
        // reviews.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $fx($section_header(lang('Our travellers'), lang('What travellers say'), lang('About Us'), '{{page:about}}'), $fade_in),
                $rise($row($testimonial_cols, '4'), 120),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From the Journal'), array(
            $container(array(
                $fx($section_header(lang('Travel Journal'), lang('Stories from the road'), lang('All stories'), '{{page:blog}}'), $fade_in),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-signpost-split', 'title' => lang('We go first'), 'text' => lang('Every tour is one we have walked ourselves before the first traveller joins it.')),
    array('icon' => 'bi-basket', 'title' => lang('Money stays local'), 'text' => lang('Local guides, family-run stays and village kitchens: what you spend stays where you travel.')),
    array('icon' => 'bi-recycle', 'title' => lang('Light on the land'), 'text' => lang('Trains where they run, no single-use plastic on tour and a tree planted for every traveller.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text'], '4');

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A small travel agency started by two guides: small groups, local guides and family-run stays.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $agency['tagline'] . '.'),
        $story_band('tv-our-story', lang('Our story'), lang('Two guides and a mountain path'),
            array($agency['story'], lang('Today a small team of planners and guides keeps the same rule: we sell only the journeys we would take ourselves.')),
            $photo('about-guides', 1200, 900), lang('Two guides reading a map at a mountain pass'), false, array(),
            lang('history, founders, team, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($rise($figure_row(), 120))),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $fx($section_header(lang('What we care about'), lang('How we travel')), $fade_in),
                $rise($row($about_value_tiles, '4')),
            )),
        ), $searchable('tv-values', lang('sustainable, responsible travel, environment, local'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Why Travel with Us'), array(
            $container(array(
                $fx($section_header(lang('The way we travel'), lang('Why travel with us'), lang('Travel Information'), '{{page:travel_info}}'), $fade_in),
                $rise($row($why_tiles, '4')),
            )),
        ), $searchable('tv-about-why', lang('small group, local guide, family hotel, licensed, protected'))),
    )),
);

// ── Travel information ──────────────────────────────────────────────────

$booking_steps = array(
    array(lang('Send a booking request'), lang('Choose a tour, a departure date and how many travel; the request opens an account for you.')),
    array(lang('We confirm your places'), lang('A planner checks the departure and answers within a day with the price for your group.')),
    array(lang('Pay the deposit'), sprintf(lang('A deposit of %s per traveller holds your places; the rest is paid six weeks before departure.'), $deposit_text)),
    array(lang('Get ready to go'), lang('Two weeks before departure you receive the final itinerary, the hotels and your tour leader\'s phone.')),
);
$step_items = array();
foreach ($booking_steps as $n => $s) $step_items[] = $step($n + 1, $s[0], $s[1]);
$before_you_go = array(
    array('icon' => 'bi-passport', 'title' => lang('Passports and visas'), 'text' => lang('Your passport should be valid for six months after you return. We tell you which countries ask for a visa when we confirm.')),
    array('icon' => 'bi-shield-plus', 'title' => lang('Travel insurance'), 'text' => lang('Insurance that covers medical care and cancellation is a condition of every tour; send us its number before departure.')),
    array('icon' => 'bi-heart-pulse', 'title' => lang('Health'), 'text' => lang('Ask your doctor about vaccinations six weeks before you go, and tell us about any condition the guides should know.')),
    array('icon' => 'bi-backpack', 'title' => lang('What to pack'), 'text' => lang('Light layers, good walking shoes and a small day pack; the final itinerary comes with a packing list for your tour.')),
);
$before_tiles = array();
foreach ($before_you_go as $b) $before_tiles[] = $feature_tile($b['icon'], $b['title'], $b['text']);

$public_pages[] = array(
    'key'              => 'travel_info',
    'name'             => lang('travel-information'),
    'folder'           => 'public',
    'title'            => lang('Travel Information'),
    'meta_description' => lang('How a booking works, what every tour includes, the deposit, and what to know about passports, insurance and health.'),
    'tree'             => $page(array(
        $page_header(lang('Travel Information'), lang('How a booking works, what a tour includes and what to arrange before you go.')),
        $el('section', 'py-5', lang('How Booking Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Step by step'), 'ptv-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('From request to departure'), 'ptv-display h2 mb-4'),
                        $rise($el('ol', 'list-unstyled mb-4', lang('Steps'), $step_items), 120),
                        $link(lang('Book a Tour'), '{{page:book}}', 'btn btn-primary rounded-pill px-4'),
                    ), '', '6'),
                    $col(array($fx($framed($photo('info-planning', 1200, 900), lang('A planner on the phone in front of a world map')),
                        array('effect' => 'fade', 'trigger' => 'view', 'duration' => 900))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('tv-how-booking-works', lang('book, request, confirm, deposit, payment, balance'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What a Tour Includes'), array(
            $container(array(
                $rise($row(array(
                    $col(array(
                        $heading('h2', lang('Every tour includes'), 'ptv-display h3 mb-3'),
                        $check_list($included),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Not included'), 'ptv-display h3 mb-3'),
                        $check_list($not_included),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'), 120),
            )),
        ), $searchable('tv-included', lang('included, price includes, flights, meals, insurance, extras'))),
        $el('section', 'py-5', lang('Before You Go'), array(
            $container(array(
                $fx($section_header(lang('Before you go'), lang('What to arrange at home'), lang('Frequently Asked Questions'), '{{page:faq}}'), $fade_in),
                $rise($row($before_tiles, '4')),
            )),
        ), $searchable('tv-before-you-go', lang('passport, visa, insurance, vaccination, packing list'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Payments and Cancellation'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Paying for your tour'), 'ptv-display h3 mb-3'),
                        $check_list(array(
                            sprintf(lang('A deposit of %s per traveller once we confirm your places'), $deposit_text),
                            lang('The balance six weeks before departure, by card or bank transfer'),
                            lang('Prices are per traveller sharing a double room and include taxes'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('If you need to cancel'), 'ptv-display h3 mb-3'),
                        $check_list(array(
                            lang('More than six weeks before departure: only the deposit is kept'),
                            lang('Two to six weeks before: half of the tour price'),
                            lang('Less than two weeks before: the whole tour price'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('tv-payments', lang('deposit, balance, refund, cancellation policy, terms'))),
    )),
);

// ── Deals ───────────────────────────────────────────────────────────────

// A row of the price table for each sample tour, its button opening the
// booking request with the tour chosen.
$price_rows = array();
foreach ($tours as $key => $t) {
    $price_rows[] = $el('tr', '', $t['name'], array(
        $el('th', 'fw-semibold', lang('Tour Name'), array(), array('text' => $t['name'], '_attrs' => array($attr('scope', 'row')))),
        $el('td', '', lang('Tour Destination'), array(), array('text' => $destinations[$t['destination']]['name'])),
        $el('td', 'text-nowrap', lang('Length'), array(), array('text' => $length_text($key))),
        $el('td', 'text-nowrap', lang('Departures'), array(), array('text' => $t['departures'])),
        $el('td', 'ptv-price fw-semibold text-nowrap', lang('Price'), array(), array('text' => $money($tour_prices[$key]))),
        $el('td', 'text-end', lang('Action'), array(
            $link(lang('Book this tour'), '{{page:book}}?' . $query(array('tour' => $t['name'])), 'btn btn-sm btn-outline-primary rounded-pill px-3 text-nowrap'),
        )),
    ));
}

$public_pages[] = array(
    'key'              => 'deals',
    'name'             => lang('deals'),
    'folder'           => 'public',
    'title'            => lang('Deals'),
    'meta_description' => lang('Save on early booking, travel with friends or alone without a single supplement, and see the price of every tour.'),
    'tree'             => $page(array(
        $page_header(lang('Deals'), lang('Ways to save on every tour. Choose a deal in your booking request; we take it off the price when we confirm.')),
        $el('section', 'py-5', lang('Deals'), array(
            $container(array(
                $rise($row($deal_cards_row, '4'), 120),
            )),
        ), $searchable('tv-deals', lang('discount, offer, saving, early booking, group, solo'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Tour Prices'), array(
            $container(array(
                $fx($section_header(lang('Every tour'), lang('Prices per traveller'), lang('All Tours'), '{{page:tours}}'), $fade_in),
                $fx($el('div', 'table-responsive', lang('Table'), array(
                    $el('table', 'table align-middle mb-0', lang('Price Table'), array(
                        $el('thead', '', lang('Table Head'), array(
                            $el('tr', '', lang('Row'), array(
                                $el('th', '', lang('Tour Name'), array(), array('text' => lang('Tour'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Tour Destination'), array(), array('text' => lang('Where'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Length'), array(), array('text' => lang('How long'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Departures'), array(), array('text' => lang('Departures'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Price'), array(), array('text' => lang('From, per traveller'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Action'), array($span(lang('Book this tour'), 'visually-hidden', lang('Text'))), array('_attrs' => array($attr('scope', 'col')))),
                            )),
                        )),
                        $el('tbody', '', lang('Table Body'), $price_rows),
                    )),
                )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700)),
                $para(lang('Prices are per traveller sharing a double room; a planner confirms the price for your departure.'), 'small text-body-secondary mt-3 mb-0'),
            )),
        ), $searchable('tv-prices', lang('price, prices, cost, how much, per person'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: booking, on tour, payments. Each question is a search
// section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'ptv-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about booking a tour, group size, travelling alone, fitness, the deposit and cancellation.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Booking, life on tour and payments. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $rise($col(array(
                        $faq_group('tv-faq-booking', lang('Booking a tour'), array(
                            array(lang('Is my booking request a confirmed booking?'), lang('Not yet: a planner checks the departure and answers within a day. Your places are confirmed when you read "Confirmed" on your request.'), lang('confirmation, reservation, request, availability')),
                            array(lang('How do I follow my booking request?'), lang('Sending the form opens an account for you. Under My Account you see the state of each request and can write to us there.'), lang('my account, status, follow, message')),
                            array(lang('Can you plan a tour just for us?'), lang('Yes: every tour can run as a private departure, and a tailor-made journey is planned around you from the start.'), lang('private tour, custom, tailor-made, own dates')),
                            array(lang('Are flights included?'), lang('No: tours start and end at the airport named in the itinerary, so you can fly from wherever suits you. We are glad to suggest flights.'), lang('flights, airfare, plane, airport')),
                        )),
                        $faq_group('tv-faq-tour', lang('On tour'), array(
                            array(lang('How big are the groups?'), lang('Never more than twelve travellers, and often fewer; each tour page shows its largest group.'), lang('group size, how many people, small group')),
                            array(lang('Can I travel alone?'), lang('Of course. Share a room with another traveller of the same group, or ask for a room of your own; some departures carry no single supplement.'), lang('solo, single, alone, single supplement')),
                            array(lang('How fit do I need to be?'), lang('If you can walk for three hours with stops, every tour suits you. The tour page says where a day is longer or steeper.'), lang('fitness, walking, hiking, difficulty, level')),
                            array(lang('Are children welcome?'), lang('Children from eight years on are welcome on every tour with a parent; for a family we suggest a tailor-made journey.'), lang('children, kids, family, age')),
                        )),
                        $faq_group('tv-faq-payments', lang('Payments'), array(
                            array(lang('How much is the deposit?'), sprintf(lang('%s per traveller, paid once we confirm your places. The balance is due six weeks before departure.'), $deposit_text), lang('deposit, down payment, balance')),
                            array(lang('What if I need to cancel?'), lang('More than six weeks before departure only the deposit is kept; closer to the date, part of the price. Travel insurance covers most reasons to cancel.'), lang('cancel, cancellation, refund, change')),
                            array(lang('Is my money protected?'), $agency['licence'] . '.', lang('protection, guarantee, licence, safe')),
                        )),
                        // The page header's "Write to us", where the
                        // questions end.
                        $el('div', 'd-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 rounded-4 bg-body-tertiary', lang('Write to Us'), array(
                            $heading('h2', lang('Still have a question?'), 'ptv-display h5 mb-0'),
                            $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                        )),
                    ), '', '8'), 120),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured tours (home_tours) and destinations
// (home_destinations) come with the tours, its journal (home_posts) and
// the Google reviews (google_reviews) with the standard pages; this part
// places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
