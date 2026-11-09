<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Zoo", its public pages: home, today's feeding times and
 * keeper talks, the park map, tickets, memberships, adoptions, about and
 * the questions. Included after parts.php and cards.php in the same scope
 * (their builders, cards and the zoo's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * The pages are built on the Zoo palette blocks
 * (assets/js/style_designer_blocks_zoo.js): today's schedule, the animal
 * spotlight, the park map and the conservation projects are drawn here
 * with the blocks' structure and classes, so the operator edits them as
 * the palette's own; their rules come with the template's styles. Every
 * figure and price comes from parts.php, and no page here links a shop
 * page: the template stands on a site without the shop.
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

// ── The palette blocks, with the zoo's data ─────────────────────────────

// A kind of the schedule as the block says it: an icon and a word, never
// the colour alone.
$kind_pill = function ($kind) use ($el, $icon, $span, $schedule_kinds) {
    $look = array('feed' => 'bg-success-subtle text-success-emphasis', 'talk' => 'bg-info-subtle text-info-emphasis');
    return $el('span', 'badge rounded-pill text-wrap ' . $look[$kind], lang('Status Badge'), array(
        $icon($schedule_kinds[$kind]['icon'], 'me-1'),
        $span($schedule_kinds[$kind]['title'], '', lang('Text')),
    ));
};

// Today's schedule (zoo-today): the opening line, the kinds, a slot per
// feeding or talk with its time, place and kind, the next one marked; and
// a card of good-to-know tips with its way on. The staff edit the slots in
// the designer: the day is the same every day of a season.
$today_block = function ($id, $title, $lead, $more_text, $more_href)
    use ($el, $heading, $para, $span, $icon, $link, $container, $row, $col, $attr, $searchable, $schedule, $animal_place, $kind_pill, $rise) {
    $slots = array();
    foreach ($schedule as $n => $s) {
        $next = $n === 1;
        $head = array(
            $heading('h3', $s['title'], 'pzo-display h5 fw-bold mb-0'),
            $kind_pill($s['kind']),
        );
        if ($next) $head[] = $span(lang('Next up'), 'badge rounded-pill text-bg-success', lang('Badge'));
        $slots[] = $el('li', 'd-flex gap-3 p-3 rounded-3' . ($next ? ' bg-body shadow-sm' : ''), sprintf(lang('Slot %s'), $n + 1), array(
            $el('time', 'pg-zoo-time h4 fw-bold mb-0 text-success', lang('Slot Time'), array(), array('text' => $s['time'], '_attrs' => array($attr('datetime', $s['time'])))),
            $el('div', 'flex-grow-1 border-start border-2 ps-3', lang('Slot Details'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-1', lang('Slot Head'), $head),
                $para($animal_place($s['animal']), 'small text-body-secondary mb-0'),
            )),
        ), $next ? array('_attrs' => array($attr('aria-current', 'time'))) : array());
    }
    $tips = array(
        array('bi-clock', lang('A talk lasts about fifteen minutes; come five minutes early for a front spot.')),
        array('bi-heart', lang('The animals set the pace: a feeding can move a little.')),
        array('bi-universal-access', lang('Every habitat has a step-free viewing spot.')),
    );
    $tip_items = array();
    foreach ($tips as $t) {
        $tip_items[] = $el('li', 'd-flex gap-2', lang('Tip'), array($icon($t[0], 'text-success flex-shrink-0'), $span($t[1], '', lang('Text'))));
    }
    return $el('section', 'py-5 bg-body-tertiary', lang('Today\'s Schedule'), array(
        $container(array(
            $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                $el('div', '', lang('Section Title'), array(
                    $heading('h2', $title, 'pzo-display display-6 fw-bold mb-1'),
                    $para($lead, 'text-body-secondary mb-0'),
                )),
                $el('div', 'd-flex flex-wrap gap-2', lang('Kind Legend'), array($kind_pill('feed'), $kind_pill('talk'))),
            )),
            $row(array(
                $col(array(
                    $rise($el('ol', 'list-unstyled d-grid gap-2 mb-0', lang('Schedule'), $slots)),
                ), '', '8'),
                $col(array(
                    $el('div', 'card h-100 border-0 shadow-sm rounded-4', lang('Tips Card'), array(
                        $el('div', 'card-body p-4', lang('Card Body'), array(
                            $heading('h3', lang('Good to know'), 'pzo-display h5 fw-bold mb-3'),
                            $el('ul', 'list-unstyled d-grid gap-3 mb-4', lang('Tips'), $tip_items),
                            $link($more_text, $more_href, 'btn btn-outline-success rounded-pill'),
                        )),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable($id, lang('feeding times, keeper talk, schedule, today, what\'s on')));
};

// A conservation level as the blocks say it: an icon and the code with
// its words ("EN · Endangered").
$level_pill = function ($status) use ($el, $icon, $span, $conservation) {
    return $el('span', 'badge rounded-pill text-wrap bg-danger-subtle text-danger-emphasis', lang('Status Badge'), array(
        $icon('bi-exclamation-diamond-fill', 'me-1'),
        $span(sprintf(lang('%1$s · %2$s'), $conservation[$status]['code'], $conservation[$status]['title']), '', lang('Text')),
    ));
};

// The animal spotlight (zoo-animal): the photo scaling in, the name, the
// common and Latin name, the story, four facts, the red list's seven
// levels with the animal's own marked and said in words, and the ways to
// adopt it and find it on the map.
$spotlight = function ($id, $key) use ($el, $heading, $para, $span, $image, $link, $container, $row, $col, $attr, $searchable, $photo,
    $animals, $conservation, $animal_habitat, $animal_class) {
    $a = $animals[$key];
    $levels = array();
    foreach ($conservation as $code => $c) {
        $current = $code === $a['status'];
        $levels[] = $el('li', '', lang('Level'), array(
            $span($c['code'], 'badge rounded-pill ' . ($current ? 'text-bg-success fs-6' : 'bg-secondary-subtle text-secondary-emphasis'), lang('Code')),
            $span($c['title'], 'visually-hidden', lang('Text')),
        ), $current ? array('_attrs' => array($attr('aria-current', 'true'))) : array());
    }
    $fact = function ($label, $value) use ($el) {
        return $el('div', 'col', $label, array(
            $el('dt', 'small fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
            $el('dd', 'fw-semibold mb-0', lang('Value'), array(), array('text' => $value)),
        ));
    };
    $picture = $image($photo('spotlight-' . $key, 900, 1100), $a['title'], 'img-fluid w-100 rounded-4 shadow', array('_attrs' => array($attr('loading', 'lazy'))));
    $picture['props']['_fx'] = array('effect' => 'scale', 'trigger' => 'view', 'duration' => 800);
    return $el('section', 'py-5', lang('Animal Spotlight'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('figure', 'mb-0', lang('Animal Photo'), array(
                        $picture,
                        $el('figcaption', 'small text-body-secondary mt-2', lang('Photo Caption'), array(), array('text' => sprintf(lang('Habitat %1$s · %2$s'), $a['number'], $animal_habitat($key)))),
                    )),
                ), '', '5', '', '12', 'mb-4 mb-lg-0'),
                $col(array(
                    $heading('h2', $a['title'], 'pzo-display display-5 fw-bold mb-1'),
                    $el('div', 'mb-3', lang('Species'), array(
                        $span($a['species'], 'fst-italic', lang('Species'), array('_attrs' => array($attr('lang', 'la')))),
                    )),
                    $para($a['summary'], 'lead mb-4'),
                    $el('dl', 'row row-cols-2 row-cols-sm-4 g-3 mb-4', lang('Fact Sheet'), array(
                        $fact(lang('Habitat'), $animal_habitat($key)),
                        $fact(lang('Animal class'), $animal_class($key)),
                        $fact(lang('Diet'), $a['diet']),
                        $fact(lang('Lifespan'), $a['lifespan']),
                    )),
                    $heading('h3', lang('Conservation status'), 'h6 fw-bold mb-2'),
                    $el('ol', 'list-unstyled d-flex flex-wrap align-items-center gap-1 mb-2', lang('Status Scale'), $levels),
                    $para(sprintf(lang('%s on the red list of threatened species.'), $conservation[$a['status']]['title']), 'small text-body-secondary mb-4'),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Actions'), array(
                        $link(lang('Adopt an Animal'), '{{page:adopt}}#zoo-adopt-animals', 'btn btn-success rounded-pill px-4'),
                        $link(lang('Find it on the map'), '{{page:map}}#zoo-map', 'btn btn-outline-secondary rounded-pill px-4'),
                    )),
                ), '', '7'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, lang('animal of the month, giraffe, endangered, red list, spotlight')));
};

// The park map (zoo-map): the map picture, then the zones with their
// numbered habitats (a pin in the zone's colour, the animal named in
// words). The full map adds the amenities along the way; the home's
// teaser ends on the way to the map page instead.
$amenities = array(
    array('bi-cup-hot', lang('Café')),
    array('bi-badge-wc', lang('Toilets')),
    array('bi-bandaid', lang('First aid')),
    array('bi-basket', lang('Picnic area')),
    array('bi-universal-access', lang('Step-free route')),
);
$map_block = function ($id, $title, $lead, $full) use ($el, $heading, $para, $span, $icon, $image, $link, $container, $row, $col, $attr, $searchable,
    $photo, $habitats, $animals, $pin, $amenities, $section_header, $rise) {
    $zones = array();
    foreach ($habitats as $hkey => $h) {
        $here = array();
        foreach ($animals as $a) if ($a['habitat'] === $hkey) $here[(int)$a['number']] = $a;
        ksort($here);
        $items = array();
        foreach ($here as $a) {
            $items[] = $el('li', 'd-flex align-items-center gap-2', lang('Habitat'), array(
                $pin($a['number'], $hkey),
                $span($a['title'], '', lang('Text')),
            ));
        }
        $zones[] = $el('div', 'col', lang('Zone'), array(
            $heading('h3', $h['title'], 'h6 fw-bold mb-2'),
            $el('ul', 'list-unstyled d-grid gap-2 mb-0', lang('Habitats'), $items),
        ));
    }
    $legend = array($rise($el('div', 'row row-cols-1 row-cols-sm-2 g-4 mb-4', lang('Zones'), $zones)));
    if ($full) {
        $items = array();
        foreach ($amenities as $a) $items[] = $el('li', 'd-flex align-items-center gap-2', lang('Amenity'), array($icon($a[0], 'text-success'), $span($a[1], '', lang('Text'))));
        $legend[] = $heading('h3', lang('Along the way'), 'h6 fw-bold mb-2');
        $legend[] = $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-4', lang('Amenities'), $items);
        $legend[] = $link(lang('Today\'s feeding times'), '{{page:today}}', 'btn btn-success rounded-pill px-4');
    } else {
        $legend[] = $link(lang('Open the park map'), '{{page:map}}', 'btn btn-success rounded-pill px-4');
    }
    return $el('section', 'py-5', lang('Park Map'), array(
        $container(array(
            $section_header($title, $lead),
            $row(array(
                $col(array(
                    $el('figure', 'mb-0', lang('Park Map'), array(
                        $image($photo('park-map', 1200, 900), lang('Map of the zoo with its numbered habitats and the step-free loop'), 'img-fluid w-100 rounded-4 border', array('_attrs' => array($attr('loading', 'lazy')))),
                        $el('figcaption', 'small text-body-secondary mt-3', lang('Map Caption'), array(), array('text' => lang('The numbers on the map match the signs at each habitat.'))),
                    )),
                ), '', '7'),
                $col($legend, '', '5'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable($id, lang('map, habitat number, zone, where, route, toilets, café')));
};

// The conservation projects (zoo-conservation): three field projects on
// the soft green ground, each with its level, place and partner, what is
// done and one figure of what it achieved; then the ways to help.
$projects = array(
    array('animal' => 'orangutan', 'where' => lang('Sumatra · with a forest ranger team'),
        'text' => lang('Rangers walk the forest edge with local farmers so that orangutans and crops can live side by side.'),
        'figure' => '42', 'unit' => lang('rangers on patrol')),
    array('animal' => 'penguin', 'where' => lang('Pacific coast · with a seabird centre'),
        'text' => lang('Nesting beaches are kept free of rubbish and dogs, and the colonies are counted every spring.'),
        'figure' => '120', 'unit' => lang('chicks fledged this season')),
    array('animal' => 'turtle', 'where' => lang('Mediterranean coast · with a marine lab'),
        'text' => lang('Volunteers guard the nests at night and help the hatchlings reach the sea.'),
        'figure' => '900', 'unit' => lang('hatchlings reached the sea')),
);
$conservation_block = function ($id) use ($el, $heading, $para, $span, $link, $container, $row, $col, $searchable, $projects, $animals, $level_pill, $rise) {
    $cols = array();
    foreach ($projects as $p) {
        $a = $animals[$p['animal']];
        $cols[] = $col(array(
            $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Project Card'), array(
                $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                    $el('div', 'mb-3', lang('Status'), array($level_pill($a['status']))),
                    $heading('h3', $a['title'], 'pzo-display h5 fw-bold mb-1'),
                    $para($p['where'], 'small text-body-secondary mb-3'),
                    $para($p['text'], 'mb-4'),
                    $el('div', 'd-flex align-items-baseline gap-2 mt-auto pt-3 border-top', lang('Impact'), array(
                        $span($p['figure'], 'pg-zoo-time display-6 fw-bold text-success-emphasis lh-1', lang('Value')),
                        $span($p['unit'], 'small', lang('Label')),
                    )),
                )),
            )),
        ), '6', '4');
    }
    return $el('section', 'py-5 bg-success-subtle', lang('Conservation Projects'), array(
        $container(array(
            $el('div', 'col-lg-8 mb-5', lang('Section Header'), array(
                $heading('h2', lang('Conservation in the wild'), 'pzo-display display-6 fw-bold mb-2'),
                $para(lang('A share of every ticket, membership and adoption goes to the field teams we work with.'), 'lead mb-0'),
            )),
            $rise($row($cols, '4', '', 'mb-5')),
            $el('div', 'd-flex flex-wrap gap-2', lang('Actions'), array(
                $link(lang('Adopt an Animal'), '{{page:adopt}}', 'btn btn-success rounded-pill px-4'),
                $link(lang('Conservation News'), '{{page:blog}}', 'btn btn-outline-success rounded-pill px-4'),
            )),
        )),
    ), $searchable($id, lang('conservation, wild, field project, endangered species, protect')));
};

// ── Home ────────────────────────────────────────────────────────────────
// The day first: a short hero, today's schedule straight under it, then
// the animal spotlight, the habitats, the map teaser and the conservation
// band; no figures row, quotes or questions.

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Homepage'),
    'meta_description' => lang('Feeding times and keeper talks today, animals from six habitats, the park map, tickets, adoptions and our conservation work.'),
    'tree'             => $page(array(
        $el('section', 'pzo-hero py-5 bg-success-subtle', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h1', lang('A day among the animals'), 'pzo-display display-4 fw-bold mb-3'),
                        $para($zoo['tagline'] . '.', 'lead mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Today at the Zoo'), '{{page:today}}', 'btn btn-success btn-lg rounded-pill px-4'),
                            $link(lang('Tickets'), '{{page:tickets}}', 'btn btn-outline-success btn-lg rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $image($photo('hero', 1400, 900), lang('Giraffes reaching for leaves above the savanna path'), 'img-fluid w-100 rounded-4 shadow'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $today_block('zoo-home-today', lang('Today at the zoo'), $zoo['hours'] . '.', lang('The whole day'), '{{page:today}}'),
        $spotlight('zoo-home-spotlight', 'giraffe'),
        $el('section', 'py-5 bg-body-tertiary', lang('Habitats'), array(
            $container(array(
                $section_header(lang('Six habitats, one loop'), $zoo['size'] . '.', lang('All Animals'), '{{page:animals}}'),
                $rise($row(array_map($habitat_card, array_keys($habitats)), '4', '', 'mb-5')),
                $widget('home_animals'),
            )),
        ), $searchable('zoo-home-habitats', lang('animals, habitat, savanna, rainforest, aquarium, farm'))),
        $map_block('zoo-home-map', lang('Find your way'), lang('Every habitat has a number on the map and on its sign.'), false),
        $conservation_block('zoo-home-conservation'),
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $section_header(lang('Coming up'), lang('Night tours, family days and talks'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('News'), array(
            $container(array(
                $section_header(lang('Conservation News'), lang('From the keepers and the field teams'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Today ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'today',
    'name'             => lang('today'),
    'folder'           => 'public',
    'title'            => lang('Today at the Zoo'),
    'meta_description' => lang('The feeding times and keeper talks of the day, where to find them in the park and the opening hours of every part of the zoo.'),
    'tree'             => $page(array(
        $page_header(lang('Today at the Zoo'), lang('Feeding times and keeper talks, the same every day of the season.')),
        $today_block('zoo-today', lang('Feeding times and keeper talks'), $zoo['hours'] . '. ' . $zoo['season'] . '.', lang('Park Map'), '{{page:map}}'),
        $el('section', 'py-5', lang('Opening Hours'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Opening hours'), 'pzo-display h3 mb-3'),
                        $hours_list(),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Make the most of the day'), 'pzo-display h3 mb-3'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Start with the penguins'), lang('The first feeding is on the Polar Coast; the aquarium is quietest before noon.')),
                            $step(2, lang('Picnic by the savanna'), lang('The picnic area looks over the giraffes and has shade all afternoon.')),
                            $step(3, lang('End at the farm'), lang('Brushing time with the goats is the gentlest end of the day for small children.')),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('zoo-hours', lang('opening hours, closing time, winter, aquarium hours'))),
    )),
);

// ── Park map ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'map',
    'name'             => lang('map'),
    'folder'           => 'public',
    'title'            => lang('Park Map'),
    'meta_description' => lang('The zoo\'s numbered habitats by zone, the step-free loop, the café, toilets and first aid, and how to reach the park gate.'),
    'tree'             => $page(array(
        $page_header(lang('Park Map'), $zoo['size'] . '.'),
        $map_block('zoo-map', lang('Habitats by zone'), lang('The numbers on the map match the signs at each habitat.'), true),
        $el('section', 'py-5 bg-body-tertiary', lang('Habitats'), array(
            $container(array(
                $section_header(lang('The habitats'), lang('Choose a habitat to see who lives there.'), lang('All Animals'), '{{page:animals}}'),
                $rise($row(array_map($habitat_card, array_keys($habitats)), '4')),
            )),
        ), $searchable('zoo-habitats', lang('habitat, zone, animals by habitat'))),
        $el('section', 'py-5', lang('Getting Here'), array(
            $container(array(
                $section_header(lang('Getting here'), $zoo['address'] . '.'),
                $rise($row(array(
                    $col(array($el('div', 'd-flex gap-3', lang('Way'), array(
                        $icon_tile('bi-bus-front', 'align-self-start'),
                        $el('div', '', lang('Text'), array(
                            $heading('h3', lang('By bus'), 'pzo-display h5 mb-1'),
                            $para(lang('The river line stops at the park gate every fifteen minutes.'), 'small text-body-secondary mb-0'),
                        )),
                    ))), '', '4'),
                    $col(array($el('div', 'd-flex gap-3', lang('Way'), array(
                        $icon_tile('bi-car-front', 'align-self-start'),
                        $el('div', '', lang('Text'), array(
                            $heading('h3', lang('By car'), 'pzo-display h5 mb-1'),
                            $para(lang('The car park by the gate has spaces for coaches and charging points.'), 'small text-body-secondary mb-0'),
                        )),
                    ))), '', '4'),
                    $col(array($el('div', 'd-flex gap-3', lang('Way'), array(
                        $icon_tile('bi-bicycle', 'align-self-start'),
                        $el('div', '', lang('Text'), array(
                            $heading('h3', lang('By bike or on foot'), 'pzo-display h5 mb-1'),
                            $para(lang('The riverside path leads to the gate; bike racks are inside the car park.'), 'small text-body-secondary mb-0'),
                        )),
                    ))), '', '4'),
                ), '4')),
            )),
        ), $searchable('zoo-getting-here', lang('directions, bus, parking, car park, bike, address'))),
    )),
);

// ── Tickets ─────────────────────────────────────────────────────────────
// The prices of the day in words and figures, from the one table the
// shop reads too; this page names no shop page.

$public_pages[] = array(
    'key'              => 'tickets',
    'name'             => lang('tickets'),
    'folder'           => 'public',
    'title'            => lang('Tickets'),
    'meta_description' => lang('Day tickets for adults, children, seniors and families, the opening hours and the ways to save with a membership or a group visit.'),
    'tree'             => $page(array(
        $page_header(lang('Tickets'), lang('One ticket opens the whole park and the aquarium for the day. Children under three come in free.')),
        $el('section', 'py-5', lang('Ticket Prices'), array(
            $container(array(
                $section_header(lang('Day tickets'), lang('Shown at the gate on the day of your visit.')),
                $rise($row(array_map($ticket_card, array_keys($tickets)), '4')),
            )),
        ), $searchable('zoo-tickets', lang('price, ticket, entry, adult, child, senior, family'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Save on Your Visit'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Opening hours'), 'pzo-display h3 mb-3'),
                        $hours_list(),
                    ), '', '5'),
                    $col(array(
                        $heading('h2', lang('Ways to save'), 'pzo-display h3 mb-3'),
                        $el('ol', 'list-unstyled mb-4', lang('Steps'), array(
                            $step(1, lang('Come back all year'), lang('A membership pays for itself in about three visits.')),
                            $step(2, lang('Come as a group'), lang('Groups of ten or more ask for a guided tour with a group price.')),
                            $step(3, lang('Come with your class'), lang('School trips include a workshop with an education keeper.')),
                        )),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Actions'), array(
                            $link(lang('Memberships'), '{{page:memberships}}', 'btn btn-success rounded-pill px-4'),
                            $link(lang('Ask for a group tour'), '{{page:visit_request}}?' . $query(array('visit' => $visits['tour']['title'])), 'btn btn-outline-success rounded-pill px-4'),
                            $link(lang('Plan a school trip'), '{{page:visit_request}}?' . $query(array('visit' => $visits['school']['title'])), 'btn btn-outline-success rounded-pill px-4'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('zoo-save', lang('discount, membership, group price, school trip'))),
    )),
);

// ── Memberships ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'memberships',
    'name'             => lang('memberships'),
    'folder'           => 'public',
    'title'            => lang('Memberships'),
    'meta_description' => lang('A year of free entry for one, two or the whole family, with early entry, shop discounts and a members\' night tour.'),
    'tree'             => $page(array(
        $page_header(lang('Memberships'), lang('A year of visits, as often as you like, and a share for the animals in the wild.')),
        $el('section', 'py-5', lang('Membership Prices'), array(
            $container(array(
                $rise($row(array(
                    $membership_card('single'),
                    $membership_card('couple'),
                    $membership_card('family', true),
                ), '4', 'center')),
            )),
        ), $searchable('zoo-memberships', lang('membership, annual pass, season ticket, member benefits'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Join'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How to join'), 'pzo-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose your membership'), lang('Single, couple or family: every one gives free entry every day for a year.')),
                            $step(2, lang('Join at the gate'), lang('The membership desk by the gate takes your photo and prints your card.')),
                            $step(3, lang('Come back whenever you like'), lang('Show your card at the members\' lane; no ticket needed.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body shadow-sm', lang('Questions'), array(
                            $heading('h2', lang('Questions about membership?'), 'pzo-display h5 mb-2'),
                            $para(lang('Our membership team answers within a working day.'), 'small text-body-secondary mb-3'),
                            $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-outline-success rounded-pill'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('zoo-join', lang('join, membership card, renew'))),
    )),
);

// ── Adopt ───────────────────────────────────────────────────────────────
// The levels once, the animals that can be adopted beside them, then the
// projects an adoption pays for.

$adoptable = function ($key) use ($el, $heading, $para, $image, $col, $attr, $photo, $animals, $pin, $animal_habitat, $level_pill) {
    $a = $animals[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden', lang('Animal Card'), array(
            $el('div', 'ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('animal-' . $key, 800, 600), $a['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center gap-2 small fw-semibold mb-2', lang('Habitat Tag'), array(
                    $pin($a['number'], $a['habitat']),
                    $el('span', '', lang('Habitat'), array(), array('text' => $animal_habitat($key))),
                )),
                $heading('h3', $a['title'], 'pzo-display h5 mb-2'),
                $el('div', 'mb-2', lang('Status'), array($level_pill($a['status']))),
                $para($a['summary'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};
$adopt_keys = array();
foreach ($animals as $key => $a) if ($a['adopt']) $adopt_keys[] = $key;

$public_pages[] = array(
    'key'              => 'adopt',
    'name'             => lang('adopt'),
    'folder'           => 'public',
    'title'            => lang('Adopt an Animal'),
    'meta_description' => lang('Adopt a giraffe, an orangutan, a penguin or a sea turtle for a year: three levels, a certificate by e-mail and news from the keepers.'),
    'tree'             => $page(array(
        $page_header(lang('Adopt an Animal'), lang('An adoption feeds an animal here for a year and helps its cousins in the wild.')),
        $el('section', 'py-5', lang('Adoption Levels'), array(
            $container(array(
                $section_header(lang('Three levels'), lang('The same for every animal; choose how close you want to be.')),
                $rise($row(array_map($adoption_card, array_keys($adoption_levels)), '4')),
            )),
        ), $searchable('zoo-adopt-levels', lang('adoption price, adoption pack, certificate, gift'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Animals to Adopt'), array(
            $container(array(
                $section_header(lang('Who can be adopted'), lang('Ask at the gate desk or write to us to adopt one of them.'), lang('Contact Us'), '{{page:contact}}'),
                $rise($row(array_map($adoptable, $adopt_keys), '4')),
            )),
        ), $searchable('zoo-adopt-animals', lang('adopt a giraffe, adopt a penguin, adopt an orangutan, gift'))),
        $conservation_block('zoo-adopt-conservation'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About the Zoo'),
    'meta_description' => lang('How a garden of rescued animals became a conservation zoo, the zoo in figures and the keepers who care for the animals.'),
    'tree'             => $page(array(
        $page_header(lang('About the Zoo'), $zoo['tagline'] . '.'),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('From a rescue garden to a conservation zoo'), 'pzo-display h2 mb-3'),
                        $para($zoo['story'], 'lead mb-3'),
                        $para($zoo['licence'] . '.', 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $image($photo('keepers', 1200, 900), lang('Keepers preparing fruit for the orangutans'), 'img-fluid w-100 rounded-4', array('_attrs' => array($attr('loading', 'lazy')))),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
                $figure_row('mt-5'),
            )),
        ), $searchable('zoo-story', lang('history, story, rescue, conservation zoo'))),
        $conservation_block('zoo-about-conservation'),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The keepers'), lang('The people who care for the animals every day')),
                $row(array_map($person_card, array_keys($people)), '4'),
            )),
        ), $searchable('zoo-people', lang('team, director, keeper, curator, education'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: the visit, the animals, and helping. Each question is
// a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pzo-display h4 mb-3'),
        $el('div', 'accordion accordion-flush', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about tickets and opening hours, prams and dogs, feeding times, school visits, memberships and adopting an animal.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Your visit, the animals and how to help. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('zoo-faq-visit', lang('Your visit'), array(
                            array(lang('How long does a visit take?'), lang('Most families stay four to five hours; the whole loop with the aquarium takes about three.'), lang('time, how long, duration')),
                            array(lang('Can I bring a pram or a wheelchair?'), lang('Yes: the main loop is step-free, and wheelchairs can be borrowed at the gate free of charge.'), lang('pram, buggy, wheelchair, accessible')),
                            array(lang('Can I bring my dog?'), lang('Only assistance dogs: other dogs upset the animals. There are shaded kennels by the car park.'), lang('dog, pets, assistance dog')),
                        )),
                        $faq_group('zoo-faq-animals', lang('The animals'), array(
                            array(lang('When are the feeding times?'), lang('The same every day of the season; the Today page lists them with the keeper talks.'), lang('feeding, keeper talk, schedule')),
                            array(lang('Can we feed the animals ourselves?'), lang('Not the zoo\'s animals: their keepers plan every meal. At the farm you can brush the goats at brushing time.'), lang('feed animals, petting, farm')),
                        )),
                        $faq_group('zoo-faq-help', lang('Helping the zoo'), array(
                            array(lang('Where does the money of an adoption go?'), lang('It feeds and cares for the animal for a year, and a share goes to the field projects we support.'), lang('adoption, donation, where the money goes')),
                            array(lang('Can a school class visit?'), lang('Yes: send a visit request with the date and the age of the class, and the education team writes back.'), lang('school trip, class, workshop')),
                            array(lang('Can I give a membership as a gift?'), lang('Yes: the membership desk at the gate makes a gift card that the new member activates on the first visit.'), lang('gift, present, membership')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home's lists: its animals (home_animals) come with the animals, its
// events (home_events) with the events and its articles (home_posts) with
// the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
