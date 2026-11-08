<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort", its public pages: home, today's
 * conditions, passes, rental, the ski school, lodging, about and the
 * questions. Included after parts.php and cards.php in the same scope
 * (their builders, cards and the resort's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * The home and the conditions page are built on the Ski Resort palette
 * blocks (assets/js/style_designer_blocks_skiresort.js): the conditions
 * board, the lift status, the snow report and the pass cards are drawn
 * here with the blocks' structure and classes, so the operator edits them
 * as the palette's own; their rules come with the template's styles.
 * Every figure comes from parts.php, and no page here links a shop page:
 * the template stands on a site without the shop.
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

// ── The palette blocks, with the resort's data ──────────────────────────

// An entrance the operator can change in the Interactions section: the
// children of the node rise one after another as it scrolls into view.
$rise = function ($node) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 90);
    return $node;
};

// A status as the blocks say it: an icon and a word, never the colour alone.
$status_pill = function ($status) use ($el, $icon, $span, $run_statuses) {
    $look = array(
        'open'    => array('bg-success-subtle text-success-emphasis', 'bi-check-circle-fill'),
        'closed'  => array('bg-danger-subtle text-danger-emphasis', 'bi-x-circle-fill'),
        'on_hold' => array('bg-warning-subtle text-warning-emphasis', 'bi-pause-circle-fill'),
    );
    return $el('span', 'badge rounded-pill text-wrap ' . $look[$status][0], lang('Status Badge'), array(
        $icon($look[$status][1], 'me-1'),
        $span($run_statuses[$status], '', lang('Text')),
    ));
};

// How many of a list are open, as the board says it ("4 / 5").
$open_count = function ($list) {
    $open = 0;
    foreach ($list as $item) if ($item['status'] === 'open') $open++;
    return sprintf(lang('%1$s / %2$s'), $open, count($list));
};

