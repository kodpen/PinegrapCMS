<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Mobile App Developer", its public pages: home, the
 * services with the three packages, the way of working, about, the
 * questions users and clients ask, and the press kit. Included after
 * parts.php in the same scope (its builders and the developer's data);
 * returns the list of pages. The lists the home places (home_apps,
 * home_releases, home_posts) are widgets of the other page files.
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

// ── Pieces the pages share ──────────────────────────────────────────────

// The developer in four figures, in a row.
$figure_row = function ($class = '') use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pma-display display-6 fw-bold text-primary mb-1'),
                $para($f['label'], 'small text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $el('div', $class, lang('Figures'), array($row($cols, '4')));
};

// A service card: what the developer does, its points and the way to ask
// for it (the project request with the kind of project chosen, ?type=).
$service_card = function ($key, $lg = '4') use ($el, $heading, $para, $link, $icon_tile, $check_list, $col, $searchable, $services, $project_types, $query) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', $s['title'], array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3 align-self-start'),
                $heading('h3', $s['title'], 'h5 fw-bold mb-2'),
                $para($s['text'], 'text-body-secondary mb-3'),
                $check_list($s['points'], 'list-unstyled small mb-4'),
                $link(lang('Ask about this'), '{{page:hire}}?' . $query(array('type' => $project_types[$s['type']])),
                    'btn btn-outline-primary rounded-pill align-self-start mt-auto'),
            )),
        ), $searchable('service-' . $key)),
    ), '6', $lg);
};

// The way of working as a numbered timeline: the icon, the step, how long
// it takes and what happens.
$process_list = function ($class = 'list-unstyled mb-0') use ($el, $heading, $para, $span, $icon_tile, $process) {
    $items = array();
    $last = count($process) - 1;
    foreach (array_values($process) as $n => $p) {
        $items[] = $el('li', 'd-flex gap-3 ' . ($n === $last ? 'mb-0' : 'mb-4'), lang('Step'), array(
            $icon_tile($p['icon'], 'flex-shrink-0 align-self-start'),
            $el('div', '', lang('Text'), array(
                $el('div', 'd-flex flex-wrap align-items-baseline gap-2 mb-1', lang('Step Title'), array(
                    $heading('h3', ($n + 1) . '. ' . $p['title'], 'h6 fw-bold mb-0'),
                    $span($p['time'], 'badge rounded-pill bg-body text-body-emphasis border', lang('Time')),
                )),
                $para($p['text'], 'text-body-secondary mb-0'),
            )),
        ));
    }
    return $el('ol', $class, lang('Process'), $items);
};

// A phone-shaped frame around a sample screenshot (9:16, kept by the
// picture's own width and height).
$phone_shot = function ($seed, $alt, $class = '') use ($el, $image, $attr, $photo) {
    return $el('div', trim('border border-4 border-dark rounded-5 overflow-hidden shadow bg-body-tertiary ' . $class), lang('Phone Frame'), array(
        $image($photo($seed, 540, 960), $alt, 'd-block w-100 h-auto', array('_attrs' => array($attr('width', '540'), $attr('height', '960'), $attr('loading', 'lazy')))),
    ));
};

// The tools the apps are made with, as badges (the services and about pages).
$tool_list = array();
foreach ($tools as $t) $tool_list[] = $span($t, 'badge rounded-pill bg-body text-body-emphasis border fw-normal pma-mono', lang('Tool'));

$package_cards = array();
foreach (array_keys($packages) as $key) $package_cards[] = $package_card($key);
$testimonial_cards = array();
foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────
// Built on the designer's App Studio blocks (style_designer_blocks_appstudio.js,
// the same classes and interactions): the phone fan hero, the app switch,
// the release chapter and the two platforms; the figures as the Creative
// group's bento tiles. Motion is the nodes' interactions (props._fx).

$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
$dark = array('_attrs' => array($attr('data-bs-theme', 'dark')));

