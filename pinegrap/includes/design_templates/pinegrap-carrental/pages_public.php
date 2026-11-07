<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Rental", its public pages: home, the pick-up desks,
 * the extras, the deals, the rental terms, about and the questions renters
 * ask. Included after parts.php in the same scope (its builders and the
 * company's data); returns the list of pages. The widgets the home places
 * (home_cars, home_posts, google_reviews) are built by fleet.php and
 * standard.php; booking_band is the shared component of $booking_band.
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

// ── Pieces the pages share ──────────────────────────────────────────────

// The company's figures in a row.
$figure_row = function ($class = '') use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pcr-display display-6 fw-bold text-primary mb-1'),
                $para($f['label'], 'small text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $el('div', $class, lang('Figures'), array($row($cols, '4')));
};

// A person of the team: the portrait, the name, the role and a line.
$team_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $team) {
    $t = $team[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm text-center', lang('Team Member'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $image($portrait($t['name'], 192), $t['name'], 'rounded-circle mb-3',
                    array('_attrs' => array($attr('width', '96'), $attr('height', '96'), $attr('loading', 'lazy')))),
                $heading('h3', $t['name'], 'h5 fw-semibold mb-1'),
                $para($t['role'], 'small fw-semibold text-primary mb-2'),
                $para($t['bio'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// One of the company's principles: the icon, the title and a line.
$value = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('div', 'h-100', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'h5 fw-semibold mb-2'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// How renting goes, from the request to the return.
$rental_steps = function () use ($el, $step) {
    return $el('ol', 'list-unstyled mb-0', lang('List'), array(
        $step(1, lang('Send a booking request'), lang('Choose the class, the desk, the dates and the extras; no payment is taken online.')),
        $step(2, lang('Get the confirmation'), lang('We confirm the car and the full price within the hour, by e-mail and on your booking\'s page.')),
        $step(3, lang('Collect the car'), lang('Bring your licence, an ID and a credit card for the deposit; the car is clean, full and waiting.')),
        $step(4, lang('Return it to any desk'), lang('Bring it back full to any of our desks; the deposit is released the same day.')),
    ));
};

// The quick ways in on the home: book, see the fleet, and a chip for each
// desk that opens the booking request with it chosen (?location=).
$booking_box = function () use ($el, $link, $icon, $span, $para, $query, $locations) {
    $chips = array();
    foreach ($locations as $l) {
        $chips[] = $el('a', 'btn btn-sm btn-light rounded-pill d-inline-flex align-items-center gap-1', $l['title'], array(
            $icon($l['icon'], 'text-primary'),
            $span($l['title'], '', lang('Link Text')),
        ), array('href' => '{{page:book}}?' . $query(array('location' => $l['title']))));
    }
    return $el('div', 'card border-0 shadow p-3 p-lg-4', lang('Search Box'), array(
        $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Buttons'), array(
            $link(lang('Book a Car'), '{{page:book}}', 'btn btn-primary px-4'),
            $link(lang('Our Fleet'), '{{page:fleet}}', 'btn btn-outline-primary px-4'),
            $link(lang('See the Deals'), '{{page:deals}}', 'btn btn-link link-body-emphasis'),
        )),
        $para(lang('Pick up at'), 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'd-flex flex-wrap gap-2', lang('Locations'), $chips),
    ));
};

// A rental term: the icon, its title and its words. A search section of
// its own, so "deposit" opens the terms page there.
$term_item = function ($key) use ($el, $heading, $para, $icon_tile, $col, $searchable, $terms) {
    $t = $terms[$key];
    return $col(array(
        $el('div', 'd-flex gap-3 h-100', $t['title'], array(
            $icon_tile($t['icon'], 'flex-shrink-0 align-self-start'),
            $el('div', '', lang('Text'), array(
                $heading('h2', $t['title'], 'h5 fw-semibold mb-2'),
                $para($t['text'], 'text-body-secondary mb-0'),
            )),
        ), $searchable('cr-term-' . $key)),
    ), '6');
};

$phone_href = 'tel:' . preg_replace('/[^0-9+]/', '', $company['phone']);

$class_tiles = array();
foreach (array_keys($classes) as $key) $class_tiles[] = $class_tile($key);
$location_cards = array();
foreach (array_keys($locations) as $key) $location_cards[] = $location_card($key);
$extra_cards = array();
foreach (array_keys($extras) as $key) $extra_cards[] = $extra_card($key);
$deal_cards = array();
foreach (array_keys($deals) as $key) $deal_cards[] = $deal_card($key);
$testimonial_cards = array();
foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
$team_cards = array();
foreach (array_keys($team) as $n) $team_cards[] = $team_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Rent a clean, checked car at the airport, in the city centre or on the coast, with clear prices, free cancellation and extras you choose.'),
    'tree'             => $page(array(
        // The way in: the words and the quick ways to book beside a photo
        // of a car on the coast road.
        $el('section', 'pcr-hero position-relative py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Car rental · Izmir'), 'pcr-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Pick up at the airport, return wherever we are'), 'pcr-display display-4 fw-bold mb-3'),
                        $para($company['tagline'], 'lead text-body-secondary mb-4'),
                        $booking_box(),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden shadow-sm', lang('Picture Frame'), array(
                            $image($photo('hero', 1200, 900), lang('A rental car on a coast road by the sea'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Car Classes'), array(
            $container(array(
                $section_header(lang('The fleet'), lang('Choose a car class'), lang('All cars'), '{{page:fleet}}'),
                $row($class_tiles, '3', 'center'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Featured Cars'), array(
            $container(array(
                $section_header(lang('Most booked'), lang('Cars our renters choose'), lang('Our Fleet'), '{{page:fleet}}'),
                $widget('home_cars'),
            )),
        )),

        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Renting with us'), 'pcr-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('From the request to the return'), 'h3 fw-semibold mb-3'),
                        $para(lang('No account needed to begin: the booking form opens one for you, so you can follow your booking and write to us afterwards.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book a Car'), '{{page:book}}', 'btn btn-primary px-4'),
                            $link(lang('Rental Terms'), '{{page:terms}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '', '5'),
                    $col(array($rental_steps()), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Locations'), array(
            $container(array(
                $section_header(lang('Where to collect'), lang('Three desks, one fleet'), lang('Locations'), '{{page:locations}}'),
                $row($location_cards, '4'),
            )),
        )),

        $el('section', 'py-5', lang('Deals'), array(
            $container(array(
                $section_header(lang('Save on your rental'), lang('This season\'s deals'), lang('All deals'), '{{page:deals}}'),
                $row($deal_cards, '4'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Extras'), array(
            $container(array(
                $section_header(lang('Add to your rental'), lang('Extras for the road'), lang('All extras'), '{{page:extras}}'),
                $row($extra_cards, '4'),
            )),
        )),

        // What renters say: sample words for the company to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our renters'), lang('What renters say')),
                $row($testimonial_cards, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 border-top', lang('Road Trip Journal'), array(
            $container(array(
                $section_header(lang('Ideas for the road'), lang('Road Trip Journal'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('booking_band'),
    )),
);

// ── Locations ───────────────────────────────────────────────────────────

// Each desk in a section of its own, with the anchor the footer links
// (#cr-loc-<key>) and the words a renter may search for it by.
$location_sections = array();
$location_topics = array(
    'airport' => lang('airport, arrivals, flight, late, 24 hours, key box'),
    'city'    => lang('city centre, ferry, office, parking, hotel delivery'),
    'coast'   => lang('coast, marina, holiday, resort, villa delivery'),
);
$desk_n = 0;
foreach ($locations as $key => $l) {
    $photo_col = $col(array(
        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
            $image($photo('desk-' . $key, 1200, 900), $l['title'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
        )),
    ), '', '5');
    $text_col = $col(array(
        $para($l['kind'], 'pcr-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $l['title'], 'h3 fw-semibold mb-2'),
        $para($l['address'], 'fw-semibold mb-3'),
        $para($l['text'], 'text-body-secondary mb-3'),
        $row(array(
            $col(array(
                $heading('h3', lang('Opening hours'), 'h6 text-uppercase small fw-bold mb-2'),
                $hours($key, 'row small mb-0'),
            ), '6'),
            $col(array(
                $heading('h3', lang('At this desk'), 'h6 text-uppercase small fw-bold mb-2'),
                $check_list($l['points'], 'list-unstyled small mb-0'),
            ), '6'),
        ), '4', '', 'mb-4'),
        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
            $link(lang('Book from here'), '{{page:book}}?' . $query(array('location' => $l['title'])), 'btn btn-primary px-4'),
            $link(lang('Get directions'), 'https://www.google.com/maps/search/?' . $query(array('api' => '1', 'query' => $l['address'])),
                'btn btn-outline-secondary px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
        )),
    ), '', '7');
    // The photo sits on the other side for every second desk on wide screens.
    $cols = ($desk_n % 2 === 0) ? array($photo_col, $text_col) : array($text_col, $photo_col);
    $location_sections[] = $el('section', 'py-5' . ($desk_n % 2 === 1 ? ' bg-body-tertiary' : ''), $l['title'], array(
        $container(array($row($cols, '4', '', 'g-lg-5 align-items-center'))),
    ), $searchable('cr-loc-' . $key, $location_topics[$key]));
    $desk_n++;
}

$public_pages[] = array(
    'key'              => 'locations',
    'name'             => lang('locations'),
    'folder'           => 'public',
    'title'            => lang('Locations'),
    'meta_description' => lang('Our desks at the airport, in the city centre and on the coast: addresses, opening hours, late returns and delivery to your hotel.'),
    'tree'             => $page(array_merge(array(
        $page_header(lang('Locations'), lang('Collect a car at any of our three desks and return it to whichever suits you, at no charge.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Book a Car'), '{{page:book}}', 'btn btn-primary px-4'),
                $link($company['phone'], $phone_href, 'btn btn-outline-secondary px-4'),
            )),
        )),
    ), $location_sections, array(
        $shared('booking_band'),
    ))),
);

// ── Extras ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'extras',
    'name'             => lang('extras'),
    'folder'           => 'public',
    'title'            => lang('Extras'),
    'meta_description' => lang('Full cover, an additional driver, child seats, navigation, mobile Wi-Fi and delivery: what each extra gives and what it costs.'),
    'tree'             => $page(array(
        $page_header(lang('Extras'), lang('Tick what you need in your booking request; we fit it before you arrive.')),
        $el('section', 'py-5', lang('Extras'), array(
            $container(array(
                $row($extra_cards, '4'),
            )),
        ), $searchable('cr-extras', lang('insurance, cover, child seat, booster, GPS, sat nav, Wi-Fi, second driver, delivery'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Good to know'), 'pcr-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('How extras work'), 'h3 fw-semibold mb-3'),
                        $check_list(array(
                            lang('Prices are per day unless they say per rental'),
                            lang('Extras are confirmed with your booking and paid at the desk'),
                            lang('Child seats are fitted before you arrive; tell us the child\'s age'),
                            lang('An extra can be added at the desk if one is free'),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm', lang('Call to Action'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Ready to choose?'), 'h5 fw-semibold mb-2'),
                                $para(lang('The booking request lists every extra as a box to tick.'), 'small text-body-secondary mb-3'),
                                $link(lang('Book a Car'), '{{page:book}}', 'btn btn-primary w-100'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $shared('booking_band'),
    )),
);

// ── Deals ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'deals',
    'name'             => lang('deals'),
    'folder'           => 'public',
    'title'            => lang('Deals'),
    'meta_description' => lang('Our car rental deals: a free day on weekly rentals, a lower rate for early bookings and a free extra driver at the weekend.'),
    'tree'             => $page(array(
        $page_header(lang('Deals'), lang('Choose a deal in your booking request; we take it off the price we confirm.')),
        $el('section', 'py-5', lang('Deals'), array(
            $container(array(
                $row($deal_cards, '4'),
                $para(lang('One deal per booking. Deals depend on the cars free on your dates; the confirmation states the price you pay.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('cr-deals', lang('discount, offer, promotion, weekly, weekend, early booking'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Business Rentals'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('For companies'), 'pcr-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Renting for a month or longer?'), 'h3 fw-semibold mb-2'),
                        $para(lang('Monthly rentals, long-term leases and company accounts have their own prices.'), 'text-body-secondary mb-0'),
                    ), '', '8'),
                    $col(array(
                        $link(lang('Business Rentals'), '{{page:business}}', 'btn btn-outline-primary px-4'),
                    ), '', '4', '', '12', 'text-lg-end'),
                ), '4', '', 'align-items-center'),
            )),
        )),
        $shared('booking_band'),
    )),
);

// ── Rental terms ────────────────────────────────────────────────────────

$term_items = array();
foreach (array_keys($terms) as $key) $term_items[] = $term_item($key);

$public_pages[] = array(
    'key'              => 'terms',
    'name'             => lang('rental-terms'),
    'folder'           => 'public',
    'title'            => lang('Rental Terms'),
    'meta_description' => lang('What renting with us asks of you: driver age and licence, the deposit, fuel, mileage, cancellation and taking the car abroad.'),
    'tree'             => $page(array(
        $page_header(lang('Rental Terms'), lang('The rules of every rental in plain words; the contract you sign at the desk says the same.')),
        $el('section', 'py-5', lang('Terms'), array(
            $container(array(
                $row($term_items, '5'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('What to Bring'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('At the desk'), 'pcr-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What to bring'), 'h3 fw-semibold mb-3'),
                        $check_list(array(
                            lang('The main driver\'s full driving licence'),
                            lang('A passport or ID card'),
                            lang('A credit card in the main driver\'s name for the deposit'),
                            lang('Each additional driver\'s licence'),
                            lang('Your booking confirmation, on your phone is fine'),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm', lang('Questions'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Still have a question?'), 'h5 fw-semibold mb-2'),
                                $para(lang('Most answers are in our questions and answers; for anything else, write to us.'), 'small text-body-secondary mb-3'),
                                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                    $link(lang('Questions and Answers'), '{{page:faq}}', 'btn btn-sm btn-outline-primary'),
                                    $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                                )),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('cr-bring', lang('documents, licence, passport, credit card, ID'))),
        $shared('booking_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('A local car rental company with three desks: how we look after the fleet, the people you meet and what every renter can expect.'),
    'tree'             => $page(array(
        $page_header(lang('About'), $company['tagline']),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('Rental cars lined up and ready at the city office'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Our story'), 'pcr-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('A local company, open when you land'), 'h3 fw-semibold mb-3'),
                        $para($company['bio'], 'text-body-secondary'),
                        $para(lang('We keep a fleet small enough to know every car: each one is cleaned, checked and filled between rentals by our own team.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('story, company, local, fleet'))),
        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('How we work'), lang('Our promises')),
                $row(array(
                    $value('bi-tag', lang('Clear prices'), lang('The price on your confirmation is the price you pay, with nothing added at the desk.')),
                    $value('bi-stars', lang('Clean cars'), lang('Every car cleaned inside and out and checked between rentals.')),
                    $value('bi-clock-history', lang('We wait for you'), lang('A late flight is not your fault: the airport desk follows it and waits.')),
                    $value('bi-telephone', lang('A real person'), lang('Call any time of day and talk to someone at one of our desks.')),
                ), '4'),
            )),
        ), $searchable('values', lang('trust, price, cleaning, service'))),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The team'), lang('The people you meet')),
                $row($team_cards, '4'),
            )),
        ), $searchable('team', lang('staff, desk, fleet, business rentals'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our renters'), lang('What renters say')),
                $row($testimonial_cards, '4'),
            )),
        )),
        $shared('booking_band'),
    )),
);

