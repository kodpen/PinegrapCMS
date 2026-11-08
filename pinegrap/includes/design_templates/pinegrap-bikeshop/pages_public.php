<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bike Shop", its public pages: home, the workshop, the
 * frame sizes, the bike fit and about. Included after parts.php and
 * cards.php in the same scope (their builders, cards and the shop's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * The pages are built on the Bike Shop palette blocks
 * (assets/js/style_designer_blocks_bikeshop.js): the workshop board, the
 * frame size chart, the group rides' frame and the bike fit are drawn here
 * with the blocks' structure and classes, so the operator edits them as
 * the palette's own; their rules come with the template's styles. Every
 * price comes from parts.php's one price table, and only the home links a
 * shop page, and only while the shop is on: the template stands on a site
 * without it.
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

// With the shop on, the home's bikes and parts lead to the catalog; without
// it they lead to the pages that stand alone.
$shop_on = defined('ECOMMERCE') && ECOMMERCE;

// ── The palette blocks, with the shop's data ────────────────────────────

// A pill as the blocks draw it: an icon and a word, never the colour alone.
$block_pill = function ($look, $icon_name, $text) use ($el, $icon, $span) {
    return $el('span', 'badge rounded-pill text-wrap ' . $look, lang('Status Badge'), array(
        $icon($icon_name, 'me-1'),
        $span($text, '', lang('Text')),
    ));
};

// The paces in the colours of the rides block, each said in words.
$pace_legend = function () use ($el, $block_pill, $paces) {
    $look = array(
        'social' => 'bg-success-subtle text-success-emphasis',
        'steady' => 'bg-info-subtle text-info-emphasis',
        'fast'   => 'bg-danger-subtle text-danger-emphasis',
    );
    $pills = array();
    foreach ($paces as $key => $p) $pills[] = $block_pill($look[$key], $p['icon'], $p['title']);
    return $el('div', 'd-flex flex-wrap gap-2', lang('Pace Legend'), $pills);
};

// The workshop board's load column (bikeshop-workshop): how busy the
// workshop is this week on a scale of four words, the wait in a sentence
// and the next free drop-off with what to bring. The staff change the
// current level and the day in the designer: there is no live queue.
$load_column = function ($title_tag, $title) use ($el, $heading, $para, $span, $icon, $link, $attr) {
    $levels = array();
    foreach (array(lang('Quiet'), lang('Steady'), lang('Busy'), lang('Full')) as $n => $word) {
        $current = $n === 2;
        $levels[] = $el('li', '', sprintf(lang('Level %s'), $n + 1), array(
            $span($word, 'badge rounded-pill ' . ($current ? 'text-bg-warning fs-6' : 'bg-secondary-subtle text-secondary-emphasis'), lang('Text')),
        ), $current ? array('_attrs' => array($attr('aria-current', 'true'))) : array());
    }
    $tips = array();
    foreach (array(
        array('bi-key', lang('The key of any lock left on the bike.')),
        array('bi-battery-charging', lang('An e-bike\'s battery and charger.')),
        array('bi-chat-left-text', lang('A word on what the bike does wrong: noises, slipping, rubbing.')),
    ) as $n => $t) {
        $tips[] = $el('li', 'd-flex gap-2', sprintf(lang('Tip %s'), $n + 1), array($icon($t[0], 'text-primary flex-shrink-0'), $span($t[1], '', lang('Text'))));
    }
    return array(
        $span(lang('Workshop'), 'd-block small fw-bold text-uppercase text-primary mb-2', lang('Label')),
        $heading($title_tag, $title, 'pbs-display display-6 text-uppercase mb-3'),
        $el('ol', 'list-unstyled d-flex flex-wrap gap-1 mb-2', lang('Load Scale'), $levels),
        $para(lang('Busy: a bike dropped off today is ready in about three working days.'), 'mb-4'),
        $el('div', 'card rounded-0 border-0 shadow-sm', lang('Drop-off Card'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $heading('h3', lang('Next free drop-off'), 'h6 fw-bold mb-1'),
                $para(lang('Thursday from 09:00'), 'h4 fw-bold text-primary pg-bike-num mb-3'),
                $el('ul', 'list-unstyled d-grid gap-2 small mb-4', lang('Bring Along'), $tips),
                $link(lang('Request a Service'), '{{page:service_request}}', 'btn btn-primary rounded-0 w-100'),
            )),
        )),
    );
};

