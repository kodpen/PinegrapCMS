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

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$feature_band = function ($id, $eyebrow, $title, $text, $points, $picture, $alt, $flip = false, $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $image, $attr, $container, $row, $col, $check_list, $searchable) {
    $text_col = $col(array(
        $para($eyebrow, 'plx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'display-6 fw-semibold mb-3'),
        $para($text, 'lead text-body-secondary mb-4'),
        $check_list($points),
    ), '', '6', '', '12', $flip ? 'order-lg-last' : '');
    $picture_col = $col(array(
        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
            $image($picture, $alt, 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
        )),
    ), '', '6');
    return $el('section', $class, $title, array(
        $container(array(
            $row(array($text_col, $picture_col), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// The model cards in a row, smallest first.
$model_cards = array();
foreach (array_keys($models) as $key) $model_cards[] = $model_card($key);

// The highlight tiles, in the order the home page tells them.
$highlight_tiles = array();
foreach (array_keys($highlights) as $key) $highlight_tiles[] = $highlight_tile($key);

// The press quotes in a row.
$press_cols = array();
foreach ($press_quotes as $q) $press_cols[] = $press_quote($q);

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

// ── Home ────────────────────────────────────────────────────────────────
$featured_model = 'one';
foreach ($models as $key => $m) {
    if ($m['featured']) $featured_model = $key;
}

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('The new Orbit phone: a screen you can read in the sun, three matched cameras, two days of battery and seven years of updates.'),
    'tree'             => $page(array(
        // The phone on a dark stage: its name, one line, the price it starts
        // at and the two ways on.
        $el('section', 'py-5 bg-body text-body overflow-hidden plx-hero', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $span(lang('New'), 'badge rounded-pill text-bg-primary mb-3', lang('Badge')),
                        $heading('h1', $models[$featured_model]['name'], 'display-3 fw-semibold mb-3'),
                        $para(lang('Made to last. A brighter screen, three matched cameras and a battery that sees out the weekend.'), 'lead text-body-secondary mb-3'),
                        $para($price_from($featured_model), 'fw-semibold mb-4 plx-price'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Buy'), '{{page:buy}}#buy-' . $featured_model, 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Explore the features'), '{{page:features}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3', lang('Picture Frame'), array(
                            $image($render($models[$featured_model]['name'], 1200, 900), lang('The Orbit One phone from the front and the back'), 'object-fit-contain'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),

        // The highlights: six tiles, each opening its part of the features page.
        $el('section', 'py-5', lang('Highlights'), array(
            $container(array(
                $section_header(lang('Why Orbit'), lang('Everything you notice every day'), lang('All features'), '{{page:features}}'),
                $row($highlight_tiles, '4'),
            )),
        )),

        // The three models.
        $el('section', 'py-5 bg-body-tertiary', lang('Models'), array(
            $container(array(
                $section_header(lang('The family'), lang('Three sizes, one Orbit'), lang('Compare models'), '{{page:compare}}'),
                $row($model_cards, '4'),
            )),
        )),

        // The colours.
        $el('section', 'py-5', lang('Colours'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Colours'), 'plx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('Four colours, every model'), 'h3 fw-semibold mb-3'),
                        $para(lang('Each colour goes through the glass, not on it, so a scratch never shows a different shade.'), 'text-body-secondary mb-4'),
                        $swatches(),
                    ), '', '5'),
                    $col(array(
                        $el('div', 'ratio ratio-16x9 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('colours', 1200, 675), lang('Four Orbit phones side by side, one in each colour'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The camera, on the dark stage again.
        $el('section', 'py-5 bg-body text-body', lang('Camera'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('camera-night', 1200, 900), lang('A street at night photographed with the Orbit One'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6', '', '12', 'order-lg-last'),
                    $col(array(
                        $para(lang('Camera'), 'plx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('Three cameras, one look'), 'display-6 fw-semibold mb-3'),
                        $para(lang('Wide, ultra wide and zoom share one colour science, so the photos of a day look like one set. At night the main camera takes in four times more light.'), 'lead text-body-secondary mb-4'),
                        $link(lang('See the camera'), '{{page:camera}}', 'btn btn-outline-light rounded-pill px-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),

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

        // What the press says.
        $el('section', 'py-5 bg-body-tertiary', lang('Press'), array(
            $container(array(
                $section_header(lang('Reviews'), lang('What the press says'), lang('Newsroom'), '{{page:blog}}'),
                $row($press_cols, '4'),
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
// A band for each highlight, the picture on alternating sides; each band is
// the anchor its home tile opens (#feature-<key>) and a search section.
$feature_points = array(
    'design'  => array(lang('Recycled aluminium frame'), lang('Matte glass front and back'), lang('IP68: 6 metres for 30 minutes')),
    'display' => array(lang('Up to 2,600 nits outdoors'), lang('1 to 120 Hz, adapting to what you do'), lang('Eye comfort mode for the evening')),
    'camera'  => array(lang('50 MP main and ultra wide cameras'), lang('3x or 5x optical zoom on One and One Plus'), lang('Night mode on every lens')),
    'battery' => array(lang('Two days of ordinary use'), lang('50% in 15 minutes with the 65 W charger'), lang('25 W wireless charging')),
    'chip'    => array(lang('Orbit O3, built at 3 nm'), lang('Up to 16 GB of memory'), lang('A vapour chamber that keeps it cool')),
    'updates' => array(lang('Seven years of system updates'), lang('Monthly security fixes'), lang('Parts and repairs for as long')),
);
$feature_bands = array();
$n = 0;
foreach ($highlights as $key => $h) {
    $feature_bands[] = $feature_band('feature-' . $key, $h['figure'], $h['title'], $h['text'], $feature_points[$key],
        $photo('feature-' . $key, 1200, 900), $h['title'], $n % 2 === 1, $h['topics'], 'py-5' . ($n % 2 === 1 ? ' bg-body-tertiary' : ''));
    $n++;
}

$colour_cols = array();
foreach ($colours as $key => $c) {
    $colour_cols[] = $col(array(
        $el('div', 'text-center', $c['name'], array(
            $el('span', 'plx-swatch plx-swatch-' . $key . ' plx-swatch-lg d-inline-block rounded-circle border mb-3', lang('Swatch'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
            $heading('h3', $c['name'], 'h6 fw-semibold mb-1'),
            $para($c['text'], 'small text-body-secondary mb-0'),
        )),
    ), '3', '', '', '6');
}

$public_pages[] = array(
    'key'              => 'features',
    'name'             => lang('orbit-features'),
    'folder'           => 'public',
    'title'            => lang('Features'),
    'meta_description' => lang('The Orbit\'s design, screen, cameras, battery, chip and seven years of updates, one by one.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Features'), lang('What makes Orbit the phone you keep: the parts you notice every day, one by one.'))),
        $feature_bands,
        array(
            $el('section', 'py-5', lang('Colours'), array(
                $container(array(
                    $section_header(lang('Colours'), lang('Four colours, every model')),
                    $row($colour_cols, '4'),
                )),
            ), $searchable('colours', lang('colour, color, graphite, glacier, sage, coral'))),
            $shared('launch_band'),
        )
    )),
);

// ── Camera ──────────────────────────────────────────────────────────────
// The cameras one by one, a strip of sample photos and the camera rows of
// the spec table.
$sample_photos = array();
foreach (array(
    array('sample-portrait', lang('A portrait in soft evening light')),
    array('sample-zoom', lang('A bird on a branch taken at 5x zoom')),
    array('sample-night', lang('A harbour at night without a tripod')),
    array('sample-wide', lang('A mountain valley with the ultra wide camera')),
) as $s) {
    $sample_photos[] = $col(array(
        $el('figure', 'mb-0', lang('Sample Photo'), array(
            $el('div', 'ratio ratio-1x1 rounded-4 overflow-hidden bg-body-tertiary mb-2', lang('Picture Frame'), array(
                $image($photo($s[0], 800, 800), $s[1], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
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
        $el('section', 'py-5 bg-body text-body plx-hero', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Camera'), 'plx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h1', lang('Three cameras, one look'), 'display-4 fw-semibold mb-3'),
                        $para(lang('Every lens shares one colour science and one night mode, so you choose the frame, not the camera.'), 'lead text-body-secondary mb-0'),
                    ), '', '8'),
                ), '4', 'center', 'text-center'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),
        $feature_band('camera-main', lang('50 MP main camera'), lang('More light, less noise'),
            lang('A large sensor and an f/1.7 lens take in light the way your eye does; optical stabilisation keeps the hand out of the picture.'),
            array(lang('50 MP, f/1.7 with optical stabilisation'), lang('Portraits with a natural edge around hair'), lang('Raw photos for editing')),
            $photo('camera-main', 1200, 900), lang('A close portrait taken with the main camera'), false, lang('main camera, sensor, portrait, raw')),
        $feature_band('camera-zoom', lang('Up to 5x optical zoom'), lang('Close from the back row'),
            lang('Orbit One zooms 3x through glass, Orbit One Plus 5x through a periscope lens, both with the main camera\'s colours.'),
            array(lang('3x on Orbit One, 5x on Orbit One Plus'), lang('Up to 30x digital zoom'), lang('Zoom in video without a jump in colour')),
            $photo('camera-zoom', 1200, 900), lang('A singer on a far stage, taken with the telephoto camera'), true, lang('zoom, telephoto, periscope, concert'), 'py-5 bg-body-tertiary'),
        $feature_band('camera-night', lang('Night mode'), lang('After dark, still sharp'),
            lang('Night mode starts by itself and works on every lens; a second at arm\'s length is enough for a street at midnight.'),
            array(lang('Night mode on all three cameras'), lang('Night portraits with the front camera'), lang('Astro mode on a tripod')),
            $photo('camera-night-2', 1200, 900), lang('City lights over water at night'), false, lang('night mode, low light, astro, dark')),
        $feature_band('camera-video', lang('Video'), lang('Cinema in your pocket'),
            lang('4K at 120 frames for slow motion, steady enough to run with; on Orbit One Plus 8K for cropping later.'),
            array(lang('4K at up to 120 fps'), lang('8K at 30 fps on Orbit One Plus'), lang('Four microphones that follow your subject')),
            $photo('camera-video', 1200, 900), lang('A cyclist filmed while running beside the bike'), true, lang('video, 4K, 8K, slow motion, stabilisation'), 'py-5 bg-body-tertiary'),
        $el('section', 'py-5', lang('Sample Photos'), array(
            $container(array(
                $section_header(lang('Shot on Orbit'), lang('Sample photos')),
                $row($sample_photos, '3'),
                $para(lang('Sample photos are unedited apart from cropping.'), 'small text-body-secondary mt-3 mb-0'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Camera Specifications'), array(
            $container(array(
                $section_header(lang('In detail'), lang('Camera specifications'), lang('All specs'), '{{page:specs}}'),
                $spec_table('camera'),
            )),
        )),
        $shared('launch_band'),
    )),
);

// ── Tech specs ──────────────────────────────────────────────────────────
// A table for each group of $spec_groups, every model in a column, behind a
// jump list; each group is a search section (#spec-<key>).
$jump_links = array();
$spec_sections = array();
foreach ($spec_groups as $key => $g) {
    $jump_links[] = $el('li', 'nav-item', lang('Nav Item'), array($link($g['title'], '#spec-' . $key, 'nav-link px-0')));
    $spec_sections[] = $el('section', 'pb-5', $g['title'], array(
        $el('h2', 'h4 fw-semibold d-flex align-items-center gap-2 mb-3', lang('Title'), array(
            $icon($g['icon'], 'text-primary'),
            $span($g['title'], '', lang('Text')),
        )),
        $spec_table($key),
    ), $searchable('spec-' . $key, lang('specs, specifications, technical details')));
}

$in_the_box = array(lang('Orbit phone'), lang('USB-C to USB-C cable, 1 m'), lang('SIM tool'), lang('Quick start guide'));

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
        $el('div', 'py-5', lang('Specifications'), array(
            $container(array_merge($spec_sections, array(
                $el('section', 'pb-4', lang('In the Box'), array(
                    $heading('h2', lang('In the box'), 'h4 fw-semibold mb-3'),
                    $check_list($in_the_box),
                    $para(lang('No charger in the box: it comes separately, so a drawer of old chargers does not grow.'), 'small text-body-secondary mt-3 mb-0'),
                ), $searchable('in-the-box', lang('box contents, charger, cable, accessories'))),
                $para(lang('Battery and charging figures are from lab tests; real use varies with settings and signal.'), 'small text-body-secondary mb-0'),
            ))),
        )),
        $shared('launch_band'),
    )),
);

// ── Compare ─────────────────────────────────────────────────────────────
// The three models side by side: a head of renders and prices, then every
// spec group as one table each.
$compare_heads = array();
foreach ($models as $key => $m) {
    $compare_heads[] = $col(array(
        $el('div', 'text-center', $m['name'], array(
            $el('div', 'ratio ratio-1x1 mb-3', lang('Picture Frame'), array(
                $image($render($m['name'], 600, 600), $m['name'], 'object-fit-contain', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $heading('h2', $m['name'], 'h5 fw-semibold mb-1'),
            $para($m['tagline'], 'small text-body-secondary mb-2'),
            $para($price_from($key), 'fw-semibold mb-3 plx-price'),
            $link(lang('Buy'), '{{page:buy}}#buy-' . $key, 'btn btn-sm btn-primary rounded-pill px-4'),
        )),
    ), '4', '', '', '4');
}
$compare_tables = array();
foreach (array_keys($spec_groups) as $key) {
    $compare_tables[] = $el('div', 'mb-4', $spec_groups[$key]['title'], array($spec_table($key)));
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
                $row($compare_heads, '3', '', 'mb-5'),
                $el('div', '', lang('Specifications'), $compare_tables),
            )),
        ), $searchable('compare', lang('compare, difference, which model, mini, plus'))),
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
