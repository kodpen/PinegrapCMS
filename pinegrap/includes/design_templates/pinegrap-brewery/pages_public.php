<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Craft Brewery", its public pages: home, the taproom,
 * about the brewery and the questions page. Included after parts.php in
 * the same scope (its builders and the brewery's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * The home and the taproom are drawn on the editor's Brewery blocks: the
 * pour hero, the fresh release, the tap board and the tasting flight, with
 * their motion; the about page brings its parts in as they scroll into
 * view. Every figure comes from parts.php ($brewery, $beers, $tours,
 * $hours, $food, $testimonials, $figures), so a change made there reaches
 * every page here. No page here links a shop page: the template stands on
 * a site without the shop.
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

// ── The Brewery blocks ──────────────────────────────────────────────────
// The home and the taproom are drawn on the editor's Brewery blocks
// (style_designer_blocks_brewery.js): the same nodes and classes, so the
// block rules the template ships in its User Styles draw them and the
// editor moves them like a placed block. Buttons are links with the button
// classes, the template's way. The sections are page content: the staff
// change a tap in the designer when a keg changes.

// The colour of each style's glass: tone 1 a pale straw to 4 a stout.
$bw_tones = array('lager' => 1, 'wheat' => 1, 'pale' => 2, 'ipa' => 2, 'sour' => 3, 'stout' => 4);
// A dark band: the palette's dark colours apply inside it.
$bw_dark = array('_attrs' => array($attr('data-bs-theme', 'dark')));
// The small line over a block's title.
$bw_eyebrow = function ($text, $class = 'text-primary') use ($span) {
    return $span($text, 'pbw-eyebrow d-block mb-3 fw-semibold text-uppercase ' . $class, lang('Text'));
};
// A glass with the beer of a tone at its foot; $pour is the interaction
// that fills it. Decoration: the words beside it say the style.
$bw_glass = function ($tone, $class, $pour = null) use ($el, $attr, $fx) {
    $beer = $el('div', 'pg-brewery-beer pg-brewery-tone-' . $tone, lang('Beer'));
    if ($pour) $beer = $fx($beer, $pour);
    return $el('div', 'pg-brewery-glass ' . $class, lang('Beer Glass'), array($beer), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
// A strength figure and the word after it.
$bw_abv = function ($figure, $class = 'fs-4') use ($el, $span) {
    return $el('div', 'd-flex align-items-baseline gap-1', lang('Beer Strength'), array(
        $span($figure, 'pg-brewery-figure fw-bold ' . $class, lang('Fact Figure')),
        $span(lang('ABV'), 'small text-body-secondary', lang('Text')),
    ));
};
// Two buttons of a block.
$bw_buttons = function ($name, $first, $second) use ($el, $link) {
    return $el('div', 'd-flex flex-wrap gap-3', $name, array(
        $link($first[0], $first[1], 'btn btn-primary btn-lg rounded-pill px-4'),
        $link($second[0], $second[1], 'btn btn-outline-primary btn-lg rounded-pill px-4'),
    ));
};

// Brewery Hero: a very large line, the tagline, two buttons and three
// facts rise on load; beside them the brewhouse fades in and the glass
// over the picture's corner fills from the foot up. pbw-hero adds the
// template's glow.
$hero_fact = function ($icon_name, $text) use ($el, $icon, $span) {
    return $el('li', 'd-flex align-items-center gap-2', lang('Fact'), array($icon($icon_name, 'text-primary'), $span($text, '', lang('Text'))));
};
$bw_hero = $el('section', 'pbw-hero py-5 bg-body text-body overflow-hidden', lang('Brewery Hero'), array(
    $container(array(
        $row(array(
            $col(array(
                $fx($el('div', '', lang('Hero Words'), array(
                    $bw_eyebrow(lang('Brewery and taproom')),
                    $heading('h1', lang('Fresh from the tank.'), 'display-1 fw-bold text-uppercase lh-1 mb-4'),
                    $para($brewery['tagline'] . '.', 'lead text-body-secondary mb-4'),
                    $el('div', 'd-flex flex-wrap gap-3', lang('Hero Buttons'), array(
                        $link(lang('See what is on tap'), '{{page:taproom}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                        $link(lang('Book a tour'), '{{page:tours}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                    )),
                )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 900)),
                $fx($el('ul', 'list-unstyled d-flex flex-wrap gap-3 gap-lg-4 small mt-5 mb-0', lang('Facts'), array(
                    $hero_fact('bi-cup-straw', lang('Six beers on tap')),
                    $hero_fact('bi-signpost', lang('Brewery tours and tastings')),
                    $hero_fact('bi-clock', lang('Open Wednesday to Sunday')),
                )), array('effect' => 'fade', 'trigger' => 'load', 'duration' => 800, 'delay' => 600, 'stagger' => 120)),
            ), '', '6'),
            $col(array(
                $el('div', 'px-4 px-lg-0 pb-5 pb-lg-0', lang('Picture Stage'), array(
                    $fx($image($photo('hero', 960, 1200), lang('Copper brewing kettles in a brick brewhouse'), 'pg-brewery-photo w-100 object-fit-cover rounded-4 shadow-lg'),
                        array('effect' => 'fade', 'trigger' => 'load', 'duration' => 1200, 'delay' => 200)),
                    $bw_glass(2, 'pg-brewery-pour', array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 1800, 'delay' => 700, 'distance' => 400)),
                )),
            ), '', '5', '', '12', 'offset-lg-1'),
        ), '4', '', 'align-items-center g-lg-5'),
    ), 'py-lg-5'),
), $bw_dark);

// Brewery Release: the limited beer as one chapter, its name very large,
// its figures rising one after another, its notes and the ways to it; its
// picture with the sticker moves slower than the page.
$bw_release_beer = $beers['tide'];
$bw_release_fact = function ($n, $label, $figure) use ($el, $span) {
    return $el('div', 'col border-top border-2 pt-3', str_replace('{var}', $n, lang('Fact {var}')), array(
        $el('dt', 'small fw-normal text-body-secondary mb-1', lang('Fact Label'), array($span($label, '', lang('Text')))),
        $el('dd', 'pg-brewery-figure fs-2 fw-bold lh-1 mb-0', lang('Fact Figure'), array($span($figure, '', lang('Text')))),
    ));
};
$bw_release_notes = array();
foreach (array(lang('Sour cherry'), lang('Lemon'), lang('Light, dry finish')) as $t) {
    $bw_release_notes[] = $el('li', 'badge rounded-pill bg-body text-body-emphasis border fw-normal', lang('Note'), array($span($t, '', lang('Text'))));
}
$bw_release = $el('section', 'py-5 bg-body-tertiary overflow-hidden', lang('Brewery Release'), array(
    $container(array(
        $row(array(
            $col(array(
                $fx($el('div', 'px-4 px-lg-0', lang('Picture Stage'), array(
                    $image($photo('beer-tide', 960, 1200), lang('A can of sour beer beside a glass on the bar'), 'pg-brewery-photo w-100 object-fit-cover rounded-4 shadow',
                        array('_attrs' => array($attr('loading', 'lazy')))),
                    $el('div', 'pg-brewery-sticker text-bg-primary small fw-bold text-uppercase shadow', lang('Sticker'), array(
                        $span($availabilities[$bw_release_beer['availability']]['title'], '', lang('Text')),
                    )),
                )), array('effect' => 'parallax', 'speed' => 15)),
            ), '', '5'),
            $col(array(
                $bw_eyebrow(lang('Fresh release'), 'text-body-emphasis'),
                $heading('h2', $bw_release_beer['name'], 'display-2 fw-bold text-uppercase lh-1 mb-3'),
                $para($bw_release_beer['summary'], 'lead text-body-secondary mb-4'),
                $fx($el('dl', 'row row-cols-3 g-3 mb-4', lang('Release Facts'), array(
                    $bw_release_fact(1, lang('Alcohol'), $bw_release_beer['abv']),
                    $bw_release_fact(2, lang('Bitterness'), str_replace('{var}', $bw_release_beer['ibu'], lang('{var} IBU'))),
                    $bw_release_fact(3, lang('Beer Style'), $styles[$bw_release_beer['style']]['title']),
                )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => 120)),
                $heading('h3', lang('Tasting notes'), 'h6 fw-semibold text-uppercase text-body-secondary mb-2'),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-4', lang('Notes'), $bw_release_notes),
                $bw_buttons(lang('Release Buttons'), array(lang('Find it on tap'), '{{page:taproom}}'), array(lang('See all beers'), '{{page:beers}}')),
            ), '', '6', '', '12', 'offset-lg-1'),
        ), '4', '', 'align-items-center g-lg-5'),
    ), 'py-lg-5'),
), $searchable('bw-home-release', lang('new beer, release, limited, sour, cherry')));

// Brewery Tap Board: a dark board of numbered taps, every sample beer in
// its order with a small glass of its style's colour, a badge where it is
// not brewed all year, its strength and bitterness; the taps come in one
// after another as the board scrolls in.
$bw_taps = array();
$bw_tap_n = 0;
foreach ($beers as $b) {
    $bw_tap_n++;
    $bw_tap_title = array($heading('h3', $b['name'], 'h5 fw-bold mb-0'));
    if ($b['availability'] !== 'core') {
        $bw_tap_title[] = $span($availabilities[$b['availability']]['title'], 'badge rounded-pill ' . $availabilities[$b['availability']]['badge'], lang('Beer Availability'));
    }
    $bw_taps[] = $el('li', 'col', str_replace('{var}', $bw_tap_n, lang('Tap {var}')), array(
        $el('div', 'd-flex align-items-center gap-3 py-3 border-bottom', lang('Tap Row'), array(
            $span(sprintf('%02d', $bw_tap_n), 'pg-brewery-tap text-primary fw-bold', lang('Tap')),
            $bw_glass($bw_tones[$b['style']], 'pg-brewery-mini'),
            $el('div', 'flex-grow-1', lang('Beer'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-1', lang('Beer Name'), $bw_tap_title),
                $para($styles[$b['style']]['title'], 'small text-body-secondary mb-0'),
            )),
            $el('div', 'text-end', lang('Facts'), array(
                $bw_abv($b['abv']),
                $span(str_replace('{var}', $b['ibu'], lang('{var} IBU')), 'pg-brewery-figure small text-body-secondary', lang('Bitterness')),
            )),
        )),
    ));
}
$bw_tap_board = function ($id, $topics) use ($el, $heading, $para, $container, $fx, $fade_in, $bw_eyebrow, $bw_taps, $bw_dark, $searchable) {
    return $el('section', 'py-5 bg-body text-body', lang('Brewery Tap Board'), array(
        $container(array(
            $fx($el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                $el('div', '', lang('Header Words'), array(
                    $bw_eyebrow(lang('On tap now')),
                    $heading('h2', lang('Six taps, poured fresh.'), 'display-5 fw-bold mb-0'),
                )),
                $para(lang('Taps change when a keg runs out: ask at the bar what is next.'), 'text-body-secondary mb-0 col-lg-4'),
            )), $fade_in),
            $fx($el('ol', 'list-unstyled row row-cols-1 row-cols-lg-2 gx-lg-5 mb-0', lang('Taps'), $bw_taps),
                array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => 90, 'distance' => 24)),
        ), 'py-lg-4'),
    ), array_merge($bw_dark, $searchable($id, $topics)));
};

// Brewery Tasting Flight: four tasters on one paddle from light to dark,
// each glass filling a moment after the one before as the paddle scrolls
// in; beside them what a flight is and the way to a private tasting.
$bw_tasters = array();
foreach (array('harbour', 'crane', 'tide', 'anchor') as $i => $key) {
    $b = $beers[$key];
    $bw_tasters[] = $el('li', 'col text-center', str_replace('{var}', $i + 1, lang('Taster {var}')), array(
        $el('div', 'pg-brewery-slot', lang('Paddle'), array(
            $bw_glass($i + 1, 'pg-brewery-taster', array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 1200, 'delay' => 400 + $i * 250, 'distance' => 200)),
        )),
        $el('div', 'pt-3 px-2', lang('Taster Words'), array(
            $heading('h3', $b['name'], 'h6 fw-bold mb-1'),
            $para($styles[$b['style']]['title'], 'small text-body-secondary mb-1'),
            $el('div', 'd-flex justify-content-center', lang('Beer Strength'), array($bw_abv($b['abv'], 'fs-6'))),
        )),
    ));
}
$bw_flight = function ($id, $topics, $class = 'py-5') use ($el, $heading, $para, $container, $row, $col, $fx, $check_list, $bw_eyebrow, $bw_buttons, $bw_tasters, $searchable) {
    return $el('section', $class, lang('Brewery Tasting Flight'), array(
        $container(array(
            $row(array(
                $col(array(
                    $fx($el('div', '', lang('Flight Copy'), array(
                        $bw_eyebrow(lang('Tasting flight')),
                        $heading('h2', lang('Four beers, one paddle.'), 'display-5 fw-bold mb-3'),
                        $para(lang('Four small glasses poured from light to dark, so you taste the whole tap list before you pick a pint.'), 'lead text-body-secondary mb-4'),
                        $check_list(array(lang('Four tasters of 150 ml each'), lang('The beers change with the taps'), lang('Water and bread to clear the palate')), 'list-unstyled mb-4'),
                        $bw_buttons(lang('Flight Buttons'), array(lang('Book a tasting'), '{{page:book_online}}'), array(lang('See all beers'), '{{page:beers}}')),
                    )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700)),
                ), '', '5'),
                $col(array(
                    $el('ol', 'list-unstyled row row-cols-2 row-cols-md-4 g-0 mb-0 pg-brewery-paddle', lang('Flight'), $bw_tasters),
                ), '', '6', '', '12', 'offset-lg-1'),
            ), '4', '', 'align-items-center g-lg-5'),
        )),
    ), $searchable($id, $topics));
};

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
    // The Brewery blocks open it: the hero pours its glass, the limited
    // release, every tap on the dark board; the featured beers, the
    // tasting flight, the tours, the events, the reviews and the news
    // follow. The story lives on the about page.
    'tree'             => $page(array(
        $bw_hero,
        $bw_release,
        $bw_tap_board('bw-home-taps', lang('tap list, draught, on tap today, strength, bitterness')),

        // The featured beers, read from the Beers collection.
        $el('section', 'py-5', lang('Our Beers'), array(
            $container(array(
                $fx($section_header(lang('On tap'), lang('Our beers'), lang('All Beers'), '{{page:beers}}'), $fade_in),
                $widget('home_beers'),
            )),
        )),

        $bw_flight('bw-home-flight', lang('tasting flight, taster, private tasting, sample'), 'py-5 bg-body-tertiary'),

        $el('section', 'py-5', lang('Brewery Tours'), array(
            $container(array(
                $fx($section_header(lang('Behind the bar'), lang('Brewery tours'), lang('All Tours'), '{{page:tours}}'), $fade_in),
                $rise($row($tour_cols, '4')),
            )),
        ), $searchable('bw-home-tours', lang('tour, visit, tasting, brewhouse, group'))),

        // The coming events, read from the Taproom Events collection from
        // today on.
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Events'), array(
            $container(array(
                $fx($section_header(lang('In the taproom'), lang('What is on'), lang('All Events'), '{{page:events}}'), $fade_in),
                $widget('home_events'),
            )),
        )),

        $el('section', 'py-5', lang('Reviews'), array(
            $container(array(
                $fx($section_header(lang('Our guests'), lang('What drinkers say')), $fade_in),
                $rise($row($testimonial_cols, '4'), 120),
            )),
        ), $searchable('bw-home-reviews', lang('reviews, opinions, rating, guests'))),

        $el('section', 'py-5 bg-body-tertiary', lang('From the News'), array(
            $container(array(
                $fx($section_header(lang('News'), lang('From the brewhouse'), lang('All News'), '{{page:blog}}'), $fade_in),
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
        $bw_tap_board('bw-taproom-taps', lang('tap list, draught, on tap today, strength, bitterness')),
        $el('section', 'py-5', lang('Visit'), array(
            $container(array(
                $rise($row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary h-100', lang('Opening Hours'), array(
                            $heading('h2', lang('Opening hours'), 'pbw-display h4 mb-3'),
                            $hours_list(),
                        )),
                    ), '6', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 border h-100', lang('Address'), array(
                            $heading('h2', lang('Find us'), 'pbw-display h4 mb-3'),
                            $el('p', 'd-flex gap-2 mb-2', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span($brewery['address'], '', lang('Address')),
                            )),
                            $el('p', 'd-flex gap-2 mb-0', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($brewery['phone'], 'tel:' . str_replace(' ', '', $brewery['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $para(lang('Guest beers change every week; ask at the bar what is on.'), 'small text-body-secondary mt-3 mb-0'),
                        )),
                    ), '6', '5'),
                ), '4', '', 'g-lg-5')),
            )),
        ), $searchable('bw-taproom-visit', lang('opening hours, open today, address, phone'))),
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
        $bw_flight('bw-taproom-flight', lang('tasting flight, taster, private tasting, sample'), 'py-5 bg-body-tertiary'),
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
        $fx($story_band('bw-our-story', lang('Our story'), lang('From a boat workshop to a brewhouse'),
            array($brewery['story']),
            $photo('about-story', 1200, 900), lang('Two brewers beside a steel fermenter'), false, array(),
            lang('history, founders, who we are, how it started')), $fade_in),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($rise($figure_row()))),
        )),
        $el('section', 'py-5', lang('How We Brew'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('The brewhouse'), lang('How we brew')),
                        $rise($el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Mash'), lang('Crushed malt steeps in hot water and gives up its sugars.')),
                            $step(2, lang('Boil'), lang('The sweet wort boils for an hour while the hops go in.')),
                            $step(3, lang('Ferment'), lang('Yeast turns the sugars into alcohol over one to three weeks.')),
                            $step(4, lang('Condition'), lang('The beer rests cold until it is clear and smooth, then goes into kegs and cans.')),
                        ))),
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
                $rise($row($team_cols, '4')),
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
