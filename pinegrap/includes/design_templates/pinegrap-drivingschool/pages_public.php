<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Driving School", its public pages: home, the licence
 * classes and one page per class, the theory test, the practical test, the
 * instructors, about and the questions page. Included after parts.php in
 * the same scope (its builders and the school's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($school, $licences, $instructors,
 * $theory_topics, $test_day, $branches, $why_us, $figures), so a change
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

$public_pages = array();

// A photo in a framed ratio, loaded lazily unless it is the first thing a
// page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
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
        $para($eyebrow, 'pdr-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pdr-display h2 mb-3'),
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

// A tile with an icon, a title and a line: why learners choose the school,
// the theory topics and the branches.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pdr-display h5 mb-2'),
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
                    $heading('h2', $title, 'pdr-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
            )),
        )),
    ));
};

// The band of figures under a page's opening.
$figures_band = function () use ($el, $container, $figure_row) {
    return $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
        $container(array($figure_row())),
    ));
};

// The cards and tiles of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$instructor_cols = array();
foreach (array_keys($instructors) as $n) $instructor_cols[] = $instructor_card($n);
$test_day_items = array();
foreach ($test_day as $n => $s) $test_day_items[] = $step($n + 1, $s['title'], $s['text']);
$branch_tiles = array();
foreach ($branches as $b) {
    $branch_tiles[] = $feature_tile('bi-geo-alt', $b['title'], $b['text'], '6', array(
        $para($b['address'], 'small fw-semibold mt-3 mb-0'),
    ));
}

// ── The palette blocks, with the school's data ──────────────────────────

// An entrance the operator can change in the Interactions section: the
// children of the node rise one after another, as it scrolls into view or
// as the page opens. pg_fx keeps them still under reduced motion.
$rise = function ($node, $trigger = 'view', $stagger = 90) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => $trigger, 'stagger' => $stagger);
    return $node;
};

// A picture with its corners rounded, loaded lazily unless it is the first
// thing a page shows.
$picture = function ($src, $alt, $lazy = true) use ($image, $attr) {
    return $image($src, $alt, 'img-fluid w-100 rounded-4 shadow-sm', $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array());
};

// The licence classes (drivingschool-licences): a card per class, the
// letter as a number plate, what it covers, its facts as a description
// list, the class's page and its courses (?licence=).
$ds_gearbox = array(
    'car'         => lang('Manual or automatic'),
    'automatic'   => lang('Automatic'),
    'motorcycle'  => lang('Manual'),
    'light_truck' => lang('Manual'),
    'trailer'     => lang('Manual or automatic'),
);
$licence_board = function ($id, $title, $lead, $class = 'py-5 bg-body-tertiary')
    use ($el, $heading, $para, $span, $icon, $link, $container, $row, $col, $searchable, $licences, $query, $ds_gearbox, $rise) {
    $fact = function ($label, $value) use ($el, $span) {
        return $el('div', 'd-flex justify-content-between gap-3 py-2 border-top', $label, array(
            $el('dt', 'fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
            $el('dd', 'fw-semibold text-end mb-0', lang('Value'), array(), array('text' => $value)),
        ));
    };
    $cols = array();
    foreach ($licences as $key => $l) {
        $cols[] = $col(array(
            $el('article', 'card h-100 border-0 shadow-sm pdr-lift', lang('Licence Card'), array(
                $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                    $el('div', 'd-flex align-items-center justify-content-between gap-3 mb-3', lang('Card Head'), array(
                        $span($l['code'], 'pg-drv-plate pdr-display fs-4', lang('Licence Class')),
                        $icon($l['icon'], 'fs-2 text-primary'),
                    )),
                    $heading('h3', $l['title'], 'pdr-display h5 mb-2'),
                    $para($l['summary'], 'small text-body-secondary mb-3'),
                    $el('dl', 'small mb-4', lang('Licence Facts'), array(
                        $fact(lang('Minimum age'), $l['age']),
                        $fact(lang('Lessons'), $l['lessons']),
                        $fact(lang('Gearbox'), $ds_gearbox[$key]),
                    )),
                    $el('div', 'd-flex flex-wrap align-items-center gap-3 mt-auto', lang('Buttons'), array(
                        $link(lang('See the class'), '{{page:licence_' . $key . '}}', 'btn btn-outline-primary'),
                        $link(lang('See the courses'), '{{page:courses}}?' . $query(array('licence' => $l['title'])), 'link-primary fw-semibold small'),
                    )),
                )),
            )),
        ), '6', '4');
    }
    return $el('section', $class, lang('Licence Classes'), array(
        $container(array(
            $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                $el('div', '', lang('Section Title'), array(
                    $heading('h2', $title, 'pdr-display h2 mb-1'),
                    $para($lead, 'text-body-secondary mb-0'),
                )),
                $link(lang('Book a Lesson'), '{{page:lesson_request}}', 'btn btn-primary'),
            )),
            $rise($row($cols, '4')),
            $para(lang('Driving only automatics? Class B can be learned and tested in an automatic car.'), 'small text-body-secondary text-center mt-4 mb-0'),
        )),
    ), $searchable($id, lang('car licence, automatic, motorcycle, truck, trailer, licence class, minimum age')));
};