// The workshop board's jobs column: every job of the workshop with what it
// is and how long it takes, as a pill of a clock and the words.
$jobs_column = function () use ($el, $heading, $para, $block_pill, $job_kinds, $rise) {
    $items = array();
    foreach (array_values($job_kinds) as $n => $j) {
        $items[] = $el('li', 'd-flex flex-wrap flex-sm-nowrap align-items-start gap-3 py-3 border-bottom', sprintf(lang('Job %s'), $n + 1), array(
            $el('div', 'flex-grow-1', lang('Job Details'), array(
                $heading('h3', $j['title'], 'h5 fw-bold mb-1'),
                $para($j['summary'], 'small text-body-secondary mb-0'),
            )),
            $block_pill('bg-body-secondary text-body-emphasis pg-bike-num', 'bi-clock', $j['turnaround']),
        ));
    }
    return array(
        $heading('h3', lang('How long it takes'), 'h5 fw-bold mb-2'),
        $rise($el('ul', 'list-unstyled mb-3', lang('Jobs'), $items)),
        $para(lang('We call before any work beyond the price we agreed.'), 'small text-body-secondary mb-0'),
    );
};

// The frame size chart (bikeshop-sizes): the lettered drawing, the two
// measures that matter most, and a row per size of the shop's size option
// with the rider heights from parts.php and the sample frame's geometry.
$size_chart = function ($id) use ($el, $heading, $para, $span, $image, $link, $container, $row, $col, $attr, $searchable, $photo, $frame_sizes) {
    $key = function ($mark) use ($span) {
        return $span($mark, 'pg-bike-key text-bg-primary', lang('Key'));
    };
    $cell = function ($tag, $text, $class = '', $scope = '') use ($el, $span, $attr) {
        return $el($tag, $class, $tag === 'th' ? lang('Header Cell') : lang('Cell'), array($span($text, '', lang('Text'))),
            $scope !== '' ? array('_attrs' => array($attr('scope', $scope))) : array());
    };
    $head = function ($mark, $text) use ($el, $span, $attr, $key) {
        $k = $key($mark);
        $k['props']['cssClass'] .= ' me-1';
        return $el('th', 'text-nowrap', lang('Header Cell'), array($k, $span($text, '', lang('Text'))), array('_attrs' => array($attr('scope', 'col'))));
    };
    // Stack, reach, seat tube and top tube of the sample frame, in mm.
    $geometry = array(
        'S'  => array('598', '420', '380', '575'),
        'M'  => array('616', '445', '420', '605'),
        'L'  => array('634', '470', '460', '632'),
        'XL' => array('652', '495', '500', '660'),
    );
    $rows = array();
    foreach ($frame_sizes as $size => $riders) {
        $cells = array($cell('th', $size, 'fw-bold', 'row'), $cell('td', $riders));
        foreach ($geometry[$size] as $mm) $cells[] = $cell('td', $mm . ' mm');
        $rows[] = $el('tr', '', lang('Size Row'), $cells);
    }
    $measure = function ($mark, $title, $text) use ($el, $heading, $para, $key) {
        return $el('li', 'd-flex gap-2', $title, array(
            $key($mark),
            $el('div', '', lang('Measure Text'), array(
                $heading('h3', $title, 'h6 fw-bold mb-0'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ));
    };
    return $el('section', 'py-5', lang('Frame Sizes'), array(
        $container(array(
            $el('div', 'col-lg-8 mb-4', lang('Section Header'), array(
                $heading('h2', lang('Find your frame size'), 'pbs-display display-6 text-uppercase mb-2'),
                $para(lang('Start from your height, then compare stack and reach with the bike you ride now.'), 'lead mb-0'),
            )),
            $row(array(
                $col(array(
                    $el('figure', 'mb-4', lang('Geometry Drawing'), array(
                        $image($photo('geometry', 900, 600), lang('Side view of a frame with stack, reach, seat tube and top tube marked A to D'), 'img-fluid w-100 border',
                            array('_attrs' => array($attr('loading', 'lazy')))),
                        $el('figcaption', 'small text-body-secondary mt-2', lang('Drawing Caption'), array(
                            $span(lang('The letters on the drawing match the table.'), '', lang('Text')),
                        )),
                    )),
                    $el('ul', 'list-unstyled d-grid gap-3 mb-0', lang('Measures'), array(
                        $measure('A', lang('Stack'), lang('How high the bars sit: more stack, a more upright ride.')),
                        $measure('B', lang('Reach'), lang('How far forward the bars sit: more reach, a longer, lower ride.')),
                    )),
                ), '', '5', '', '12', 'mb-4 mb-lg-0'),
                $col(array(
                    $el('div', 'table-responsive', lang('Table Wrap'), array(
                        $el('table', 'table align-middle pg-bike-num mb-0', lang('Size Table'), array(
                            $el('caption', 'small text-body-secondary', lang('Table Caption'), array(
                                $span(lang('Frame sizes of our trail hardtail; every model has its own chart.'), '', lang('Text')),
                            )),
                            $el('thead', '', lang('Table Head'), array(
                                $el('tr', '', lang('Header Row'), array(
                                    $cell('th', lang('Size'), '', 'col'),
                                    $cell('th', lang('Rider height'), 'text-nowrap', 'col'),
                                    $head('A', lang('Stack')),
                                    $head('B', lang('Reach')),
                                    $head('C', lang('Seat tube')),
                                    $head('D', lang('Top tube')),
                                )),
                            )),
                            $el('tbody', '', lang('Table Body'), $rows),
                        )),
                    )),
                    $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4', lang('Actions'), array(
                        $para(lang('Between two sizes? A fitting settles it.'), 'mb-0'),
                        $link(lang('Book a Bike Fit'), '{{page:fit}}', 'btn btn-outline-primary rounded-0'),
                    )),
                ), '', '7'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable($id, lang('frame size, size chart, height, stack, reach, geometry, small, medium, large')));
};

// The bike fit (bikeshop-fit): the fit photo fading in, the five contact
// points numbered as on the photo, on the fit page the fit sheet a rider
// takes home, and the way on.
$fit_block = function ($id, $with_sheet, $more_text, $more_href)
    use ($el, $heading, $para, $span, $image, $link, $container, $row, $col, $attr, $searchable, $photo, $rise, $fade_in) {
    $points = array();
    foreach (array(
        array(lang('Saddle height'), lang('Set from the knee angle at the bottom of the stroke.')),
        array(lang('Saddle setback'), lang('Where the saddle sits over the pedals.')),
        array(lang('Reach to the bars'), lang('Stem length and bar position for a relaxed back.')),
        array(lang('Bar drop'), lang('How far the bars sit below the saddle.')),
        array(lang('Cleats'), lang('Angle and position under the foot, for knees that track straight.')),
    ) as $n => $p) {
        $points[] = $el('li', 'd-flex gap-3', sprintf(lang('Point %s'), $n + 1), array(
            $span((string)($n + 1), 'pg-bike-key text-bg-primary', lang('Key')),
            $el('div', '', lang('Point Text'), array(
                $heading('h3', $p[0], 'h6 fw-bold mb-0'),
                $para($p[1], 'small text-body-secondary mb-0'),
            )),
        ));
    }
    $text = array(
        $span(lang('Bike Fit'), 'd-block small fw-bold text-uppercase text-primary mb-2', lang('Label')),
        $heading('h2', lang('Five points where you meet the bike'), 'pbs-display display-6 text-uppercase mb-3'),
        $para(lang('A fit sets your riding position on your own bike. We watch you pedal, measure, change one thing at a time and ride again.'), 'mb-4'),
        $rise($el('ol', 'list-unstyled d-grid gap-3 mb-4', lang('Contact Points'), $points)),
    );
    if ($with_sheet) {
        $readings = array();
        foreach (array(array(lang('Saddle height'), '742 mm'), array(lang('Setback'), '58 mm'), array(lang('Reach'), '512 mm'), array(lang('Bar drop'), '64 mm')) as $n => $r) {
            $readings[] = $el('div', 'col', sprintf(lang('Reading %s'), $n + 1), array(
                $el('dt', 'small fw-normal text-body-secondary', lang('Term'), array(), array('text' => $r[0])),
                $el('dd', 'h5 fw-bold pg-bike-num mb-0', lang('Value'), array(), array('text' => $r[1])),
            ));
        }
        $text[] = $el('div', 'card rounded-0 border-0 shadow-sm mb-4', lang('Fit Sheet'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $heading('h3', lang('What you take home'), 'h6 fw-bold mb-3'),
                $el('dl', 'row row-cols-2 row-cols-sm-4 g-3 mb-0', lang('Fit Readings'), $readings),
            )),
        ));
    }
    $text[] = $link($more_text, $more_href, 'btn btn-primary rounded-0 px-4');
    return $el('section', 'py-5 bg-body-tertiary', lang('Bike Fit'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('figure', 'mb-0', lang('Fit Photo'), array(
                        $fade_in($image($photo('fit', 1000, 800), lang('A rider on a bike in the fit studio, the five contact points numbered'), 'img-fluid w-100 shadow',
                            array('_attrs' => array($attr('loading', 'lazy'))))),
                        $el('figcaption', 'small text-body-secondary mt-2', lang('Photo Caption'), array(
                            $span(lang('The numbers on the photo match the list.'), '', lang('Text')),
                        )),
                    )),
                ), '', '6', '', '12', 'mb-4 mb-lg-0'),
                $col($text, '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, lang('bike fit, riding position, saddle height, cleats, back pain, knee pain')));
};

// ── Home ────────────────────────────────────────────────────────────────
// A workbench, not a slogan: the workshop's week on one side and the
// newest bikes on the other, then the rides, the fit, the parts by group
// and the news.

$bike_tile = function ($key) use ($el, $heading, $para, $span, $image, $attr, $photo, $products, $product_prices, $bike_kinds, $frame_sizes, $money, $pill) {
    $p = $products[$key];
    return $el('li', 'd-flex gap-3 py-3 border-bottom', lang('Bike'), array(
        $image($photo('bike-' . $key, 320, 240), $p['title'], 'flex-shrink-0 object-fit-cover',
            array('_attrs' => array($attr('width', '160'), $attr('height', '120')))),
        $el('div', 'flex-grow-1', lang('Bike Details'), array(
            $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-1', lang('Bike Head'), array(
                $heading('h3', $p['title'], 'pbs-display h5 text-uppercase mb-0'),
                $pill($bike_kinds[$p['kind']]['icon'], $bike_kinds[$p['kind']]['title']),
            )),
            $para($p['summary'], 'small text-body-secondary mb-2'),
            $el('p', 'd-flex flex-wrap align-items-baseline gap-3 small mb-0', lang('Price and Sizes'), array(
                $span($money($product_prices[$key]), 'pbs-price pg-bike-num fw-bold fs-5', lang('Price')),
                $span(sprintf(lang('Frame sizes %s'), implode(' · ', array_keys($frame_sizes))), 'text-body-secondary', lang('Sizes')),
            )),
        )),
    ));
};
$new_bikes = array();
foreach ($products as $key => $p) {
    if ($p['group'] === 'bikes' && !empty($p['featured'])) $new_bikes[] = $bike_tile($key);
}
$bike_links = array($link(lang('Find Your Frame Size'), '{{page:sizes}}', 'btn btn-outline-dark rounded-0'));
if ($shop_on) array_unshift($bike_links, $link(lang('All Bikes'), '{{page:shop}}', 'btn btn-dark rounded-0'));

// The catalog's groups beside the bikes: what each holds and its lowest
// price, said in words.
$group_tiles = array();
foreach ($product_groups as $group_key => $g) {
    if ($group_key === 'bikes') continue;
    $names = array();
    $low = null;
    foreach ($products as $key => $p) {
        if ($p['group'] !== $group_key) continue;
        $names[] = $p['title'];
        if ($low === null || $product_prices[$key]['USD'] < $product_prices[$low]['USD']) $low = $key;
    }
    $group_tiles[] = $col(array(
        $el('article', 'h-100 p-4 bg-body border-top border-4 border-dark', lang('Group Card'), array(
            $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Card Head'), array(
                $icon_tile($g['icon']),
                $heading('h3', $g['title'], 'pbs-display h5 text-uppercase mb-0'),
            )),
            $para(implode(', ', $names), 'small text-body-secondary mb-2'),
            $span(sprintf(lang('From %s'), $money($product_prices[$low])), 'pbs-price pg-bike-num d-block fw-bold', lang('Price')),
        )),
    ), '6', '4');
}

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Homepage'),
    'meta_description' => lang('A bike shop with a repair workshop: this week\'s workshop board, the newest bikes, group rides, bike fitting, parts and news.'),
    'tree'             => $page(array(
        $el('section', 'pbs-workbench py-5 bg-body-tertiary border-bottom border-4 border-primary', lang('Workbench'), array(
            $container(array(
                $row(array(
                    $col($load_column('h1', lang('The workshop this week')), '', '5', '', '12', 'mb-4 mb-lg-0'),
                    $col(array(
                        $el('div', 'pbs-mark border-start border-4 border-primary ps-3 mb-2', lang('Section Title'), array(
                            $heading('h2', lang('Newest bikes'), 'pbs-display h2 text-uppercase mb-1'),
                            $para($shop['collect'] . '.', 'text-body-secondary mb-0'),
                        )),
                        $rise($el('ul', 'list-unstyled mb-4', lang('Bikes'), $new_bikes)),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $bike_links),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-home-workbench', lang('workshop, repair, service, drop-off, new bikes, road, gravel, mountain'))),
        $el('section', 'py-5', lang('Group Rides'), array(
            $container(array(
                $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                    $el('div', 'pbs-mark border-start border-4 border-primary ps-3', lang('Section Title'), array(
                        $heading('h2', lang('Ride with us this week'), 'pbs-display h2 text-uppercase mb-1'),
                        $para(lang('Free and open to every rider; bring lights once the evenings draw in.'), 'text-body-secondary mb-0'),
                    )),
                    $pace_legend(),
                )),
                $widget('home_rides'),
                $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4', lang('Actions'), array(
                    $link(lang('All Group Rides'), '{{page:rides}}', 'btn btn-primary rounded-0'),
                )),
            )),
        ), $searchable('bikeshop-home-rides', lang('group ride, club ride, cafe ride, pace, no-drop'))),
        $fit_block('bikeshop-home-fit', false, lang('Bike Fit Packages'), '{{page:fit}}'),
        $el('section', 'py-5', lang('Parts and Accessories'), array(
            $container(array(
                $section_header(lang('Parts and accessories'), lang('Tubes, chains, pads, lights and locks on the shelf; posted or collected.'),
                    $shop_on ? lang('Visit the Shop') : '', $shop_on ? '{{page:shop}}' : ''),
                $rise($row($group_tiles, '4')),
            )),
        ), $searchable('bikeshop-home-parts', lang('spare parts, tube, chain, brake pads, helmet, lights, lock, voucher'))),
        $el('section', 'py-5 bg-body-tertiary', lang('News'), array(
            $container(array(
                $section_header(lang('News'), lang('From the workshop and the rides'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── The workshop ────────────────────────────────────────────────────────

// The prices as a price list (services-price-list): the quick jobs, then
// the services; the turnaround beside each and the price from.
$price_list = function ($title, $keys) use ($el, $heading, $span, $col, $job_kinds, $job_price_text) {
    $items = array();
    foreach ($keys as $key) {
        $j = $job_kinds[$key];
        $items[] = $el('li', 'list-group-item d-flex justify-content-between align-items-start gap-3 px-0 py-3 bg-transparent', $j['title'], array(
            $el('div', '', lang('Item Copy'), array(
                $span($j['title'], 'fw-semibold d-block', lang('Name')),
                $span(sprintf(lang('%1$s · %2$s'), $j['turnaround'], $j['includes'][0]), 'small text-body-secondary', lang('Details')),
            )),
            $span($job_price_text($key), 'pbs-price pg-bike-num fw-semibold text-nowrap', lang('Price')),
        ));
    }
    return $col(array(
        $heading('h3', $title, 'h5 fw-bold mb-2'),
        $el('ul', 'list-group list-group-flush', lang('Price List'), $items),
    ), '', '6');
};

$public_pages[] = array(
    'key'              => 'workshop',
    'name'             => lang('workshop'),
    'folder'           => 'public',
    'title'            => lang('Workshop'),
    'meta_description' => lang('Puncture repairs, safety checks, full services, overhauls, suspension and e-bike checks: how busy the workshop is, how long and the prices.'),
    'tree'             => $page(array(
        $page_header(lang('Workshop'), $shop['workshop'] . '.', array(
            $el('div', 'd-flex flex-wrap gap-2 mt-3', lang('Buttons'), array(
                $link(lang('Request a Service'), '{{page:service_request}}', 'btn btn-primary rounded-0 px-4'),
                $link(lang('My Service Requests'), '{{page:my_services}}', 'btn btn-outline-dark rounded-0 px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Workshop Board'), array(
            $container(array(
                $row(array(
                    $col($load_column('h2', lang('The workshop this week')), '', '5', '', '12', 'mb-4 mb-lg-0'),
                    $col($jobs_column(), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-workshop-board', lang('waiting time, drop-off, how long, turnaround, busy'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Prices'), array(
            $container(array(
                $section_header(lang('Prices'), lang('Labour from the price below; parts are quoted and agreed before we fit them.')),
                $row(array(
                    $price_list(lang('Repairs and checks'), array('puncture', 'safety', 'ebike')),
                    $price_list(lang('Services'), array('service', 'overhaul', 'suspension')),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-workshop-prices', lang('price, cost, labour, service price, repair price'))),
        $el('section', 'py-5', lang('Bike Kinds'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Every kind of bike'), 'pbs-display h3 text-uppercase mb-2'),
                        $para(lang('Choose your bike to start a service request with it filled in.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col(array($bike_kind_pills()), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('bikeshop-workshop-bikes', lang('road bike, gravel bike, mountain bike, e-bike, children\'s bike'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How a Service Goes'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a service goes'), 'pbs-display h3 text-uppercase mb-3'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Send a request'), lang('Choose the job, your bike and the day you would like to drop it off.')),
                            $step(2, lang('Hear from us'), lang('The workshop books your bike in and writes back under your request.')),
                            $step(3, lang('Drop it off and collect it'), lang('We call before any extra work, and again when the bike is ready.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $heading('h2', lang('Opening hours'), 'pbs-display h3 text-uppercase mb-3'),
                        $hours_list(),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-workshop-how', lang('booking, drop-off times, collection, opening hours'))),
    )),
);

// ── Frame sizes ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'sizes',
    'name'             => lang('sizes'),
    'folder'           => 'public',
    'title'            => lang('Frame Sizes'),
    'meta_description' => lang('Which frame size fits your height, what stack and reach mean, and how to measure your inseam before you choose a bike.'),
    'tree'             => $page(array(
        $page_header(lang('Frame Sizes'), lang('Every bike comes in four sizes. Your height is the start; a test ride or a fit settles it.')),
        $size_chart('bikeshop-sizes-chart'),
        $el('section', 'py-5 bg-body-tertiary', lang('Measuring'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Measuring your inseam'), 'pbs-display h3 text-uppercase mb-3'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Stand against a wall'), lang('In socks, feet a hand apart, with a book held level between your legs.')),
                            $step(2, lang('Mark the top of the book'), lang('Raise it gently until it touches, then mark the wall.')),
                            $step(3, lang('Measure to the floor'), lang('That height is your inseam; bring it to a fit or a test ride.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $heading('h2', lang('Before you choose'), 'pbs-display h3 text-uppercase mb-3'),
                        $check_list(array(
                            lang('Sizes differ between makers: compare stack and reach, not the letter'),
                            lang('Long legs and a short back often suit the smaller size'),
                            lang('A test ride round the yard is free with every bike'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-sizes-measure', lang('inseam, leg length, measure, test ride'))),
    )),
);

// ── Bike fit ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'fit',
    'name'             => lang('bike-fit'),
    'folder'           => 'public',
    'title'            => lang('Bike Fit'),
    'meta_description' => lang('A bike fit sets your riding position on your own bike: saddle, bars and cleats, with a basic, full or cleat fit in our studio.'),
    'tree'             => $page(array(
        $page_header(lang('Bike Fit'), lang('Your riding position set on your own bike, for comfort, power and knees that last.')),
        $fit_block('bikeshop-fit-points', true, lang('Ask for a Bike Fit'), '{{page:fit_request}}'),
        $el('section', 'py-5', lang('Fit Packages'), array(
            $container(array(
                $section_header(lang('Fit packages'), lang('Every package is on your own bike, in the fitting studio behind the shop.')),
                $rise($row(array($fit_card('basic'), $fit_card('full', true), $fit_card('cleat')), '4', 'center')),
            )),
        ), $searchable('bikeshop-fit-packages', lang('fit price, basic fit, full fit, cleat fit'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How a Fit Goes'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a fit goes'), 'pbs-display h3 text-uppercase mb-3'),
                        $para(lang('A request is not a booking: the fitter reads it and writes back with a time.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Ask for a Bike Fit'), '{{page:fit_request}}', 'btn btn-primary rounded-0'),
                            $link(lang('Book a Fit Online'), '{{page:book_online}}', 'btn btn-outline-dark rounded-0'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Tell us how you ride'), lang('Your height, inseam, riding style and anything that hurts.')),
                            $step(2, lang('Bring your bike and shoes'), lang('With the pedals you use and the kit you ride in.')),
                            $step(3, lang('Ride away set up'), lang('With your fit sheet, and a follow-up visit with the full fit.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-fit-how', lang('fit request, appointment, what to bring'))),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$brand_tiles = array();
foreach ($brands as $name) {
    $brand_tiles[] = $el('div', 'col', lang('Brand'), array(
        $span($name, 'pbs-display d-block h5 text-uppercase text-center border py-3 mb-0', lang('Name')),
    ));
}

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('How a weekend repair bench became a bike shop with a workshop, the mechanics, fitter and ride leader, and the brands we stock.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $shop['tagline'] . '.'),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('From a garage bench to four stands'), 'pbs-display h2 text-uppercase mb-3'),
                        $para($shop['story'], 'text-body-secondary mb-3'),
                        $para($shop['workshop'] . '.', 'small fw-semibold mb-0'),
                    ), '', '6'),
                    $col(array(
                        $image($photo('workshop', 1200, 900), lang('Bikes on workshop stands with tools on the wall behind them'), 'img-fluid w-100', array('_attrs' => array($attr('loading', 'lazy')))),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('bikeshop-about-story', lang('history, garage, family shop'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The people'), lang('Mechanics, a fitter and a ride leader')),
                $rise($row(array_map($person_card, array_keys($people)), '4')),
            )),
        ), $searchable('bikeshop-about-team', lang('mechanic, fitter, ride leader, staff'))),
        $el('section', 'py-5', lang('Brands'), array(
            $container(array(
                $section_header(lang('Brands we stock'), lang('Bikes, parts and kit we ride and service ourselves.')),
                $el('div', 'row g-3 row-cols-2 row-cols-md-3 row-cols-lg-6', lang('Brands Row'), $brand_tiles),
            )),
        ), $searchable('bikeshop-about-brands', lang('brands, makes, dealer'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Visit Us'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Visit the shop'), 'pbs-display h3 text-uppercase mb-3'),
                        $para($shop['address'], 'fw-semibold mb-2'),
                        $para($shop['hours'] . '.', 'text-body-secondary mb-4'),
                        $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-outline-dark rounded-0'),
                    ), '', '5'),
                    $col(array($hours_list()), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bikeshop-about-visit', lang('address, opening hours, directions'))),
    )),
);

// The home's lists: its rides (home_rides) come with the group rides and
// its articles (home_posts) with the standard pages; this part places no
// widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
