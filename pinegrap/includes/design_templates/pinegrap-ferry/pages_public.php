<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ferry", its public pages: home, the routes and one page
 * per route, the ports, the timetables, the fares, what is on board,
 * travelling with us, about and the questions page. Included after
 * parts.php in the same scope (its builders and the line's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($line, $ports, $routes, $seasons,
 * $fare_kinds, $fares, $onboard, $travel_tips, $why_us, $figures,
 * $testimonials), so a change made there reaches every page here.
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
        $para($eyebrow, 'pfy-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pfy-display h2 mb-3'),
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
                    $heading('h2', $title, 'pfy-display h4 mb-1'),
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

// The ways to book and to read the notices of one route, as buttons.
$route_buttons = function ($key) use ($el, $link, $routes, $query) {
    $title = $routes[$key]['title'];
    return $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
        $link(lang('Request a Booking'), '{{page:booking_request}}?' . $query(array('route' => $title)), 'btn btn-primary rounded-pill px-4'),
        $link(lang('Travel Notices for This Route'), '{{page:notices}}?' . $query(array('route' => $title)), 'btn btn-outline-secondary rounded-pill px-4'),
    ));
};

// The cards of the shared data, in their order.
$route_cols = array();
foreach (array_keys($routes) as $key) $route_cols[] = $route_card($key);
$port_cols = array();
foreach (array_keys($ports) as $key) $port_cols[] = $port_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages = array();
$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Ferries between the mainland and the islands for foot passengers, cars and campervans, with timetables, fares and travel notices.'),
    'tree'             => $page(array(
        // The way in: the line in a sentence, the two ways on and a
        // picture of a ferry in the bay.
        $el('section', 'pfy-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Ferries to the islands'), 'pfy-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Across the bay, every day of the year'), 'pfy-display display-3 mb-3'),
                        $para($line['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Request a Booking'), '{{page:booking_request}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('See the Timetables'), '{{page:timetables}}', 'btn btn-outline-secondary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A white car ferry crossing a calm blue bay towards a green island'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $figures_band(),

        $el('section', 'py-5', lang('Routes'), array(
            $container(array(
                $section_header(lang('Where we sail'), lang('Our routes'), lang('All Routes'), '{{page:routes}}'),
                $row($route_cols, '4'),
            )),
        ), $searchable('fy-home-routes', lang('route, crossing, ferry, island, mainland, port, harbour'))),

        // The latest travel notices, read from the collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Travel Notices'), array(
            $container(array(
                $section_header(lang('Before you travel'), lang('Latest travel notices'), lang('All Travel Notices'), '{{page:notices}}'),
                $widget('home_notices'),
            )),
        )),

        $feature_section('fy-home-onboard', lang('On Board'),
            $section_header(lang('On the crossing'), lang('On board our ships'), lang('All Services on Board'), '{{page:onboard}}'),
            array_slice($onboard, 0, 4), '3', lang('cafe, restaurant, lounge, cabin, deck, food, drinks')),

        $feature_section('fy-home-why', lang('Why Travel with Us'),
            $section_header(lang('Our promise'), lang('Why travel with us'), lang('About Us'), '{{page:about}}'),
            $why_us, '3', lang('safe, reliable, every day, vehicles, notices'), 'py-5 bg-body-tertiary'),

        // What travellers say: sample words for the line to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our travellers'), lang('What travellers say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from the line'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Routes ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'routes',
    'name'             => lang('ferry-routes'),
    'folder'           => 'public',
    'title'            => lang('Routes'),
    'meta_description' => lang('Four ferry routes between the mainland and the islands: crossing times, ships and fares for foot passengers and vehicles.'),
    'tree'             => $page(array(
        $page_header(lang('Routes'), lang('Four crossings link the two mainland ports with Gull Island and Cedar Island.')),
        $el('section', 'py-5', lang('Routes'), array(
            $container(array(
                $row($route_cols, '4'),
            )),
        ), $searchable('fy-routes', lang('route, crossing, ferry, island, mainland, duration, ship'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Fares'), array(
            $container(array(
                $section_header(lang('What a crossing costs'), lang('Fares by route'), lang('All Fares'), '{{page:fares}}'),
                $fare_table(),
            )),
        ), $searchable('fy-routes-fares', lang('price, fare, ticket, cost, car, passenger'))),
        $ask_band(lang('Not sure which route suits your trip?'), lang('Tell us where you are going and with what, and the booking office will suggest the best crossing.')),
    )),
);