// ── FAQ ─────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Questions and Answers'),
    'meta_description' => lang('Answers to what renters ask: booking, documents, the deposit, insurance, fuel, late flights, cancellations and returns.'),
    'tree'             => $page(array(
        $page_header(lang('Questions and Answers'), lang('What renters ask before they collect a car. Anything else? Write to us and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('How do I book a car?'), lang('Send a booking request with the class, the desk, the dates and the extras. We confirm the car and the full price within the hour; nothing is paid online.'), lang('book, reserve, request, online')),
                            $faq(2, lang('Will I get the exact car shown?'), lang('We promise the class: the car shown or a similar one with the same seats, gearbox and boot. Ask in your request if you need a particular model.'), lang('model, or similar, class')),
                            $faq(3, lang('What do I need to collect the car?'), lang('Your full driving licence, a passport or ID card and a credit card in the main driver\'s name for the deposit.'), lang('documents, licence, credit card, ID')),
                            $faq(4, lang('How much is the security deposit?'), lang('It depends on the class and is shown on the car\'s page. With Full Cover the deposit is smaller; it is released when the car comes back.'), lang('deposit, card, hold')),
                            $faq(5, lang('What if my flight is late?'), lang('Give your flight number in the request. The airport desk follows it and waits for you, at no charge.'), lang('flight, late, delay, airport')),
                            $faq(6, lang('Can I return the car to another desk?'), lang('Yes, to any of our three desks at no charge. Tell us in the request so we expect you there.'), lang('one way, return, drop off')),
                            $faq(7, lang('How do I cancel?'), lang('Write to us on your booking\'s page. Cancellation is free up to 48 hours before pick-up.'), lang('cancel, change, refund')),
                            $faq(8, lang('How do I follow my booking?'), lang('The booking form opens an account for you. Your booking\'s page shows its status, and you can write to us there.'), lang('status, follow, account, message')),
                        ), array('id' => 'cr-faq')),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('booking_band'),
    )),
);

return $public_pages;
