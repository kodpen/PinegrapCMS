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
                    array('width' => '96', 'height' => '96', '_attrs' => array($attr('loading', 'lazy')))),
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

// A section's eyebrow, the small line over its title.
$eyebrow = function ($text, $class = 'text-primary') use ($para) {
    return $para($text, 'pcr-eyebrow small fw-semibold text-uppercase mb-3 ' . $class);
};

// One end of the hero's trip: the desk's role, the desk, the day and the
// hour (a <time> keeps the machine-readable value).
$trip_end = function ($label, $place, $day, $time, $class = '') use ($el, $span, $attr) {
    return $el('div', $class, $label, array(
        $span($label, 'd-block small text-body-secondary', lang('Label')),
        $span($place, 'd-block fw-semibold text-body-emphasis', lang('Name')),
        $el('div', 'd-flex gap-1 small', lang('Day and time'), array(
            $span($day, '', lang('Text')),
            $el('time', 'pg-carrental-figure', lang('Time'), array(), array('text' => $time, '_attrs' => array($attr('datetime', $time)))),
        )),
    ));
};

// The home's hero, the Car Rental Hero block: the line rises on load;
// under it the trip slip (a weekend from the airport desk to the coast
// office in a mid-size car, its day rate and the way to book it) stands
// over the picture of the car, which slides in from the right; a small car
// drives along the slip's road; three facts fade in last.
$hero = function () use ($el, $heading, $para, $link, $image, $span, $icon, $attr, $container, $row, $col, $fx, $tick, $eyebrow,
                         $trip_end, $model_line, $day_rate, $class_car, $classes, $locations, $company, $photo, $query) {
    $class = $classes['compact'];
    $from = $locations['airport']['title'];
    return $el('section', 'pcr-hero py-5 overflow-hidden', lang('Car Rental Hero'), array(
        $container(array(
            $fx($el('div', 'col-lg-10 mb-5', lang('Hero Text'), array(
                $eyebrow(lang('Car rental · Izmir')),
                $heading('h1', lang('Pick up at the airport, return wherever we are'), 'pcr-display display-2 fw-bold lh-1 mb-4'),
                $para($company['tagline'], 'lead text-body-secondary mb-0'),
            )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 900)),
            $row(array(
                $col(array(
                    $fx($el('div', 'pg-carrental-slip card border-0 shadow-lg', lang('Trip Slip'), array(
                        $el('div', 'card-body p-3 p-sm-4 p-xl-5', lang('Card Body'), array(
                            $heading('h2', lang('Plan your trip'), 'h5 fw-bold mb-4'),
                            $el('div', 'pg-carrental-trip d-flex gap-3 mb-4', lang('Route'), array(
                                $trip_end(lang('Pick-up desk'), $from, lang('Friday'), '10:00'),
                                $el('div', 'pg-carrental-road', lang('Road'), array(
                                    $fx($el('div', 'pg-carrental-car text-primary', lang('Car'), array($icon('bi-car-front-fill'))),
                                        array('effect' => 'slide-right', 'trigger' => 'load', 'duration' => 1800, 'delay' => 900, 'distance' => 400, 'easing' => 'ease-in-out')),
                                ), array('_attrs' => array($attr('aria-hidden', 'true')))),
                                $trip_end(lang('Return desk'), $locations['coast']['title'], lang('Monday'), '09:00', 'text-end'),
                            )),
                            $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 py-3 border-top border-bottom mb-4', lang('Price Row'), array(
                                $el('div', '', lang('Car Class'), array(
                                    $span($class['title'], 'd-block small text-body-secondary', lang('Name')),
                                    $model_line($class_car('compact')),
                                )),
                                $day_rate($class['rate'], 'fs-2'),
                            )),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Book a Car'), '{{page:book}}?' . $query(array('class' => $class['title'], 'location' => $from)), 'btn btn-primary btn-lg'),
                                $link(lang('Our Fleet'), '{{page:fleet}}', 'btn btn-outline-primary btn-lg'),
                            )),
                        )),
                    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800, 'delay' => 400, 'distance' => 60)),
                ), '', '5', '', '12', 'order-2 order-lg-1'),
                $col(array(
                    $fx($image($photo('hero', 1200, 900), lang('A rental car on a coast road by the sea'), 'pg-carrental-photo w-100 object-fit-cover rounded-4 shadow'),
                        array('effect' => 'slide-left', 'trigger' => 'load', 'duration' => 1400, 'delay' => 100, 'distance' => 400)),
                ), '', '7', '', '12', 'order-1 order-lg-2'),
            ), '4', '', 'align-items-center g-lg-0'),
            $fx($el('ul', 'list-unstyled d-flex flex-wrap gap-3 gap-lg-5 small mt-5 mb-0', lang('Facts'), array(
                $tick('bi-calendar-check', lang('Free cancellation up to 48 hours before')),
                $tick('bi-infinity', lang('Unlimited mileage')),
                $tick('bi-airplane', lang('Airport desk open around the clock')),
            )), array('effect' => 'fade', 'trigger' => 'load', 'duration' => 800, 'delay' => 1200, 'stagger' => 120)),
        ), 'py-lg-4'),
    ));
};

