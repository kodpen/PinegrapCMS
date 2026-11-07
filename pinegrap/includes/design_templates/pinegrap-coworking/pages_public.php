<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Coworking", its public pages: home, the membership
 * plans and prices, the amenities, about, the questions and getting here.
 * Included after parts.php in the same scope (its builders and the
 * space's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($hub, $opening_hours, $kinds,
 * $spaces, $plans, $amenities, $figures, $testimonials), so a change made
 * there reaches every page here. No page here links a shop page: the
 * template stands on a site without the shop, and a plan is started
 * through the tour request.
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

// A picture in a frame of the given ratio, loaded lazily unless it is the
// first thing a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-3 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pcw-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pcw-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the values and the ways to the
// space.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pcw-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The space in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pcw-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The staffed hours as a short list, for the location and plans pages.
$hours_list = function ($class = 'list-unstyled mb-0') use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'pcw-price fw-semibold', lang('Hours')),
        ));
    }
    return $el('ul', $class, lang('Opening Hours'), $items);
};

// The spaces of one kind as a price list: the name, the seats and the
// price on each line. The plans page lists the offices and the meeting
// rooms this way.
$price_list = function ($kind) use ($el, $span, $spaces, $space_price) {
    $items = array();
    foreach ($spaces as $key => $s) {
        if ($s['kind'] !== $kind) continue;
        $items[] = $el('li', 'list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2 py-3', $s['name'], array(
            $el('div', '', lang('Space Name'), array(
                $span($s['name'], 'd-block fw-semibold', lang('Name')),
                $span(sprintf(lang('Up to %d people'), $s['seats']), 'd-block small text-body-secondary', lang('Seats')),
            )),
            $span($space_price($key), 'pcw-price fw-bold text-primary', lang('Price')),
        ));
    }
    return $el('ul', 'list-group list-group-flush rounded-3 border', lang('Price List'), $items);
};