// A phone frame around a sample screen (9:19.5, the frame's own ratio).
// The hero's phones load at once, the others when they come near.
$as_phone = function ($seed, $alt, $class = '', $lazy = true) use ($el, $image, $attr, $photo) {
    $attrs = array($attr('width', '540'), $attr('height', '1170'));
    if ($lazy) $attrs[] = $attr('loading', 'lazy');
    return $el('div', trim('pg-appstudio-phone ' . $class), lang('Phone Frame'), array(
        $image($photo($seed, 540, 1170), $alt, '', array('_attrs' => $attrs)),
    ));
};
$as_eyebrow = function ($text, $class = 'mb-3') use ($para) {
    return $para($text, trim('pma-eyebrow small fw-semibold text-uppercase text-primary ' . $class));
};
// A section's head, centred: the line above, a very large title, a lead.
$as_header = function ($eyebrow, $title, $lead) use ($el, $heading, $para, $as_eyebrow) {
    return $el('div', 'text-center mb-5', lang('Section Header'), array(
        $as_eyebrow($eyebrow),
        $heading('h2', $title, 'pma-display pg-appstudio-tight fw-bold mb-3'),
        $para($lead, 'lead text-body-secondary mx-auto mb-0 col-lg-7'),
    ));
};

// The local bar: the developer's name, the parts of the site and the call
// to action, held at the top of the window while the home scrolls.
$local_bar = $fx($el('nav', 'bg-body border-bottom z-3', lang('Local Bar'), array(
    $container(array(
        $el('div', 'd-flex align-items-center gap-3 py-2', lang('Bar Row'), array(
            $span($developer['name'], 'fw-semibold me-auto', lang('Name')),
            $link(lang('Apps'), '{{page:apps}}', 'd-none d-md-inline small link-body-emphasis text-decoration-none'),
            $link(lang('Releases'), '{{page:releases}}', 'd-none d-md-inline small link-body-emphasis text-decoration-none'),
            $link(lang('Services'), '{{page:services}}', 'd-none d-md-inline small link-body-emphasis text-decoration-none'),
            $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-primary btn-sm rounded-pill px-3'),
        )),
    )),
), array('_attrs' => array($attr('aria-label', lang('Home sections'))))), array('effect' => 'sticky', 'offset' => 0));

