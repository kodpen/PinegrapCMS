<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Planetarium", its public pages: home, tonight's sky,
 * visit, tickets, memberships, the observatory and the questions. Included
 * after parts.php and cards.php in the same scope (their builders, cards
 * and the planetarium's data); returns array('pages' => ..., 'widgets' =>
 * ...).
 *
 * The home and the tonight page are built on the Planetarium palette
 * blocks (assets/js/style_designer_blocks_planetarium.js): tonight's sky,
 * the dome shows by day, the objects through the telescope and the moon
 * phases with the telescope nights are drawn here with the blocks'
 * structure and pg-sky-* classes, so the operator edits them as the
 * palette's own; their rules come with the template's styles. Every price
 * comes from parts.php, and no page here links a shop page: the template
 * stands on a site without the shop.
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

// ── The palette blocks, with the planetarium's data ─────────────────────

// A pill that says its meaning with an icon and a word, never the colour
// alone (the blocks' own pill).
$sky_pill = function ($class, $icon_name, $text) use ($el, $icon, $span) {
    return $el('span', 'badge rounded-pill text-wrap ' . $class, lang('Status Badge'), array(
        $icon($icon_name, 'me-1'),
        $span($text, '', lang('Text')),
    ));
};
// A round marker that only decorates: the word beside it carries the
// meaning, so screen readers skip it.
$orb = function ($class, $children) use ($el, $attr) {
    return $el('span', 'pg-sky-orb ' . $class, lang('Marker'), $children, array('_attrs' => array($attr('aria-hidden', 'true'))));
};
// A time as the blocks write it: a <time> element with tabular figures.
$sky_time = function ($at, $class, $name) use ($el, $attr) {
    return $el('time', trim('pg-sky-time ' . $class), $name, array(), array('text' => $at, '_attrs' => array($attr('datetime', $at))));
};
// What an object needs to be seen: the eye, binoculars or a telescope.
$seen_with = function ($need) use ($sky_pill) {
    $look = array(
        'eye'   => array('bg-success-subtle text-success-emphasis', 'bi-eye-fill', lang('Naked eye')),
        'bino'  => array('bg-info-subtle text-info-emphasis', 'bi-binoculars-fill', lang('Binoculars')),
        'scope' => array('bg-primary-subtle text-primary-emphasis', 'bi-search', lang('Telescope')),
    );
    return $sky_pill($look[$need][0], $look[$need][1], $look[$need][2]);
};

// The dark hours of tonight, the cloud cover the staff saw at five and the
// planets up tonight: words the staff edit, never a live feed.
$dark_hours = array(
    array('icon' => 'bi-sunset', 'label' => lang('Sunset'), 'at' => '18:42'),
    array('icon' => 'bi-stars', 'label' => lang('Full darkness'), 'at' => '20:15'),
    array('icon' => 'bi-moon', 'label' => lang('Moonrise'), 'at' => '23:58'),
    array('icon' => 'bi-door-closed', 'label' => lang('Dome closes'), 'at' => '23:00'),
);
$cloud_cover = array(
    array('icon' => 'bi-moon-stars', 'title' => lang('Clear')),
    array('icon' => 'bi-cloud-moon', 'title' => lang('Mostly clear'), 'tonight' => true),
    array('icon' => 'bi-cloud', 'title' => lang('Partly cloudy')),
    array('icon' => 'bi-clouds', 'title' => lang('Mostly cloudy')),
    array('icon' => 'bi-cloud-drizzle', 'title' => lang('Overcast')),
);
$planets_tonight = array(
    array('name' => lang('Venus'), 'where' => lang('Low in the west after sunset'), 'rises' => '18:50', 'sets' => '20:30', 'need' => 'eye'),
    array('name' => lang('Jupiter'), 'where' => lang('High in the south by ten'), 'rises' => '19:10', 'sets' => '04:20', 'need' => 'bino'),
    array('name' => lang('Saturn'), 'where' => lang('In the south-east, rings tilted'), 'rises' => '21:05', 'sets' => '02:40', 'need' => 'scope'),
    array('name' => lang('Mars'), 'where' => lang('Rising in the east before midnight'), 'rises' => '23:30', 'sets' => '05:50', 'need' => 'eye'),
);