// The conditions board (skiresort-conditions): today's figures on a dark
// band, the slopes and lifts open first, then the staff's words for the
// snow and the weather, and the ways to the lifts and the snow report.
$conditions_board = function ($id, $title, $lead, $links)
    use ($el, $heading, $para, $span, $icon, $link, $container, $row, $col, $searchable, $conditions, $slopes, $lifts, $open_count, $status_pill, $rise) {
    $tiles = array(
        array('icon' => 'bi-signpost-split', 'value' => $open_count($slopes), 'label' => lang('Slopes open')),
        array('icon' => 'bi-arrow-up-right', 'value' => $open_count($lifts), 'label' => lang('Lifts open')),
    );
    foreach ($conditions as $c) $tiles[] = array('icon' => $c['icon'], 'value' => $c['value'], 'label' => $c['label']);
    $cols = array();
    foreach ($tiles as $t) {
        $cols[] = $col(array(
            $el('div', 'h-100 p-3 rounded-3 border', lang('Figure Tile'), array(
                $icon($t['icon'], 'd-block fs-4 mb-2 text-primary'),
                $span($t['value'], 'psk-stat d-block h4 fw-bold mb-1', lang('Value')),
                $span($t['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '4', '3', '6');
    }
    $buttons = array();
    foreach ($links as $n => $l) $buttons[] = $link($l[0], $l[1], 'btn ' . ($n === count($links) - 1 ? 'btn-primary' : 'btn-secondary'));
    return $el('section', 'py-5 bg-body text-body pg-ski-board', lang('Conditions Board'), array(
        $container(array(
            $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Board Head'), array(
                $el('div', '', lang('Board Title'), array(
                    $heading('h2', $title, 'psk-display display-6 fw-bold mb-1'),
                    $para($lead, 'text-body-secondary mb-0'),
                )),
                $status_pill('open'),
            )),
            $rise($row($cols, '3')),
            $el('div', 'd-flex flex-wrap align-items-center justify-content-end gap-2 mt-4 pt-4 border-top', lang('Board Foot'), $buttons),
        )),
    ), array_merge($searchable($id, lang('snow, weather, open slopes, open lifts, temperature, wind, avalanche')),
        array('_attrs' => array(array('name' => 'data-bs-theme', 'value' => 'dark')))));
};

// The lift status (skiresort-lifts): a card per area, a line per lift with
// its kind, ride, capacity and status. The staff change the status each
// morning; the lift list page shows the same lifts from the Lifts form.
$lift_status_block = function ($id) use ($el, $heading, $para, $span, $icon, $link, $container, $row, $col, $searchable, $areas, $lifts, $lift_kinds, $open_count, $status_pill, $rise) {
    $kind_icons = array('gondola' => 'bi-arrow-up-right', 'chair' => 'bi-arrow-up-right', 'drag' => 'bi-arrow-up', 'carpet' => 'bi-arrow-right');
    $cols = array();
    foreach ($areas as $area_key => $a) {
        $items = array();
        foreach ($lifts as $l) {
            if ($l['area'] !== $area_key) continue;
            $items[] = $el('li', 'list-group-item d-flex align-items-center gap-3 px-0 py-3', $l['title'], array(
                $icon($kind_icons[$l['kind']], 'fs-4 text-primary flex-shrink-0'),
                $el('div', 'flex-grow-1', lang('Lift Details'), array(
                    $heading('h4', $l['title'], 'h6 fw-semibold mb-1'),
                    $para(sprintf(lang('%1$s · %2$s · %3$s'), $lift_kinds[$l['kind']], $l['ride'], $l['capacity']), 'small text-body-secondary mb-0'),
                )),
                $status_pill($l['status']),
            ));
        }
        if (!$items) continue;
        $cols[] = $col(array(
            $el('div', 'card h-100 border-0 shadow-sm', lang('Area Card'), array(
                $el('div', 'card-body p-4', lang('Card Body'), array(
                    $heading('h3', $a['title'], 'psk-display h5 fw-bold mb-1'),
                    $para($a['summary'], 'small text-body-secondary mb-2'),
                    $el('ul', 'list-group list-group-flush', lang('Lift List'), $items),
                )),
            )),
        ), '', '4');
    }
    return $el('section', 'py-5 bg-body-tertiary pg-ski-board', lang('Lift Status'), array(
        $container(array(
            $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                $el('div', '', lang('Section Title'), array(
                    $heading('h2', lang('Lift status'), 'psk-display fw-bold mb-1'),
                    $para(lang('A lift on hold waits for the wind to drop; the ski patrol opens it again as soon as it is safe.'), 'text-body-secondary mb-0'),
                )),
                $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Lift Count'), array(
                    $span(sprintf(lang('%s lifts open'), $open_count($lifts)), 'fw-semibold', lang('Text')),
                    $link(lang('All Lifts'), '{{page:lifts}}', 'link-primary fw-semibold'),
                )),
            )),
            $rise($row($cols, '4')),
        )),
    ), $searchable($id, lang('lift, gondola, chairlift, drag lift, open, closed, on hold')));
};