// The cards of the shared data, in their order.
$kind_cards_row = array();
foreach (array_keys($kinds) as $key) $kind_cards_row[] = $kind_card($key);
$plan_cards_row = array();
foreach (array_keys($plans) as $key) $plan_cards_row[] = $plan_card($key);
$amenity_cols = array();
foreach (array_keys($amenities) as $key) $amenity_cols[] = $amenity_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Hot desks, fixed desks, private offices and meeting rooms in an old warehouse, with day passes, monthly plans and a free first day.'),
    'tree'             => $page(array(
        // The way in: the space in a sentence, the two ways on and a photo
        // of the open floor.
        $el('section', 'pcw-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($hub['region'], 'pcw-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('A better place to do your work'), 'pcw-display display-3 mb-3'),
                        $para($hub['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book a Free Day'), '{{page:tour}}?' . $query(array('plan' => lang('Free trial day'))), 'btn btn-primary btn-lg px-4'),
                            $link(lang('See the Plans'), '{{page:plans}}', 'btn btn-outline-primary btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('Members working at long shared tables under tall warehouse windows'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Ways to Work'), array(
            $container(array(
                $section_header(lang('Ways to work'), lang('A desk, an office or a room for an hour'), lang('All Spaces'), '{{page:spaces}}'),
                $row($kind_cards_row, '4'),
            )),
        ), $searchable('cw-home-kinds', lang('desk, office, room, workspace, coworking'))),

        // The spaces, read from the Spaces collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Spaces'), array(
            $container(array(
                $section_header(lang('Inside'), lang('Find your spot'), lang('All Spaces'), '{{page:spaces}}'),
                $widget('home_spaces'),
            )),
        )),

        $el('section', 'py-5', lang('Membership Plans'), array(
            $container(array(
                $section_header(lang('Membership'), lang('A plan for how you work'), lang('Compare the plans'), '{{page:plans}}'),
                $row($plan_cards_row, '4'),
            )),
        )),

        $story_band('cw-home-rooms', lang('Meeting Rooms'), lang('A room for your next meeting'),
            array(lang('Rooms for four to twelve with a screen and a whiteboard, booked by the hour. Members and visitors pick a free time online.')),
            $photo('home-rooms', 1200, 900), lang('A meeting room with a long table and a screen on the wall'), true,
            array($link(lang('Book a meeting room'), '{{page:book_online}}', 'btn btn-outline-primary px-4')),
            lang('meeting room, conference room, hourly, boardroom'), 'py-5 bg-body-tertiary'),

        $el('section', 'py-5', lang('Amenities'), array(
            $container(array(
                $section_header(lang('Included'), lang('Everything you need to get on with it'), lang('All amenities'), '{{page:amenities}}'),
                $row($amenity_cols, '4'),
            )),
        ), $searchable('cw-home-amenities', lang('wifi, coffee, printing, phone booth, lockers, showers'))),

        // The community events, read from the Events collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Community Events'), array(
            $container(array(
                $section_header(lang('Community'), lang('Meet the people around you'), lang('All events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // What members say: sample words for the space to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Member Stories'), array(
            $container(array(
                $section_header(lang('Our members'), lang('What members say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Blog'), array(
            $container(array(
                $section_header(lang('Blog'), lang('Notes on working well'), lang('All posts'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Membership plans ────────────────────────────────────────────────────
// The plans, the private offices, the meeting rooms by the hour and how
// starting works. Each plan opens the tour request with the plan chosen;
// the shop's membership pages are linked from the shop pages only.
$public_pages[] = array(
    'key'              => 'plans',
    'name'             => lang('membership'),
    'folder'           => 'public',
    'title'            => lang('Membership Plans'),
    'meta_description' => lang('A day pass, ten days a month, an open membership or your own desk: monthly plans with no joining fee and a free first day.'),
    'tree'             => $page(array(
        $page_header(lang('Membership Plans'), lang('Pay by the day or by the month, with no joining fee. Come for a tour and your first day is free.')),
        $el('section', 'py-5', lang('Plans'), array(
            $container(array(
                $row($plan_cards_row, '4'),
            )),
        ), $searchable('cw-plans', lang('price, prices, cost, monthly, day pass, membership fee'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Private Offices'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('For teams'), 'pcw-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('An office of your own'), 'pcw-display h2 mb-3'),
                        $para(lang('Furnished offices for two to ten people, let by the month with meeting room hours, post handling and everything the members have. Tell us about your team and we send an offer.'), 'text-body-secondary mb-4'),
                        $link(lang('Ask About an Office'), '{{page:office_enquiry}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array($price_list('office')), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('cw-offices', lang('private office, team room, studio, lease, rent'))),
        $el('section', 'py-5', lang('Meeting Rooms'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('By the hour'), 'pcw-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('Meeting rooms for members and visitors'), 'pcw-display h2 mb-3'),
                        $para(lang('Every plan comes with meeting room hours. Beyond them, or without a plan, a room is paid by the hour.'), 'text-body-secondary mb-4'),
                        $link(lang('Book a meeting room'), '{{page:book_online}}', 'btn btn-outline-primary px-4'),
                    ), '', '6', '', '12', 'order-lg-last'),
                    $col(array($price_list('meeting')), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('cw-room-prices', lang('meeting room price, hourly rate, conference room'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Every plan includes'), 'pcw-display h3 mb-3'),
                        $check_list(array(
                            lang('No joining fee and no contract longer than a month'),
                            lang('Fast Wi-Fi, coffee, tea and the phone booths'),
                            lang('Members\' events and the community board'),
                            lang('Stop before the next month starts, at the front desk or by e-mail'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('How starting works'), 'pcw-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Book a tour'), lang('Choose a plan or the free trial day and a time that suits you.')),
                            $step(2, lang('Look around and stay'), lang('The front desk shows you the floors, then you work for the rest of the day.')),
                            $step(3, lang('Choose your plan'), lang('We set up your membership and your key card at the desk.')),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('cw-plan-terms', lang('contract, cancel, joining fee, terms, notice'))),
    )),
);

// ── Amenities ───────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'amenities',
    'name'             => lang('amenities'),
    'folder'           => 'public',
    'title'            => lang('Amenities'),
    'meta_description' => lang('Fast Wi-Fi, a coffee bar, printing, phone booths, lockers, showers, a business address and a roof terrace, included for members.'),
    'tree'             => $page(array(
        $page_header(lang('Amenities'), lang('Everything that makes a working day easier, included in every plan.')),
        $el('section', 'py-5', lang('Amenities'), array(
            $container(array(
                $row($amenity_cols, '4'),
            )),
        ), $searchable('cw-amenities', lang('wifi, internet, coffee, kitchen, printer, lockers, showers, parking'))),
        $story_band('cw-coffee', lang('Coffee Bar'), lang('Good coffee, all day'),
            array($amenities['coffee']['text'], lang('The coffee bar is where people meet between calls, and where most introductions happen.')),
            $photo('coffee', 1200, 900), lang('A barista pouring milk at the coffee bar'), false, array(),
            lang('coffee, tea, kitchen, lunch, snacks'), 'py-5 bg-body-tertiary'),
        $story_band('cw-terrace', lang('Roof Terrace'), lang('Work in the open air'),
            array($amenities['terrace']['text']),
            $photo('terrace', 1200, 900), lang('Tables and plants on a roof terrace above the harbour'), true,
            array($link(lang('Community Events'), '{{page:events}}', 'btn btn-outline-primary px-4')),
            lang('terrace, outdoor, roof, garden')),
        $story_band('cw-address', lang('Business Address'), lang('An address for your company'),
            array($amenities['post']['text'], lang('Resident desks and offices include it; for other plans, ask at the front desk.')),
            $photo('post', 1200, 900), lang('Pigeonholes with members\' post at the front desk'), false, array(),
            lang('virtual office, registered address, mail, post, parcels'), 'py-5 bg-body-tertiary'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-people', 'title' => lang('Neighbours, not tenants'), 'text' => lang('We introduce people, run the events and know who could help with what.')),
    array('icon' => 'bi-volume-mute', 'title' => lang('Quiet where it counts'), 'text' => lang('Calls go to the booths, the library stays silent and the buzz stays on the open floor.')),
    array('icon' => 'bi-arrows-angle-expand', 'title' => lang('Room to grow'), 'text' => lang('From a day pass to an office for ten, without moving out or signing a long lease.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Three freelancers, an empty tea warehouse and a community of designers, developers and small teams: the story of the space.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $hub['tagline'] . '.'),
        $story_band('cw-our-story', lang('Our story'), lang('From a tea warehouse to a place to work'),
            array($hub['story']),
            $photo('about', 1200, 900), lang('The brick front of the old warehouse with its loading doors'), false, array(),
            lang('history, founders, owners, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we run the space')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('cw-values', lang('values, community, quiet, flexible'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Ways to Work'), array(
            $container(array(
                $section_header(lang('Ways to work'), lang('Five kinds of space'), lang('All Spaces'), '{{page:spaces}}'),
                $row($kind_cards_row, '4'),
            )),
        )),
        $el('section', 'py-5', lang('Write to Us'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('A question before your first day?'), 'pcw-display h4 mb-1'),
                        $para(lang('Write to us and the front desk answers, usually the same day.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: membership, rooms and offices, the space. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pcw-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about the free trial day, plans and contracts, meeting rooms, private offices, guests, post and access hours.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Plans, rooms and the space. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('cw-faq-plans', lang('Plans and joining'), array(
                            array(lang('Can I try the space before I join?'), lang('Yes: book a tour and stay to work for the rest of the day at a hot desk. There is nothing to pay and nothing to sign.'), lang('trial, free, try, visit')),
                            array(lang('Is there a contract?'), lang('No. Plans run a month at a time with no joining fee; stop before the next month starts.'), lang('contract, cancel, joining fee, notice')),
                            array(lang('How do I follow my request?'), lang('Sending a form opens an account for you. Under My Account you see the state of each request and can write to the front desk there.'), lang('my account, status, track')),
                        )),
                        $faq_group('cw-faq-rooms', lang('Rooms and offices'), array(
                            array(lang('Can I book a meeting room without a plan?'), lang('Yes. Anybody can book a room by the hour online; members use their plan\'s hours first.'), lang('meeting room, visitor, hourly, booking')),
                            array(lang('How big are the private offices?'), lang('From four to eight desks, each furnished and with a door that locks. Tell us your team\'s size and we suggest the right one.'), lang('office size, team, desks, private room')),
                            array(lang('Can I hold an event in the hall?'), lang('Yes, for talks, launches and workshops of up to eighty people. Ask the front desk for a free date.'), lang('event hall, venue, launch, workshop, hire')),
                        )),
                        $faq_group('cw-faq-space', lang('At the space'), array(
                            array(lang('Can I bring a guest?'), lang('Yes: sign them in at the front desk. A guest may stay for a meeting; one who works all day needs a day pass.'), lang('guest, visitor, client, friend')),
                            array(lang('When can I come in?'), lang('The front desk is staffed on weekdays and Saturdays. Resident desks and offices have a key card for any hour.'), lang('hours, open, weekend, night, 24/7')),
                            array(lang('Can you take my post?'), lang('Yes: resident desks and offices get a business address, and the front desk takes in post and parcels.'), lang('post, mail, parcels, address, delivery')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// ── Getting here ────────────────────────────────────────────────────────

$ways_here = array(
    array('icon' => 'bi-train-lightrail-front', 'title' => lang('By tram'), 'text' => lang('Get off at the harbour stop and walk two minutes up Harbour Lane.')),
    array('icon' => 'bi-bicycle', 'title' => lang('By bike'), 'text' => lang('Covered racks in the courtyard and showers inside for those who cycle in.')),
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('The car park on the quay is two minutes away; members get a reduced day ticket at the front desk.')),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'location',
    'name'             => lang('getting-here'),
    'folder'           => 'public',
    'title'            => lang('Getting Here'),
    'meta_description' => lang('Where the coworking space is, when the front desk is open and how to reach it by tram, bike or car.'),
    'tree'             => $page(array(
        $page_header(lang('Getting Here'), $hub['region'] . '.'),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Find us'), 'pcw-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $hub['address'], 'pcw-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($hub['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $hub['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($hub['email'], 'mailto:' . $hub['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list('list-unstyled mb-4'),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($hub['address']), 'btn btn-outline-primary px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('location', 1400, 1050), lang('The warehouse front on Harbour Lane with bikes in the courtyard'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('cw-address', lang('address, map, directions, opening hours, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('cw-travel', lang('tram, parking, bike, car, transport'))),
    )),
);

// The home page's lists: its spaces (home_spaces) come with the spaces,
// its events (home_events) with the events, its posts (home_posts) and the
// Google reviews (google_reviews) with the standard pages; this part
// places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