// The home's class line-up, the Car Rental Class Line-up block: the
// classes from the lowest day rate to the highest on a rail that scrolls
// sideways (a region keyboard users reach), the cards driving in from the
// right one after another as the rail scrolls in.
$lineup = function () use ($el, $heading, $para, $link, $attr, $container, $fx, $eyebrow, $fade_in, $searchable, $class_tile, $classes) {
    $keys = array_keys($classes);
    usort($keys, function ($a, $b) use ($classes) {
        return $classes[$a]['rate']['USD'] - $classes[$b]['rate']['USD'];
    });
    $cards = array();
    foreach ($keys as $key) $cards[] = $class_tile($key);
    return $el('section', 'py-5', lang('Car Rental Class Line-up'), array(
        $container(array(
            $fx($el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                $el('div', 'col-lg-7', lang('Text'), array(
                    $eyebrow(lang('The fleet')),
                    $heading('h2', lang('Choose a car class'), 'pcr-display display-5 fw-bold mb-3'),
                    $para(lang('You book a class, not a model: the car at the desk is the one shown or one just like it.'), 'lead text-body-secondary mb-0'),
                )),
                $link(lang('All cars'), '{{page:fleet}}', 'btn btn-outline-primary btn-lg'),
            )), $fade_in),
            $el('div', 'pg-carrental-rail', lang('Class Rail'), array(
                $fx($el('ol', 'pg-carrental-lineup list-unstyled mb-0', lang('Car Classes'), $cards),
                    array('effect' => 'slide-left', 'trigger' => 'view', 'duration' => 700, 'stagger' => 90, 'distance' => 80)),
            ), array('_attrs' => array($attr('role', 'region'), $attr('aria-label', lang('Car classes')), $attr('tabindex', '0')))),
        )),
    ), $searchable('cr-home-classes', lang('car classes, day rate, deposit, seats, bags, gearbox')));
};

