<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Phone Launch Site", its public pages: home, features,
 * camera, tech specs, compare, about and the questions page. Included after
 * parts.php in the same scope (its builders and the phone's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($models, $spec_groups, $highlights,
 * $colours, the prices), so a change made there reaches every page here.
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

// The model cards in a row, smallest first.
$model_cards = array();
foreach (array_keys($models) as $key) $model_cards[] = $model_card($key);

// The highlight tiles, in the order the home page tells them.
$highlight_tiles = array();
foreach (array_keys($highlights) as $key) $highlight_tiles[] = $highlight_tile($key);

// What every order comes with, as four small columns.
$promise_cols = array();
foreach ($buy_promises as $p) {
    $promise_cols[] = $col(array(
        $el('div', 'd-flex gap-3', $p['title'], array(
            $icon($p['icon'], 'fs-4 text-primary'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $p['title'], 'h6 fw-semibold mb-1'),
                $para($p['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3');
}

// ── Product Launch blocks ───────────────────────────────────────────────
// Home and features place the palette's launch- blocks
// (assets/js/style_designer_blocks_launch.js) with the phone's own data: the
// same structure and classes, their rules shipped in User Styles from the
// block source (pinegrap-launch.php), the motion as node interactions
// (props._fx, played by pg_fx; reduced motion is honoured there).

// A node with an interaction.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};

$featured_model = 'one';
foreach ($models as $key => $m) {
    if ($m['featured']) $featured_model = $key;
}

// Launch: Product Hero — the phone on a dark stage; its name, line, price and
// the two ways on rise in on load, the phone scales in over the palette glow.
$launch_hero = function () use ($el, $heading, $para, $link, $image, $span, $container, $row, $col, $attr, $fx, $models, $featured_model, $render, $price_from) {
    $m = $models[$featured_model];
    return $el('section', 'py-5 bg-body text-body overflow-hidden', lang('Hero'), array(
        $container(array(
            $row(array(
                $col(array(
                    $fx($el('div', '', lang('Hero Text'), array(
                        $span(lang('New'), 'd-inline-block mb-3 small fw-semibold text-uppercase text-primary', lang('Badge')),
                        $heading('h1', $m['name'], 'display-3 fw-semibold mb-3'),
                        $para(lang('Made to last. A brighter screen, three matched cameras and a battery that sees out the weekend.'), 'lead text-body-secondary mb-4'),
                        $span($price_from($featured_model), 'd-block fs-5 fw-semibold pg-launch-figure', lang('Price')),
                        $el('div', 'd-flex flex-wrap justify-content-center justify-content-lg-start gap-3 mt-4', lang('Buttons'), array(
                            $link(lang('Buy'), '{{page:buy}}#buy-' . $featured_model, 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Explore the features'), '{{page:features}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        )),
                    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800, 'stagger' => 120)),
                ), '', '6', '', '12', 'order-2 order-lg-1 text-center text-lg-start'),
                $col(array(
                    $fx($el('div', 'pg-launch-glow text-center', lang('Phone Stage'), array(
                        $image($render($m['name'], 600, 1100), lang('The Orbit One phone from the front and the back'), 'img-fluid pg-launch-device'),
                    )), array('effect' => 'scale', 'trigger' => 'load', 'duration' => 1000)),
                ), '', '6', '', '12', 'order-1 order-lg-2'),
            ), '4', '', 'g-lg-5 align-items-center'),
        ), 'py-lg-5'),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// Launch: Camera Band — a tall photo band moving slower than the page; its
// text and three figures on the shared scrim, and a way on. The home page
// places it as a section (h2, on to the camera page), the camera page as its
// top (h1, on to the camera rows of the specs).
$launch_camera = function ($first = false) use ($el, $heading, $para, $link, $span, $container, $row, $col, $attr, $fx, $photo) {
    $figure = function ($value, $label) use ($span, $col) {
        return $col(array(
            $span($value, 'd-block display-6 fw-semibold pg-launch-figure', lang('Figure')),
            $span($label, 'd-block small text-body-secondary', lang('Label')),
        ), '', '', '4');
    };
    return $fx($el('section', 'd-flex pg-launch-band', lang('Camera'), array(
        $el('div', 'd-flex flex-column justify-content-end w-100 py-5 text-body pg-shade-bottom', lang('Overlay'), array(
            $container(array(
                $para(lang('Camera'), 'plx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                $heading($first ? 'h1' : 'h2', lang('Three cameras, one look'), ($first ? 'display-4' : 'display-5') . ' fw-semibold mb-3'),
                $para(lang('Wide, ultra wide and zoom share one colour science, so the photos of a day look like one set. At night the main camera takes in four times more light.'), 'lead mb-4'),
                $row(array(
                    $figure(lang('50 MP'), lang('Main and ultra wide')),
                    $figure(lang('5x'), lang('Optical zoom on One Plus')),
                    $figure(lang('4K 120'), lang('Slow-motion video')),
                ), '4', '', 'mb-4 text-center text-sm-start'),
                $first
                    ? $link(lang('Camera specifications'), '{{page:specs}}#spec-camera', 'btn btn-primary rounded-pill px-4')
                    : $link(lang('See the camera'), '{{page:camera}}', 'btn btn-primary rounded-pill px-4'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),
    ), array('_attrs' => array($attr('style', 'background-image: url(' . $photo($first ? 'camera-stage' : 'camera-night', 1920, 1080) . ');')))),
    array('effect' => 'parallax', 'speed' => 20));
};

// The colour band, drawn as the Variant Picker block (variant-picker): a
// radio dot for each finish under the phone; the checked one shows its
// picture with no script. The dots take the finish from the template's
// plx-swatch-<key> classes. $captions adds each finish's sentence under its
// picture (the features page).
$launch_colours = function ($title, $lead, $captions = false, $more = array()) use ($el, $heading, $para, $image, $span, $container, $attr, $colours, $models, $featured_model, $render) {
    $shots = array();
    $picks = array($el('legend', 'visually-hidden', lang('Legend'), array(), array('text' => lang('Colour'))));
    $n = 0;
    foreach ($colours as $key => $c) {
        $n++;
        $picture = $image($render($models[$featured_model]['name'] . ' · ' . $c['name'], 900, 900),
            sprintf(lang('The %1$s in %2$s'), $models[$featured_model]['name'], $c['name']), 'img-fluid w-100 rounded-4 object-fit-cover');
        $shot = array($picture);
        if ($captions) $shot[] = $el('figcaption', 'mt-3 text-body-secondary', lang('Caption'), array(), array('text' => $c['text']));
        $shots[] = $el('figure', 'mb-0 pg-variant-shot pg-variant-shot-' . $n, $c['name'], $shot);
        // The id is props.id: the page render writes no id from _attrs.
        $input = array($attr('type', 'radio'), $attr('name', 'lx-colour'), $attr('autocomplete', 'off'));
        if ($n === 1) $input[] = $attr('checked', 'checked');
        $picks[] = $el('div', 'pg-variant-choice', $c['name'], array(
            $el('input', 'btn-check pg-variant-pick pg-variant-pick-' . $n, lang('Swatch Choice'), array(), array('id' => 'lx-colour-' . $key, '_attrs' => $input)),
            $el('label', 'pg-variant-label', lang('Swatch Label'), array(
                $el('span', 'pg-variant-dot plx-swatch-' . $key, lang('Swatch'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
                $span($c['name'], 'small fw-semibold', lang('Name')),
            ), array('_attrs' => array($attr('for', 'lx-colour-' . $key)))),
        ));
    }
    return $el('section', 'py-5 text-center pg-variant pg-variant-dots', lang('Colours'), array(
        $container(array(
            $para(lang('Colours'), 'plx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
            $heading('h2', $title, 'display-6 fw-semibold mb-2'),
            $para($lead, 'lead text-body-secondary mb-4'),
            $el('div', 'mx-auto mb-4 col-12 col-md-8 col-lg-6', lang('Picture Stage'), $shots),
            $el('fieldset', 'd-flex justify-content-center flex-wrap gap-3 border-0 p-0 m-0', lang('Swatches'), $picks),
        )),
    ), $more);
};

// Launch: Sticky Showcase — the phone stays beside the text from 992 px up
// while a panel per highlight, one screen each, slides up as it comes in.
// Each panel is the anchor its home tile opens and a search section.
$feature_points = array(
    'design'  => array(lang('Recycled aluminium frame'), lang('Matte glass front and back'), lang('IP68: 6 metres for 30 minutes')),
    'display' => array(lang('Up to 2,600 nits outdoors'), lang('1 to 120 Hz, adapting to what you do'), lang('Eye comfort mode for the evening')),
    'camera'  => array(lang('50 MP main and ultra wide cameras'), lang('3x or 5x optical zoom on One and One Plus'), lang('Night mode on every lens')),
    'battery' => array(lang('Two days of ordinary use'), lang('50% in 15 minutes with the 65 W charger'), lang('25 W wireless charging')),
    'chip'    => array(lang('Orbit O3, built at 3 nm'), lang('Up to 16 GB of memory'), lang('A vapour chamber that keeps it cool')),
    'updates' => array(lang('Seven years of system updates'), lang('Monthly security fixes'), lang('Parts and repairs for as long')),
);
$launch_showcase = function ($panels_data, $picture, $alt) use ($el, $heading, $para, $image, $span, $container, $row, $col, $fx, $searchable, $check_list) {
    $panels = array();
    foreach ($panels_data as $id => $h) {
        $panels[] = $fx($el('div', 'pg-launch-panel py-4', $h['title'], array(
            $span($h['figure'], 'd-block mb-2 small fw-semibold text-uppercase text-primary pg-launch-figure', lang('Figure')),
            $heading('h2', $h['title'], 'display-6 fw-semibold mb-3'),
            $para($h['text'], 'fs-5 text-body-secondary mb-4'),
            $check_list($h['points']),
        ), $searchable($id, $h['topics'])), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700));
    }
    return $el('section', 'py-5', lang('Features'), array(
        $container(array(
            $row(array(
                $col(array(
                    $fx($el('div', 'py-lg-5', lang('Sticky Picture'), array(
                        $image($picture, $alt, 'img-fluid w-100 rounded-4 object-fit-cover pg-ratio-1x1'),
                    )), array('effect' => 'sticky', 'offset' => 96)),
                ), '', '6'),
                $col($panels, '', '6'),
            ), '4', '', 'g-lg-5'),
        )),
    ));
};
// The features page's panels: a highlight each, under #feature-<key>.
$feature_panels = array();
foreach ($highlights as $key => $h) {
    $feature_panels['feature-' . $key] = array_merge($h, array('points' => $feature_points[$key]));
}

// Launch: In the Box — what comes in the box, as a strip of pictures.
$box_items = array(
    array('box-phone', $models[$featured_model]['name'], lang('The phone, in the colour and size you chose')),
    array('box-cable', lang('USB-C cable, 1 m'), lang('A braided USB-C cable')),
    array('box-tool', lang('SIM tool'), lang('A tool to open the SIM tray')),
    array('box-guide', lang('Quick start guide'), lang('A quick start guide')),
);
$launch_box = function ($more = array()) use ($el, $heading, $para, $image, $span, $container, $box_items, $photo) {
    $items = array();
    foreach ($box_items as $b) {
        $items[] = $el('li', 'col', $b[1], array(
            $image($photo($b[0], 400, 400), $b[2], 'img-fluid w-100 mb-3 rounded-4 bg-body-tertiary object-fit-cover pg-ratio-1x1', array('_attrs' => array(array('name' => 'loading', 'value' => 'lazy')))),
            $span($b[1], 'd-block fw-semibold', lang('Name')),
        ));
    }
    return $el('section', 'py-5 text-center', lang('In the box'), array(
        $container(array(
            $el('div', 'text-center mb-5', lang('Section Header'), array(
                $heading('h2', lang('In the box'), 'fw-bold mb-2'),
                $para(lang('A charger only if you ask for one: most drawers already hold two.'), 'lead text-body-secondary mb-0'),
            )),
            $el('ul', 'row row-cols-2 row-cols-md-4 g-4 list-unstyled mb-0', lang('Box List'), $items),
        )),
    ), $more);
};

// ── Home ────────────────────────────────────────────────────────────────
// The product's own order: the phone, what it does, the camera, the
// colours, the three models, trade-in, the newsroom and the launch band.
$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('The new Orbit phone: a screen you can read in the sun, three matched cameras, two days of battery and seven years of updates.'),
    'tree'             => $page(array(
        $launch_hero(),

        // The highlights: six tiles revealed one after another, each opening
        // its panel of the features page.
        $el('section', 'py-5', lang('Highlights'), array(
            $container(array(
                $section_header(lang('Why Orbit'), lang('Everything you notice every day'), lang('All features'), '{{page:features}}'),
                $fx($row($highlight_tiles, '4'), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => 100)),
            )),
        )),

        $launch_camera(),

        $launch_colours(lang('Four colours, every model'), lang('Each colour goes through the glass, not on it, so a scratch never shows a different shade.')),

        // The three models, the cards revealed one after another.
        $el('section', 'py-5 bg-body-tertiary', lang('Models'), array(
            $container(array(
                $section_header(lang('The family'), lang('Three sizes, one Orbit'), lang('Compare models'), '{{page:compare}}'),
                $fx($row($model_cards, '4'), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => 150)),
            )),
        )),

        // Trade-in, in one strip.
        $el('section', 'py-5', lang('Trade-in'), array(
            $container(array(
                $el('div', 'p-4 p-lg-5 rounded-4 bg-primary-subtle d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Trade-in'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Trade in your old phone'), 'h3 fw-semibold mb-2'),
                        $para(lang('Tell us what you have and its condition; we send a quote, and the credit comes off your Orbit.'), 'mb-0'),
                    )),
                    $link(lang('Get a quote'), '{{page:tradein}}', 'btn btn-primary btn-lg rounded-pill px-4 text-nowrap'),
                )),
            )),
        )),

        // The newest from the newsroom.
        $el('section', 'py-5', lang('From the Newsroom'), array(
            $container(array(
                $section_header(lang('Newsroom'), lang('Latest news'), lang('All news'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('launch_band'),
    )),
);

// ── Features ────────────────────────────────────────────────────────────
// The highlights as the sticky showcase, the colour picker with each
// finish's sentence, and what is in the box.
$public_pages[] = array(
    'key'              => 'features',
    'name'             => lang('orbit-features'),
    'folder'           => 'public',
    'title'            => lang('Features'),
    'meta_description' => lang('The Orbit\'s design, screen, cameras, battery, chip and seven years of updates, one by one.'),
    'tree'             => $page(array(
        $page_header(lang('Features'), lang('What makes Orbit the phone you keep: the parts you notice every day, one by one.')),
        $launch_showcase($feature_panels, $render($models[$featured_model]['name'], 900, 900), lang('The Orbit One phone from the front and the back')),
        $launch_colours(lang('Four colours, every model'), lang('Pick a colour to see it.'), true,
            $searchable('colours', lang('colour, color, graphite, glacier, sage, coral'))),
        $launch_box(),
        $shared('launch_band'),
    )),
);

// ── Camera ──────────────────────────────────────────────────────────────
// The camera band as the page's top, the four ways of shooting as a sticky
// showcase beside the photo, sample photos coming in one after another and
// the camera rows of the spec comparison.
$camera_panels = array(
    'camera-main' => array('figure' => lang('50 MP main camera'), 'title' => lang('More light, less noise'),
        'text'   => lang('A large sensor and an f/1.7 lens take in light the way your eye does; optical stabilisation keeps the hand out of the picture.'),
        'points' => array(lang('50 MP, f/1.7 with optical stabilisation'), lang('Portraits with a natural edge around hair'), lang('Raw photos for editing')),
        'topics' => lang('main camera, sensor, portrait, raw')),
    'camera-zoom' => array('figure' => lang('Up to 5x optical zoom'), 'title' => lang('Close from the back row'),
        'text'   => lang('Orbit One zooms 3x through glass, Orbit One Plus 5x through a periscope lens, both with the main camera\'s colours.'),
        'points' => array(lang('3x on Orbit One, 5x on Orbit One Plus'), lang('Up to 30x digital zoom'), lang('Zoom in video without a jump in colour')),
        'topics' => lang('zoom, telephoto, periscope, concert')),
    'camera-night' => array('figure' => lang('Night mode'), 'title' => lang('After dark, still sharp'),
        'text'   => lang('Night mode starts by itself and works on every lens; a second at arm\'s length is enough for a street at midnight.'),
        'points' => array(lang('Night mode on all three cameras'), lang('Night portraits with the front camera'), lang('Astro mode on a tripod')),
        'topics' => lang('night mode, low light, astro, dark')),
    'camera-video' => array('figure' => lang('Video'), 'title' => lang('Cinema in your pocket'),
        'text'   => lang('4K at 120 frames for slow motion, steady enough to run with; on Orbit One Plus 8K for cropping later.'),
        'points' => array(lang('4K at up to 120 fps'), lang('8K at 30 fps on Orbit One Plus'), lang('Four microphones that follow your subject')),
        'topics' => lang('video, 4K, 8K, slow motion, stabilisation')),
);

$sample_photos = array();
foreach (array(
    array('sample-portrait', lang('A portrait in soft evening light')),
    array('sample-zoom', lang('A bird on a branch taken at 5x zoom')),
    array('sample-night', lang('A harbour at night without a tripod')),
    array('sample-wide', lang('A mountain valley with the ultra wide camera')),
) as $s) {
    $sample_photos[] = $col(array(
        $el('figure', 'mb-0', lang('Sample Photo'), array(
            $image($photo($s[0], 800, 800), $s[1], 'img-fluid w-100 mb-2 rounded-4 bg-body-tertiary object-fit-cover pg-ratio-1x1', array('_attrs' => array($attr('loading', 'lazy')))),
            $el('figcaption', 'small text-body-secondary', lang('Caption'), array(), array('text' => $s[1])),
        )),
    ), '6', '3', '', '6');
}

$public_pages[] = array(
    'key'              => 'camera',
    'name'             => lang('orbit-camera'),
    'folder'           => 'public',
    'title'            => lang('Camera'),
    'meta_description' => lang('Orbit\'s three cameras: a 50 MP main camera, an ultra wide, up to 5x optical zoom, night mode on every lens and 4K video.'),
    'tree'             => $page(array(
        $launch_camera(true),
        $launch_showcase($camera_panels, $photo('camera-lenses', 900, 900), lang('The three cameras on the back of the Orbit One Plus')),
        $el('section', 'py-5 bg-body-tertiary', lang('Sample Photos'), array(
            $container(array(
                $section_header(lang('Shot on Orbit'), lang('Sample photos')),
                $fx($row($sample_photos, '3'), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => 100)),
                $para(lang('Sample photos are unedited apart from cropping.'), 'small text-body-secondary mt-3 mb-0'),
            )),
        )),
        $el('section', 'py-5', lang('Camera Specifications'), array(
            $container(array(
                $section_header(lang('In detail'), lang('Camera specifications'), lang('All specs'), '{{page:specs}}'),
                $spec_table('camera', array('caption' => lang('The cameras of each model, side by side.'))),
            )),
        ), $searchable('camera-specs', lang('camera specs, lenses, megapixels, zoom, video'))),
        $shared('launch_band'),
    )),
);

// ── Tech specs ──────────────────────────────────────────────────────────
// Every group of $spec_groups in one comparison, the models' head row
// sticky as the groups pass; each group a search section (#spec-<key>)
// opened from the jump list. Then what comes in the box.
$jump_links = array();
foreach ($spec_groups as $key => $g) {
    $jump_links[] = $el('li', 'nav-item', lang('Nav Item'), array($link($g['title'], '#spec-' . $key, 'nav-link px-0')));
}

$public_pages[] = array(
    'key'              => 'specs',
    'name'             => lang('tech-specs'),
    'folder'           => 'public',
    'title'            => lang('Tech Specs'),
    'meta_description' => lang('Every specification of Orbit Mini, Orbit One and Orbit One Plus: screen, chip, cameras, battery, size, weight and connections.'),
    'tree'             => $page(array(
        $page_header(lang('Tech Specs'), lang('Every figure of the three models side by side.'), array(
            $el('nav', 'mt-3', lang('Jump to'), array(
                $el('ul', 'nav column-gap-4 small', lang('Jump Links'), $jump_links),
            ), array('_attrs' => array($attr('aria-label', lang('Specification groups'))))),
        )),
        $el('section', 'py-5', lang('Specifications'), array(
            $container(array(
                $spec_table(array(), array('anchors' => true)),
                $para(lang('Battery and charging figures are from lab tests; real use varies with settings and signal.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        )),
        $launch_box($searchable('in-the-box', lang('box contents, charger, cable, accessories'))),
        $shared('launch_band'),
    )),
);

// ── Compare ─────────────────────────────────────────────────────────────
// The three models coming in side by side, then only what tells them apart
// (the rows of $spec_groups whose values differ) and, below, what every
// model shares: one source, so a figure changed in parts.php moves a row
// between the two by itself.
$compare_heads = array();
foreach ($models as $key => $m) {
    $compare_heads[] = $col(array(
        $el('div', 'text-center', $m['name'], array(
            $image($render($m['name'], 600, 600), $m['name'], 'img-fluid w-100 mb-3 object-fit-contain pg-ratio-1x1', array('_attrs' => array($attr('loading', 'lazy')))),
            $heading('h2', $m['name'], 'h5 fw-semibold mb-1'),
            $para($m['tagline'], 'small text-body-secondary mb-2'),
            $para($price_from($key), 'fw-semibold mb-3 plx-price'),
            $link(lang('Buy'), '{{page:buy}}#buy-' . $key, 'btn btn-sm btn-primary rounded-pill px-4'),
        )),
    ), '4', '', '', '4');
}
$shared_specs = array();
foreach ($spec_groups as $g) {
    foreach ($g['rows'] as $r) {
        if (count(array_unique($r[1])) === 1) $shared_specs[] = $r[0] . ': ' . reset($r[1]);
    }
}

$public_pages[] = array(
    'key'              => 'compare',
    'name'             => lang('compare-models'),
    'folder'           => 'public',
    'title'            => lang('Compare Models'),
    'meta_description' => lang('Orbit Mini, Orbit One and Orbit One Plus compared: size, screen, cameras, battery and price.'),
    'tree'             => $page(array(
        $page_header(lang('Compare Models'), lang('Same chip, same main camera, same seven years of updates. Pick the size, the zoom and the battery.')),
        $el('section', 'py-5', lang('Models'), array(
            $container(array(
                $fx($row($compare_heads, '3', '', 'mb-5'), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => 150)),
                $spec_table(array(), array('differ' => true, 'caption' => lang('What differs between the three models.'))),
            )),
        ), $searchable('compare', lang('compare, difference, which model, mini, plus'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Shared'), array(
            $container(array(
                $section_header(lang('In every model'), lang('What every Orbit shares'), lang('All specs'), '{{page:specs}}'),
                $check_list($shared_specs, 'list-unstyled row row-cols-1 row-cols-md-2 g-3 mb-0'),
            )),
        ), $searchable('compare-shared', lang('same, shared, every model, chip, updates'))),
        $shared('launch_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
$values = array(
    array('icon' => 'bi-hourglass-split', 'title' => lang('Made to last'), 'text' => lang('One phone a year, updated for seven, with parts in stock for as long.')),
    array('icon' => 'bi-recycle', 'title' => lang('Recycled where it counts'), 'text' => lang('The frame is recycled aluminium, the back recycled glass, the box paper without plastic.')),
    array('icon' => 'bi-wrench-adjustable', 'title' => lang('Easy to repair'), 'text' => lang('A screen or a battery changes in under half an hour, at our centre or a partner\'s.')),
);
$value_cols = array();
foreach ($values as $v) {
    $value_cols[] = $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', $v['title'], array(
            $icon_tile($v['icon'], 'mb-3'),
            $heading('h3', $v['title'], 'h5 fw-semibold mb-2'),
            $para($v['text'], 'small text-body-secondary mb-0'),
        )),
    ), '', '4');
}

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Who makes Orbit: a small team that makes one phone a year, built to be repaired and updated for seven years.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), lang('We make one phone a year and look after it for seven.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Fewer phones, better ones'), 'h3 fw-semibold mb-3'),
                        $para(lang('We started with a question: why does a phone that still works feel old after two years? Our answer is one model family a year, made from materials that last, and software support long after the launch.'), 'text-body-secondary'),
                        $para(lang('Our designers, engineers and repair technicians sit in one building, so a phone is planned to be opened as much as to be closed.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('about-studio', 1200, 900), lang('Engineers at a bench testing phones'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('company, team, who we are'))),
        $el('section', 'py-5 border-top', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we stand for'), lang('How we make phones')),
                $row($value_cols, '4'),
            )),
        ), $searchable('sustainability', lang('sustainability, recycled, environment, repair, materials'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Work With Us'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Press and partners'), 'h4 fw-semibold mb-1'),
                        $para(lang('Journalists, retailers and repair partners: write to us and the right person answers.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                )),
            )),
        )),
        $shared('launch_band'),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: buying, trade-in, support and warranty. Each question
// is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'h4 fw-semibold mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about buying an Orbit, trading in your old phone, delivery, returns, repairs and the warranty.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Buying, trade-in, repairs and the warranty. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('lx-faq-buy', lang('Buying'), array(
                            array(lang('Which model should I choose?'), lang('All three have the same chip, main camera and updates. Orbit Mini fits one hand, Orbit One adds a 3x zoom and a bigger battery, Orbit One Plus the largest screen and a 5x zoom.'), lang('which model, mini, plus, difference')),
                            array(lang('Is the phone unlocked?'), lang('Yes: every Orbit works with any network, with a nano SIM and an eSIM at the same time.'), lang('unlocked, SIM, eSIM, network, carrier')),
                            array(lang('How long does delivery take?'), lang('Orders placed on a working day usually ship the same day and arrive in one to three working days.'), lang('delivery, shipping, how long')),
                            array(lang('Can I return it?'), lang('Within 30 days of delivery, in its box with what came in it. Write to us from your account and we collect it.'), lang('return, refund, money back')),
                        )),
                        $faq_group('lx-faq-trade', lang('Trade-in'), array(
                            array(lang('Which phones can I trade in?'), lang('Any smartphone that switches on, of any brand. A phone that does not switch on still gets a recycling quote.'), lang('trade-in, which phones, old phone')),
                            array(lang('How is the trade-in value set?'), lang('By the model, storage and condition you describe. We confirm it when the phone arrives; if it differs, you choose the new offer or get it back free.'), lang('trade-in value, price, quote')),
                            array(lang('What happens to my data?'), lang('Reset the phone before sending it. We erase every phone again with certified software before it is refurbished or recycled.'), lang('data, erase, reset, privacy')),
                        )),
                        $faq_group('lx-faq-support', lang('Repairs and warranty'), array(
                            array(lang('What does the warranty cover?'), lang('Two years against faults in materials and workmanship. Accidental damage is repaired at a fixed price.'), lang('warranty, guarantee, faulty')),
                            array(lang('How do I get a repair?'), lang('Send a support request from your account; we collect the phone, repair it at our centre and bring it back, usually within five working days.'), lang('repair, broken screen, battery, service')),
                            array(lang('How long will my phone get updates?'), lang('Seven years of system and security updates from launch, and spare parts for as long.'), lang('updates, software, security, how long')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('launch_band'),
    )),
);

// The home page's newsroom list (home_posts) belongs to the standard pages
// with the newsroom itself; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