// Tonight's sky (planetarium-tonight) on the night ground: whether the
// telescopes open, the dark hours, the cloud cover with tonight's step
// marked and the planets up tonight with what each needs. On the home it
// opens the page, so its title is the page's h1; elsewhere an h2.
$tonight_block = function ($id, $top, $class = 'py-5')
    use ($el, $heading, $para, $span, $icon, $link, $image, $container, $row, $col, $night, $searchable, $attr, $sky_pill, $orb, $sky_time, $seen_with,
         $dark_hours, $cloud_cover, $planets_tonight, $rise, $drift, $photo) {
    $level = (int)substr($top, 1);
    $sub = 'h' . ($level + 1);
    $leaf = 'h' . ($level + 2);
    $moments = array();
    foreach ($dark_hours as $n => $m) {
        $moments[] = $el('div', 'col', lang('Moment'), array(
            $el('dt', 'small fw-normal text-body-secondary d-flex align-items-center gap-2', lang('Label'), array(), array('text' => $m['label'])),
            $el('dd', 'mb-0 d-flex align-items-center gap-2', lang('Moment Time'), array(
                $icon($m['icon'], 'text-primary'),
                $sky_time($m['at'], 'h4 fw-bold mb-0', lang('Time')),
            )),
        ));
    }
    $steps = array();
    foreach ($cloud_cover as $c) {
        $current = !empty($c['tonight']);
        $steps[] = $el('li', 'text-center', lang('Cover Step'), array(
            $icon($c['icon'], $current ? 'fs-3 text-primary' : 'fs-5 text-body-secondary'),
            $span($c['title'], $current ? 'd-block small fw-bold' : 'visually-hidden', lang('Text')),
        ), $current ? array('_attrs' => array($attr('aria-current', 'true'))) : array());
    }
    $planets = array();
    foreach ($planets_tonight as $p) {
        $planets[] = $el('li', 'list-group-item d-flex flex-wrap align-items-center gap-3 py-3 bg-transparent', $p['name'], array(
            $orb('bg-body-secondary text-body-emphasis fw-bold', array($span(mb_substr($p['name'], 0, 1, 'UTF-8'), '', lang('Letter')))),
            $el('div', 'flex-grow-1', lang('Planet Details'), array(
                $heading($leaf, $p['name'], 'h6 fw-bold mb-0'),
                $para($p['where'], 'small text-body-secondary mb-0'),
            )),
            $el('div', 'small text-nowrap', lang('Planet Times'), array(
                $span(lang('Rises'), 'text-body-secondary me-1', lang('Label')),
                $sky_time($p['rises'], 'fw-semibold me-2', lang('Rises')),
                $span(lang('Sets'), 'text-body-secondary me-1', lang('Label')),
                $sky_time($p['sets'], 'fw-semibold', lang('Sets')),
            )),
            $seen_with($p['need']),
        ));
    }
    return $night(array(
        $container(array(
            $row(array(
                $col(array(
                    $span(lang('Tonight\'s sky'), 'd-block small fw-bold text-uppercase text-primary mb-2', lang('Eyebrow')),
                    $heading($top, lang('Clear enough to look up'), 'ppm-display display-6 fw-bold mb-3'),
                    $el('div', 'mb-4', lang('Telescope Status'), array(
                        $sky_pill('text-bg-success', 'bi-check-circle-fill', lang('Telescopes open tonight')),
                    )),
                    $el('dl', 'row row-cols-2 g-3 mb-4', lang('Dark Hours'), $moments),
                    $heading($sub, lang('Cloud cover'), 'h6 fw-bold mb-2'),
                    $el('ol', 'list-unstyled d-flex align-items-end gap-3 mb-2', lang('Cover Scale'), $steps),
                    $para(lang('We decide at 17:00. If the sky closes in, the evening moves into the dome.'), 'small text-body-secondary mb-0'),
                ), '', '5', '', '12', 'mb-4 mb-lg-0'),
                $col(array(
                    $drift($el('div', 'ratio ratio-21x9 rounded-4 overflow-hidden mb-4', lang('Sky Photo'), array(
                        $image($photo('sky', 1400, 600), lang('The Milky Way over the observatory dome'), 'w-100 h-100 object-fit-cover'),
                    ))),
                    $el('div', 'card border-0 shadow-sm', lang('Planets Card'), array(
                        $el('div', 'card-body p-4', lang('Card Body'), array(
                            $heading($sub, lang('Planets up tonight'), 'h5 fw-bold mb-3'),
                            $rise($el('ul', 'list-group list-group-flush mb-4', lang('Planets'), $planets)),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Actions'), array(
                                $link(lang('Telescope Nights'), '{{page:nights}}', 'btn btn-primary'),
                                $link(lang('What is a telescope night?'), '{{page:faq}}#plm-faq-nights', 'btn btn-outline-secondary'),
                            )),
                        )),
                    )),
                ), '', '7'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $class, lang('Tonight\'s Sky'), $searchable($id, lang('tonight, sky, clouds, planets, sunset, telescopes open, darkness')));
};

// Today's dome shows (planetarium-shows): the days of the programme, the
// home_shows list of today's shows from the Dome Shows form, and the way to
// the tickets.
$shows_block = function ($id) use ($el, $heading, $para, $link, $container, $widget, $searchable, $attr, $audience_titles, $query) {
    $days = array(
        $el('li', '', lang('Day Item'), array(
            $link(lang('Today'), '{{page:shows}}', 'btn btn-sm rounded-pill btn-primary', array($attr('aria-current', 'date'))),
        )),
    );
    foreach ($audience_titles as $title) {
        $days[] = $el('li', '', lang('Day Item'), array(
            $link($title, '{{page:shows}}?' . $query(array('audience' => $title)), 'btn btn-sm rounded-pill btn-outline-secondary'),
        ));
    }
    return $el('section', 'py-5', lang('Dome Shows'), array(
        $container(array(
            $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                $el('div', 'ppm-rule border-start border-2 border-primary ps-3', lang('Section Title'), array(
                    $heading('h2', lang('In the dome today'), 'ppm-display h2 fw-semibold mb-1'),
                    $para(lang('Full-dome shows under an eighteen-metre sky. Doors open ten minutes before each show.'), 'text-body-secondary mb-0'),
                )),
                $el('ol', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Days'), $days),
            )),
            $widget('home_shows'),
            $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4', lang('Footer'), array(
                $link(lang('Tickets and Prices'), '{{page:tickets}}', 'btn btn-primary'),
                $link(lang('All Dome Shows'), '{{page:shows}}', 'btn btn-outline-primary'),
                $para(lang('Late arrivals cannot enter once the dome goes dark.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), $searchable($id, lang('dome show, showtime, programme, today, planetarium show')));
};

// Through the telescope (planetarium-objects): what the astronomers point
// the great telescopes at this season, with the brightness and what each
// needs to be seen, and the legend of the three.
$sky_objects = array(
    array('icon' => 'bi-circle-half', 'kind' => lang('Planet'), 'name' => lang('Saturn'), 'where' => lang('In Aquarius, south-east after nine'),
        'text' => lang('The rings and the largest moon, Titan, fit in one view.'), 'mag' => '0.8', 'need' => 'scope'),
    array('icon' => 'bi-stars', 'kind' => lang('Star cluster'), 'name' => lang('The Pleiades'), 'where' => lang('In Taurus, high in the east'),
        'text' => lang('Six stars to the eye, dozens in binoculars: the best first target there is.'), 'mag' => '1.6', 'need' => 'eye'),
    array('icon' => 'bi-cloud-haze2', 'kind' => lang('Nebula'), 'name' => lang('Orion Nebula'), 'where' => lang('In Orion, below the belt'),
        'text' => lang('A cloud where new stars are forming, with four young stars at its heart.'), 'mag' => '4.0', 'need' => 'bino'),
    array('icon' => 'bi-hurricane', 'kind' => lang('Galaxy'), 'name' => lang('Andromeda Galaxy'), 'where' => lang('In Andromeda, overhead early evening'),
        'text' => lang('The farthest thing you can see without help: its light left two and a half million years ago.'), 'mag' => '3.4', 'need' => 'scope'),
);
$objects_block = function ($id) use ($el, $heading, $para, $span, $icon, $container, $row, $col, $searchable, $section_header, $orb, $seen_with, $sky_objects, $fade_in) {
    $cols = array();
    foreach ($sky_objects as $o) {
        $cols[] = $col(array(
            $el('article', 'card h-100 border-0 bg-body-tertiary', lang('Object Card'), array(
                $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                    $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Object Head'), array(
                        $orb('bg-primary-subtle text-primary-emphasis', array($icon($o['icon']))),
                        $el('div', '', lang('Object Kind'), array(
                            $span($o['kind'], 'd-block small fw-bold text-uppercase text-primary', lang('Kind')),
                            $heading('h3', $o['name'], 'ppm-display h5 fw-bold mb-0'),
                        )),
                    )),
                    $para($o['where'], 'small text-body-secondary mb-2'),
                    $para($o['text'], 'mb-4'),
                    $el('dl', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto pt-3 border-top mb-0', lang('Object Facts'), array(
                        $el('div', '', lang('Brightness'), array(
                            $el('dt', 'visually-hidden', lang('Label'), array(), array('text' => lang('Brightness'))),
                            $el('dd', 'small fw-semibold mb-0', lang('Brightness'), array(), array('text' => sprintf(lang('Magnitude %s'), $o['mag']))),
                        )),
                        $el('div', '', lang('Seen With'), array(
                            $el('dt', 'visually-hidden', lang('Label'), array(), array('text' => lang('Seen with'))),
                            $el('dd', 'mb-0', lang('Seen With Value'), array($seen_with($o['need']))),
                        )),
                    )),
                )),
            )),
        ), '6', '3');
    }
    return $el('section', 'py-5', lang('Through the Telescope'), array(
        $container(array(
            $section_header(lang('Through the telescope'), lang('What our astronomers point the big telescope at this season, and what you need to see each one at home.')),
            $fade_in($row($cols, '4', '', 'mb-4')),
            $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Legend'), array(
                $span(lang('Seen with:'), 'small text-body-secondary me-1', lang('Label')),
                $seen_with('eye'), $seen_with('bino'), $seen_with('scope'),
            )),
        )),
    ), $searchable($id, lang('planet, nebula, galaxy, star cluster, binoculars, magnitude, what to see')));
};

// The moon and the telescope nights (planetarium-moon) on the night
// ground: eight phases counted in days from today, the ones with a
// telescope night marked, and the call-off rule.
$moon_phases = array(
    array('icon' => 'bi-circle', 'wane' => false, 'name' => lang('New moon'), 'in' => 0, 'night' => false),
    array('icon' => 'bi-moon', 'wane' => true, 'name' => lang('Waxing crescent'), 'in' => 4, 'night' => true),
    array('icon' => 'bi-circle-half', 'wane' => true, 'name' => lang('First quarter'), 'in' => 7, 'night' => true),
    array('icon' => 'bi-circle-fill', 'wane' => false, 'name' => lang('Waxing gibbous'), 'in' => 11, 'night' => false),
    array('icon' => 'bi-circle-fill', 'wane' => false, 'name' => lang('Full moon'), 'in' => 15, 'night' => true),
    array('icon' => 'bi-circle-fill', 'wane' => false, 'name' => lang('Waning gibbous'), 'in' => 18, 'night' => false),
    array('icon' => 'bi-circle-half', 'wane' => false, 'name' => lang('Last quarter'), 'in' => 22, 'night' => false),
    array('icon' => 'bi-moon', 'wane' => false, 'name' => lang('Waning crescent'), 'in' => 26, 'night' => false),
);
$moon_block = function ($id) use ($el, $heading, $para, $icon, $link, $container, $night, $searchable, $sky_pill, $orb, $moon_phases, $rise) {
    $items = array();
    foreach ($moon_phases as $p) {
        $kids = array(
            $el('div', 'd-flex justify-content-center mb-2', lang('Phase Orb'), array(
                $orb('bg-body-secondary text-body-emphasis', array($icon($p['icon'], $p['wane'] ? 'pg-sky-wane' : ''))),
            )),
            $heading('h3', $p['name'], 'h6 fw-bold mb-1'),
            $para($p['in'] === 0 ? lang('Today') : sprintf(lang('In %s days'), $p['in']), 'small text-body-secondary mb-2'),
        );
        if ($p['night']) $kids[] = $sky_pill('text-bg-primary', 'bi-stars', lang('Telescope night'));
        $items[] = $el('li', 'col text-center', lang('Phase'), $kids);
    }
    return $night(array(
        $container(array(
            $el('div', 'col-lg-8 mb-5', lang('Section Header'), array(
                $heading('h2', lang('The moon and our telescope nights'), 'ppm-display display-6 fw-bold mb-2'),
                $para(lang('We open the telescopes when the moon is young and the sky is dark; a full moon is the night to look at the moon itself.'), 'lead mb-0'),
            )),
            $rise($el('ol', 'list-unstyled row row-cols-2 row-cols-sm-4 row-cols-lg-8 g-4 mb-4', lang('Phases'), $items)),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Footer'), array(
                $link(lang('See all telescope nights'), '{{page:nights}}', 'btn btn-primary'),
                $para(lang('A telescope night is called off by 17:00 if the sky is overcast; your ticket moves to the next one.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), 'py-5', lang('Moon and Telescope Nights'), $searchable($id, lang('moon phase, full moon, new moon, telescope night, called off')));
};

// ── Home ────────────────────────────────────────────────────────────────
// Tonight's sky opens the page (whether the telescopes open comes first,
// no slogan), then today's dome shows, the next telescope nights, the moon
// strip, and the news last.

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Homepage'),
    'meta_description' => lang('Tonight\'s sky and whether the telescopes open, today\'s dome shows, telescope nights, school visits and astronomy news.'),
    'tree'             => $page(array(
        $tonight_block('plm-home-tonight', 'h1'),
        $shows_block('plm-home-shows'),
        $el('section', 'py-5 bg-body-tertiary', lang('Telescope Nights'), array(
            $container(array(
                $section_header(lang('Next telescope nights'), lang('Clear Fridays and Saturdays at the eyepiece, called on the day by our astronomers.'), lang('All Telescope Nights'), '{{page:nights}}'),
                $widget('home_nights'),
            )),
        ), $searchable('plm-home-nights', lang('telescope night, stargazing, eyepiece, observing'))),
        $moon_block('plm-home-moon'),
        $el('section', 'py-5', lang('News'), array(
            $container(array(
                $section_header(lang('From the observatory'), lang('Astronomy news and what to look for this month'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Tonight's sky ───────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'tonight',
    'name'             => lang('tonight'),
    'folder'           => 'public',
    'title'            => lang('Tonight\'s Sky'),
    'meta_description' => lang('Whether the telescopes open tonight, the dark hours, the cloud cover, the planets up and the objects of the season.'),
    'tree'             => $page(array(
        $page_header(lang('Tonight\'s Sky'), lang('What our astronomers see from the roof terrace, written up before the doors open.')),
        $tonight_block('plm-tonight', 'h2'),
        $objects_block('plm-objects'),
        $moon_block('plm-moon'),
        $el('section', 'py-5 bg-body-tertiary', lang('A Telescope Night'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a telescope night goes'), 'ppm-display h3 mb-3'),
                        $para($planetarium['nights'] . '.', 'text-body-secondary mb-4'),
                        $link(lang('Telescope Nights'), '{{page:nights}}', 'btn btn-primary px-4'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Check the sky at five'), lang('We call each night by 17:00 and say it on this page and on the night\'s own page.')),
                            $step(2, lang('Let your eyes adjust'), lang('Twenty minutes in the dark terrace and the faint stars appear; red torches only.')),
                            $step(3, lang('Take your turn at the eyepiece'), lang('An astronomer points each telescope and tells you what you are looking at.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('plm-night-how', lang('stargazing, eyepiece, red torch, what to bring'))),
    )),
);

// ── Visit ───────────────────────────────────────────────────────────────

$grade_items = array();
foreach ($grade_titles as $title) {
    $grade_items[] = $el('li', 'list-inline-item mb-2', lang('Grade'), array(
        $link($title, '{{page:visit_request}}?' . $query(array('grade' => $title)), 'btn btn-sm btn-outline-primary'),
    ));
}

$public_pages[] = array(
    'key'              => 'visit',
    'name'             => lang('visit'),
    'folder'           => 'public',
    'title'            => lang('Visit'),
    'meta_description' => lang('Opening hours, how to reach the planetarium, dome shows for every age and school visits with a show and a workshop.'),
    'tree'             => $page(array(
        $page_header(lang('Visit'), $planetarium['tagline'] . '.', array(), false),
        $el('section', 'py-5', lang('Opening Hours'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Opening hours'), 'ppm-display h3 mb-3'),
                        $hours_list(),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Finding us'), 'ppm-display h3 mb-3'),
                        $para($planetarium['address'], 'fw-semibold mb-2'),
                        $para($planetarium['darksky'] . '.', 'text-body-secondary mb-3'),
                        $check_list(array(
                            lang('Buses stop at the foot of Observatory Hill'),
                            lang('Free parking beside the dome after 17:00'),
                            lang('Step-free entrance and lift to the roof terrace'),
                            lang('Warm clothes for the telescope nights, even in summer'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('plm-visit-hours', lang('opening times, address, parking, bus, accessibility, directions'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Dome Shows by Audience'), array(
            $container(array(
                $section_header(lang('A show for every age'), lang('Choose the dome shows made for you'), lang('All Dome Shows'), '{{page:shows}}'),
                $rise($row(array_map($audience_card, array_keys($audiences)), '3')),
            )),
        ), $searchable('plm-visit-audiences', lang('families, children, adults, schools, age'))),
        $el('section', 'py-5', lang('School Visits'), array(
            $container(array(
                $section_header(lang('School and group visits'), lang('A request, not a booking: our education team writes back with a date.')),
                $rise($row(array_map($visit_card, array_keys($visits)), '4', '', 'mb-4')),
                $heading('h3', lang('Request a visit for your grade'), 'h6 fw-bold mb-2'),
                $el('ul', 'list-inline mb-0', lang('Grades'), $grade_items),
            )),
        ), $searchable('plm-visit-schools', lang('school trip, class visit, workshop, teacher, group, curriculum'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Requesting a Visit'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a visit request goes'), 'ppm-display h3 mb-3'),
                        $para(lang('Tell us the class, the date you would like and the show you have in mind; we answer under your request.'), 'text-body-secondary mb-4'),
                        $link(lang('Request a School Visit'), '{{page:visit_request}}', 'btn btn-primary px-4'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Send a request'), lang('Choose the kind of visit, the grade, the number of pupils and a date.')),
                            $step(2, lang('Hear from us'), lang('We confirm the date or put you on the waiting list, and answer under your request.')),
                            $step(3, lang('Come under the dome'), lang('Arrive fifteen minutes early; an astronomer meets the class at the door.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('plm-visit-how', lang('request, booking, confirmation, waiting list'))),
    )),
);

// ── Tickets ─────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'tickets',
    'name'             => lang('tickets'),
    'folder'           => 'public',
    'title'            => lang('Tickets'),
    'meta_description' => lang('Dome show tickets for adults, children and families, telescope night tickets, and what a membership saves.'),
    'tree'             => $page(array(
        $page_header(lang('Tickets'), lang('One ticket for one show or one night; under three free on a lap.'), array(), false),
        $el('section', 'py-5', lang('Ticket Prices'), array(
            $container(array(
                $section_header(lang('Ticket prices'), lang('The same prices at the box office and online.')),
                $rise($row(array_map($ticket_card, array_keys($tickets)), '4')),
            )),
        ), $searchable('plm-ticket-prices', lang('price, cost, adult, child, family ticket, telescope night ticket'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Using Your Ticket'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Using your ticket'), 'ppm-display h3 mb-3'),
                        $para(lang('Your receipt is your ticket: show it at the door on your phone or on paper.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose a show or a night'), lang('Pick the day and the showtime from the programme.')),
                            $step(2, lang('Show your receipt'), lang('The box office opens at 09:30, half an hour before the first show.')),
                            $step(3, lang('Take your seat'), lang('Doors open ten minutes before the show; late arrivals cannot enter once the dome is dark.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('plm-ticket-use', lang('receipt, box office, door, late arrival'))),
        $el('section', 'py-5', lang('Membership Note'), array(
            $container(array(
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3 p-4 rounded-4 bg-primary-subtle text-primary-emphasis', lang('Membership Band'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Coming often?'), 'ppm-display h4 mb-1'),
                        $para(lang('A membership gives a year of free dome shows and cheaper telescope nights.'), 'mb-0'),
                    )),
                    $link(lang('Memberships'), '{{page:memberships}}', 'btn btn-primary'),
                )),
            )),
        )),
    )),
);

// ── Memberships ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'memberships',
    'name'             => lang('memberships'),
    'folder'           => 'public',
    'title'            => lang('Memberships'),
    'meta_description' => lang('A year of the sky: free dome shows, telescope nights, the members\' sky letter and star parties for one or the whole family.'),
    'tree'             => $page(array(
        $page_header(lang('Memberships'), lang('A year of the sky, and the observatory kept open for everyone.')),
        $el('section', 'py-5', lang('Membership Levels'), array(
            $container(array(
                $rise($row(array_map($membership_card, array_keys($memberships)), '4', 'center')),
            )),
        ), $searchable('plm-membership-levels', lang('membership, single, family, patron, price a year, benefits'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Joining'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Joining'), 'ppm-display h3 mb-3'),
                        $para(lang('A membership starts on the day you join and runs for twelve months.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose a membership'), lang('At the box office, or online where the planetarium sells online.')),
                            $step(2, lang('Show your receipt'), lang('The box office adds your name to the members\' list.')),
                            $step(3, lang('Come back often'), lang('Give your name at the box office for a free seat in the dome.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('plm-membership-join', lang('join, renew, box office, members\' list'))),
    )),
);

// ── The observatory ─────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'observatory',
    'name'             => lang('observatory'),
    'folder'           => 'public',
    'title'            => lang('The Observatory'),
    'meta_description' => lang('How one rooftop telescope became a planetarium and a public observatory, its telescopes and the astronomers who open it.'),
    'tree'             => $page(array(
        $page_header(lang('The Observatory'), $planetarium['dome'] . '.'),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('From one telescope on a roof'), 'ppm-display h2 mb-3'),
                        $para($planetarium['story'], 'text-body-secondary mb-3'),
                        $para($planetarium['darksky'] . '.', 'small fw-semibold mb-0'),
                    ), '', '6'),
                    $col(array(
                        $image($photo('observatory', 1200, 900), lang('The observatory dome open under the evening sky'), 'img-fluid w-100 rounded-4', array('_attrs' => array($attr('loading', 'lazy')))),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('plm-story', lang('history, observatory, dome, founded'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Telescopes'), array(
            $container(array(
                $section_header(lang('The telescopes'), lang('Three instruments, each with its own night')),
                $fade_in($row(array_map($telescope_card, array_keys($telescopes)), '4')),
            )),
        ), $searchable('plm-telescopes', lang('refractor, reflector, solar telescope, mirror, lens'))),
        $el('section', 'py-5', lang('Our Astronomers'), array(
            $container(array(
                $section_header(lang('The astronomers'), lang('Who opens the dome and answers the questions')),
                $row(array_map($person_card, array_keys($people)), '4'),
            )),
        ), $searchable('plm-team', lang('astronomer, director, education, team, staff'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: dome shows and tickets, telescope nights, school
// visits. Each question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'ppm-display h4 mb-3'),
        $el('div', 'accordion accordion-flush', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about dome shows and tickets, telescope nights and the weather, memberships and school visits.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Shows, telescope nights and school visits. Not answered here? Write to us.'), array(), false),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('plm-faq-shows', lang('Dome shows and tickets'), array(
                            array(lang('Which show suits small children?'), lang('The shows for Small Children are short, the lights never go fully dark and a child may sit on a lap.'), lang('children, toddler, age, dark')),
                            array(lang('Can I come in after the show starts?'), lang('No: once the dome is dark the doors stay shut, so please arrive ten minutes early.'), lang('late, doors, start')),
                            array(lang('Do children under three need a ticket?'), lang('No, they come in free on a lap.'), lang('baby, free, child ticket')),
                        )),
                        $faq_group('plm-faq-nights', lang('Telescope nights'), array(
                            array(lang('What is a telescope night?'), lang('An evening on the roof terrace or at the dark-sky site, taking turns at our telescopes with an astronomer beside you.'), lang('stargazing, observing, eyepiece')),
                            array(lang('What happens if it is cloudy?'), lang('We call each night by 17:00. A night called off moves your ticket to the next one, or the evening goes into the dome.'), lang('weather, clouds, called off, cancelled')),
                        )),
                        $faq_group('plm-faq-schools', lang('School visits and memberships'), array(
                            array(lang('Is a school visit request a booking?'), lang('No: send a request and our education team writes back with a date, or puts you on the waiting list.'), lang('school trip, booking, class')),
                            array(lang('How do I reach the planetarium?'), sprintf(lang('The planetarium is at %s. Buses stop at the foot of the hill.'), $planetarium['address']), lang('bus, car, parking, directions')),
                            array(lang('What does a membership include?'), lang('Free dome shows for a year, and cheaper or free telescope nights by level.'), lang('membership, member, benefits')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home's lists: its shows (home_shows) come with the shows, its
// nights (home_nights) with the nights and its articles (home_posts) with
// the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
