<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bakery", its public pages: home, the daily bake, the
 * celebration cakes, ordering and collecting, wholesale, visiting the shop,
 * about and the questions. Included after parts.php in the same scope (its
 * builders and the bakery's data); returns array('pages' => ...,
 * 'widgets' => ...).
 *
 * The home and the celebration cakes are built from the palette's blocks
 * (Bakery: Fresh from the Oven Hero, Menu: Tabs, Hours: Card, Bakery: Cake
 * Sizes) and move through node interactions only.
 *
 * Every figure comes from parts.php ($bakery, $oven, $opening_hours,
 * $pickup, $categories, $bakes, $cakes, $cake_sizes, $flavours, $fillings,
 * $wholesale_ranges, $figures, $testimonials), so a change made there
 * reaches every page here. No page here links a shop page: the template
 * stands on a site without the shop, and a cake is ordered through the
 * cake order form.
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
        $para($eyebrow, 'pbk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pbk-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the values, the ways to order
// and the ways to the shop.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pbk-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The bakery in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pbk-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The opening hours as a short list, for the visit and ordering pages.
$hours_list = function ($class = 'list-unstyled mb-0') use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'pbk-price fw-semibold text-end', lang('Hours')),
        ));
    }
    return $el('ul', $class, lang('Opening Hours'), $items);
};

// How far ahead each kind of order is placed, with the pickup window: the
// ordering page and the cakes page.
$lead_times = function () use ($el, $icon, $span, $pickup) {
    $items = array();
    foreach (array('bakes' => 'bi-basket', 'cakes' => 'bi-cake', 'custom' => 'bi-pencil-square', 'window' => 'bi-clock') as $key => $icon_name) {
        $items[] = $el('li', 'd-flex gap-3 mb-3', lang('Lead Time'), array(
            $icon($icon_name, 'fs-5 text-primary'),
            $span($pickup[$key], '', lang('Text')),
        ));
    }
    return $el('ul', 'list-unstyled mb-0', lang('Lead Times'), $items);
};

// The cards of the shared data, in their order.
$cake_cards_all = array();
foreach (array_keys($cakes) as $key) $cake_cards_all[] = $cake_card($key);
$range_cards_all = array();
foreach (array_keys($wholesale_ranges) as $key) $range_cards_all[] = $range_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
// One tile per kind of bake, linking its section of the daily bake.
$category_tiles = array();
foreach ($categories as $key => $c) {
    $category_tiles[] = $feature_tile($c['icon'], $c['title'], $c['text'], '3', array(
        $link(lang('See what is baked'), '{{page:bakes}}#bky-cat-' . $key, 'd-inline-block small fw-semibold link-primary mt-3'),
    ));
}

// The home and the celebration cakes place the palette's blocks with the
// bakery's own data: Bakery: Fresh from the Oven Hero and Bakery: Cake
// Sizes (assets/js/style_designer_blocks_bakery.js), Menu: Tabs and Hours:
// Card (style_designer.js). The Bakery rules ship in User Styles from the
// block source (pinegrap-bakery.php). Every motion is a node interaction
// (props._fx, played by pg_fx on the site and on the canvas; nothing moves
// under reduced motion): the template carries no script.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// The children of a row or a list come in one after another as they
// scroll into view.
$rise = function ($node, $stagger = 120) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger));
};
// A section's heading coming into view.
$fade_in = array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800);
// A time of the day; datetime keeps the value a machine reads.
$time = function ($value, $class, $name) use ($el, $attr) {
    return $el('time', $class, $name, array(), array('text' => $value, '_attrs' => array($attr('datetime', $value))));
};

// ── Home ────────────────────────────────────────────────────────────────