// The hero: a dark band, one very large line, three phones rising in a fan.
$home_hero = $el('section', 'pt-5 bg-body text-body text-center overflow-hidden pg-appstudio-floor', lang('Hero'), array(
    $container(array(
        $fx($el('div', 'mx-auto col-lg-10', lang('Hero Text'), array(
            $as_eyebrow($developer['role']),
            $heading('h1', lang('Apps that do one thing, and do it well'), 'pma-display pg-appstudio-tight fw-bold mb-4'),
            $para($developer['bio'], 'lead text-body-secondary mx-auto mb-4 col-lg-8'),
            $el('div', 'd-flex flex-wrap justify-content-center gap-3 mb-3', lang('Buttons'), array(
                $link(lang('See My Apps'), '{{page:apps}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
            )),
            $el('p', 'd-flex justify-content-center align-items-center gap-2 small text-body-secondary mb-0', lang('Availability'), array(
                $icon('bi-circle-fill', 'text-success small'),
                $span($developer['available'], '', lang('Text')),
            )),
        )), array('effect' => 'fade', 'trigger' => 'load', 'duration' => 900)),
    ), 'pt-lg-5'),
    $fx($el('div', 'pg-appstudio-fan', lang('Phone Fan'), array(
        $as_phone('fan-tally', $apps['tally']['name'] . ': ' . $apps['tally']['tagline'], '', false),
        $as_phone('fan-stride', $apps['stride']['name'] . ': ' . $apps['stride']['tagline'], 'pg-appstudio-lead', false),
        $as_phone('fan-wayfarer', $apps['wayfarer']['name'] . ': ' . $apps['wayfarer']['tagline'], '', false),
    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 1000, 'delay' => 200, 'stagger' => 150, 'distance' => 120)),
), $dark);

// The figures as bento tiles of different sizes, rising one after another.
$bento_tile = function ($md, $lg, $class, $children, $extra = array()) use ($el, $col) {
    return $col(array($el('div', 'h-100 p-4 rounded-4 ' . $class, lang('Tile Body'), $children, $extra)), $md, $lg);
};
$home_bento = $el('section', 'py-5', lang('Figures'), array(
    $container(array(
        $as_header(lang('In numbers'), lang('A small studio, a long reach.'), $developer['city']),
        $fx($row(array(
            $bento_tile('12', '7', 'd-flex flex-column p-lg-5 text-bg-primary', array(
                $icon('bi-phone', 'fs-2 mb-3'),
                $heading('h3', $figures[0]['value'] . ' ' . $figures[0]['label'], 'h4 fw-bold mb-2'),
                $para($apps['tally']['summary'], 'mb-4'),
                $link(lang('All apps'), '{{page:apps}}', 'align-self-start mt-auto fw-semibold text-reset'),
            )),
            $bento_tile('12', '5', 'd-flex flex-column justify-content-between bg-body-tertiary', array(
                $span($figures[1]['label'], 'mb-3 small fw-semibold text-uppercase text-body-secondary', lang('Label')),
                $para($figures[1]['value'], 'pma-display pg-appstudio-figure display-3 fw-bold lh-1 text-primary mb-0'),
            )),
            $bento_tile('6', '4', 'bg-body border', array(
                $icon_tile('bi-star-fill', 'mb-3'),
                $heading('h3', $figures[2]['value'], 'pg-appstudio-figure h2 fw-bold mb-1'),
                $para($figures[2]['label'], 'mb-0 text-body-secondary'),
            )),
            $bento_tile('6', '4', 'bg-body text-body border', array(
                $icon('bi-briefcase', 'd-block mb-3 fs-2 text-primary'),
                $heading('h3', $figures[3]['value'], 'pg-appstudio-figure h2 fw-bold mb-1'),
                $para($figures[3]['label'], 'mb-0 text-body-secondary'),
            ), $dark),
            $bento_tile('12', '4', 'bg-body border', array(
                $icon_tile('bi-shield-lock', 'mb-3'),
                $heading('h3', lang('Your data stays yours'), 'h5 fw-bold mb-2'),
                $para($apps['tally']['privacy'], 'mb-0 small text-body-secondary'),
            )),
        ), '4'), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => 120)),
    )),
));

// The app switch: the checked app puts its screen on the phone and its
// line under it (CSS :has(), no script); the kinds of app link the list.
$switch_tabs = array($el('legend', 'visually-hidden', lang('Choices Legend'), array($span(lang('App'), '', lang('Text')))));
$switch_screens = array();
$switch_notes = array();
foreach (array('tally', 'stride', 'wayfarer') as $n => $key) {
    $i = $n + 1;
    $id = 'pma-app-pick-' . $i;
    $input_attrs = array($attr('type', 'radio'), $attr('name', 'pma-app-pick'), $attr('id', $id), $attr('autocomplete', 'off'));
    if ($i === 1) $input_attrs[] = $attr('checked', 'checked');
    $switch_tabs[] = $el('div', 'pg-appstudio-tab', lang('App Choice'), array(
        $el('input', 'btn-check pg-appstudio-pick-' . $i, lang('Choice'), array(), array('_attrs' => $input_attrs)),
        $el('label', 'btn btn-outline-primary rounded-pill px-4', lang('Choice Label'), array($span($apps[$key]['name'], '', lang('Text'))),
            array('_attrs' => array($attr('for', $id)))),
    ));
    $switch_screens[] = $as_phone('switch-' . $key, $apps[$key]['name'] . ': ' . $apps[$key]['tagline'], 'pg-appstudio-screen pg-appstudio-shows-' . $i);
    $switch_notes[] = $el('div', 'pg-appstudio-note pg-appstudio-shows-' . $i, lang('App Words'), array(
        $heading('h3', $apps[$key]['name'], 'h4 fw-bold mb-1'),
        $para($apps[$key]['summary'], 'text-body-secondary mb-0'),
    ));
}
$kind_links = array($span(lang('Browse by kind'), 'small text-body-secondary me-1', lang('Text')));
foreach ($categories as $c) {
    $kind_links[] = $link($c['title'], '{{page:apps}}?' . $query(array('category' => $c['title'])), 'btn btn-sm btn-outline-secondary rounded-pill');
}
$home_switch = $el('section', 'py-5 text-center pg-appstudio-switch', lang('App Switch'), array(
    $container(array(
        $fx($as_header(lang('Pick an app'), lang('One idea per app.'), lang('Each app does one thing and does it well. Choose one to see it on the phone.')),
            array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700)),
        $el('fieldset', 'd-flex justify-content-center flex-wrap gap-2 border-0 p-0 mb-5', lang('App Choices'), $switch_tabs),
        $fx($el('div', 'pg-appstudio-stage mb-4', lang('Phone Stage'), $switch_screens), array('effect' => 'scale', 'trigger' => 'view', 'duration' => 800)),
        $el('div', 'pg-appstudio-stage mx-auto col-lg-6', lang('Words Stage'), $switch_notes),
        $el('div', 'd-flex flex-wrap justify-content-center align-items-center gap-2 mt-5', lang('Kinds of App'), $kind_links),
    )),
));

