<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Craft Brewery", its public pages: home, the taproom,
 * about the brewery and the questions page. Included after parts.php in
 * the same scope (its builders and the brewery's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($brewery, $beers, $tours, $hours,
 * $food, $testimonials, $figures), so a change made there reaches every
 * page here. No page here links a shop page: the template stands on a
 * site without the shop.
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
        $para($eyebrow, 'pbw-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pbw-display display-6 mb-3'),
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

// The brewery in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pbw-display display-4 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// A band that sends a question on: to the contact page, and a second way
// if one is given.
$ask_band = function ($title, $text, $second = array()) use ($el, $heading, $para, $link, $container) {
    $buttons = array($link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'));
    if ($second) $buttons[] = $link($second[0], $second[1], 'btn btn-outline-primary rounded-pill px-4 text-nowrap');
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pbw-display h3 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
            )),
        )),
    ));
};

// The opening hours as a list of days and times.
$hours_list = function () use ($el, $span, $hours) {
    $items = array();
    foreach ($hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', lang('Opening Hours'), array(
            $span($h['days'], 'fw-semibold', lang('Days')),
            $span($h['time'], 'text-body-secondary text-nowrap', lang('Time')),
        ));
    }
    return $el('ul', 'list-unstyled mb-0', lang('Opening Hours'), $items);
};