// The cover levels, the Car Rental Cover Levels block: a dark band of
// Basic, Plus and Full Cover, what each costs a day against the excess it
// leaves, the excess drawn as a meter that fills as the cards scroll in,
// and what each covers. Full Cover, the extra the booking request offers,
// is marked as the most chosen. $more adds the way to the extras page;
// $extra adds to the section's props (its search anchor).
$cover_band = function ($more = false, $extra = array()) use ($el, $heading, $para, $link, $span, $icon, $attr, $container, $fx, $eyebrow, $fade_in, $tick, $day_rate, $covers, $money) {
    $line = function ($text, $covered) use ($el, $span, $icon, $tick) {
        if ($covered) return $tick('bi-check-circle-fill', $text, 'mb-2');
        return $el('li', 'd-flex align-items-center gap-2 mb-2 text-body-secondary', lang('Item'), array(
            $icon('bi-dash-circle'),
            $span(lang('Not covered:'), 'visually-hidden', lang('Text')),
            $span($text, 'text-decoration-line-through', lang('Text')),
        ));
    };
    $cards = array();
    $n = 0;
    foreach ($covers as $c) {
        $n++;
        $head = array($heading('h3', $c['title'], 'h4 fw-bold mb-0'));
        if ($c['chosen']) $head[] = $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary text-wrap', lang('Badge'));
        $meter = array();
        if ($c['meter'] !== '') {
            $meter[] = $fx($el('div', 'progress-bar ' . $c['meter'], lang('Bar')),
                array('effect' => 'slide-right', 'trigger' => 'view', 'duration' => 1400, 'delay' => 300 + $n * 150, 'easing' => 'ease-in-out'));
        }
        $cards[] = $el('div', 'col', $c['title'], array(
            $el('article', 'card h-100 p-4 ' . ($c['chosen'] ? 'border-primary border-2 shadow-lg' : 'border-0'), lang('Card'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Title'), $head),
                $para($c['text'], 'small text-body-secondary mb-4'),
                $c['price'] !== null ? $day_rate($c['price'], 'fs-2')
                    : $span(lang('Included'), 'd-block pg-carrental-figure fs-2 fw-bold text-body-emphasis', lang('Price')),
                $el('dl', 'my-4', lang('Excess'), array(
                    $el('div', 'd-flex align-items-baseline justify-content-between gap-2 mb-2', lang('Excess'), array(
                        $el('dt', 'small fw-normal text-body-secondary', lang('Label'), array($span(lang('Excess'), '', lang('Text')))),
                        $el('dd', 'pg-carrental-figure fs-5 fw-semibold mb-0', lang('Price'), array($span($money($c['excess']), '', lang('Price')))),
                    )),
                )),
                $el('div', 'progress mb-4', lang('Progress Bar'), $meter, array('_attrs' => array($attr('aria-hidden', 'true')))),
                $el('ul', 'list-unstyled small mb-0', lang('Lines'), array(
                    $line(lang('Third-party liability'), true),
                    $line(lang('Theft and fire'), true),
                    $line(lang('Glass, tyres and mirrors'), $c['covers']['glass']),
                    $line(lang('A smaller deposit at pick-up'), $c['covers']['deposit']),
                )),
            )),
        ));
    }
    $foot = array(
        $el('div', 'd-flex align-items-center justify-content-center gap-2 mt-4 small text-body-secondary text-center', lang('Note'), array(
            $icon('bi-info-circle'),
            $span(lang('Basic Cover comes with every rental. Tick Full Cover in your booking request; ask for Plus Cover in its notes or at the desk.'), '', lang('Text')),
        )),
    );
    if ($more) {
        $foot[] = $el('div', 'text-center mt-4', lang('Buttons'), array(
            $link(lang('All extras'), '{{page:extras}}', 'btn btn-outline-primary px-4'),
        ));
    }
    return $el('section', 'py-5 bg-body text-body', lang('Car Rental Cover Levels'), array(
        $container(array_merge(array(
            $fx($el('div', 'col-lg-8 mb-5', lang('Section Header'), array(
                $eyebrow(lang('Insurance')),
                $heading('h2', lang('Choose your cover'), 'pcr-display display-5 fw-bold mb-3'),
                $para(lang('Every rental is insured. The cover sets the excess: the most you pay if the car comes back damaged.'), 'lead text-body-secondary mb-0'),
            )), $fade_in),
            $fx($el('div', 'row row-cols-1 row-cols-lg-3 g-4 align-items-stretch', lang('Cards'), $cards),
                array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => 120, 'distance' => 40)),
        ), $foot), 'py-lg-4'),
    ), array_merge(array('_attrs' => array($attr('data-bs-theme', 'dark'))), $extra));
};