// The newest release as a dark chapter: its version very large, what
// changed rising one after another, the phone moving slower than the page.
$release = $releases[0];
$release_app = $apps[$release['app']];
$change = function ($tag, $tag_class, $text) use ($el, $span) {
    return $el('li', 'd-flex align-items-start gap-3 mb-3', lang('Change'), array(
        $span($tag, 'badge rounded-pill flex-shrink-0 mt-1 ' . $tag_class, lang('Tag')),
        $span($text, 'text-body', lang('Text')),
    ));
};
$home_release = $el('section', 'py-5 bg-body text-body overflow-hidden', lang('Release Chapter'), array(
    $container(array(
        $row(array(
            $col(array(
                $as_eyebrow(lang('Just released')),
                $heading('h2', $release_app['name'] . ' ' . $release['version'], 'pma-display pg-appstudio-tight pg-appstudio-figure fw-bold mb-3'),
                $para($release['notes'], 'lead text-body-secondary mb-4'),
                $heading('h3', lang('What changed'), 'h6 fw-semibold text-uppercase text-body-secondary mb-3'),
                $fx($el('ul', 'list-unstyled mb-4', lang('Changes'), array(
                    $change(lang('New'), 'text-bg-primary', lang('Interval runs with a voice that counts you through each one.')),
                    $change(lang('Improved'), 'bg-primary-subtle text-primary-emphasis', lang('A calmer summary after every run, with only what matters.')),
                    $change(lang('Bug fix'), 'bg-body-secondary text-body-emphasis border', lang('Runs from the watch come in again after a system update.')),
                )), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => 120)),
                $el('div', 'd-flex flex-wrap gap-3', lang('Buttons'), array(
                    $link(lang('All release notes'), '{{page:releases}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                    $link(lang('Join the Beta'), '{{page:beta}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                )),
            ), '', '6', '', '12', 'order-2 order-lg-1'),
            $col(array(
                $fx($el('div', 'px-5 px-lg-0', lang('Phone Stage'), array(
                    $as_phone('release-' . $release['app'], $release_app['name'] . ': ' . $release_app['tagline'], 'col-lg-8'),
                )), array('effect' => 'parallax', 'speed' => 15)),
            ), '', '5', '', '12', 'order-1 order-lg-2 offset-lg-1'),
        ), '4', '', 'g-lg-5 align-items-center'),
    ), 'py-lg-5'),
), $dark);

// The two platforms side by side, each with its tools and a phone peeking
// out of the card.
$platform_card = function ($icon_name, $title, $text, $tool_names, $seed, $alt) use ($el, $heading, $para, $span, $icon, $col, $as_phone) {
    $tool_items = array();
    foreach ($tool_names as $t) $tool_items[] = $el('li', 'badge rounded-pill bg-body text-body-emphasis border fw-normal pma-mono', lang('Tool'), array($span($t, '', lang('Text'))));
    return $col(array(
        $el('div', 'h-100 d-flex flex-column align-items-center pt-5 px-4 text-center bg-body-tertiary rounded-4 overflow-hidden', lang('Platform Card'), array(
            $icon($icon_name, 'fs-1 mb-3 text-body-emphasis'),
            $heading('h3', $title, 'h3 fw-bold mb-2'),
            $para($text, 'text-body-secondary mb-3'),
            $el('ul', 'list-unstyled d-flex flex-wrap justify-content-center gap-2 mb-5', lang('Tools'), $tool_items),
            $as_phone($seed, $alt, 'pg-appstudio-peek mt-auto'),
        )),
    ), '6');
};
$home_platforms = $el('section', 'py-5', lang('Platforms'), array(
    $container(array(
        $as_header(lang('Native on both'), lang('Made for the phone in your hand.'), lang('Written in each platform\'s own language, so every app feels at home and stays fast.')),
        $fx($row(array(
            $platform_card('bi-apple', lang('iPhone, iPad and Apple Watch'), lang('Widgets, Live Activities and a watch app that works on its own.'),
                array($tools[0], $tools[1], $tools[4]), 'platform-ios', lang('An app on an iPhone')),
            $platform_card('bi-android2', lang('Android phones, tablets and Wear OS'), lang('Material You colours, home screen widgets and offline sync.'),
                array($tools[2], $tools[3], $tools[5]), 'platform-android', lang('An app on an Android phone')),
        ), '4'), array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => 150)),
    )),
));

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Small, calm apps for iPhone and Android, the notes of every release, and apps built for companies with the same care.'),
    'tree'             => $page(array(
        $local_bar,
        $home_hero,
        $home_bento,
        $home_switch,

        $el('section', 'py-5', lang('Featured Apps'), array(
            $container(array(
                $section_header(lang('In the stores'), lang('My apps'), lang('All apps'), '{{page:apps}}'),
                $widget('home_apps'),
            )),
        )),

        $home_release,

        $el('section', 'py-5', lang('Latest Releases'), array(
            $container(array(
                $section_header(lang('What\'s new'), lang('Every update, in plain words'), lang('All release notes'), '{{page:releases}}'),
                $widget('home_releases'),
            )),
        )),

        $home_platforms,

        $el('section', 'py-5 bg-body-tertiary', lang('Services'), array(
            $container(array(
                $section_header(lang('Client projects'), lang('I can build yours too'), lang('All services'), '{{page:services}}'),
                $row($package_cards, '4'),
                $el('p', 'small text-body-secondary text-center mt-4 mb-0', lang('Note'), array(
                    $span(lang('Not sure which one fits?'), 'me-1', lang('Text')),
                    $link(lang('See how I work'), '{{page:process}}', 'link-primary fw-semibold'),
                )),
            )),
        )),

        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('From the reviews'), lang('What users say')),
                $row($testimonial_cards, '4'),
            )),
        )),

        $el('section', 'py-5 border-top', lang('Dev Notes'), array(
            $container(array(
                $section_header(lang('From the desk'), lang('Dev Notes'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('project_band'),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

$service_cards = array();
foreach (array_keys($services) as $key) $service_cards[] = $service_card($key);

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Services'),
    'meta_description' => lang('Native apps for iPhone and Android, prototypes, accounts and sync, store launches and a monthly care plan, in three packages.'),
    'tree'             => $page(array(
        $page_header(lang('Services'), lang('The same care my own apps get, for yours: from a clickable prototype to an app in both stores.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-primary rounded-pill px-4'),
                $link(lang('How I Work'), '{{page:process}}', 'btn btn-outline-secondary rounded-pill px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $section_header(lang('What I do'), lang('From the idea to the stores')),
                $row($service_cards, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Packages'), array(
            $container(array(
                $section_header(lang('Packages'), lang('Three ways to start')),
                $row($package_cards, '4'),
                $el('div', 'alert alert-light border d-flex gap-2 mt-4 mb-0', lang('Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Every project gets a fixed price in writing after the first conversation. The prices above are where most projects start.'), '', lang('Text')),
                )),
            )),
        ), $searchable('packages', lang('price, cost, budget, quote, package'))),
        $el('section', 'py-5', lang('Tools'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Made with'), 'pma-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Native tools, no shortcuts'), 'h3 fw-semibold mb-3'),
                        $para(lang('Each app is written in the language its phone speaks, so it stays fast, works with the newest system features and is easy for another developer to take over.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array($el('div', 'd-flex flex-wrap gap-2', lang('Tools'), $tool_list)), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('tools', lang('Swift, Kotlin, SwiftUI, Jetpack Compose, native'))),
        $shared('project_band'),
    )),
);

// ── How I work ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'process',
    'name'             => lang('how-i-work'),
    'folder'           => 'public',
    'title'            => lang('How I Work'),
    'meta_description' => lang('From the first conversation to the store launch: the four steps of a client project, how long each takes and what you receive.'),
    'tree'             => $page(array(
        $page_header(lang('How I Work'), lang('Four steps, a fixed price and a new beta on your phone every week.')),
        $el('section', 'py-5', lang('Process'), array(
            $container(array(
                $row(array(
                    $col(array($process_list()), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary rounded-4', lang('What You Get'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('What you receive'), 'h5 fw-bold mb-3'),
                                $check_list(array(
                                    lang('A written plan and a fixed price before work begins'),
                                    lang('A short update and a new beta every Friday'),
                                    lang('The source code in your own repository from day one'),
                                    lang('The store accounts in your name, not mine'),
                                    lang('A month of fixes after the launch'),
                                ), 'list-unstyled small mb-4'),
                                $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-primary rounded-pill w-100'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('steps', lang('process, timeline, weeks, beta, launch'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What I Need'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Before we start'), 'pma-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What helps me help you'), 'h3 fw-semibold mb-3'),
                        $para(lang('None of these are needed to send a request; they make the first conversation shorter.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Who the app is for'), lang('The people who will use it and the one thing they should be able to do on the first day.')),
                            $step(2, lang('What exists already'), lang('Designs, a website, an older app or a service the app must talk to.')),
                            $step(3, lang('Your dates'), lang('A launch date, an event or a season the app must be ready for.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('before-we-start', lang('brief, requirements, design, deadline'))),
        $shared('project_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$experience_items = array();
foreach ($experience as $n => $x) {
    $experience_items[] = $el('li', 'd-flex gap-3 ' . ($n === count($experience) - 1 ? 'mb-0' : 'mb-3'), lang('Experience'), array(
        $span($x['years'], 'small text-body-secondary pma-mono text-nowrap pt-1', lang('Years')),
        $el('div', '', lang('Text'), array(
            $heading('h3', $x['title'], 'h6 fw-bold mb-0'),
            $para($x['place'], 'small text-body-secondary mb-0'),
        )),
    ));
}
$skill_badges = array();
foreach ($skills as $s) $skill_badges[] = $span($s, 'badge rounded-pill text-bg-primary-subtle text-primary-emphasis fw-normal fs-6', lang('Skill'));

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('The independent developer behind the apps: the story, the skills, the tools and the years before the studio.'),
    'tree'             => $page(array(
        $page_header(lang('About'), $developer['role'] . ' · ' . $developer['city']),
        $el('section', 'py-5', lang('My Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-1x1 rounded-4 overflow-hidden shadow-sm', lang('Picture Frame'), array(
                            $image($portrait($developer['name'], 800), $developer['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '5'),
                    $col(array(
                        $para(lang('My story'), 'pma-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', sprintf(lang('Hello, I am %s'), $developer['name']), 'h3 fw-semibold mb-3'),
                        $para(lang('I wrote my first iPhone app in 2012 to count the laps of my morning swim. Ten years at an agency and a ride-sharing company taught me how apps are built for millions of people; my own studio lets me build them for one person at a time.'), 'text-body-secondary'),
                        $para(lang('My apps are small on purpose. They ask for no account they do not need, collect no data they do not use, and still work when the signal drops. I build client apps the same way.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Start a Project'), '{{page:hire}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Press Kit'), '{{page:press}}', 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('my-story', lang('story, developer, studio, independent'))),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Skills and Experience'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Skills'), 'h4 fw-bold mb-3'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Skills'), $skill_badges),
                        $heading('h2', lang('Tools'), 'h4 fw-bold mb-3'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Tools'), $tool_list),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Experience'), 'h4 fw-bold mb-3'),
                        $el('ol', 'list-unstyled mb-0', lang('Experience'), $experience_items),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('skills', lang('skills, experience, career, tools'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Principles'), array(
            $container(array(
                $section_header(lang('How I build'), lang('Four promises in every app')),
                $row(array(
                    $col(array($el('div', 'h-100', lang('Privacy'), array(
                        $icon_tile('bi-incognito', 'mb-3'),
                        $heading('h3', lang('Private by default'), 'h6 fw-bold mb-2'),
                        $para(lang('No tracking, no ads, and only the data the app needs to work.'), 'small text-body-secondary mb-0'),
                    ))), '6', '3'),
                    $col(array($el('div', 'h-100', lang('Accessibility'), array(
                        $icon_tile('bi-universal-access', 'mb-3'),
                        $heading('h3', lang('Usable by everyone'), 'h6 fw-bold mb-2'),
                        $para(lang('Large text, VoiceOver and TalkBack tested on every screen.'), 'small text-body-secondary mb-0'),
                    ))), '6', '3'),
                    $col(array($el('div', 'h-100', lang('Offline'), array(
                        $icon_tile('bi-wifi-off', 'mb-3'),
                        $heading('h3', lang('Works offline'), 'h6 fw-bold mb-2'),
                        $para(lang('The app keeps working in a tunnel and catches up when the signal returns.'), 'small text-body-secondary mb-0'),
                    ))), '6', '3'),
                    $col(array($el('div', 'h-100', lang('Care'), array(
                        $icon_tile('bi-arrow-repeat', 'mb-3'),
                        $heading('h3', lang('Kept up to date'), 'h6 fw-bold mb-2'),
                        $para(lang('Ready for each new iOS and Android version on the day it comes out.'), 'small text-body-secondary mb-0'),
                    ))), '6', '3'),
                ), '4'),
            )),
        ), $searchable('principles', lang('privacy, accessibility, offline, updates'))),
        $shared('project_band'),
    )),
);

// ── FAQ ─────────────────────────────────────────────────────────────────
// Two groups of questions: the users of the apps and the clients of the
// studio, each an accordion of its own.

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Questions and Answers'),
    'meta_description' => lang('Answers for app users (help, purchases, data, beta) and for clients (prices, timelines, code ownership, care after the launch).'),
    'tree'             => $page(array(
        $page_header(lang('Questions and Answers'), lang('What users and clients ask most. Anything else? Send a support request and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('For app users'), 'h4 fw-bold mb-3'),
                        $el('div', 'accordion mb-5', lang('Questions'), array(
                            $faq(1, lang('How do I get help with an app?'), lang('Send a support request and choose the app. You can follow it, and answer my questions, from My Requests in your account.'), lang('help, support, bug, problem'), 'ma-faq-users'),
                            $faq(2, lang('How do I restore a purchase?'), lang('Open the app\'s settings and choose Restore Purchases, signed in with the same Apple or Google account you bought with.'), lang('purchase, restore, subscription, refund'), 'ma-faq-users'),
                            $faq(3, lang('How do I cancel a subscription?'), lang('Subscriptions are managed by the store: in your Apple ID settings on an iPhone, or under Payments and subscriptions in Google Play.'), lang('cancel, subscription, billing'), 'ma-faq-users'),
                            $faq(4, lang('What happens to my data?'), lang('Each app\'s page has a privacy summary, and the privacy policy says it in full. No app shows ads or sells data.'), lang('privacy, data, delete, export'), 'ma-faq-users'),
                            $faq(5, lang('How can I try new versions early?'), lang('Join the beta with your store e-mail; the invitation to TestFlight or Google Play testing comes by e-mail.'), lang('beta, testflight, early access'), 'ma-faq-users'),
                        ), array('id' => 'ma-faq-users')),
                        $heading('h2', lang('For clients'), 'h4 fw-bold mb-3'),
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('How much does an app cost?'), lang('Most first versions start around the First Version package. After a short call you get a fixed price in writing.'), lang('price, cost, budget, quote'), 'ma-faq-clients'),
                            $faq(2, lang('How long does it take?'), lang('A prototype takes two to three weeks, a first version ten to fourteen. You get a new beta every week along the way.'), lang('time, timeline, weeks, deadline'), 'ma-faq-clients'),
                            $faq(3, lang('Who owns the code?'), lang('You do. The code lives in your repository and the store accounts are in your name from the first day.'), lang('code, ownership, repository, rights'), 'ma-faq-clients'),
                            $faq(4, lang('Do you build for both iPhone and Android?'), lang('Yes, natively for each, from one plan and one design, so both apps feel at home on their phones.'), lang('iOS, Android, native, cross-platform'), 'ma-faq-clients'),
                            $faq(5, lang('What happens after the launch?'), lang('The first month of fixes is included. After that the care plan keeps the app ready for new system versions.'), lang('maintenance, support, care, updates'), 'ma-faq-clients'),
                        ), array('id' => 'ma-faq-clients')),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('project_band'),
    )),
);

// ── Press kit ───────────────────────────────────────────────────────────
// What a journalist needs on one page: the facts, the apps with their icons,
// screenshots, the developer's bio and the press contact. Each part is a
// search section, so a search for "logo" or "screenshots" opens it.

$press_apps = array();
foreach ($apps as $key => $a) {
    $press_apps[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', $a['name'], array(
            $el('div', 'card-body d-flex gap-3 p-4', lang('Card Body'), array(
                $el('span', 'd-block flex-shrink-0 rounded-4 overflow-hidden pma-icon', lang('Icon Frame'), array(
                    $image($portrait($a['name'], 160), $a['name'], 'w-100 h-100 object-fit-cover',
                        array('_attrs' => array($attr('width', '64'), $attr('height', '64'), $attr('loading', 'lazy')))),
                )),
                $el('div', '', lang('Text'), array(
                    $heading('h3', $a['name'], 'h6 fw-bold mb-1'),
                    $para($a['tagline'], 'small text-body-secondary mb-2'),
                    $para($categories[$a['category']]['title'] . ' · ' . $platform_title($a['platforms']) . ' · ' . $a['version'], 'small mb-2'),
                    $link(lang('Download the icon'), $portrait($a['name'], 1024), 'small link-primary', array($attr('download', '')), array('target' => '_blank')),
                )),
            )),
        )),
    ), '6', '4');
}
$press_shots = array();
foreach (array('tally', 'stride', 'wayfarer', 'lingo') as $n => $key) {
    $press_shots[] = $col(array($phone_shot('press-' . ($n + 1), $apps[$key]['name'])), '3', '', '', '6');
}
$fact = function ($label, $value) use ($el, $span) {
    return $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom small', $label, array(
        $span($label, 'text-body-secondary', lang('Label')),
        $span($value, 'fw-semibold text-end', lang('Value')),
    ));
};

$public_pages[] = array(
    'key'              => 'press',
    'name'             => lang('press'),
    'folder'           => 'public',
    'title'            => lang('Press Kit'),
    'meta_description' => lang('Facts, app icons, screenshots and the developer\'s bio for writers and reviewers, with the press contact.'),
    'tree'             => $page(array(
        $page_header(lang('Press Kit'), lang('Facts, icons and screenshots, free to use when you write about the apps.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Write to the press contact'), 'mailto:' . $developer['email'] . '?subject=' . rawurlencode(lang('Press request')), 'btn btn-primary rounded-pill px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Facts'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Facts'), 'h4 fw-bold mb-3'),
                        $el('div', '', lang('Facts'), array(
                            $fact(lang('Developer'), $developer['name']),
                            $fact(lang('Based in'), $developer['city']),
                            $fact(lang('Studio founded'), '2020'),
                            $fact(lang('Apps'), (string)count($apps)),
                            $fact(lang('Downloads'), $figures[1]['value']),
                            $fact(lang('Press contact'), $developer['email']),
                        )),
                    ), '', '5'),
                    $col(array(
                        $heading('h2', lang('About the developer'), 'h4 fw-bold mb-3'),
                        $para(sprintf(lang('%1$s is an independent iOS and Android developer. %2$s'), $developer['name'], $developer['bio']), 'text-body-secondary'),
                        $para(lang('Before starting the studio in 2020, they built apps at a ride-sharing company in Istanbul and a digital agency in Izmir. Their own apps have been downloaded more than a million times.'), 'text-body-secondary mb-0'),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('facts', lang('facts, bio, developer, founded'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Apps'), array(
            $container(array(
                $section_header(lang('The apps'), lang('Names, lines and icons'), lang('All apps'), '{{page:apps}}'),
                $row($press_apps, '4'),
            )),
        ), $searchable('press-apps', lang('icon, logo, app names'))),
        $el('section', 'py-5', lang('Screenshots'), array(
            $container(array(
                $section_header(lang('Screenshots'), lang('Screens from the apps')),
                $row($press_shots, '4', 'center'),
            )),
        ), $searchable('screenshots', lang('screenshots, images, pictures'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Guidelines'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Using the material'), 'h4 fw-bold mb-3'),
                        $check_list(array(
                            lang('Write the app names as they are written here'),
                            lang('Use the icons and screenshots as they are, without changing their colours'),
                            lang('Ask for a promo code to review a paid app'),
                            lang('Use the official App Store and Google Play badges'),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        ), $searchable('press-guidelines', lang('promo code, review, brand, guidelines'))),
        $shared('project_band'),
    )),
);

return $public_pages;