// One page per route: what the crossing is like, its facts, its sailings
// in each season, its fares and its two ports, and the ways to the booking
// request and to the route's travel notices with the route chosen. Its
// address is a translated slug, so a Turkish site reads Turkish addresses.
$route_slugs = array(
    'bay_gull'    => lang('pine-bay-gull-island'),
    'bay_cedar'   => lang('pine-bay-cedar-island'),
    'north_cedar' => lang('north-harbour-cedar-island'),
    'gull_cedar'  => lang('gull-island-cedar-island'),
);
foreach ($routes as $key => $r) {
    $public_pages[] = array(
        'key'              => 'route_' . $key,
        'name'             => $route_slugs[$key],
        'folder'           => 'public',
        'title'            => $r['title'],
        'meta_description' => $r['summary'],
        'tree'             => $page(array(
            $page_header($r['title'], $r['summary'], array($route_facts($key, 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mt-3 mb-0'))),
            $el('section', 'py-5', $r['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($r['icon'], 'mb-3'),
                            $para($r['text'], 'lead mb-4'),
                            $heading('h2', lang('On this crossing'), 'pfy-display h4 mb-3'),
                            $check_list($r['points'], 'list-unstyled mb-4'),
                            $route_buttons($key),
                        ), '', '6'),
                        $col(array($framed($photo('route-' . $key, 1200, 900), $r['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('fy-route-' . $key)),
            $el('section', 'py-5 bg-body-tertiary', lang('Timetable'), array(
                $container(array(
                    $section_header(lang('When we sail'), lang('Sailings by season'), lang('All Timetables'), '{{page:timetables}}'),
                    $timetable($key),
                    $para($line['checkin'] . '.', 'small text-body-secondary mb-0'),
                )),
            ), $searchable('fy-route-' . $key . '-timetable', lang('timetable, schedule, departure, sailing, time, summer, winter'))),
            $el('section', 'py-5', lang('Fares'), array(
                $container(array(
                    $section_header(lang('What a crossing costs'), lang('Fares on this route'), lang('All Fares'), '{{page:fares}}'),
                    $fare_table($key),
                    $para($fare_note, 'small text-body-secondary mt-3 mb-0'),
                )),
            ), $searchable('fy-route-' . $key . '-fares', lang('price, fare, ticket, cost, car, passenger, cabin'))),
            $el('section', 'py-5 bg-body-tertiary', lang('Ports'), array(
                $container(array(
                    $section_header(lang('Where to board'), lang('The ports of this route'), lang('All Ports'), '{{page:ports}}'),
                    $row(array($port_card($r['from']), $port_card($r['to'])), '4'),
                )),
            )),
        )),
    );
}

// ── Ports ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'ports',
    'name'             => lang('ports'),
    'folder'           => 'public',
    'title'            => lang('Ports'),
    'meta_description' => lang('Where to board: the address, parking, buses and services at each of our four ports, with a link to the map.'),
    'tree'             => $page(array(
        $page_header(lang('Ports'), $line['checkin'] . '.'),
        $el('section', 'py-5', lang('Ports'), array(
            $container(array(
                $row($port_cols, '4'),
            )),
        ), $searchable('fy-ports', lang('port, terminal, harbour, quay, address, parking, bus, taxi, map, check-in'))),
        $ask_band(lang('Coming by bus or train?'), lang('Ask us how to connect with your sailing and we will help you plan the journey.')),
    )),
);

// ── Timetables ──────────────────────────────────────────────────────────
// Timetables as content: one table per route with the summer and winter
// departures from each port. The line writes the season dates each year.

$timetable_blocks = array();
foreach (array_keys($routes) as $key) $timetable_blocks[] = $timetable($key);

$public_pages[] = array(
    'key'              => 'timetables',
    'name'             => lang('timetables'),
    'folder'           => 'public',
    'title'            => lang('Timetables'),
    'meta_description' => lang('Summer and winter departure times of every ferry route, from each port.'),
    'tree'             => $page(array(
        $page_header(lang('Timetables'), lang('Departure times from each port, in summer and in winter. Check the travel notices before you set off.')),
        $el('section', 'py-5', lang('Timetables'), array(
            $container(array_merge($timetable_blocks, array(
                $para($line['checkin'] . '.', 'small text-body-secondary mb-0'),
            ))),
        ), $searchable('fy-timetables', lang('timetable, schedule, departure, sailing, time, summer, winter, first, last'))),
        $ask_band(lang('Is your sailing running today?'), lang('Every change and disruption is on the travel notices; the booking office answers the phone too.')),
    )),
);

// ── Fares ───────────────────────────────────────────────────────────────
// Fares as content: the table by route and what every fare includes. A
// booking request asks for a place; nothing is charged online.

$public_pages[] = array(
    'key'              => 'fares',
    'name'             => lang('fares'),
    'folder'           => 'public',
    'title'            => lang('Fares'),
    'meta_description' => lang('Single crossing fares for adults, children, seniors, bicycles, motorcycles, cars, vans and cabins on every route.'),
    'tree'             => $page(array(
        $page_header(lang('Fares'), $fare_note),
        $el('section', 'py-5', lang('Fares'), array(
            $container(array(
                $section_header(lang('What a crossing costs'), lang('Fares by route')),
                $fare_table(),
            )),
        ), $searchable('fy-fares', lang('price, fare, ticket, cost, adult, child, senior, car, van, campervan, motorcycle, bicycle, cabin'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Every Fare Includes'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Every fare includes'), 'pfy-display h3 mb-3'),
                        $check_list($fare_includes, 'list-unstyled mb-0'),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Travelling as a group?'), 'pfy-display h3 mb-3'),
                        $para(lang('Schools, coach tours, clubs and companies travel on a group fare. Tell us your route, your day and how many you are, and the group desk sends you an offer.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Group Enquiry'), '{{page:group_enquiry}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Request a Booking'), '{{page:booking_request}}', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('fy-fares-includes', lang('included, luggage, wifi, change, group, school, coach'))),
    )),
);

// ── On board ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'onboard',
    'name'             => lang('on-board'),
    'folder'           => 'public',
    'title'            => lang('On Board'),
    'meta_description' => lang('Café, restaurant, lounges, cabins, open decks, a children\'s corner, pets and help for every passenger on our ships.'),
    'tree'             => $page(array(
        $page_header(lang('On Board'), lang('Make the most of the crossing, whether it takes fifty minutes or two hours.')),
        $feature_section('fy-onboard', lang('On Board'), null, $onboard, '3',
            lang('cafe, restaurant, food, drinks, lounge, seats, cabin, deck, children, pets, dogs, wheelchair, accessible')),
        $story_band('fy-onboard-star', lang('The long crossing'), lang('Two hours on Island Star'),
            array($onboard[1]['text'], $onboard[3]['text']),
            $photo('onboard-star', 1200, 900), lang('Passengers at tables by the windows of a ferry restaurant with the sea outside'), true, array(
                $link(lang('Pine Bay – Cedar Island'), '{{page:route_bay_cedar}}', 'btn btn-outline-secondary rounded-pill px-4'),
            ), lang('restaurant, meal, cabin, rest, long crossing'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Travelling with us ──────────────────────────────────────────────────
// How a crossing goes, step by step, and what to know before the day.

$travel_steps = array(
    array(lang('Ask for a booking'), lang('Send a booking request with your route, your day and who is travelling. The booking office confirms your place within a day.')),
    array(lang('Check the notices'), lang('On the day, look at the travel notices for your route: weather and works can change a sailing.')),
    array(lang('Check in at the port'), $line['checkin'] . '.'),
    array(lang('Board and enjoy the crossing'), lang('Drive or walk on when the crew calls your lane, then head up to the café or the open deck.')),
);
$travel_step_items = array();
foreach ($travel_steps as $n => $s) $travel_step_items[] = $step($n + 1, $s[0], $s[1]);

$public_pages[] = array(
    'key'              => 'travel',
    'name'             => lang('travelling-with-us'),
    'folder'           => 'public',
    'title'            => lang('Travelling with Us'),
    'meta_description' => lang('Check-in times, tickets and ID, luggage, vehicles, pets and help on board: what to know before your crossing.'),
    'tree'             => $page(array(
        $page_header(lang('Travelling with Us'), lang('What to know before the day of your crossing.')),
        $el('section', 'py-5', lang('Steps'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a crossing goes'), 'pfy-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $travel_step_items),
                    ), '', '6'),
                    $col(array($framed($photo('travel-boarding', 1200, 900), lang('Cars driving up the ramp onto a ferry\'s car deck'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('fy-travel-steps', lang('how to, booking, check-in, boarding, ramp'))),
        $feature_section('fy-travel-tips', lang('Before You Travel'),
            $section_header(lang('Good to know'), lang('Before you travel')),
            $travel_tips, '4', lang('check-in, ticket, id, passport, luggage, vehicle, height, trailer, pets, wheelchair, assistance'), 'py-5 bg-body-tertiary'),
        $ask_band(lang('A question about your journey?'), lang('Write to the booking office, or call us: we are glad to help.')),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('From one wooden boat carrying the islanders\' post to three ships sailing four routes across the bay.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $line['tagline'] . '.'),
        $story_band('fy-our-story', lang('Our story'), lang('From one wooden boat'),
            array($line['story'], $line['opening'] . '.'),
            $photo('about-quay', 1200, 900), lang('An old wooden passenger boat moored beside a modern car ferry at a stone quay'), false, array(),
            lang('history, company, fleet, ships, who we are')),
        $figures_band(),
        $feature_section('fy-about-why', lang('Why Travel with Us'),
            $section_header(lang('Our promise'), lang('Why travel with us')),
            $why_us, '3', lang('safe, reliable, every day, vehicles, notices')),
        $ask_band(lang('Planning a trip to the islands?'), lang('Write to us and the booking office will help you choose the route and the sailing.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: bookings and fares, and travelling on board. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pfy-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about booking requests, fares, check-in, vehicles, pets, cancelled sailings and travelling on board.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Bookings, fares and life on board. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('fy-faq-bookings', lang('Bookings and fares'), array(
                            array(lang('How do I book a crossing?'), lang('Send a booking request with your route, your day and who is travelling. It opens an account for you, where you follow the request and read our answer.'), lang('book, reserve, ticket, reservation')),
                            array(lang('Is my place confirmed when I send the request?'), lang('Not yet: the booking office checks the room on board for your sailing and vehicle and confirms the booking, usually within a day.'), lang('confirmation, availability')),
                            array(lang('Do foot passengers need to book?'), lang('Not on the short routes, where there is nearly always room; on the long crossing and with a vehicle in summer, we recommend it.'), lang('walk on, foot passenger, without booking')),
                            array(lang('How do I pay?'), lang('At the booking office or the port\'s ticket kiosk before you board, by card or in cash. Nothing is paid on this site.'), lang('payment, card, cash, ticket office')),
                        )),
                        $faq_group('fy-faq-travel', lang('On the day'), array(
                            array(lang('What if the weather stops my sailing?'), lang('The captain decides before each sailing. If it is held, your booking moves to the next sailing with room and we let you know; the travel notices say it first.'), lang('cancelled, delayed, weather, wind, storm')),
                            array(lang('Can I take my dog?'), lang('Yes: dogs on a lead travel free on the open decks and in the pet area, and may stay in the car on the long crossing.'), lang('pets, dog, cat, animal')),
                            array(lang('My vehicle is tall or tows a trailer. Can it travel?'), lang('Yes, tell us its length and height when you book; vehicles over three metres high take the lorry deck.'), lang('height, trailer, caravan, campervan, lorry')),
                            array(lang('Can I get help boarding?'), lang('Yes: tell us when you book and the crew meets you at the ramp, with a wheelchair if you need one.'), lang('wheelchair, assistance, accessible, disabled')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its latest travel notices (home_notices) come
// with the notices, its news (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