// Bakery: Fresh from the Oven Hero: when the ovens are lit, the title, the
// two ways on and today's batches beside a tall picture of the bread, with
// the next day's last order on a tag hung over its corner. The words rise
// on load, the picture grows, the batches follow one by one and the tag
// slides in last; it hangs at a slant and straightens under the pointer.
$batches = array();
foreach ($oven['batches'] as $b) {
    $batches[] = $el('li', 'col', $b['name'], array(
        $time($b['time'], 'd-block fs-4 fw-bold lh-1 mb-2', lang('Out of the Oven')),
        $heading('h3', $b['name'], 'h6 fw-semibold mb-0'),
    ));
}
$home_hero = $el('section', 'pbk-hero py-5 overflow-hidden', lang('Bakery (Fresh from the Oven Hero)'), array(
    $container(array(
        $row(array(
            $col(array(
                $fx($el('div', 'mb-5', lang('Hero Text'), array(
                    $el('div', 'badge rounded-pill bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center gap-1 mb-3', lang('Ovens Lit'), array(
                        $icon('bi-fire'),
                        $span(lang('Ovens lit at'), '', lang('Text')),
                        $time($oven['lit'], '', lang('Lighting Time')),
                    )),
                    $heading('h1', lang('Fresh from the oven, every morning'), 'pbk-display display-3 mb-3'),
                    $para($bakery['tagline'] . '.', 'lead text-body-secondary mb-4'),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $link(lang('See the daily bake'), '{{page:bakes}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                        $link(lang('Order a Cake'), '{{page:cake_order}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                    )),
                )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700)),
                $el('div', '', lang('Oven Times'), array(
                    $heading('h2', lang('Out of the oven today'), 'h6 text-uppercase fw-semibold text-body-secondary mb-3'),
                    $fx($el('ol', 'pg-bakery-oven list-unstyled row row-cols-3 g-3 mb-0', lang('Batches'), $batches),
                        array('effect' => 'slide-up', 'trigger' => 'load', 'delay' => 400, 'stagger' => 120)),
                )),
            ), '', '6'),
            $col(array(
                $el('div', 'pg-bakery-photo ms-lg-4', lang('Picture Frame'), array(
                    $fx($el('div', 'ratio rounded-4 overflow-hidden shadow-lg', lang('Picture'), array(
                        $image($photo('hero', 960, 1200), lang('Loaves of sourdough cooling on a rack beside the stone oven'), 'object-fit-cover'),
                    )), array('effect' => 'scale', 'trigger' => 'load', 'duration' => 1000)),
                    $fx($el('div', 'pg-bakery-hang mx-auto mt-4 mt-md-0', lang('Tag Holder'), array(
                        $el('div', 'pg-bakery-tag text-center', lang('Tag'), array(
                            $para(lang('Order ahead'), 'pbk-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                            $time($oven['last_order'], 'd-block display-6 fw-bold lh-1 mb-1', lang('Last Order Time')),
                            $para(lang('Last orders for the next day'), 'fw-semibold mb-2'),
                            $para(lang('Collected at the counter, with your name on the bag.'), 'small text-body-secondary mb-0'),
                        )),
                    )), array('effect' => 'slide-left', 'trigger' => 'load', 'duration' => 800, 'delay' => 700)),
                )),
            ), '', '6'),
        ), '4', '', 'g-lg-5 align-items-center'),
    )),
));

// Menu: Tabs for the counter: a tab per kind of bake with its bakes, their
// badges and the price of one, two columns to a tab, and the way to the
// kind's section of the daily bake. The tabs are buttons, so a bake's
// name sits right under the section's h2.
$counter_item = function ($b) use ($el, $heading, $para, $span, $diet_tags, $money) {
    $kids = array(
        $el('div', 'd-flex align-items-baseline gap-2', lang('Item Head'), array(
            $heading('h3', $b['name'], 'h6 fw-semibold mb-0'),
            $el('div', 'flex-grow-1 border-bottom border-secondary-subtle', lang('Leader')),
            $span($money($b['price']), 'pbk-price fw-semibold text-nowrap', lang('Price')),
        )),
        $para($b['summary'], 'small text-body-secondary mb-0 mt-1'),
    );
    $badges = array();
    foreach ($b['tags'] as $t) $badges[] = $span($diet_tags[$t], 'badge rounded-pill text-bg-light border', lang('Badge'));
    if ($badges) $kids[] = $el('div', 'd-flex flex-wrap gap-1 mt-2', lang('Tags'), $badges);
    return $el('div', 'py-2', $b['name'], $kids);
};
$counter_tabs = array();
$counter_panes = array();
$n = 0;
foreach ($categories as $key => $c) {
    $items = array();
    foreach ($bakes as $b) {
        if ($b['category'] === $key) $items[] = $counter_item($b);
    }
    $half = (int)ceil(count($items) / 2);
    $pane = 'bky-counter-' . $key;
    $counter_tabs[] = $el('li', 'nav-item', $c['title'], array(
        $el('button', 'nav-link d-flex align-items-center gap-2' . ($n === 0 ? ' active' : ''), lang('Tab Button'), array(
            $icon($c['icon']),
            $span($c['title'], '', lang('Text')),
        ), array('_attrs' => array($attr('type', 'button'), $attr('data-bs-toggle', 'pill'), $attr('data-bs-target', '#' . $pane),
            $attr('role', 'tab'), $attr('aria-controls', $pane), $attr('aria-selected', $n === 0 ? 'true' : 'false')))),
    ), array('_attrs' => array($attr('role', 'presentation'))));
    $counter_panes[] = $el('div', 'tab-pane fade' . ($n === 0 ? ' show active' : ''), $c['title'], array(
        $row(array(
            $col(array_slice($items, 0, $half), '6'),
            $col(array_slice($items, $half), '6'),
        ), '4', '', 'g-lg-5'),
        $link(lang('See what is baked'), '{{page:bakes}}#bky-cat-' . $key, 'd-inline-block small fw-semibold link-primary mt-3'),
    ), array('id' => $pane, '_attrs' => array($attr('role', 'tabpanel'), $attr('tabindex', '0'))));
    $n++;
}
$home_counter = $el('section', 'py-5', lang('Menu (Tabs)'), array(
    $container(array(
        $fx($el('div', 'text-center mb-4', lang('Section Header'), array(
            $para(lang('Baked today'), 'pbk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
            $heading('h2', lang('From the oven to the counter'), 'pbk-display h2 mb-2'),
            $para(lang('What comes out of the oven every morning. Come early for the buns, or order ahead and collect.'), 'lead text-body-secondary mb-0'),
        )), $fade_in),
        $rise($el('ul', 'nav nav-pills justify-content-center flex-wrap gap-2 mb-5', lang('Menu Tabs'), $counter_tabs,
            array('_attrs' => array($attr('role', 'tablist')))), 80),
        $el('div', 'tab-content', lang('Menu Panes'), $counter_panes),
        $el('div', 'text-center mt-4', lang('Buttons'), array(
            $link(lang('The whole daily bake'), '{{page:bakes}}', 'btn btn-outline-primary rounded-pill px-4'),
        )),
    )),
), $searchable('bky-home-bakes', lang('bread, sourdough, croissant, pastries, cakes, menu, prices')));

// Ordering ahead beside Hours: Card: the cut-off and the way it works on
// the left, the opening hours with the pickup window under them on the
// right; the trade's line closes the section.
$hours_items = array();
foreach ($opening_hours as $h) {
    $closed = !empty($h['closed']);
    $hours_items[] = $el('li', 'list-group-item d-flex justify-content-between gap-3' . ($closed ? ' text-body-secondary' : ''), $h['days'], array(
        $span($h['days'], '', lang('Days')),
        $span($h['hours'], $closed ? 'text-end' : 'pbk-price fw-semibold text-end', lang('Hours')),
    ));
}
// The hours card slides in from the right; the section clips it until it
// arrives, so a phone never scrolls sideways.
$home_order = $el('section', 'py-5 bg-body-tertiary overflow-hidden', lang('Order and Collect'), array(
    $container(array(
        $row(array(
            $fx($col(array(
                $para(lang('Order and Collect'), 'pbk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                $heading('h2', lang('Order ahead, collect it warm'), 'pbk-display display-6 mb-3'),
                $para($pickup['bakes'], 'lead mb-2'),
                $para($pickup['cakes'] . ' ' . $pickup['custom'], 'text-body-secondary mb-4'),
                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                    $link(lang('How Ordering Works'), '{{page:how_to_order}}', 'btn btn-primary rounded-pill px-4'),
                    $link(lang('Visit the Bakery'), '{{page:visit}}', 'btn btn-outline-primary rounded-pill px-4'),
                )),
            ), '', '7'), array('effect' => 'slide-right', 'trigger' => 'view', 'duration' => 800)),
            $fx($col(array(
                $el('div', 'card border-0 shadow-sm', lang('Hours Card'), array(
                    $el('div', 'card-header bg-transparent d-flex align-items-center gap-2 py-3', lang('Card Header'), array(
                        $icon('bi-clock', 'text-primary'),
                        $heading('h3', lang('Opening hours'), 'h5 fw-bold mb-0'),
                    )),
                    $el('ul', 'list-group list-group-flush', lang('Hours List'), $hours_items),
                    $el('div', 'card-footer bg-transparent small text-body-secondary py-3', lang('Card Footer'), array(
                        $para($pickup['window'], 'mb-0'),
                    )),
                )),
            ), '', '5'), array('effect' => 'slide-left', 'trigger' => 'view', 'duration' => 800, 'delay' => 150)),
        ), '4', '', 'g-lg-5 align-items-center'),
    )),
), $searchable('bky-home-order', lang('pickup, collect, pre-order, order ahead')));

// The trade's line: a slim band, not a story of its own.
$home_trade = $el('section', 'py-5', lang('Wholesale'), array(
    $container(array(
        $fx($el('div', 'd-flex flex-column flex-md-row align-items-md-center gap-4 p-4 p-lg-5 rounded-4 border', lang('Note'), array(
            $icon_tile('bi-truck', 'align-self-start align-self-md-center flex-shrink-0'),
            $el('div', 'flex-grow-1', lang('Text'), array(
                $heading('h2', lang('Bread for your café or restaurant'), 'pbk-display h4 mb-1'),
                $para(lang('More than twenty cafés and restaurants serve our bread and pastries, delivered every morning before they open.'), 'text-body-secondary mb-0'),
            )),
            $link(lang('Wholesale for businesses'), '{{page:wholesale}}', 'btn btn-outline-primary rounded-pill px-4 flex-shrink-0'),
        )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700)),
    )),
), $searchable('bky-home-wholesale', lang('trade, business, supply, cafés, restaurants')));

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A neighbourhood bakery and patisserie: sourdough and pastries every morning, celebration cakes to order, baking classes and wholesale.'),
    'tree'             => $page(array(
        $home_hero,
        $home_counter,

        // The cakes grow in one after another.
        $el('section', 'py-5 bg-body-tertiary', lang('Celebration Cakes'), array(
            $container(array(
                $fx($section_header(lang('Made to order'), lang('Cakes for the days that matter'), lang('All celebration cakes'), '{{page:cakes}}'), $fade_in),
                $fx($row($cake_cards_all, '4'), array('effect' => 'scale', 'trigger' => 'view', 'duration' => 700, 'stagger' => 150)),
            )),
        )),

        // The classes, read from the Baking Classes collection.
        $el('section', 'py-5', lang('Baking Classes'), array(
            $container(array(
                $fx($section_header(lang('Learn with us'), lang('Bake at the long table'), lang('All baking classes'), '{{page:classes}}'), $fade_in),
                $widget('home_classes'),
            )),
        )),

        $home_order,
        $home_trade,

        // What customers say: sample words for the bakery to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Customer Stories'), array(
            $container(array(
                $fx($section_header(lang('Our customers'), lang('What our customers say')), $fade_in),
                $rise($row($testimonial_cols, '4')),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From the Bakery'), array(
            $container(array(
                $fx($section_header(lang('Journal'), lang('Notes from the bakehouse'), lang('All notes'), '{{page:blog}}'), $fade_in),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── The daily bake ──────────────────────────────────────────────────────
// A section per kind of bake with its bakes and prices, then the words on
// what the badges mean and how to order ahead.
$bake_cols = array();
foreach (array_keys($categories) as $key) $bake_cols[] = $col(array($bake_section($key)), '6', '', '', '12');
$tag_items = array();
foreach ($diet_tags as $t) $tag_items[] = $span($t, 'badge rounded-pill text-bg-light border', lang('Badge'));

$public_pages[] = array(
    'key'              => 'bakes',
    'name'             => lang('daily-bake'),
    'folder'           => 'public',
    'title'            => lang('The Daily Bake'),
    'meta_description' => lang('Sourdough, rye and focaccia, croissants and buns, cakes by the slice and fruit tarts, baked every morning, with prices.'),
    'tree'             => $page(array(
        $page_header(lang('The Daily Bake'), lang('What comes out of the oven every morning. Come early for the buns, or order ahead and collect.'), array(
            $link(lang('How Ordering Works'), '{{page:how_to_order}}', 'btn btn-primary rounded-pill px-4 mt-3'),
        )),
        $el('section', 'py-5', lang('Bakes'), array(
            $container(array(
                $rise($row($bake_cols, '4'), 150),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Allergies and ingredients'), 'pbk-display h3 mb-3'),
                        $para(lang('Every bake is made in one kitchen where flour, nuts, eggs, milk and sesame are used, so we cannot promise any bake is free from traces. The badges say what a bake is made without or holds:'), 'text-body-secondary mb-3'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Badges'), $tag_items),
                        $para(lang('Ask at the counter for the full list of ingredients of any bake.'), 'small text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Order ahead'), 'pbk-display h3 mb-3'),
                        $lead_times(),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bky-bakes-allergies', lang('allergens, gluten, nuts, vegan, ingredients, dairy'))),
    )),
);

// ── Celebration cakes ───────────────────────────────────────────────────
// The cakes, each opening the cake order with it chosen; then Bakery: Cake
// Sizes: each size drawn to scale with how many it serves and the price it
// starts at, the sponges and the fillings a cake order chooses from, and
// how far ahead to ask. The cakes grow in one by one, so do the sizes.
// The layers of each size's drawing, from the top down.
$size_layers = array(
    'small'  => array('pg-bakery-d15'),
    'medium' => array('pg-bakery-d20'),
    'large'  => array('pg-bakery-d25'),
    'tiered' => array('pg-bakery-d15', 'pg-bakery-d25'),
);
$size_items = array();
foreach ($cake_sizes as $key => $s) {
    $layers = array();
    foreach ($size_layers[$key] as $w) $layers[] = $el('div', 'pg-bakery-layer ' . $w, lang('Cake Layer'));
    $size_items[] = $el('li', 'col', $s['title'], array(
        $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', lang('Size Card'), array(
            $el('div', 'pg-bakery-cake mb-3', lang('Drawing'), $layers, array('_attrs' => array($attr('aria-hidden', 'true')))),
            $heading('h3', $s['title'], 'h5 fw-bold mb-1'),
            $el('div', 'd-flex align-items-center gap-2 text-body-secondary mb-3', lang('Servings'), array(
                $icon('bi-people'),
                $span($s['servings'], '', lang('Text')),
            )),
            $el('div', 'd-flex flex-wrap align-items-baseline gap-2', lang('Price'), array(
                $span(lang('Starting from'), 'small text-body-secondary', lang('Text')),
                $span($money($s['price']), 'pbk-price fs-4 fw-bold', lang('Price')),
            )),
        )),
    ));
}
$choice_col = function ($title, $items) use ($el, $heading, $span, $col) {
    $pills = array();
    foreach ($items as $text) $pills[] = $el('li', 'rounded-pill border px-3 py-1', $text, array($span($text, '', lang('Text'))));
    return $col(array(
        $heading('h3', $title, 'h5 fw-bold mb-3'),
        $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Choices'), $pills),
    ), '', '6');
};

$public_pages[] = array(
    'key'              => 'cakes',
    'name'             => lang('celebration-cakes'),
    'folder'           => 'public',
    'title'            => lang('Celebration Cakes'),
    'meta_description' => lang('Birthday, wedding and celebration cakes made to order, in the size, sponge and filling you choose, with a design of your own.'),
    'tree'             => $page(array(
        $page_header(lang('Celebration Cakes'), lang('Every cake is baked for its day. Choose one of ours or tell us about the cake you have in mind.'), array(
            $link(lang('Order a Cake'), '{{page:cake_order}}', 'btn btn-primary rounded-pill px-4 mt-3'),
        )),
        $el('section', 'py-5', lang('Our Cakes'), array(
            $container(array(
                $fx($row($cake_cards_all, '4'), array('effect' => 'scale', 'trigger' => 'view', 'duration' => 700, 'stagger' => 150)),
            )),
        ), $searchable('bky-cakes', lang('birthday cake, wedding cake, custom cake, prices'))),
        $el('section', 'py-5 border-top', lang('Bakery (Cake Sizes)'), array(
            $container(array(
                $fx($row(array(
                    $col(array(
                        $para(lang('Sizes'), 'pbk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('How big should the cake be?'), 'pbk-display display-5 mb-0'),
                    ), '', '7'),
                    $col(array(
                        $para(lang('Choose the size by how many it should serve, then the sponge and the filling. Every cake is baked for its day.'), 'lead text-body-secondary mb-0'),
                    ), '', '5'),
                ), '4', '', 'align-items-end mb-5'), $fade_in),
                $fx($el('ol', 'list-unstyled row row-cols-2 row-cols-lg-4 g-3 g-lg-4 mb-5', lang('Sizes'), $size_items),
                    array('effect' => 'scale', 'trigger' => 'view', 'duration' => 700, 'stagger' => 150)),
                $fx($el('div', '', lang('Choices and Order'), array(
                    $row(array(
                        $choice_col(lang('Sponges'), $flavours),
                        $choice_col(lang('Fillings'), $fillings),
                    ), '4', '', 'mb-4'),
                    $el('div', 'd-flex flex-column flex-md-row align-items-md-center gap-3 p-4 rounded-4 border', lang('Note'), array(
                        $icon_tile('bi-calendar-heart', 'align-self-start align-self-md-center flex-shrink-0'),
                        $para($pickup['custom'], 'flex-grow-1 mb-0'),
                        $link(lang('Order a Cake'), '{{page:cake_order}}', 'btn btn-primary rounded-pill px-4 flex-shrink-0'),
                    )),
                )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800, 'delay' => 200)),
            )),
        ), $searchable('bky-cake-options', lang('servings, portions, flavours, fillings, buttercream, vegan cake'))),
        // Clipped while the lead-time card slides in from the right.
        $el('section', 'py-5 bg-body-tertiary overflow-hidden', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $fx($heading('h2', lang('How a cake order works'), 'pbk-display h3 mb-4'), $fade_in),
                        $rise($el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Tell us about the cake'), lang('The occasion, the day, how many it should serve and a picture or sketch if you have one.')),
                            $step(2, lang('We send a design and a price'), lang('Within a day, under My Cake Orders; write back there to change anything.')),
                            $step(3, lang('Collect it on the day'), lang('Or ask us to deliver it; a wedding cake is always delivered and set up by us.')),
                        )), 150),
                    ), '', '6'),
                    $fx($col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('How far ahead?'), 'pbk-display h4 mb-3'),
                            $lead_times(),
                        )),
                    ), '', '6'), array('effect' => 'slide-left', 'trigger' => 'view', 'duration' => 800, 'delay' => 150)),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('bky-cakes-how', lang('order a cake, deposit, delivery, tasting, lead time'))),
    )),
);

// ── Ordering and collecting ─────────────────────────────────────────────
// How the shop's orders are collected on a chosen day: the pickup day is
// the checkout's arrival date and the place the bakery's counter, both set
// up in the control panel; this page says so in words and links the ways
// to order that stand without the shop.
$order_ways = array(
    array('icon' => 'bi-shop', 'title' => lang('At the counter'), 'text' => lang('Come in, choose and take it home; whatever is left at closing goes to the food bank.')),
    array('icon' => 'bi-bag-check', 'title' => lang('Online, collected on your day'), 'text' => lang('Order from the daily bake, choose the day you collect and pay online; your name is on the bag.')),
    array('icon' => 'bi-cake2', 'title' => lang('A cake made for you'), 'text' => lang('Ask for a celebration cake with the cake order form; we answer with a design and a price.')),
);
$order_way_tiles = array();
foreach ($order_ways as $w) $order_way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

// With the shop on, the page leads to it: the one public page that links a
// shop page, so the shared parts and the other pages stand without it.
$shop_on = defined('ECOMMERCE') && ECOMMERCE;
$order_more = $shop_on
    ? array($link(lang('Order for pickup'), '{{page:shop}}', 'btn btn-primary rounded-pill px-4 mt-3'))
    : array();

$public_pages[] = array(
    'key'              => 'how_to_order',
    'name'             => lang('order-and-collect'),
    'folder'           => 'public',
    'title'            => lang('Order and Collect'),
    'meta_description' => lang('Order bread, pastries and cakes ahead, choose the day you collect them at the counter, and how far ahead each kind of order is placed.'),
    'tree'             => $page(array(
        $page_header(lang('Order and Collect'), lang('Order ahead and collect at the counter on the day you choose: your order is packed and waiting with your name on it.'), $order_more),
        $el('section', 'py-5', lang('Ways to Order'), array(
            $container(array(
                $row($order_way_tiles, '4'),
            )),
        ), $searchable('bky-order-ways', lang('pre-order, click and collect, online order, pickup'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Collecting Your Order'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('When to collect'), 'pbk-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose your day'), lang('At checkout, pick the day you will collect; the days on offer are the days the shop is open.')),
                            $step(2, lang('We bake it that morning'), lang('Your order goes into the oven with the morning bake and is packed with your name.')),
                            $step(3, lang('Collect at the counter'), lang('Give your name or order number at the counter during the pickup window.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('How far ahead?'), 'pbk-display h3 mb-3'),
                        $lead_times(),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bky-order-collect', lang('pickup day, collection time, cut-off, deadline'))),
        $el('section', 'py-5', lang('Opening Hours'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Opening hours'), 'pbk-display h3 mb-3'),
                        $hours_list(),
                    ), '', '5'),
                    $col(array(
                        $heading('h2', lang('Good to know'), 'pbk-display h3 mb-3'),
                        $check_list(array(
                            lang('An order not collected by closing time is kept until noon the next day, then given away'),
                            lang('To change or cancel an order, write to us by 14:00 the day before'),
                            lang('Someone else may collect for you with the order number'),
                            lang('Bring a bag of your own if you can; ours are paper'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('bky-order-rules', lang('cancel, change, late, refund, collect for someone'))),
    )),
);

// ── Wholesale ───────────────────────────────────────────────────────────
// The ranges for the trade, each opening the enquiry with it chosen, and
// how supplying a business works.
$public_pages[] = array(
    'key'              => 'wholesale',
    'name'             => lang('wholesale'),
    'folder'           => 'public',
    'title'            => lang('Wholesale'),
    'meta_description' => lang('Bread, pastries and cakes for cafés, restaurants, hotels and offices, baked that morning and delivered before you open.'),
    'tree'             => $page(array(
        $page_header(lang('Wholesale'), lang('We bake for cafés, restaurants, hotels and offices across town, every morning and delivered before you open.'), array(
            $link(lang('Send a wholesale enquiry'), '{{page:wholesale_enquiry}}', 'btn btn-primary rounded-pill px-4 mt-3'),
        )),
        $el('section', 'py-5', lang('Our Ranges'), array(
            $container(array(
                $row($range_cards_all, '4'),
            )),
        ), $searchable('bky-wholesale-ranges', lang('trade, supplier, bulk, B2B, catering'))),
        $story_band('bky-wholesale-how', lang('Working together'), lang('From a tasting to the morning round'),
            array(
                lang('Tell us what you serve and how much you need. We bring samples to taste, agree the bakes, the amounts and the delivery days, and open an account for you.'),
                lang('Orders change by e-mail until 14:00 the day before; the van comes before eight every delivery day.'),
            ),
            $photo('wholesale', 1200, 900), lang('The delivery van at the back door, loaded with crates of bread'), false,
            array($link(lang('Send a wholesale enquiry'), '{{page:wholesale_enquiry}}', 'btn btn-outline-primary rounded-pill px-4')),
            lang('delivery, minimum order, invoice, account, samples'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Visit the bakery ────────────────────────────────────────────────────

$ways_here = array(
    array('icon' => 'bi-person-walking', 'title' => lang('On foot'), 'text' => lang('On the corner of the market square, two minutes from the old town hall.')),
    array('icon' => 'bi-bicycle', 'title' => lang('By bike'), 'text' => lang('Racks for bikes and room for prams and cargo bikes right at the door.')),
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('Short-stay parking around the square; on market days the car park behind the church is easier.')),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'visit',
    'name'             => lang('visit'),
    'folder'           => 'public',
    'title'            => lang('Visit the Bakery'),
    'meta_description' => lang('Where the bakery is, its opening hours and how to reach it on foot, by bike or by car.'),
    'tree'             => $page(array(
        $page_header(lang('Visit the Bakery'), $bakery['region'] . '.'),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Find us'), 'pbk-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $bakery['address'], 'pbk-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($bakery['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $bakery['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($bakery['email'], 'mailto:' . $bakery['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list('list-unstyled mb-4'),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($bakery['address']), 'btn btn-outline-primary rounded-pill px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('visit', 1400, 1050), lang('The bakery\'s shop front with its striped awning on the market square'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('bky-address', lang('address, map, directions, opening hours, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('bky-travel', lang('parking, bike, walk, transport'))),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-hourglass-split', 'title' => lang('Time is an ingredient'), 'text' => lang('Long, slow rises for flavour and for bread that keeps; we never hurry a dough.')),
    array('icon' => 'bi-flower1', 'title' => lang('Flour from nearby'), 'text' => lang('Stone-ground flour from a mill an hour away, and fruit from the market across the square.')),
    array('icon' => 'bi-recycle', 'title' => lang('Nothing wasted'), 'text' => lang('What is left at closing goes to the food bank; yesterday\'s bread becomes crumbs and puddings.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Two bakers, an old corner dairy and a sourdough starter kept for years: the story of the bakery and how we bake.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $bakery['tagline'] . '.'),
        $story_band('bky-our-story', lang('Our story'), lang('From a corner dairy to the bakery of the square'),
            array($bakery['story']),
            $photo('about', 1200, 900), lang('Two bakers shaping loaves at a floury wooden bench'), false, array(),
            lang('history, founders, owners, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we bake')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('bky-values', lang('values, organic, local, sustainability, waste'))),
        $el('section', 'py-5 bg-body-tertiary', lang('The Daily Bake'), array(
            $container(array(
                $section_header(lang('What we bake'), lang('Four ovens\' worth every morning'), lang('The whole daily bake'), '{{page:bakes}}'),
                $row($category_tiles, '4'),
            )),
        )),
        $el('section', 'py-5', lang('Write to Us'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('A question about an order or a bake?'), 'pbk-display h4 mb-1'),
                        $para(lang('Write to us and a baker answers, usually the same day.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: ordering, cakes, classes and the bakery. Each question
// is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pbk-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about ordering ahead and collecting, celebration cakes, allergies, baking classes and wholesale.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Ordering, cakes, classes and the bakery. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('bky-faq-ordering', lang('Ordering and collecting'), array(
                            array(lang('Can I order bread for the next day?'), $pickup['bakes'] . ' ' . $pickup['window'], lang('pre-order, tomorrow, cut-off, deadline')),
                            array(lang('Do you deliver?'), lang('Bakes are collected at the counter. A celebration cake can be delivered in town; a wedding cake is always delivered and set up by us.'), lang('delivery, courier, shipping')),
                            array(lang('How do I follow my cake order?'), lang('Sending the cake order opens an account for you. Under My Account you see its state and write to the bakers there.'), lang('my account, status, track')),
                        )),
                        $faq_group('bky-faq-cakes', lang('Celebration cakes'), array(
                            array(lang('How far ahead should I order a cake?'), $pickup['cakes'] . ' ' . $pickup['custom'], lang('lead time, notice, last minute')),
                            array(lang('Can you make a cake I designed?'), lang('Yes: choose My own design in the cake order and add a sketch or a picture. We answer with what we can make and its price.'), lang('custom, photo, design, theme')),
                            array(lang('Do you make vegan or wheat-free cakes?'), lang('Most of our sponges can be made vegan, and some wheat-free; say so in the order. We cannot promise a cake is free from traces.'), lang('vegan, gluten-free, allergies, dairy-free')),
                        )),
                        $faq_group('bky-faq-bakery', lang('Classes and the bakery'), array(
                            array(lang('Do I need experience for a baking class?'), lang('No: each class says who it is for, and the first-time classes start from the very beginning. Everything you need is at the bench.'), lang('beginner, first time, level')),
                            array(lang('Can children come to a class?'), lang('Little Bakers is for children from seven, with a grown-up close by; the other classes are for 16 and older.'), lang('kids, children, age, family')),
                            array(lang('Do you supply cafés and restaurants?'), lang('Yes: send a wholesale enquiry and we bring samples to taste and agree the bakes and the delivery days.'), lang('wholesale, trade, supply, business')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its classes (home_classes) come with the classes,
// its notes (home_posts) and the Google reviews (google_reviews) with the
// standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