// The team of the about page: page content, edited in the designer like
// any other page.
$team = array(
    array('name' => lang('Mert Aydın'), 'role' => lang('Head brewer and co-founder'),
        'bio' => lang('Brewed his first beer in a kitchen pot and still tastes every batch before it is canned.')),
    array('name' => lang('Hanna Berg'), 'role' => lang('Brewer and co-founder'),
        'bio' => lang('Looks after the yeast, the sours and the long list of recipes still to try.')),
    array('name' => lang('Ece Tan'), 'role' => lang('Taproom manager'),
        'bio' => lang('Runs the bar, the quiz and the events, and knows every regular by name.')),
    array('name' => lang('Jonas Weber'), 'role' => lang('Tours and tastings'),
        'bio' => lang('Leads the tours and explains malt and hops so that everyone follows.')),
);
$team_card = function ($m) use ($el, $heading, $para, $image, $col, $attr, $portrait) {
    return $col(array(
        $el('article', 'text-center', lang('Team Member'), array(
            $image($portrait($m['name'], 320), $m['name'], 'rounded-circle mb-3', array('_attrs' => array($attr('width', '160'), $attr('height', '160'), $attr('loading', 'lazy')))),
            $heading('h3', $m['name'], 'pbw-display h5 mb-1'),
            $para($m['role'], 'small fw-semibold text-primary mb-2'),
            $para($m['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// The cards of the shared data, in their order.
$tour_cols = array();
foreach (array_keys($tours) as $key) $tour_cols[] = $tour_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$team_cols = array();
foreach ($team as $m) $team_cols[] = $team_card($m);

// The tap list of the taproom page: each sample beer with its style and
// strength. Page content: the staff change it in the designer when a keg
// changes, so it says what is on today without a form of its own.
$tap_items = array();
foreach ($beers as $key => $b) {
    $tap_items[] = $el('li', 'd-flex flex-wrap justify-content-between gap-2 py-3 border-bottom', lang('Tap'), array(
        $el('div', '', lang('Beer'), array(
            $span($b['name'], 'pbw-display d-block h5 mb-1', lang('Beer Name')),
            $span($styles[$b['style']]['title'], 'small text-body-secondary', lang('Beer Style')),
        )),
        $span($strength_text($key), 'pbw-abv small fw-semibold text-nowrap align-self-center', lang('Beer Strength')),
    ));
}
// The taproom's food, with the prices in the shop's currency.
$food_items = array();
foreach ($food as $f) {
    $food_items[] = $el('li', 'd-flex justify-content-between gap-3 py-3 border-bottom', lang('Menu Item'), array(
        $el('div', '', lang('Menu Item'), array(
            $span($f['title'], 'd-block fw-semibold', lang('Name')),
            $span($f['text'], 'small text-body-secondary', lang('Description')),
        )),
        $span($money($f['price']), 'pbw-price fw-bold text-nowrap', lang('Price')),
    ));
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Craft beer brewed by the old harbour: our beers, the taproom, events and brewery tours.'),
    'tree'             => $page(array(
        // The way in, on a dark band: the brewery in a sentence, the two
        // ways on and a photo of the brewhouse.
        $el('section', 'pbw-hero py-5 bg-body text-body overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Brewery and taproom'), 'pbw-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Small batches, brewed by the harbour'), 'pbw-display display-3 mb-3'),
                        $para($brewery['tagline'] . '.', 'lead mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Our Beers'), '{{page:beers}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Visit the Taproom'), '{{page:taproom}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('Copper brewing kettles in a brick brewhouse'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),

        // The featured beers, read from the Beers collection.
        $el('section', 'py-5', lang('Our Beers'), array(
            $container(array(
                $section_header(lang('On tap'), lang('Our beers'), lang('All Beers'), '{{page:beers}}'),
                $widget('home_beers'),
            )),
        )),

        // The coming events, read from the Taproom Events collection from
        // today on.
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Events'), array(
            $container(array(
                $section_header(lang('In the taproom'), lang('What is on'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        $el('section', 'py-5', lang('Brewery Tours'), array(
            $container(array(
                $section_header(lang('Behind the bar'), lang('Brewery tours'), lang('All Tours'), '{{page:tours}}'),
                $row($tour_cols, '4'),
            )),
        ), $searchable('bw-home-tours', lang('tour, visit, tasting, brewhouse, group'))),

        $story_band('bw-home-story', lang('Our story'), lang('From a boat workshop to a brewhouse'),
            array($brewery['story']),
            $photo('home-story', 1200, 900), lang('An old workshop crane above a wooden bar'), true,
            array($link(lang('About the Brewery'), '{{page:about}}', 'btn btn-outline-primary rounded-pill px-4')),
            lang('history, founders, who we are, brewery')),

        $el('section', 'py-5 bg-body-tertiary', lang('Reviews'), array(
            $container(array(
                $section_header(lang('Our guests'), lang('What drinkers say')),
                $row($testimonial_cols, '4'),
            )),
        ), $searchable('bw-home-reviews', lang('reviews, opinions, rating, guests'))),

        $el('section', 'py-5', lang('From the News'), array(
            $container(array(
                $section_header(lang('News'), lang('From the brewhouse'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Taproom ─────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'taproom',
    'name'             => lang('taproom'),
    'folder'           => 'public',
    'title'            => lang('Taproom'),
    'meta_description' => lang('Opening hours, what is on tap today, the food and how to find the taproom by the old harbour.'),
    'tree'             => $page(array(
        $page_header(lang('Taproom'), lang('Our beers fresh from the tank, simple food and a long table by the window.')),
        $el('section', 'py-5', lang('Visit'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('On tap today'), 'pbw-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Tap List'), $tap_items),
                        $para(lang('Guest beers change every week; ask at the bar what is on.'), 'small text-body-secondary mb-0'),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Opening Hours'), array(
                            $heading('h2', lang('Opening hours'), 'pbw-display h4 mb-3'),
                            $hours_list(),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Address'), array(
                            $heading('h2', lang('Find us'), 'pbw-display h4 mb-3'),
                            $el('p', 'd-flex gap-2 mb-2', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span($brewery['address'], '', lang('Address')),
                            )),
                            $el('p', 'd-flex gap-2 mb-0', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($brewery['phone'], 'tel:' . str_replace(' ', '', $brewery['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bw-taproom-taps', lang('opening hours, open today, tap list, draught, address'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Food'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('From the kitchen'), lang('Food to go with the beer')),
                        $el('ul', 'list-unstyled mb-3', lang('Menu'), $food_items),
                        $para(lang('Food is served until an hour before closing. Tell us about allergies when you order.'), 'small text-body-secondary mb-0'),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        ), $searchable('bw-taproom-food', lang('food, menu, kitchen, snacks, allergies, vegetarian'))),
        $story_band('bw-taproom-getting-there', lang('Getting here'), lang('By the old harbour'),
            array(lang('The taproom is a short walk from the ferry pier, at the end of the harbour wall. There is no car park of our own: come by ferry, bus or bicycle, and leave the car at home.'),
                lang('Children are welcome with an adult until eight in the evening; dogs on a lead are welcome all day.')),
            $photo('taproom-harbour', 1200, 900), lang('A brick taproom at the end of a harbour wall'), false, array(),
            lang('directions, parking, ferry, bus, children, dogs, accessibility')),
        $el('section', 'py-5 bg-body-tertiary', lang('Reviews'), array(
            $container(array(
                $section_header(lang('Our guests'), lang('What drinkers say')),
                $row($testimonial_cols, '4'),
            )),
        )),
        $ask_band(lang('A table for a group?'), lang('For a birthday or a team evening, write to us; for a private tasting, book a time.'),
            array(lang('Private Tastings'), '{{page:book_online}}')),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About the Brewery'),
    'meta_description' => lang('The story of the brewery, how our beer is brewed and the people who brew and pour it.'),
    'tree'             => $page(array(
        $page_header(lang('About the Brewery'), $brewery['tagline'] . '.'),
        $story_band('bw-our-story', lang('Our story'), lang('From a boat workshop to a brewhouse'),
            array($brewery['story']),
            $photo('about-story', 1200, 900), lang('Two brewers beside a steel fermenter'), false, array(),
            lang('history, founders, who we are, how it started')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('How We Brew'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('The brewhouse'), lang('How we brew')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Mash'), lang('Crushed malt steeps in hot water and gives up its sugars.')),
                            $step(2, lang('Boil'), lang('The sweet wort boils for an hour while the hops go in.')),
                            $step(3, lang('Ferment'), lang('Yeast turns the sugars into alcohol over one to three weeks.')),
                            $step(4, lang('Condition'), lang('The beer rests cold until it is clear and smooth, then goes into kegs and cans.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $framed($photo('about-brewhouse', 1200, 900), lang('Steam rising from a brewing kettle')),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('bw-how-we-brew', lang('brewing, process, malt, hops, yeast, ingredients'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Team'), array(
            $container(array(
                $section_header(lang('The people'), lang('Who brews and pours')),
                $row($team_cols, '4'),
            )),
        ), $searchable('bw-team', lang('team, brewers, staff, founders'))),
        $ask_band(lang('See it for yourself'), lang('Walk through the brewhouse with a brewer and taste the beer where it is made.'),
            array(lang('Book a Tour'), '{{page:tour_booking}}')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: visiting the taproom, tours and tastings, our beers.
// Each question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pbw-display h3 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about visiting the taproom, age rules, brewery tours, tastings and our beers.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Visits, tours and beer. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('bw-faq-visit', lang('Visiting the taproom'), array(
                            array(lang('Do I need to book a table?'), lang('No: tables are first come, first served. For a group of more than ten, write to us a few days ahead.'), lang('reservation, table, group, booking')),
                            array(lang('Can I bring children or a dog?'), lang('Children are welcome with an adult until eight in the evening, dogs on a lead all day.'), lang('kids, family, dog, pets')),
                            array(lang('Do you check age?'), lang('Yes. We serve beer only to adults of legal drinking age and may ask for an ID at the bar.'), lang('age, ID, under age, adults')),
                            array(lang('Do you serve drinks without alcohol?'), lang('Always: an alcohol-free beer, house lemonade, soft drinks and coffee.'), lang('alcohol-free, non-alcoholic, soft drinks, driver')),
                        )),
                        $faq_group('bw-faq-tours', lang('Tours and tastings'), array(
                            array(lang('How do I book a tour?'), lang('Choose a tour and send a booking request with the day, the time and how many you are; we confirm it by e-mail.'), lang('book, tour, request, group')),
                            array(lang('How long does a tour take?'), lang('The brewery tour takes about an hour, the tasting tour about ninety minutes, the brew day a whole day.'), lang('duration, length, time')),
                            array(lang('Can I change or cancel my booking?'), lang('Yes: write to us on your booking\'s page, at least a day before the tour.'), lang('cancel, change, move, refund')),
                            array(lang('Is the brewery accessible?'), lang('The taproom and most of the tour are on one level; tell us when you book and we plan the route.'), lang('wheelchair, accessible, stairs, mobility')),
                        )),
                        $faq_group('bw-faq-beer', lang('Our beers'), array(
                            array(lang('Which beers are on today?'), lang('The taproom page lists what is on tap; seasonal and limited beers come and go.'), lang('tap list, draught, today, seasonal')),
                            array(lang('Are your beers vegan or gluten-free?'), lang('Most of our beers are vegan; none is gluten-free, as all are brewed with barley or wheat.'), lang('vegan, gluten, allergens, ingredients')),
                            array(lang('Can I buy beer to take home?'), lang('Yes: cans and glasses are sold at the bar, and mixed cases can be ordered for collection.'), lang('take away, cans, buy, collect')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its beers (home_beers) and events (home_events)
// come with the beers and events, its news (home_posts) with the standard
// pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