// The snow report (skiresort-snow): the depth at each station, the new
// snow, the last snowfall and the avalanche danger scale with today's
// level marked. Words and figures the staff write each morning.
$snow_stations = array(
    array('name' => lang('Village'), 'height' => lang('1,450 m'), 'depth' => lang('65 cm'), 'width' => 'w-25'),
    array('name' => lang('Pine Forest'), 'height' => lang('2,000 m'), 'depth' => lang('100 cm'), 'width' => 'w-50'),
    array('name' => lang('Summit'), 'height' => lang('2,600 m'), 'depth' => lang('140 cm'), 'width' => 'w-75'),
);
$snow_report = function ($id) use ($el, $heading, $para, $span, $container, $row, $col, $searchable, $attr, $snow_stations, $rise) {
    $stations = array();
    foreach ($snow_stations as $n => $s) {
        $stations[] = $el('li', 'mb-4', lang('Station'), array(
            $el('div', 'd-flex justify-content-between gap-3 mb-2', lang('Station Head'), array(
                $el('div', '', lang('Station Name'), array(
                    $heading('h4', $s['name'], 'h6 fw-semibold mb-0'),
                    $span($s['height'], 'small text-body-secondary', lang('Height')),
                )),
                $span($s['depth'], 'psk-stat h4 fw-bold mb-0', lang('Depth')),
            )),
            $el('div', 'progress', lang('Depth Bar'), array(
                $el('div', 'progress-bar ' . $s['width'], lang('Depth')),
            ), array('_attrs' => array(
                $attr('role', 'progressbar'),
                $attr('aria-label', sprintf(lang('Snow depth at %s'), $s['name'])),
                $attr('aria-valuenow', substr($s['width'], 2)),
                $attr('aria-valuemin', '0'),
                $attr('aria-valuemax', '100'),
            ))),
        ));
    }
    $fresh = array();
    foreach (array(array(lang('20 cm'), lang('24 hours')), array(lang('25 cm'), lang('48 hours')), array(lang('45 cm'), lang('7 days'))) as $f) {
        $fresh[] = $el('div', 'col', lang('New Snow'), array(
            $el('div', 'h-100 p-3 rounded-3 bg-body text-center', lang('New Snow Tile'), array(
                $span($f[0], 'psk-stat d-block h3 fw-bold mb-0', lang('Value')),
                $span($f[1], 'd-block small text-body-secondary', lang('Label')),
            )),
        ));
    }
    $levels = array();
    foreach (array(lang('Low danger'), lang('Moderate danger'), lang('Considerable danger'), lang('High danger'), lang('Very high danger')) as $n => $name) {
        $today = $n === 1;
        $kids = array(
            $span((string)($n + 1), 'badge rounded-pill ' . ($today ? 'text-bg-primary' : 'text-bg-secondary'), lang('Level')),
            $span($name, $today ? 'fw-bold' : '', lang('Text')),
        );
        if ($today) $kids[] = $span(lang('Today'), 'badge rounded-pill bg-warning-subtle text-warning-emphasis ms-auto', lang('Today'));
        $levels[] = $el('li', 'd-flex align-items-center gap-2 py-1', lang('Danger Level'), $kids,
            $today ? array('_attrs' => array($attr('aria-current', 'true'))) : array());
    }
    return $el('section', 'py-5 bg-body-tertiary pg-ski-board', lang('Snow Report'), array(
        $container(array(
            $el('div', 'mb-4', lang('Section Header'), array(
                $heading('h2', lang('Snow report'), 'psk-display fw-bold mb-1'),
                $para(lang('Measured at three stations every morning before the lifts open.'), 'text-body-secondary mb-0'),
            )),
            $row(array(
                $col(array(
                    $heading('h3', lang('Snow depth'), 'h5 fw-bold mb-3'),
                    $el('ul', 'list-unstyled mb-0', lang('Stations'), $stations),
                ), '', '6'),
                $col(array(
                    $heading('h3', lang('New snow'), 'h5 fw-bold mb-3'),
                    $rise($el('div', 'row row-cols-3 g-2 mb-3', lang('New Snow Tiles'), $fresh)),
                    $para(lang('Light, dry snow overnight: powder on the upper slopes, packed snow below the forest.'), 'mb-4'),
                    $heading('h3', lang('Avalanche danger'), 'h5 fw-bold mb-2'),
                    $el('ol', 'list-unstyled mb-2', lang('Danger Scale'), $levels),
                    $para(lang('Off-piste only with a guide, transceiver, probe and shovel.'), 'small text-body-secondary mb-0'),
                ), '', '6'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable($id, lang('snow depth, fresh snow, powder, avalanche, off-piste')));
};

// The pass cards (skiresort-passes): one card per kind of pass, a price
// line for each choice and what every choice includes. The prices come
// from the one table the shop reads too.
$pass_cards = function ($id, $title, $lead, $buttons)
    use ($el, $heading, $para, $span, $link, $container, $row, $col, $searchable, $check_list, $lift_passes, $lift_pass_prices, $season_passes, $season_pass_prices, $rental, $rental_prices, $money, $rise) {
    $lines = function ($items, $prices) use ($el, $money) {
        $out = array();
        foreach ($items as $key => $item) {
            $out[] = $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom', lang('Price Line'), array(
                $el('dt', 'fw-normal', lang('Choice'), array(), array('text' => $item['title'])),
                $el('dd', 'psk-price fw-bold mb-0 text-end', lang('Price'), array(), array('text' => $money($prices[$key]))),
            ));
        }
        return $el('dl', 'mb-4', lang('Prices'), $out);
    };
    $cards = array(
        array(lang('Lift passes'), lang('From an afternoon to a whole week, for every lift of the resort.'), $lines($lift_passes, $lift_pass_prices),
            array(lang('Every lift of the resort'), lang('Night skiing with a day pass or longer'), lang('Collected at the ticket office')), false),
        array(lang('Season passes'), lang('Every day of the winter, for one skier or the whole family.'), $lines($season_passes, $season_pass_prices),
            $season_passes['adult']['benefits'], true),
        array(lang('Rental, per day'), lang('Fitted by our technicians at the foot of the gondola.'), $lines($rental, $rental_prices),
            array(lang('Serviced after every day'), lang('Swap sizes at no charge'), lang('Collected at the rental shop')), false),
    );
    $cols = array();
    foreach ($cards as $n => $c) {
        $head = $c[4]
            ? $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Card Head'), array(
                $heading('h3', $c[0], 'psk-display h4 fw-bold mb-0'),
                $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary', lang('Badge')),
            ))
            : $heading('h3', $c[0], 'psk-display h4 fw-bold mb-1');
        $cols[] = $col(array(
            $el('div', 'card h-100 ' . ($c[4] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Pass Card'), array(
                $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                    $head,
                    $para($c[1], 'text-body-secondary mb-3'),
                    $c[2],
                    $check_list($c[3], 'list-unstyled small mb-4'),
                    $link($buttons[$n][0], $buttons[$n][1], 'btn mt-auto ' . ($c[4] ? 'btn-primary' : 'btn-outline-primary')),
                )),
            )),
        ), '6', '4');
    }
    return $el('section', 'py-5', lang('Pass Cards'), array(
        $container(array(
            $el('div', 'text-center mb-5', lang('Section Header'), array(
                $heading('h2', $title, 'psk-display fw-bold mb-2'),
                $para($lead, 'lead text-body-secondary mb-0'),
            )),
            $rise($row($cols, '4', 'center')),
        )),
    ), $searchable($id, lang('price, ticket, skipass, season pass, rental, cost')));
};