// The home's sample quote, the Car Rental Sample Quote block: what a
// mid-size car for seven days with Full Cover and the weekly deal costs,
// set out as a receipt whose lines print one after another, with the
// deposit Full Cover leaves. Every figure is worked out from the class,
// the extra and the deal, in each currency.
$quote_band = function () use ($el, $heading, $para, $link, $span, $icon, $attr, $container, $row, $col, $fx, $eyebrow, $tick, $searchable,
                               $model_line, $class_car, $classes, $extras, $covers, $deals, $terms, $money, $times, $query) {
    $class = $classes['compact'];
    $deal = $deals['week'];
    $rate = $class['rate'];
    $cover = $extras['cover']['price'];
    $total = array();
    foreach ($rate as $code => $amount) $total[$code] = $amount * 7 + $cover[$code] * 7 - $amount;
    $cell = function ($tag, $children, $class, $attrs = array()) use ($el) {
        return $el($tag, $class, $tag === 'th' ? lang('Header cell') : lang('Cell'), $children, $attrs ? array('_attrs' => $attrs) : array());
    };
    $line = function ($name, $detail, $amount, $class = '') use ($el, $span, $attr, $cell) {
        $label = array($span($name, 'd-block fw-semibold', lang('Name')));
        if ($detail !== '') $label[] = $span($detail, 'd-block small text-body-secondary', lang('Text'));
        return $el('tr', '', $name, array(
            $cell('th', $label, 'fw-normal py-3', array($attr('scope', 'row'))),
            $cell('td', array($span($amount, '', lang('Price'))), trim('text-end fw-semibold py-3 ' . $class)),
        ));
    };
    return $el('section', 'py-5 bg-body-tertiary', lang('Car Rental Sample Quote'), array(
        $container(array(
            $row(array(
                $col(array(
                    $fx($el('div', '', lang('Body'), array(
                        $eyebrow(lang('Sample quote'), 'text-body-emphasis'),
                        $heading('h2', lang('What a week costs'), 'pcr-display display-5 fw-bold mb-3'),
                        $para(lang('A mid-size car for seven days with Full Cover: every line on the bill, nothing added at the desk.'), 'lead text-body-secondary mb-4'),
                        $el('ul', 'list-unstyled mb-4', lang('Facts'), array(
                            $tick('bi-infinity', lang('Unlimited mileage'), 'mb-2'),
                            $tick('bi-calendar-check', lang('Free cancellation up to 48 hours before'), 'mb-2'),
                            $tick('bi-fuel-pump', $terms['fuel']['text'], 'mb-2'),
                        )),
                        $link(lang('Book with this deal'), '{{page:book}}?' . $query(array('class' => $class['title'], 'deal' => $deal['title'])), 'btn btn-primary btn-lg'),
                    )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800)),
                ), '', '5'),
                $col(array(
                    $fx($el('div', 'pg-carrental-receipt bg-body p-4 p-lg-5 shadow', lang('Your quote'), array(
                        $el('div', 'd-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3', lang('Header'), array(
                            $span(lang('Your quote'), 'small fw-semibold text-uppercase text-primary', lang('Text')),
                            $model_line($class_car('compact'), 'small'),
                        )),
                        $el('table', 'table table-borderless align-middle mb-0', lang('Table'), array(
                            $fx($el('tbody', '', lang('Table Body'), array(
                                $line($class['title'], lang('7 days'), $money($times($rate, 7))),
                                $line($extras['cover']['title'], lang('7 days'), $money($times($cover, 7))),
                                $line(lang('Unlimited mileage'), '', lang('Included'), 'text-body-secondary'),
                                $line($deal['title'], $deal['badge'], "\xE2\x88\x92" . $money($rate), 'text-primary'),
                            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 500, 'delay' => 300, 'stagger' => 180)),
                            $el('tfoot', '', lang('Table Foot'), array(
                                $el('tr', '', lang('Total'), array(
                                    $cell('th', array($span(lang('Total'), '', lang('Text'))), 'border-top pt-3 fs-5', array($attr('scope', 'row'))),
                                    $cell('td', array($span($money($total), '', lang('Price'))), 'pg-carrental-figure border-top pt-3 text-end fs-2 fw-bold text-body-emphasis'),
                                )),
                            )),
                        )),
                        $el('div', 'd-flex align-items-start gap-3 mt-4 pt-3 border-top small', lang('Security deposit'), array(
                            $icon('bi-shield-lock', 'text-primary fs-5'),
                            $el('div', 'flex-grow-1', lang('Text'), array(
                                $el('div', 'd-flex justify-content-between gap-2 fw-semibold', lang('Price Row'), array(
                                    $span(lang('Security deposit'), '', lang('Text')),
                                    $span($money($times($class['deposit'], $covers['full']['deposit'])), 'pg-carrental-figure', lang('Price')),
                                )),
                                $para(lang('Held on your card at pick-up and released when the car is back.'), 'text-body-secondary mb-0'),
                            )),
                        )),
                    )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 800, 'distance' => 60)),
                ), '', '6', '', '12', 'offset-lg-1'),
            ), '4', '', 'align-items-center g-lg-5'),
        ), 'py-lg-4'),
    ), $searchable('cr-home-quote', lang('price, cost, total, week, quote, deposit')));
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

// Drawn on the Car Rental blocks: the hero with its trip slip, the class
// line-up, the sample quote and the cover levels; the featured cars, the
// desks, the deals, what renters say and the journal come in as they
// scroll into view.
$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Rent a clean, checked car at the airport, in the city centre or on the coast, with clear prices, free cancellation and extras you choose.'),
    'tree'             => $page(array(
        $hero(),
        $lineup(),
        $quote_band(),

        $el('section', 'py-5', lang('Featured Cars'), array(
            $container(array(
                $fx($section_header(lang('Most booked'), lang('Cars our renters choose'), lang('Our Fleet'), '{{page:fleet}}'), $fade_in),
                $widget('home_cars'),
            )),
        )),

        $cover_band(true),

        $el('section', 'py-5', lang('Locations'), array(
            $container(array(
                $fx($section_header(lang('Where to collect'), lang('Three desks, one fleet'), lang('Locations'), '{{page:locations}}'), $fade_in),
                $rise($row($location_cards, '4')),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Deals'), array(
            $container(array(
                $fx($section_header(lang('Save on your rental'), lang('This season\'s deals'), lang('All deals'), '{{page:deals}}'), $fade_in),
                $rise($row($deal_cards, '4')),
            )),
        )),

        // What renters say: sample words for the company to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $fx($section_header(lang('Our renters'), lang('What renters say')), $fade_in),
                $rise($row($testimonial_cards, '4')),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 border-top', lang('Road Trip Journal'), array(
            $container(array(
                $fx($section_header(lang('Ideas for the road'), lang('Road Trip Journal'), lang('All articles'), '{{page:blog}}'), $fade_in),
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
        $container(array($rise($row($cols, '4', '', 'g-lg-5 align-items-center'), 150))),
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
        $cover_band(false, $searchable('cr-cover', lang('insurance, excess, damage, theft, glass, tyres, deposit'))),
        $el('section', 'py-5', lang('Extras'), array(
            $container(array(
                $fx($section_header(lang('Add to your rental'), lang('Extras for the road')), $fade_in),
                $rise($row($extra_cards, '4')),
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
                $rise($row($deal_cards, '4')),
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
                $rise($row($term_items, '4', '', 'g-lg-5'), 70),
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
                $fx($section_header(lang('How we work'), lang('Our promises')), $fade_in),
                $rise($row(array(
                    $value('bi-tag', lang('Clear prices'), lang('The price on your confirmation is the price you pay, with nothing added at the desk.')),
                    $value('bi-stars', lang('Clean cars'), lang('Every car cleaned inside and out and checked between rentals.')),
                    $value('bi-clock-history', lang('We wait for you'), lang('A late flight is not your fault: the airport desk follows it and waits.')),
                    $value('bi-telephone', lang('A real person'), lang('Call any time of day and talk to someone at one of our desks.')),
                ), '4')),
            )),
        ), $searchable('values', lang('trust, price, cleaning, service'))),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $fx($section_header(lang('The team'), lang('The people you meet')), $fade_in),
                $rise($row($team_cards, '4')),
            )),
        ), $searchable('team', lang('staff, desk, fleet, business rentals'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $fx($section_header(lang('Our renters'), lang('What renters say')), $fade_in),
                $rise($row($testimonial_cards, '4')),
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
                        ), array('id' => 'cr-faq', '_fx' => $fade_in)),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('booking_band'),
    )),
);

return $public_pages;
