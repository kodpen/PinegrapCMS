<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Film Studio", its public pages: home with the
 * showreel, the services and one page per service, how a production runs,
 * the studio and its spaces, the crew, about and the questions page.
 * Included after parts.php in the same scope (its builders and the
 * studio's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * The home and the services page place the palette's Film Studio blocks
 * (Showreel Hero, Title Card and Credits, Cuts for Every Screen) and the
 * general Timeline: Horizontal, drawn with the studio's own data; their
 * rules ship in the template's User Styles. Motion is node interactions
 * (props._fx) only.
 *
 * Every figure comes from parts.php ($studio, $services, $industries,
 * $process, $spaces, $crew, $why_us, $figures, $testimonials), so a change
 * made there reaches every page here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// A still in a framed ratio, loaded lazily unless it is the first thing a
// page shows. Films are wide, so the frame is 16:9 unless asked otherwise.
$framed = function ($src, $alt, $ratio = '16x9', $lazy = true) use ($el, $image, $attr) {
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
        $para($eyebrow, 'pfm-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pfm-display h2 mb-3'),
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

// A tile with an icon, a title and a line: why clients choose the studio
// and the industries it films for.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pfm-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pfm-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
            )),
        )),
    ));
};

// The band of figures under a page's opening; the figures rise in one
// after another.
$figures_band = function () use ($el, $container, $figure_row, $rise) {
    return $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
        $container(array($rise($figure_row(), 100))),
    ));
};

// The cards and tiles of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$service_cols = array();
foreach (array_keys($services) as $key) $service_cols[] = $service_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$crew_cols = array();
foreach (array_keys($crew) as $n) $crew_cols[] = $crew_card($n);
$space_cols = array();
foreach (array_keys($spaces) as $key) $space_cols[] = $space_card($key);
$process_items = array();
foreach ($process as $n => $s) $process_items[] = $step($n + 1, $s['title'], $s['text']);
// An industry's tile links the work list filtered on it, in the words the
// Industry list uses.
$industry_tiles = array();
foreach ($industries as $s) {
    $industry_tiles[] = $feature_tile($s['icon'], $s['title'], $s['text'], '4', array(
        $link(lang('See the work'), '{{page:projects}}?' . $query(array('industry' => $s['title'])), 'd-inline-block small fw-semibold link-primary mt-3'),
    ));
}

// ── Home ────────────────────────────────────────────────────────────────

// The showreel hero (Film Studio: Showreel Hero): the studio in a line over
// a cinema screen that plays the reel, the slate with its running time and
// format, and the films the reel is cut from, each at its timecode (a
// duration from the reel's first frame).
$reel_chapters = array();
foreach (array('night_drive' => 0, 'thread_and_needle' => 24, 'slow_bloom' => 51, 'harvest_table' => 78, 'build_day' => 115) as $key => $at) {
    $p = $projects[$key];
    $reel_chapters[] = $el('li', 'col', $p['name'], array(
        $el('time', 'd-block small text-primary mb-1', lang('Time'), array(),
            array('text' => sprintf('%02d:%02d', intdiv($at, 60), $at % 60), '_attrs' => array($attr('datetime', 'PT' . $at . 'S')))),
        $heading('h3', $p['name'], 'h6 fw-bold mb-1'),
        $para($services[$p['service']]['title'], 'small text-body-secondary mb-0'),
    ));
}
$home_reel = $el('section', 'pfm-hero py-5 bg-body text-body overflow-hidden', lang('Hero'), array(
    $container(array(
        $row(array(
            $col(array(
                $fx($el('div', 'text-center mb-5', lang('Hero Text'), array(
                    $para(lang('Film and video production'), 'pfm-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                    $heading('h1', lang('Stories worth watching'), 'pfm-display display-2 mb-3'),
                    $para($studio['tagline'] . '.', 'lead text-body-secondary mb-4'),
                    // On the dark band an outline button is too faint
                    // under some palettes, so the second way on is solid.
                    $el('div', 'd-flex flex-wrap justify-content-center gap-2', lang('Buttons'), array(
                        $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-primary btn-lg px-4'),
                        $link(lang('See Our Work'), '{{page:projects}}', 'btn btn-secondary btn-lg px-4'),
                    )),
                )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800)),
                // The player never starts by itself: the visitor starts it.
                $fx($el('div', 'pg-filmstudio-screen', lang('Screen'), array(
                    $el('div', 'd-flex flex-wrap align-items-center gap-2 gap-md-4 px-1 pb-2 small text-body-secondary', lang('Facts'), array(
                        $el('div', 'd-inline-flex align-items-center gap-2 fw-semibold text-body', lang('Title'), array(
                            $icon('bi-record-circle', 'text-primary'),
                            $span(lang('Showreel'), '', lang('Text')),
                        )),
                        $el('div', 'd-inline-flex align-items-center gap-1', lang('Running Time'), array(
                            $icon('bi-clock'),
                            $el('time', '', lang('Running Time'), array(), array('text' => '02:30', '_attrs' => array($attr('datetime', 'PT2M30S')))),
                        )),
                        $span('4K · 16:9', 'ms-md-auto', lang('Format')),
                    )),
                    $showreel('showreel', lang('Our showreel'), 'ratio ratio-16x9'),
                )), array('effect' => 'scale', 'trigger' => 'load', 'duration' => 1000, 'delay' => 250)),
                $el('div', 'mt-5', lang('Chapters'), array(
                    $heading('h2', lang('In this reel'), 'h6 text-uppercase fw-semibold text-body-secondary mb-4'),
                    $fx($el('ol', 'pg-filmstudio-chapters list-unstyled row row-cols-2 row-cols-md-3 row-cols-lg-5 g-4 mb-0', lang('Films'), $reel_chapters),
                        array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700, 'delay' => 700, 'stagger' => 100)),
                )),
            ), '', '10'),
        ), '', 'center'),
    ), 'py-lg-5'),
), array_merge($searchable('fm-showreel', lang('showreel, reel, video production, film company')),
    array('_attrs' => array($attr('data-bs-theme', 'dark')))));