// The road to the licence (the palette's steps-numbered): four numbered
// cards from the assessment drive to the test.
$road_steps = array(
    array('icon' => 'bi-person-check', 'title' => lang('An assessment drive'), 'text' => lang('Your instructor sees how you drive today and writes your lesson plan.')),
    array('icon' => 'bi-journal-check', 'title' => lang('Theory classes and test'), 'text' => lang('Evening classes and the practice app until the questions feel easy.')),
    array('icon' => 'bi-car-front-fill', 'title' => lang('Lessons with one instructor'), 'text' => lang('Town, country and motorway, in a dual-control car, with the same face every time.')),
    array('icon' => 'bi-clipboard-check', 'title' => lang('Mock test, then the real one'), 'text' => lang('A mock test timed like the real one, then the test in the car you learned in.')),
);
$road = function ($id, $title, $lead, $class = 'py-5') use ($el, $heading, $para, $span, $icon, $container, $row, $col, $searchable, $road_steps, $rise) {
    $cols = array();
    foreach ($road_steps as $n => $s) {
        $cols[] = $col(array(
            $el('div', 'h-100 p-4 rounded-4 border bg-body', lang('Step Card'), array(
                $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Step Head'), array(
                    $span((string)($n + 1), 'badge rounded-pill text-bg-primary fs-6 px-3 py-2', lang('Step Number')),
                    $icon($s['icon'], 'fs-4 text-primary'),
                )),
                $heading('h3', $s['title'], 'h6 fw-bold mb-2'),
                $para($s['text'], 'small text-body-secondary mb-0'),
            )),
        ), '6', '3');
    }
    return $el('section', $class, lang('Steps (Numbered)'), array(
        $container(array(
            $el('div', 'text-center mb-5', lang('Section Header'), array(
                $heading('h2', $title, 'pdr-display h2 mb-2'),
                $para($lead, 'lead text-body-secondary mb-0'),
            )),
            $rise($row($cols, '4')),
        )),
    ), $searchable($id, lang('how to get a driving licence, first lesson, assessment, steps')));
};

