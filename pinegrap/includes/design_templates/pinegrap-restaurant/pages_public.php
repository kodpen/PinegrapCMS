<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Restaurant", its public pages: home, the tasting
 * menus, the wine list, the restaurant's story and team, the questions and
 * finding the restaurant. Included after parts.php in the same scope (its
 * builders and the restaurant's data); returns array('pages' => ...,
 * 'widgets' => ...).
 *
 * Every figure comes from parts.php ($restaurant, $sittings,
 * $opening_hours, $tastings, $wine_groups, $wines, $team, $rooms,
 * $figures, $testimonials), so a change made there reaches every page
 * here. No page here links a shop page: the template stands on a site
 * without the shop, and the gift vouchers are named in words only.
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
        $para($eyebrow, 'prt-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'prt-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the values, the good-to-know
// points and the ways to the restaurant.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'prt-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The restaurant in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'prt-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The opening hours as a short list, for the location page and the
// tasting page.
$hours_list = function ($class = 'list-unstyled mb-0') use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'prt-price fw-semibold text-end', lang('Hours')),
        ));
    }
    return $el('ul', $class, lang('Opening Hours'), $items);
};

// The cards of the shared data, in their order.
$tasting_cards_row = array();
foreach (array_keys($tastings) as $key) $tasting_cards_row[] = $tasting_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$team_cols = array();
foreach (array_keys($team) as $key) $team_cols[] = $team_card($key);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A seasonal restaurant with a wood oven, tasting menus, a wine list of small growers, a chef\'s table, private rooms and events.'),
    'tree'             => $page(array(
        // The way in: the restaurant in a sentence, the two ways on and a
        // photo of the dining room.
        $el('section', 'prt-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($restaurant['region'], 'prt-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Cooked with the seasons'), 'prt-display display-3 mb-3'),
                        $para($restaurant['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Reserve a Table'), '{{page:reserve}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('See the menu'), '{{page:menu}}', 'btn btn-outline-primary btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('The candle-lit dining room with the wood oven glowing at the back'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        // The favourites, read from the Menu form: the dishes marked for
        // the home page.
        $el('section', 'py-5', lang('From the Menu'), array(
            $container(array(
                $section_header(lang('This week'), lang('Plates our guests come back for'), lang('The whole menu'), '{{page:menu}}'),
                $widget('home_dishes'),
            )),
        ), $searchable('rt-home-menu', lang('food, dishes, menu, plates, à la carte'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Tasting Menus'), array(
            $container(array(
                $section_header(lang('Let the kitchen choose'), lang('Our tasting menus'), lang('About the tasting menus'), '{{page:tasting}}'),
                $row($tasting_cards_row, '4'),
            )),
        ), $searchable('rt-home-tasting', lang('tasting menu, set menu, degustation, wine pairing'))),

        $story_band('rt-home-chefs-table', lang('The Chef\'s Table'), lang('Six seats beside the fire'),
            array(lang('One sitting a night at the kitchen counter: the chef cooks in front of you, talks you through every plate and pours a glass to go with it. Pick a free evening online.')),
            $photo('home-counter', 1200, 900), lang('Guests at the kitchen counter watching the chef plate a dish'), true,
            array($link(lang('Book the chef\'s table'), '{{page:book_online}}', 'btn btn-outline-primary px-4')),
            lang('chef\'s table, kitchen counter, open kitchen')),

        $story_band('rt-home-private', lang('Private Dining'), lang('A room of your own'),
            array(lang('The wine cellar for twelve, the garden room for twenty-eight or the whole restaurant: tell us the occasion and we write a menu for it.')),
            $photo('home-private', 1200, 900), lang('A long table laid for a private dinner in the wine cellar'), false,
            array($link(lang('Plan a private dinner'), '{{page:private_dining}}', 'btn btn-outline-primary px-4')),
            lang('private room, party, group, wedding, company dinner'), 'py-5 bg-body-tertiary'),

        // The next events, read from the Events form.
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $section_header(lang('Coming up'), lang('Dinners, classes and long lunches'), lang('All events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // What guests say: sample words for the restaurant to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Guest Stories'), array(
            $container(array(
                $section_header(lang('Our guests'), lang('What guests say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From the Kitchen Journal'), array(
            $container(array(
                $section_header(lang('Kitchen Journal'), lang('Notes from the kitchen and the cellar'), lang('All posts'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Tasting menus ───────────────────────────────────────────────────────
// The menus side by side, what to know before booking one and how the
// evening goes. Each menu opens the reservation with the menu chosen.
$tasting_points = array(
    array('icon' => 'bi-people', 'title' => lang('For the whole table'), 'text' => lang('A tasting menu is served to everyone at the table, so the kitchen can send each course at once.')),
    array('icon' => 'bi-heart-pulse', 'title' => lang('Allergies and diets'), 'text' => lang('Tell us when you book: the kitchen changes a course for an allergy or a diet with a day\'s notice.')),
    array('icon' => 'bi-hourglass-split', 'title' => lang('Take your time'), 'text' => lang('Allow about three hours for the Seasons Menu and two and a half for the Garden Menu.')),
);
$tasting_point_tiles = array();
foreach ($tasting_points as $p) $tasting_point_tiles[] = $feature_tile($p['icon'], $p['title'], $p['text']);

$public_pages[] = array(
    'key'              => 'tasting',
    'name'             => lang('tasting-menus'),
    'folder'           => 'public',
    'title'            => lang('Tasting Menus'),
    'meta_description' => lang('The Seasons Menu, the vegetarian Garden Menu and the Long Lunch, each with a wine pairing chosen by our sommelier.'),
    'tree'             => $page(array(
        $page_header(lang('Tasting Menus'), lang('Let the kitchen cook for you: the week\'s market in a row of small plates, with a glass chosen for each.')),
        $el('section', 'py-5', lang('Menus'), array(
            $container(array(
                $row($tasting_cards_row, '4'),
            )),
        ), $searchable('rt-tasting', lang('tasting menu, set menu, price, wine pairing, vegetarian'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $section_header(lang('Before you book'), lang('Good to know')),
                $row($tasting_point_tiles, '4'),
            )),
        ), $searchable('rt-tasting-notes', lang('allergy, allergies, diet, vegan, gluten, how long'))),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How the evening goes'), 'prt-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Reserve with the menu chosen'), lang('Open the reservation from a menu above, or choose it in the form.')),
                            $step(2, lang('We confirm your table'), lang('We answer the same day; you read the answer under My Reservations.')),
                            $step(3, lang('Sit back'), lang('The courses arrive at a gentle pace; the sommelier pours the pairing if you chose it.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('When we serve them'), 'prt-display h3 mb-3'),
                        $hours_list(),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('rt-tasting-how', lang('opening hours, when, lunch, dinner, booking'))),
    )),
);

// ── Wine list ───────────────────────────────────────────────────────────
// A link to each kind at the top, the kinds in their order, then the
// sommelier.
$wine_jumps = array();
foreach ($wine_groups as $g => $title) {
    $wine_jumps[] = $el('li', 'nav-item', lang('Nav Item'), array($link($title, '#rt-wine-' . $g, 'nav-link')));
}
$wine_sections = array();
foreach (array_keys($wine_groups) as $g) $wine_sections[] = $wine_group($g);

$public_pages[] = array(
    'key'              => 'wine',
    'name'             => lang('wine-list'),
    'folder'           => 'public',
    'title'            => lang('Wine List'),
    'meta_description' => lang('Sparkling, white, rosé, red and sweet wines from small growers, by the glass and by the bottle, chosen by our sommelier.'),
    'tree'             => $page(array(
        $page_header(lang('Wine List'), lang('Small growers, local grapes and a few old friends from further away. Ask the sommelier for what is open by the glass tonight.'), array(
            $el('nav', 'mt-3', lang('Wine Kinds'), array(
                $el('ul', 'nav nav-pills gap-2', lang('Links'), $wine_jumps),
            ), array('_attrs' => array($attr('aria-label', lang('Wine Kinds'))))),
        )),
        $el('section', 'py-5', lang('Wines'), array(
            $container(array(
                $row(array(
                    $col($wine_sections, '', '9'),
                ), '', 'center'),
            )),
        ), $searchable('rt-wines', lang('wine, wines, glass, bottle, red, white, price'))),
        $story_band('rt-sommelier', lang('Sommelier'), lang('A glass for every plate'),
            array($team['sommelier']['text'], lang('The list changes with the seasons; bottles from the cellar not on it are open to ask about.')),
            $photo('sommelier', 1200, 900), lang('The sommelier pouring a glass of red wine at the table'), false, array(),
            lang('sommelier, pairing, recommendation, cellar'), 'py-5 bg-body-tertiary'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-basket', 'title' => lang('The market decides'), 'text' => lang('We write the menu after the morning\'s deliveries, not before: what is best today is what we cook.')),
    array('icon' => 'bi-recycle', 'title' => lang('Nothing wasted'), 'text' => lang('Bones become broth, peels become oils and the garden takes the rest back as compost.')),
    array('icon' => 'bi-house-heart', 'title' => lang('A welcome, not a ceremony'), 'text' => lang('Good food without the stiffness: come as you are, stay as long as you like.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('our-story'),
    'folder'           => 'public',
    'title'            => lang('Our Story'),
    'meta_description' => lang('An old bakery, a wood oven and a menu that follows the market: the story of the restaurant and the team behind it.'),
    'tree'             => $page(array(
        $page_header(lang('Our Story'), $restaurant['tagline'] . '.'),
        $story_band('rt-our-story', lang('Our story'), lang('From a bakery to a dining room'),
            array($restaurant['story']),
            $photo('about', 1200, 900), lang('The old bakery\'s brick oven in the middle of the dining room'), false, array(),
            lang('history, chef, owner, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('The Team'), array(
            $container(array(
                $section_header(lang('In the kitchen and the dining room'), lang('Meet the team')),
                $row($team_cols, '4'),
            )),
        ), $searchable('rt-team', lang('chef, cooks, sommelier, staff, team'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we cook')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('rt-values', lang('local, seasonal, sustainable, farms, waste'))),
        $el('section', 'py-5', lang('Write to Us'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('A question before your visit?'), 'prt-display h4 mb-1'),
                        $para(lang('Write to us and we answer, usually the same day.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: reservations, food and diets, private dining and
// gifts. Each question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'prt-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about reservations, cancelling, allergies and diets, children, the chef\'s table, private dining and gift vouchers.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Reservations, food and private dinners. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('rt-faq-reservations', lang('Reservations'), array(
                            array(lang('How do I reserve a table?'), lang('Send the reservation form with the day, the time and how many you are. We confirm by e-mail the same day; the answer is also under My Reservations.'), lang('book, booking, reserve, table')),
                            array(lang('Can I come without a reservation?'), lang('Yes, when a table is free: the kitchen counter and the terrace keep a few seats for guests who walk in.'), lang('walk in, no booking, free table')),
                            array(lang('How do I change or cancel?'), lang('Write on your reservation under My Reservations, or call us. Please tell us a day ahead so someone else can have the table.'), lang('cancel, change, late, no show')),
                        )),
                        $faq_group('rt-faq-food', lang('Food and diets'), array(
                            array(lang('Can you cook for my allergy or diet?'), lang('Yes. Every dish on the menu carries its diet tags; for an allergy, tell us when you book and the kitchen plans your plates.'), lang('allergy, allergies, gluten, vegan, vegetarian, nuts')),
                            array(lang('Are children welcome?'), lang('Yes, at lunch and early dinner. The kitchen makes a small plate of most dishes; ask your waiter.'), lang('children, kids, family, high chair')),
                            array(lang('Can I bring my own wine?'), lang('One bottle per table that is not on our list, for a small corkage fee the sommelier tells you at the table.'), lang('corkage, bring your own, wine')),
                        )),
                        $faq_group('rt-faq-private', lang('Private dining and gifts'), array(
                            array(lang('How many guests can a private room take?'), lang('The wine cellar seats twelve, the garden room twenty-eight and the whole restaurant sixty. Send an enquiry and we propose a menu.'), lang('private room, group, party, capacity')),
                            array(lang('What is the chef\'s table?'), lang('Six seats at the kitchen counter, one sitting a night, with a menu the chef cooks in front of you. Book a free evening online.'), lang('chef\'s table, counter, open kitchen')),
                            array(lang('Do you sell gift vouchers?'), lang('Yes: vouchers for an amount, a tasting menu for two or a place at the cooking class, valid for a year. Ask at the restaurant or in our online shop where it is open.'), lang('gift, voucher, present, gift card')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// ── Finding us ──────────────────────────────────────────────────────────

$ways_here = array(
    array('icon' => 'bi-signpost', 'title' => lang('On foot'), 'text' => lang('From the market square take Market Lane past the fountain; we are on the left with the green door.')),
    array('icon' => 'bi-bus-front', 'title' => lang('By bus'), 'text' => lang('Every line to the old town stops at the market square, two minutes away.')),
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('Park under the town hall; the old town\'s lanes are closed to cars in the evening.')),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'location',
    'name'             => lang('find-us'),
    'folder'           => 'public',
    'title'            => lang('Getting Here'),
    'meta_description' => lang('Where the restaurant is, when it is open for lunch and dinner and how to reach it on foot, by bus or by car.'),
    'tree'             => $page(array(
        $page_header(lang('Getting Here'), $restaurant['region'] . '.'),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Visit us'), 'prt-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $restaurant['address'], 'prt-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($restaurant['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $restaurant['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($restaurant['email'], 'mailto:' . $restaurant['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list('list-unstyled mb-4'),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($restaurant['address']), 'btn btn-outline-primary px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('location', 1400, 1050), lang('The restaurant\'s green door and terrace on Market Lane'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('rt-address', lang('address, map, directions, opening hours, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('rt-travel', lang('bus, parking, walk, car, transport'))),
    )),
);

// The home page's lists: its dishes (home_dishes) and events (home_events)
// come with the menu, its posts (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