// One film in depth (Film Studio: Title Card and Credits): the sample
// Night Drive as a title card, its still at the cinema's 2.39:1 with a play
// mark, the crew who made it rolling up as end credits and the client's
// words. Page content, not a record: the studio puts its own film here in
// the editor. Until then the play mark opens the work of the film's kind.
$home_film = $projects['night_drive'];
$home_film_fact = function ($icon_name, $text) use ($el, $icon, $span) {
    return $el('li', 'd-inline-flex align-items-center gap-1', $text, array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Text')),
    ));
};
$home_film_credits = array();
foreach (array(array(lang('Directed by'), 0), array(lang('Director of Photography'), 1), array(lang('Producer'), 2),
    array(lang('Editor'), 3), array(lang('Colourist'), 4)) as $c) {
    $home_film_credits[] = $el('div', '', $c[0], array(
        $el('dt', '', lang('Role'), array(), array('text' => $c[0])),
        $el('dd', '', lang('Name'), array(), array('text' => $crew[$c[1]]['name'])),
    ));
}
// The film's name keeps its own case, as every name does: capitals under a
// Turkish page would print a dotted capital I in an English title.
$home_featured = $el('section', 'py-5 overflow-hidden', lang('Featured film'), array(
    $container(array(
        $row(array(
            $col(array(
                $fade_in($el('div', 'text-center mb-4', lang('Title Card'), array(
                    $para(lang('Featured film'), 'pfm-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', $home_film['name'], 'display-4 fw-bold mb-3'),
                    $para($home_film['summary'], 'lead text-body-secondary mb-3'),
                    $el('ul', 'list-unstyled d-flex flex-wrap justify-content-center gap-3 small text-body-secondary mb-0', lang('Facts'), array(
                        $home_film_fact($services[$home_film['service']]['icon'], $services[$home_film['service']]['title']),
                        $home_film_fact($industries[$home_film['industry']]['icon'], $industries[$home_film['industry']]['title']),
                        $el('li', 'd-inline-flex align-items-center gap-1', lang('Running Time'), array(
                            $icon('bi-clock', 'text-primary'),
                            $el('time', '', lang('Running Time'), array(), array('text' => $home_film['runtime'], '_attrs' => array($attr('datetime', 'PT60S')))),
                        )),
                    )),
                ))),
            ), '', '8'),
        ), '', 'center'),
        $fx($el('div', 'pg-filmstudio-scope mb-5', lang('Still'), array(
            $el('div', 'ratio rounded-4 overflow-hidden shadow-lg', lang('Picture Frame'), array(
                $image($photo('project-night_drive', 1600, 670), lang('A car\'s headlights on an empty coast road at night'), 'object-fit-cover',
                    array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('a', 'pg-filmstudio-play btn btn-primary rounded-circle', lang('Link'), array(
                $icon('bi-play-fill'),
                $span(lang('See the work'), 'visually-hidden', lang('Link Text')),
            ), array('href' => '{{page:projects}}?' . $query(array('service' => $services[$home_film['service']]['title'])))),
        )), array('effect' => 'scale', 'trigger' => 'view', 'duration' => 900)),
        $row(array(
            $col(array(
                $heading('h3', lang('Credits'), 'h6 text-center text-uppercase fw-semibold text-body-secondary mb-4'),
                $rise($el('dl', 'pg-filmstudio-roll mb-5', lang('Credits'), $home_film_credits), 110),
                $fade_in($el('figure', 'text-center mb-0', lang('Quote'), array(
                    $el('blockquote', 'blockquote mb-2', lang('Text'), array($para($home_film['quote'], 'pfm-quote fst-italic mb-0'))),
                    $el('figcaption', 'small text-body-secondary', lang('Client'), array(), array('text' => $home_film['client'])),
                )), 300),
            ), '', '8'),
        ), '', 'center'),
    )),
), $searchable('fm-featured-film', lang('featured film, credits, director, car commercial, launch film')));

// How a production runs (Timeline: Horizontal): the five stages side by
// side, each under its number in a circle.
$home_timeline = array();
foreach ($process as $n => $s) {
    $home_timeline[] = $el('li', 'col text-center', $s['title'], array(
        $el('div', 'd-flex align-items-center justify-content-center rounded-circle text-bg-primary mx-auto mb-3 pg-circle-60', lang('Step Number'), array(
            $span(sprintf('%02d', $n + 1), 'h4 fw-bold mb-0', lang('Text')),
        )),
        $heading('h3', $s['title'], 'h6 fw-semibold mb-2'),
        $para($s['text'], 'small text-body-secondary mb-0'),
    ));
}

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A film and video production studio: commercials, brand films, music videos and documentaries, shot and finished in house.'),
    'tree'             => $page(array(
        $home_reel,
        $figures_band(),
        $home_featured,

        $el('section', 'py-5 bg-body-tertiary', lang('Our Services'), array(
            $container(array(
                $fade_in($section_header(lang('What we make'), lang('From the first idea to the final cut'), lang('All Services'), '{{page:services}}')),
                $rise($row($service_cols, '4')),
            )),
        ), $searchable('fm-home-services', lang('commercial, advert, brand film, music video, documentary, corporate video, editing'))),

        // The featured projects, read from the Projects collection.
        $el('section', 'py-5', lang('Featured Work'), array(
            $container(array(
                $fade_in($section_header(lang('Our work'), lang('Films we are proud of'), lang('All Work'), '{{page:projects}}')),
                $widget('home_projects'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('How We Work'), array(
            $container(array(
                $fade_in($section_header(lang('Step by step'), lang('From the brief to the master'), lang('How We Work'), '{{page:process}}')),
                $rise($el('ol', 'list-unstyled row row-cols-1 row-cols-sm-2 row-cols-lg-5 g-4 justify-content-center mb-0', lang('Steps'), $home_timeline), 120),
            )),
        ), $searchable('fm-home-process', lang('how it works, treatment, pre-production, shoot, edit, delivery'))),

        // What clients say: sample words for the studio to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $fade_in($section_header(lang('Our clients'), lang('What clients say'))),
                $rise($row($testimonial_cols, '4')),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Journal'), array(
            $container(array(
                $fade_in($section_header(lang('Journal'), lang('Stories from the set'), lang('All Stories'), '{{page:blog}}')),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

// The cuts a film is delivered in (Film Studio: Cuts for Every Screen):
// each frame drawn at its aspect ratio (hidden from a screen reader, which
// reads the ratio in words), where it is shown and the lengths it is cut to.
$format_card = function ($shape, $ratio, $use, $lengths) use ($el, $heading, $para, $attr) {
    $badges = array();
    foreach ($lengths as $text) $badges[] = $el('li', 'badge rounded-pill text-bg-secondary fw-normal', $text, array(), array('text' => $text));
    return $el('li', 'col', $ratio, array(
        $el('div', 'h-100 p-3 rounded-4 border bg-body', lang('Card'), array(
            $el('div', 'pg-filmstudio-frame mb-3', lang('Drawing'), array(
                $el('div', $shape, lang('Frame')),
            ), array('_attrs' => array($attr('aria-hidden', 'true')))),
            $heading('h3', $ratio, 'h4 fw-bold mb-1'),
            $para($use, 'small text-body-secondary mb-3'),
            $el('ul', 'list-unstyled d-flex flex-wrap gap-1 mb-0', lang('Lengths'), $badges),
        )),
    ));
};
$services_formats = $el('section', 'py-5 bg-body-tertiary', lang('Formats'), array(
    $container(array(
        $fade_in($el('div', 'row g-4 align-items-end mb-5', lang('Section Header'), array(
            $el('div', 'col-lg-7', lang('Section Title'), array(
                $para(lang('Formats'), 'pfm-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                $heading('h2', lang('A cut for every screen and length'), 'pfm-display display-5 mb-0'),
            )),
            $el('div', 'col-lg-5', lang('Lead'), array(
                $para(lang('One shoot, delivered in every shape and length the film will be shown in. The frames are drawn to scale.'), 'lead text-body-secondary mb-0'),
            )),
        ))),
        $fx($el('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3 g-lg-4 mb-4', lang('Formats'), array(
            $format_card('pg-filmstudio-r239', '2.39:1', lang('Cinema, before the feature'), array(lang('60 s'), lang('30 s'))),
            $format_card('pg-filmstudio-r169', '16:9', lang('Television and the web'), array(lang('60 s'), lang('30 s'), lang('15 s'))),
            $format_card('pg-filmstudio-r45', '4:5', lang('Social media feeds'), array(lang('30 s'), lang('15 s'), lang('6 s'))),
            $format_card('pg-filmstudio-r916', '9:16', lang('Vertical video on phones'), array(lang('15 s'), lang('6 s'))),
        )), array('effect' => 'scale', 'trigger' => 'view', 'duration' => 700, 'stagger' => 120)),
        $fade_in($el('div', 'd-flex flex-column flex-md-row align-items-md-center gap-3', lang('Note'), array(
            $para(lang('Subtitles, captions and versions in other languages are cut from the same master.'), 'flex-grow-1 text-body-secondary mb-0'),
            $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-primary px-4 flex-shrink-0'),
        )), 200),
    )),
), $searchable('fm-formats', lang('aspect ratio, vertical video, cut-down, versions, subtitles, cinema, social media')));

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Our Services'),
    'meta_description' => lang('Commercials, brand films, music videos, documentaries, corporate and event films, and post-production.'),
    'tree'             => $page(array(
        $page_header(lang('Our Services'), lang('Six kinds of film we make, from a six-second spot to a feature documentary.')),
        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $rise($row($service_cols, '4')),
            )),
        ), $searchable('fm-services', lang('video production, filming, advert, brand film, music video, documentary, editing, grading'))),
        $services_formats,
        $el('section', 'py-5', lang('Industries'), array(
            $container(array(
                $fade_in($section_header(lang('Who we film for'), lang('Industries we know'), lang('All Work'), '{{page:projects}}')),
                $rise($row($industry_tiles, '4')),
            )),
        ), $searchable('fm-industries', lang('cars, fashion, food, music, technology, charity'))),
        $ask_band(lang('Not sure what kind of film you need?'), lang('Tell us what the film must do and a producer calls you back within a working day.')),
    )),
);

// One page per service: what it is, what it covers, the work made with it
// and the enquiry with the service chosen. Its address is a translated
// slug, so a Turkish site reads Turkish addresses.
$service_slugs = array(
    'commercials'  => lang('commercials'),
    'brand_films'  => lang('brand-films'),
    'music_videos' => lang('music-videos'),
    'documentary'  => lang('documentaries'),
    'corporate'    => lang('corporate-and-event-films'),
    'post'         => lang('post-production'),
);
foreach ($services as $key => $s) {
    $public_pages[] = array(
        'key'              => 'service_' . $key,
        'name'             => $service_slugs[$key],
        'folder'           => 'public',
        'title'            => $s['title'],
        'meta_description' => $s['summary'],
        'tree'             => $page(array(
            $page_header($s['title'], $s['summary']),
            $el('section', 'py-5', $s['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($s['icon'], 'mb-3'),
                            $para($s['text'], 'lead mb-4'),
                            $heading('h2', lang('What it covers'), 'pfm-display h4 mb-3'),
                            $check_list($s['points'], 'list-unstyled mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Start a Project'), '{{page:enquiry}}?' . $query(array('service' => $s['title'])), 'btn btn-primary px-4'),
                                $link(lang('See the work'), '{{page:projects}}?' . $query(array('service' => $s['title'])), 'btn btn-outline-secondary px-4'),
                            )),
                        ), '', '6'),
                        $col(array($framed($photo('service-' . $key, 1280, 720), $s['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('fm-service-' . str_replace('_', '-', $key))),
            $el('section', 'py-5 bg-body-tertiary', lang('How We Work'), array(
                $container(array(
                    $section_header(lang('Step by step'), lang('How we make your film'), lang('How We Work'), '{{page:process}}'),
                    $rise($el('ol', 'list-unstyled mb-0', lang('Steps'), $process_items), 120),
                )),
            )),
        )),
    );
}

// ── How we work ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'process',
    'name'             => lang('how-we-work'),
    'folder'           => 'public',
    'title'            => lang('How We Work'),
    'meta_description' => lang('How a production runs with us: brief and treatment, pre-production, the shoot, the edit and delivery.'),
    'tree'             => $page(array(
        $page_header(lang('How We Work'), lang('Five stages from the first call to the master, with one producer all the way.')),
        $el('section', 'py-5', lang('Steps'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $rise($el('ol', 'list-unstyled mb-4', lang('Steps'), $process_items), 120),
                        $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('process', 1280, 720), lang('A storyboard pinned on the wall of the production office'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('fm-process', lang('how it works, treatment, storyboard, casting, shoot, edit, grade, delivery'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What You Can Count On'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Your budget'), 'pfm-display h3 mb-3'),
                        $check_list(array(
                            lang('A budget for the film we agree, in writing before the shoot'),
                            lang('Extra shoot days or versions are priced before we make them'),
                            lang('Payments in stages, as the film takes shape'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Your film'), 'pfm-display h3 mb-3'),
                        $check_list(array(
                            lang('Two rounds of changes on every cut'),
                            lang('Music and footage licensed for where the film will be shown'),
                            lang('The master and the project files kept safe after delivery'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('fm-promises', lang('budget, payments, changes, revisions, music licence, archive'))),
        $ask_band(lang('A question about how we work?'), lang('Write to us and a producer answers, usually the same day.')),
    )),
);

// ── The studio ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'studio',
    'name'             => lang('the-studio'),
    'folder'           => 'public',
    'title'            => lang('The Studio'),
    'meta_description' => lang('Our sound stage, cyclorama, edit suites and grading and sound suite, for our own films and for hire.'),
    'tree'             => $page(array(
        $page_header(lang('The Studio'), lang('Stages, edit rooms and a grading theatre under one roof, for our own films and for yours.')),
        $el('section', 'py-5', lang('Our Spaces'), array(
            $container(array(
                $section_header(lang('Under one roof'), lang('Our spaces'), lang('Hire the Studio'), '{{page:studio_hire}}'),
                $rise($row($space_cols, '4')),
            )),
        ), $searchable('fm-spaces', lang('sound stage, green screen, infinity cove, edit suite, colour grading, sound mix'))),
        $story_band('fm-hire', lang('For hire'), lang('Shoot in our studio'),
            array(lang('Our stages and suites are free for hire on the days our own crew does not need them. Ask for a space and a date, and we confirm within a working day.'),
                lang('Every hire includes a studio manager on the day; lights, grip and crew can be added.')),
            $photo('studio-hire', 1280, 720), lang('A lit set on the sound stage between takes'), true,
            array($link(lang('Hire the Studio'), '{{page:studio_hire}}', 'btn btn-primary px-4')),
            lang('studio hire, rent, stage rental, price, book'), 'py-5 bg-body-tertiary'),
        $figures_band(),
    )),
);

// ── The crew ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'crew',
    'name'             => lang('crew'),
    'folder'           => 'public',
    'title'            => lang('The Crew'),
    'meta_description' => lang('The directors, producers, editors and sound designers who make our films.'),
    'tree'             => $page(array(
        $page_header(lang('The Crew'), lang('The people behind the camera, in the edit and at the mixing desk.')),
        $el('section', 'py-5', lang('The Crew'), array(
            $container(array(
                $rise($row($crew_cols, '4')),
            )),
        ), $searchable('fm-crew', lang('people, director, producer, editor, colourist, sound, team'))),
        $story_band('fm-join-us', lang('Crew calls'), lang('Make films with us'),
            array(lang('We hire for each production and keep a core crew all year. Runners learn every part of a shoot, and assistants grow into their own roles.')),
            $photo('crew-join', 1280, 720), lang('A camera assistant pulling focus on set'), true,
            array($link(lang('See the crew calls'), '{{page:careers}}', 'btn btn-primary px-4')),
            lang('jobs, freelance, internship, work for us, crew'), 'py-5 bg-body-tertiary'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('An independent production company started by a director and a cinematographer: one producer and everything in house.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $studio['tagline'] . '.'),
        $story_band('fm-our-story', lang('Our story'), lang('One camera and a borrowed light'),
            array($studio['story'], lang('Our own crew writes, shoots and finishes most of our films, so we answer for every frame.')),
            $photo('about-studio', 1280, 720), lang('The crew on set in the studio'), false, array(),
            lang('history, founders, company, who we are')),
        $figures_band(),
        $el('section', 'py-5', lang('Why Work with Us'), array(
            $container(array(
                $section_header(lang('The way we work'), lang('Why film with us')),
                $rise($row($why_tiles, '4')),
            )),
        ), $searchable('fm-about-why', lang('treatment, producer, in house, fixed budget, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('The Crew'), array(
            $container(array(
                $section_header(lang('Our people'), lang('The people who make your film'), lang('The Crew'), '{{page:crew}}'),
                $rise($row(array_slice($crew_cols, 0, 3), '4')),
            )),
        )),
        $ask_band(lang('Want to make a film with us?'), lang('Write to us about your idea, or see the crew calls open now.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: before the shoot and during the production. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pfm-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about treatments, budgets, timings, changes, music rights and hiring the studio.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Budgets, timings and life on set. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('fm-faq-before', lang('Before the shoot'), array(
                            array(lang('What does a film cost?'), lang('It depends on the days of shooting, the crew, the cast and the places. After the first call we send a treatment with a first budget, so you know before you commit.'), lang('price, cost, budget, quote, how much')),
                            array(lang('How long does a film take?'), lang('A short online film can be ready in two or three weeks; a commercial with casting and locations usually takes six to eight. We agree the dates in the treatment.'), lang('how long, timing, deadline, schedule')),
                            array(lang('Do you write the idea, or do we?'), lang('Either. We can write the idea and the script from your brief, or shoot a script your agency has written.'), lang('idea, script, agency, creative')),
                            array(lang('How do I follow my enquiry?'), lang('Sending the enquiry opens an account for you. Under My Account you see the state of each enquiry and can write to us there.'), lang('my account, status, follow, message')),
                        )),
                        $faq_group('fm-faq-during', lang('During the production'), array(
                            array(lang('Can we come to the shoot?'), lang('Yes, and we recommend it: the producer keeps a place for you by the monitor and checks each scene with you before we move on.'), lang('set visit, attend, shoot day, client')),
                            array(lang('How many changes can we ask for?'), lang('Two rounds of changes on every cut are in the budget. More rounds are priced before we make them.'), lang('changes, revisions, feedback, amendments')),
                            array(lang('Who owns the film?'), lang('You do, for the uses agreed in the budget. Music, archive footage and actors are licensed for those uses; wider use is agreed and priced first.'), lang('rights, copyright, licence, usage, music')),
                            array(lang('Can we hire the studio without your crew?'), lang('Yes. Ask for a space and a date on the studio hire page; a studio manager is with you on the day.'), lang('studio hire, rent, stage, dry hire')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured projects (home_projects) come with
// the projects, its Journal stories (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