// The theory topics as road signs (drivingschool-signs): the practice app
// beside six topic cards, each led by a sign drawn in CSS. The sign is
// decoration; the topic is named in its heading.
$theory_signs = function ($id, $title, $lead, $class = 'py-5')
    use ($el, $heading, $para, $span, $icon, $link, $attr, $container, $row, $col, $searchable, $theory_topics, $rise) {
    $shapes = array(
        'warn' => 'pg-drv-sign pg-drv-sign-warn',
        'ban'  => 'pg-drv-sign pg-drv-sign-ban',
        'info' => 'pg-drv-sign text-bg-primary rounded-3',
    );
    $cards = array();
    foreach ($theory_topics as $t) {
        $mark = isset($t['mark']) ? $span($t['mark'], '', lang('Text')) : $icon($t['sign_icon']);
        $cards[] = $el('div', 'col', $t['title'], array(
            $el('div', 'd-flex gap-3 h-100 p-3 rounded-3 border bg-body', lang('Topic Card'), array(
                $el('div', $shapes[$t['sign']], lang('Road Sign'), array($mark), array('_attrs' => array($attr('aria-hidden', 'true')))),
                $el('div', '', lang('Topic Copy'), array(
                    $heading('h3', $t['title'], 'h6 fw-bold mb-1'),
                    $para($t['text'], 'small text-body-secondary mb-0'),
                )),
            )),
        ));
    }
    return $el('section', $class, lang('Theory Topics'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', $title, 'pdr-display h2 mb-2'),
                    $para($lead, 'text-body-secondary mb-4'),
                    $el('div', 'p-4 rounded-4 border border-primary border-2 mb-4', lang('Practice App'), array(
                        $span(lang('Practice questions'), 'd-block small fw-semibold text-uppercase text-primary mb-1', lang('Label')),
                        $span('1,000+', 'pdr-stat d-block display-5 fw-bold lh-1 mb-2', lang('Value')),
                        $para(lang('In the practice app every course learner gets, with a mock test timed like the real one.'), 'small text-body-secondary mb-0'),
                    )),
                    $link(lang('Enrol Now'), '{{page:enrol}}', 'btn btn-primary px-4'),
                ), '', '4'),
                $col(array(
                    $rise($el('div', 'row row-cols-1 row-cols-sm-2 g-3', lang('Topics'), $cards)),
                ), '', '8'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, lang('road signs, highway code, hazard perception, first aid, questions')));
};