// ── Home ────────────────────────────────────────────────────────────────
// The mountain first, then today's board straight under it: the resort's
// own order, not a stock row of bands.

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Homepage'),
    'meta_description' => lang('Today\'s snow and open lifts, slopes for every level, lift passes, the ski school and places to stay at a family ski resort.'),
    'tree'             => $page(array(
        $el('section', 'psk-hero py-5', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($resort['altitude'], 'psk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h1', lang('Fresh tracks above the pine forest'), 'psk-display display-4 fw-bold mb-3'),
                        $para($resort['tagline'], 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Today\'s Conditions'), '{{page:conditions}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Lift Passes'), '{{page:passes}}', 'btn btn-outline-primary btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $image($photo('hero', 1400, 1000), lang('Skiers on a sunny slope above the pine forest'), 'img-fluid w-100 rounded-4 shadow'),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $conditions_board('skr-home-today', lang('Today on the mountain'), lang('Updated by the slope team every morning before the first lift.'), array(
            array(lang('Lift status'), '{{page:conditions}}#skr-lifts'),
            array(lang('Snow report'), '{{page:conditions}}#skr-snow'),
        )),
        $el('section', 'py-5', lang('Slopes'), array(
            $container(array(
                $section_header(lang('Slopes for every level'), lang('Find your slope'), lang('All Slopes'), '{{page:slopes}}'),
                $row(array_map($difficulty_card, array_keys($difficulties)), '4', '', 'mb-5'),
                $widget('home_slopes'),
            )),
        ), $searchable('skr-home-slopes', lang('piste, run, beginner, expert, difficulty'))),
        $pass_cards('skr-home-passes', lang('Passes and rental'), lang('One table of prices for the whole winter. Children under six ski free with an adult.'), array(
            array(lang('All Lift Passes'), '{{page:passes}}'),
            array(lang('Season Passes'), '{{page:passes}}#skr-season'),
            array(lang('Rental'), '{{page:rental}}'),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Ski School'), array(
            $container(array(
                $section_header(lang('Ski school'), lang('Lessons for every age'), lang('The Ski School'), '{{page:ski_school}}'),
                $row(array($lesson_card('snow_cubs'), $lesson_card('junior'), $lesson_card('adult_start')), '4'),
            )),
        ), $searchable('skr-home-school', lang('lesson, instructor, children, beginner, ski school'))),
        $el('section', 'py-5', lang('Events'), array(
            $container(array(
                $section_header(lang('On the calendar'), lang('Coming up at the resort'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('News'), array(
            $container(array(
                $section_header(lang('From the mountain'), lang('News'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Today's conditions ──────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'conditions',
    'name'             => lang('conditions'),
    'folder'           => 'public',
    'title'            => lang('Today\'s Conditions'),
    'meta_description' => lang('Open slopes and lifts, snow depth, new snow, temperature, wind and avalanche danger, updated by the slope team every morning.'),
    'tree'             => $page(array(
        $page_header(lang('Today\'s Conditions'), lang('What the slope team found on the mountain this morning.')),
        $conditions_board('skr-today', lang('Today on the mountain'), $resort['hours'] . '.', array(
            array(lang('All Slopes'), '{{page:slopes}}'),
            array(lang('Lift Passes'), '{{page:passes}}'),
        )),
        $lift_status_block('skr-lifts'),
        $snow_report('skr-snow'),
        $el('section', 'py-5', lang('Opening Hours'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Opening hours'), 'psk-display h3 mb-3'),
                        $hours_list(),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Before you go up'), 'psk-display h3 mb-3'),
                        $check_list(array(
                            lang('Check the lift status: a lift on hold may open later in the day'),
                            lang('Wear a helmet; children under 16 must'),
                            lang('Keep to marked slopes; closed slopes are roped off'),
                            lang('Save the ski patrol\'s number before you set off'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('skr-hours', lang('opening times, night skiing, ticket office, safety'))),
    )),
);

// ── Passes ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'passes',
    'name'             => lang('passes'),
    'folder'           => 'public',
    'title'            => lang('Lift Passes'),
    'meta_description' => lang('Half-day, day and multi-day lift passes, season passes for every age and the rental prices, collected at the ticket office.'),
    'tree'             => $page(array(
        $page_header(lang('Lift Passes'), lang('Every pass opens every lift of the resort. Children under six ski free with an adult.')),
        $pass_cards('skr-passes', lang('Choose your pass'), lang('The prices of the season, from an afternoon to the whole winter.'), array(
            array(lang('Ask About Groups'), '{{page:group_enquiry}}'),
            array(lang('Season Pass Benefits'), '#skr-season'),
            array(lang('Rental'), '{{page:rental}}'),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Season Passes'), array(
            $container(array(
                $section_header(lang('The whole winter'), lang('Season pass benefits')),
                $row(array_map($season_pass_card, array_keys($season_passes)), '4'),
            )),
        ), $searchable('skr-season', lang('season pass, family pass, senior, young, discount'))),
        $el('section', 'py-5', lang('Collecting a Pass'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Collecting your pass'), 'psk-display h3 mb-3'),
                        $para(lang('Passes are cards you keep for the next visit: bring yours back and we load the new pass on it.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose your pass'), lang('At the ticket office or, where the resort sells online, from the shop.')),
                            $step(2, lang('Show your receipt'), lang('The ticket office at the foot of the gondola opens at 08:00.')),
                            $step(3, lang('Take the lift'), lang('Hold the card near the gate; it opens every lift your pass covers.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('skr-collect', lang('ticket office, card, receipt, gate'))),
    )),
);

// ── Rental ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'rental',
    'name'             => lang('rental'),
    'folder'           => 'public',
    'title'            => lang('Rental'),
    'meta_description' => lang('Ski sets, snowboard sets, children\'s sets and helmets by the day, fitted by our technicians at the rental shop.'),
    'tree'             => $page(array(
        $page_header(lang('Rental'), lang('Everything you need for the slopes, fitted at the foot of the gondola.')),
        $el('section', 'py-5', lang('Rental Equipment'), array(
            $container(array(
                $rise($row(array_map($rental_card, array_keys($rental)), '4')),
            )),
        ), $searchable('skr-rental', lang('hire, skis, boots, snowboard, helmet, price per day'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How Rental Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How rental works'), 'psk-display h3 mb-3'),
                        $para(lang('Come to the rental shop with your height, weight and shoe size, or let our technicians measure you.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Get fitted'), lang('Boots first, then skis or a board set for your level.')),
                            $step(2, lang('Ski all day'), lang('Leave your equipment in the heated ski room overnight.')),
                            $step(3, lang('Swap or return'), lang('Change sizes at no charge, and return everything before 17:00.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('skr-rental-how', lang('fitting, size, ski room, return'))),
    )),
);

// ── The ski school ──────────────────────────────────────────────────────

$level_items = array();
foreach ($levels as $level) {
    $level_items[] = $el('li', 'list-inline-item mb-2', lang('Level'), array(
        $link($level, '{{page:lesson_request}}?' . $query(array('level' => $level)), 'btn btn-sm btn-outline-primary'),
    ));
}

$public_pages[] = array(
    'key'              => 'ski_school',
    'name'             => lang('ski-school'),
    'folder'           => 'public',
    'title'            => lang('Ski School'),
    'meta_description' => lang('Ski and snowboard lessons for children, teenagers and adults, from the first day on snow to off-piste with a guide.'),
    'tree'             => $page(array(
        $page_header(lang('Ski School'), lang('Eighty instructors, small groups and a lesson for every age and level.'), array(
            $el('ul', 'list-inline mt-3 mb-0', lang('Levels'), $level_items),
        )),
        $el('section', 'py-5', lang('Lessons'), array(
            $container(array(
                $row(array_map($lesson_card, array_keys($lessons)), '4'),
            )),
        ), $searchable('skr-lessons', lang('lesson, course, group, private, snowboard, children'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Requesting a Lesson'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a lesson request goes'), 'psk-display h3 mb-3'),
                        $para(lang('A request is not a booking: the ski school matches you with an instructor and writes back.'), 'text-body-secondary mb-4'),
                        $link(lang('Request a Lesson'), '{{page:lesson_request}}', 'btn btn-primary px-4'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Send a request'), lang('Choose the lesson, the level, the age group and the first day.')),
                            $step(2, lang('Hear from us'), lang('We book an instructor or put you on the waiting list, and answer under your request.')),
                            $step(3, lang('Meet on the slope'), lang('Groups meet at the ski school flag on First Steps at the time we send.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('skr-school-how', lang('request, booking, instructor, meeting point'))),
        $el('section', 'py-5', lang('Guest Quotes'), array(
            $container(array(
                $row(array_map($testimonial_card, array_keys($testimonials)), '4'),
            )),
        )),
    )),
);

// ── Lodging ─────────────────────────────────────────────────────────────

$group_items = array();
foreach ($group_kinds as $kind) {
    $group_items[] = $el('li', 'list-inline-item mb-2', lang('Group'), array(
        $link($kind, '{{page:group_enquiry}}?' . $query(array('group' => $kind)), 'btn btn-sm btn-secondary'),
    ));
}

$public_pages[] = array(
    'key'              => 'lodging',
    'name'             => lang('lodging'),
    'folder'           => 'public',
    'title'            => lang('Lodging'),
    'meta_description' => lang('A ski-in hotel, chalets in the forest, village apartments and a mountain hut: places to stay, with enquiries for groups.'),
    'tree'             => $page(array(
        $page_header(lang('Lodging'), lang('From a night above the clouds to a chalet for the whole family.')),
        $el('section', 'py-5', lang('Places to Stay'), array(
            $container(array(
                $rise($row(array_map($stay_card, array_keys($lodging)), '4')),
            )),
        ), $searchable('skr-stays', lang('hotel, chalet, apartment, hut, accommodation, night'))),
        $el('section', 'py-5 bg-primary-subtle text-primary-emphasis', lang('Groups'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Coming as a group?'), 'psk-display h3 mb-2'),
                        $para(lang('Stays, passes and lessons in one offer: tell us who is coming and we write back with a plan.'), 'mb-0'),
                    ), '', '5'),
                    $col(array(
                        $el('ul', 'list-inline mb-0', lang('Group Kinds'), $group_items),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('skr-groups', lang('group, school trip, club, company, offer'))),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About the Resort'),
    'meta_description' => lang('How a village drag lift became a family ski resort, the mountain in figures and the people who run it every winter.'),
    'tree'             => $page(array(
        $page_header(lang('About the Resort'), $resort['tagline'] . '.'),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('From one drag lift to eleven'), 'psk-display h2 mb-3'),
                        $para($resort['story'], 'text-body-secondary mb-3'),
                        $para($resort['licence'], 'small fw-semibold mb-0'),
                    ), '', '6'),
                    $col(array(
                        $image($photo('village', 1200, 900), lang('The village under snow with the gondola rising behind it'), 'img-fluid w-100 rounded-4', array('_attrs' => array($attr('loading', 'lazy')))),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
                $figure_row('mt-5'),
            )),
        ), $searchable('skr-story', lang('history, village, family, resort'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The people'), lang('Who runs the mountain')),
                $row(array_map($person_card, array_keys($people)), '4'),
            )),
        ), $searchable('skr-team', lang('team, director, staff, ski patrol'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: the slopes, passes and lessons, and staying. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'psk-display h4 mb-3'),
        $el('div', 'accordion accordion-flush', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about the slopes and the weather, lift passes, rental, lessons for children and adults, and places to stay.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('The slopes, passes, lessons and staying. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('skr-faq-mountain', lang('On the mountain'), array(
                            array(lang('What happens when a lift is on hold?'), lang('It waits for the wind to drop or for the ski patrol\'s check. The conditions page shows when it opens again.'), lang('wind, closed, lift status')),
                            array(lang('Is there skiing for complete beginners?'), lang('Yes: First Steps is a fenced nursery slope with a magic carpet, and the green slopes are wide and gentle.'), lang('beginner, first time, nursery slope')),
                        )),
                        $faq_group('skr-faq-passes', lang('Passes and lessons'), array(
                            array(lang('Do young children need a lift pass?'), lang('Children under six ski free with an adult holding a pass; ask for their free card at the ticket office.'), lang('children, free, age, kids')),
                            array(lang('Can I book a lesson for a fixed time?'), lang('Send a lesson request with the first day you would like. The ski school writes back with the instructor and the meeting time.'), lang('lesson time, booking, instructor')),
                            array(lang('Can I rent equipment for one day only?'), lang('Yes, every rental is by the day, and you can swap sizes at no charge.'), lang('rental, hire, one day')),
                        )),
                        $faq_group('skr-faq-stay', lang('Staying'), array(
                            array(lang('Can you arrange a group trip?'), lang('Yes: send a group enquiry and we put stays, passes and lessons in one offer.'), lang('group, school trip, club')),
                            array(lang('How do I reach the resort?'), sprintf(lang('The resort is at %s. A bus runs from the village to the gondola every twenty minutes.'), $resort['address']), lang('bus, car, parking, directions')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home's lists: its slopes (home_slopes) come with the slopes, its
// events (home_events) with the events and its articles (home_posts) with
// the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