// The practical test sheet (drivingschool-test-sheet): the day's stages
// with their time beside a sample of the examiner's sheet, the faults
// counted per skill and the three levels of fault.
$sheet_skills = array(
    array(lang('Mirrors'), '2'), array(lang('Signals'), '1'), array(lang('Junctions'), '0'),
    array(lang('Speed'), '1'), array(lang('Road position'), '0'), array(lang('Manoeuvre'), '0'),
);
$test_sheet = function ($id, $title, $lead, $class = 'py-5')
    use ($el, $heading, $para, $span, $icon, $attr, $container, $row, $col, $searchable, $test_day, $sheet_skills, $rise) {
    $cell = function ($tag, $text, $cls = '', $scope = '') use ($el, $attr) {
        $extra = array('text' => $text);
        if ($scope !== '') $extra['_attrs'] = array($attr('scope', $scope));
        return $el($tag, $cls, $tag === 'th' ? lang('Header Cell') : lang('Cell'), array(), $extra);
    };
    $stages = array();
    foreach ($test_day as $n => $s) {
        $stages[] = $el('li', 'd-flex gap-3 pb-3', lang('Stage'), array(
            $span($s['time'], 'badge rounded-pill text-bg-primary align-self-start text-nowrap', lang('Time')),
            $el('div', '', lang('Text'), array(
                $heading('h3', $s['title'], 'h6 fw-bold mb-1'),
                $para($s['text'], 'small text-body-secondary mb-0'),
            )),
        ));
    }
    $rows = array();
    foreach ($sheet_skills as $s) {
        $rows[] = $el('tr', '', $s[0], array(
            $cell('th', $s[0], 'fw-normal', 'row'),
            $cell('td', $s[1], 'text-center fw-semibold'),
            $cell('td', '0', 'text-center fw-semibold'),
            $cell('td', '0', 'text-center fw-semibold'),
        ));
    }
    $level = function ($icon_name, $cls, $name, $rule) use ($el, $icon, $span) {
        return $el('li', 'd-flex align-items-center gap-2', $name, array(
            $icon($icon_name, $cls . ' flex-shrink-0'),
            $span($name, 'fw-semibold', lang('Text')),
            $span($rule, 'text-body-secondary', lang('Text')),
        ));
    };
    $table = $el('table', 'table align-middle mb-0', lang('Faults Table'), array(
        $el('caption', 'visually-hidden', lang('Table Caption'), array(), array('text' => lang('Faults marked on a sample test sheet'))),
        $el('thead', '', lang('Table Head'), array($el('tr', '', lang('Head Row'), array(
            $cell('th', lang('Skill'), '', 'col'),
            $cell('th', lang('Driving faults'), 'text-center', 'col'),
            $cell('th', lang('Serious faults'), 'text-center', 'col'),
            $cell('th', lang('Dangerous faults'), 'text-center', 'col'),
        )))),
        $el('tbody', '', lang('Table Body'), $rows),
    ));
    return $el('section', $class, lang('Test Sheet'), array(
        $container(array(
            $el('div', 'mb-4', lang('Section Header'), array(
                $heading('h2', $title, 'pdr-display h2 mb-1'),
                $para($lead, 'text-body-secondary mb-0'),
            )),
            $row(array(
                $col(array($rise($el('ol', 'list-unstyled mb-0', lang('Test Stages'), $stages), 'view', 70)), '', '5'),
                $col(array(
                    $el('div', 'card border-0 shadow-sm pg-drv-sheet', lang('Test Sheet'), array(
                        $el('div', 'card-body p-4', lang('Card Body'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Card Head'), array(
                                $heading('h3', lang('What the examiner marks'), 'pdr-display h5 mb-0'),
                                $el('span', 'badge rounded-pill text-wrap bg-success-subtle text-success-emphasis', lang('Status Badge'), array(
                                    $icon('bi-check-circle-fill', 'me-1'),
                                    $span(lang('Passed'), '', lang('Text')),
                                )),
                            )),
                            $el('div', 'table-responsive mb-3', lang('Table Wrap'), array($table), array('_attrs' => array(
                                $attr('role', 'region'), $attr('aria-label', lang('Faults marked on a sample test sheet')), $attr('tabindex', '0'),
                            ))),
                            $el('ul', 'list-unstyled d-grid gap-2 small mb-3', lang('Fault Levels'), array(
                                $level('bi-dash-circle', 'text-body-secondary', lang('Driving fault'), lang('a few are allowed')),
                                $level('bi-exclamation-circle-fill', 'text-warning', lang('Serious fault'), lang('one means a retake')),
                                $level('bi-x-octagon-fill', 'text-danger', lang('Dangerous fault'), lang('one means a retake')),
                            )),
                            $para(lang('This sheet: four driving faults, none serious or dangerous.'), 'small fw-semibold mb-0'),
                        )),
                    )),
                ), '', '7'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable($id, lang('driving test, test day, examiner, faults, result')));
};

// ── Home ────────────────────────────────────────────────────────────────

// The classes as plates under the opening: one tap to a class's page.
$plate_links = array();
foreach ($licences as $key => $l) {
    $plate_links[] = $el('li', '', $l['title'], array(
        $el('a', 'd-inline-flex align-items-center gap-2 btn btn-outline-light', lang('Licence Link'), array(
            $span($l['code'], 'pg-drv-plate pdr-display', lang('Licence Class')),
            $span($l['title'], '', lang('Text')),
        ), array('href' => '{{page:licence_' . $key . '}}')),
    ));
}

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A driving school for cars, automatics, motorcycles, light trucks and trailers: courses with clear prices and patient instructors.'),
    'tree'             => $page(array(
        // The way in, on a dark band: the school in a sentence, the two
        // ways on, every class as a plate, and a lesson in the car. The
        // words rise in as the page opens.
        $el('section', 'pdr-hero py-5 overflow-hidden bg-body text-body', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $rise($el('div', '', lang('Text'), array(
                            $para(lang('Driving lessons and courses'), 'pdr-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                            $heading('h1', lang('Learn to drive with confidence'), 'pdr-display display-3 mb-3'),
                            $para($school['tagline'] . '.', 'lead text-body-secondary mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                                $link(lang('Enrol Now'), '{{page:enrol}}', 'btn btn-primary btn-lg px-4'),
                                $link(lang('Courses and Prices'), '{{page:courses}}', 'btn btn-outline-light btn-lg px-4'),
                            )),
                            $para(lang('Pick your licence class'), 'small fw-semibold text-body-secondary mb-2'),
                            $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Licence Classes'), $plate_links),
                        )), 'load'),
                    ), '', '6'),
                    $col(array($picture($photo('home-hero', 1200, 900), lang('A learner driver and an instructor in a dual-control car'), false)), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array_merge($searchable('drv-welcome', lang('driving school, driving lessons, learner driver, driving instructor')),
            array('_attrs' => array($attr('data-bs-theme', 'dark'))))),

        $licence_board('drv-home-licences', lang('Choose your licence'), lang('Each class has its own minimum age, lessons and test.')),

        $road('drv-home-road', lang('From first lesson to licence'), lang('Four steps, with one instructor beside you the whole way.')),

        // The featured courses, read from the Courses collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Featured Courses'), array(
            $container(array(
                $section_header(lang('Courses and prices'), lang('Our most chosen courses'), lang('All Courses'), '{{page:courses}}'),
                $widget('home_courses'),
            )),
        )),

        $theory_signs('drv-home-theory', lang('Pass the theory first'), lang('Six topics, learned one evening at a time and practised at home until the answers come easily.')),

        // The featured pass stories, read from the Pass Stories collection,
        // and beside them the school's own Google reviews, which show once
        // Google Reviews is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Pass Stories'), array(
            $container(array(
                $section_header(lang('Our learners'), lang('They passed with us'), lang('All Pass Stories'), '{{page:stories}}'),
                $widget('home_stories'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From the News'), array(
            $container(array(
                $section_header(lang('News'), lang('Tips and news from the school'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Licences ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'licences',
    'name'             => lang('licences'),
    'folder'           => 'public',
    'title'            => lang('Licences'),
    'meta_description' => lang('The licence classes we teach: car, automatic car, motorcycle, light truck and car with trailer.'),
    'tree'             => $page(array(
        $page_header(lang('Licences'), lang('Five licence classes, each with its own lessons, courses and test.')),
        $licence_board('drv-licences', lang('Choose your licence class'), lang('Not sure which one? Tell us what you want to drive and we name the class.'), 'py-5'),
        $road('drv-licences-road', lang('How every class goes'), lang('The same four steps, whichever licence you learn for.'), 'py-5 bg-body-tertiary'),
    )),
);

// One page per licence class: what it lets you drive, the age, the
// lessons it takes, what the lessons cover, its courses and a lesson
// request with the class chosen. Its address is a translated slug, so a
// Turkish site reads Turkish addresses.
$licence_slugs = array(
    'car'         => lang('car-licence'),
    'automatic'   => lang('automatic-car-licence'),
    'motorcycle'  => lang('motorcycle-licence'),
    'light_truck' => lang('light-truck-licence'),
    'trailer'     => lang('car-and-trailer-licence'),
);
foreach ($licences as $key => $l) {
    $public_pages[] = array(
        'key'              => 'licence_' . $key,
        'name'             => $licence_slugs[$key],
        'folder'           => 'public',
        'title'            => $l['title'],
        'meta_description' => $l['summary'],
        'tree'             => $page(array(
            $page_header($l['title'], $l['summary']),
            $el('section', 'py-5', $l['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Licence Class'), array(
                                $plate($l['code'], 'fs-4'),
                                $icon($l['icon'], 'fs-2 text-primary'),
                            )),
                            $para($l['text'], 'lead mb-4'),
                            $el('ul', 'list-unstyled d-flex flex-wrap gap-4 fw-semibold mb-4', lang('Facts'), array(
                                $el('li', 'd-inline-flex align-items-center gap-2', lang('Minimum Age'), array(
                                    $icon('bi-person', 'text-primary'),
                                    $span($l['age'], '', lang('Text')),
                                )),
                                $el('li', 'd-inline-flex align-items-center gap-2', lang('Lessons'), array(
                                    $icon('bi-car-front-fill', 'text-primary'),
                                    $span($l['lessons'], '', lang('Text')),
                                )),
                            )),
                            $heading('h2', lang('What the lessons cover'), 'pdr-display h4 mb-3'),
                            $rise($check_list($l['points'], 'list-unstyled mb-4')),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('See the courses'), '{{page:courses}}?' . $query(array('licence' => $l['title'])), 'btn btn-primary px-4'),
                                $link(lang('Book a Lesson'), '{{page:lesson_request}}?' . $query(array('licence' => $l['title'])), 'btn btn-outline-secondary px-4'),
                            )),
                        ), '', '6'),
                        $col(array($picture($photo('licence-' . $key, 1200, 900), $l['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('drv-licence-' . str_replace('_', '-', $key))),
            $el('section', 'py-5 bg-body-tertiary', lang('The Practical Test'), array(
                $container(array(
                    $section_header(lang('Test day'), lang('How the practical test goes'), lang('Practical Test'), '{{page:practical_test}}'),
                    $el('ol', 'list-unstyled mb-0', lang('Steps'), $test_day_items),
                )),
            )),
        )),
    );
}

// ── Theory test ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'theory',
    'name'             => lang('theory-test'),
    'folder'           => 'public',
    'title'            => lang('Theory Test'),
    'meta_description' => lang('What the theory test covers, how our theory classes run and how to prepare for the questions.'),
    'tree'             => $page(array(
        $page_header(lang('Theory Test'), lang('The topics of the test, our classes and the habits that help you pass.')),
        $theory_signs('drv-theory-topics', lang('Six topics to know'), lang('Every question of the test comes from one of these, and our classes take them one evening at a time.')),
        // The classes and the habits side by side on a tinted band.
        $el('section', 'py-5 bg-body-tertiary', lang('Theory Classes'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Learn it in the classroom'), 'pdr-display h3 mb-3'),
                        $para(lang('Our theory teacher runs short evening classes at the main branch, one topic at a time, with questions like the ones in the test.'), 'text-body-secondary mb-3'),
                        $para(lang('Every course learner also gets the practice app, so the questions can be tried at home until they feel easy.'), 'text-body-secondary mb-4'),
                        $link(lang('Enrol Now'), '{{page:enrol}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'h-100 p-4 rounded-4 bg-body border', lang('Tips'), array(
                            $heading('h2', lang('How to prepare'), 'pdr-display h3 mb-3'),
                            $rise($check_list(array(
                                lang('Study one topic a day instead of everything the night before'),
                                lang('Try the practice questions until you pass them three times in a row'),
                                lang('Read each question to the end before you choose an answer'),
                                lang('Watch the road as a passenger and name the signs you see'),
                            ))),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('drv-theory-classes', lang('theory classes, evening classes, practice app, mock questions, tips, revise'))),
    )),
);

// ── Practical test ──────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'practical_test',
    'name'             => lang('practical-test'),
    'folder'           => 'public',
    'title'            => lang('Practical Test'),
    'meta_description' => lang('How the practical driving test goes, what the examiner checks and how we get you ready for it.'),
    'tree'             => $page(array(
        $page_header(lang('Practical Test'), lang('From the warm-up lesson to the result, so nothing on the day comes as a surprise.')),
        $test_sheet('drv-test-day', lang('How the practical test goes'), lang('About forty minutes on real roads, then the result in the car.')),
        // How the school gets a learner ready, beside the car of the test.
        $el('section', 'py-5 bg-body-tertiary', lang('How We Get You Ready'), array(
            $container(array(
                $row(array(
                    $col(array($picture($photo('practical-test', 1200, 900), lang('A learner driver at a junction with the examiner beside them'))), '', '6', '', '12', 'order-lg-last'),
                    $col(array(
                        $heading('h2', lang('How we get you ready'), 'pdr-display h3 mb-3'),
                        $rise($check_list(array(
                            lang('A mock test with the same timing as the real one'),
                            lang('Lessons on the roads around the test centre'),
                            lang('The car you learned in, for the test itself'),
                        ), 'list-unstyled mb-4')),
                        $link(lang('Book a Lesson'), '{{page:lesson_request}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('drv-test-checks', lang('mirrors, manoeuvre, mock test, test centre, nervous'))),
    )),
);

// ── Instructors ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'instructors',
    'name'             => lang('instructors'),
    'folder'           => 'public',
    'title'            => lang('Instructors'),
    'meta_description' => lang('The instructors who teach cars, automatics, motorcycles, light trucks and trailers at our school.'),
    'tree'             => $page(array(
        $page_header(lang('Instructors'), lang('Patient, qualified and with you from the first lesson to the test.')),
        $el('section', 'py-5', lang('Instructors'), array(
            $container(array(
                $row($instructor_cols, '4'),
            )),
        ), $searchable('drv-instructors', lang('instructor, teacher, motorcycle instructor, team'))),
        $story_band('drv-one-instructor', lang('One instructor'), lang('The same face every lesson'),
            array(lang('After the assessment drive you are paired with one instructor, who keeps your lesson plan and knows exactly where you left off. You can ask for a change at any time.')),
            $photo('instructor-lesson', 1200, 900), lang('An instructor explaining a junction to a learner'), true,
            array($link(lang('Book a Lesson'), '{{page:lesson_request}}', 'btn btn-primary px-4')),
            lang('lesson plan, change instructor, assessment drive'), 'py-5 bg-body-tertiary'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A driving school started by two instructors: one instructor per learner, dual-control cars and clear prices.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $school['tagline'] . '.'),
        $story_band('drv-our-story', lang('Our story'), lang('One car and a whiteboard'),
            array($school['story']),
            $photo('about-school', 1200, 900), lang('The school\'s cars lined up in the car park'), false, array(),
            lang('history, founders, who we are, driving school')),
        $figures_band(),
        $el('section', 'py-5', lang('Why Learn with Us'), array(
            $container(array(
                $section_header(lang('The way we teach'), lang('Why learn with us')),
                $row($why_tiles, '4'),
            )),
        ), $searchable('drv-about-why', lang('same instructor, dual control, pick-up, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Branches'), array(
            $container(array(
                $section_header(lang('Where we are'), lang('Our branches'), lang('Contact Us'), '{{page:contact}}'),
                $row($branch_tiles, '4'),
            )),
        ), $searchable('drv-branches', lang('address, branch, office, where, location, training ground'))),
        $el('section', 'py-5', lang('Instructors'), array(
            $container(array(
                $section_header(lang('Our people'), lang('Your instructors'), lang('Instructors'), '{{page:instructors}}'),
                $row(array_slice($instructor_cols, 0, 3), '4'),
            )),
        )),
        $ask_band(lang('Ready for your first lesson?'), lang('Write to us with the licence you want and the times that suit you.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: before you start and lessons and tests. Each question is
// a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pdr-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about prices, the number of lessons, pick-up, the theory and practical tests and paying for a course.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Lessons, prices and the tests. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('drv-faq-start', lang('Before you start'), array(
                            array(lang('How many lessons will I need?'), lang('It depends on you and the licence. Your first lesson is an assessment drive, and after it your instructor gives you a written plan with the number of lessons we expect.'), lang('how many lessons, hours, how long')),
                            array(lang('How do I pay for a course?'), lang('We confirm your enrolment by phone first. You then pay at the branch, in one payment or in two parts; nothing is charged online.'), lang('payment, pay, instalments, price, cost')),
                            array(lang('Do you pick me up from home?'), lang('Yes, within our area we pick you up from home, school or work and drop you back after the lesson.'), lang('pick-up, collect, home, school, work')),
                            array(lang('How do I follow my enrolment?'), lang('Sending the enrolment opens an account for you. Under My Account you see its state and can write to us there.'), lang('my account, status, follow, message')),
                        )),
                        $faq_group('drv-faq-tests', lang('Lessons and tests'), array(
                            array(lang('Can I learn in an automatic?'), lang('Yes. Many learners need fewer lessons in an automatic, but the licence then lets you drive automatic cars only.'), lang('automatic, manual, gearbox, hybrid, electric')),
                            array(lang('Can I change my instructor?'), lang('Yes, at any time. Tell us and your next lesson is with another instructor, who takes over your lesson plan.'), lang('change instructor, another instructor')),
                            array(lang('Do I take the test in the school\'s car?'), lang('Yes. The car or motorcycle you learned in is included in the course for the practical test.'), lang('test car, vehicle, motorcycle, test day')),
                            array(lang('What if I do not pass?'), lang('We go through the faults with you, plan a few lessons on them and book the next test as soon as you are ready.'), lang('fail, retake, second test, faults')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured courses (home_courses) come with the
// courses, its pass stories (home_stories) with the stories, its News
// posts (home_posts) and the Google reviews (google_reviews) with the
// standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
