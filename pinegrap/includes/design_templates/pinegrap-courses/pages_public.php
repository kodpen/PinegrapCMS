<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Online Courses", its public pages: home, the courses,
 * one overview page per course, the instructors, pricing with the
 * membership, about and the FAQ, and the Google Reviews widget the home
 * page places beside its sample testimonials. Included after parts.php in
 * the same scope (its builders and the school's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A course page offers three ways in: its free first lesson, the shop's
 * buy box (the buy_<course> widget, only with the shop) and an enrolment
 * request, which works without the shop.
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

// ── The Online Courses blocks ───────────────────────────────────────────
// The home, the courses page and each course's page are built on the
// visual designer's Online Courses blocks (assets/js/style_designer_blocks_
// courses.js): the same structure and classes with the school's own
// courses; their rules ship in the template's User Styles. Motion comes
// from the interactions on the nodes (pg_fx; nothing moves under reduced
// motion): $fx gives a node one effect.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// Section headings fade in; a list's or a row's children rise one by one.
$fade_in = function ($node) use ($fx) {
    return $fx($node, array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700));
};
$rise = function ($node, $stagger = 100) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => $stagger));
};
// An icon and a short text side by side, as one line of details.
$line_detail = function ($icon_name, $text, $class = '') use ($el, $icon, $span) {
    return $el('span', trim('d-inline-flex align-items-center gap-1 ' . $class), $text, array(
        $icon($icon_name),
        $span($text, '', lang('Text')),
    ));
};
// A second button: the body's text colour and a border, readable on any
// band, with its own focus ring (pg-courses-second).
$second_button = function ($text, $href, $size = '') use ($link) {
    return $link($text, $href, trim('btn ' . $size . ' border px-4 link-body-emphasis pg-courses-second'));
};
// The small spaced line above a block's title, as the template's other
// sections write it (pco-eyebrow), so the home reads as one page.
$block_eyebrow = function ($text) use ($para) {
    return $para($text, 'small text-uppercase fw-semibold text-primary mb-2 pco-eyebrow');
};
// A lesson's length, machine-readable as a duration.
$minutes = function ($n) use ($el, $attr) {
    return $el('time', 'small text-body-secondary text-nowrap', lang('Length'), array(), array(
        'text' => sprintf(lang('%d min'), $n), '_attrs' => array($attr('datetime', 'PT' . (int)$n . 'M')),
    ));
};
// What a screen reader hears beside a locked lesson; the lock is a picture
// only.
$locked = function () use ($span) {
    return $span(lang('Enrolled students only'), 'visually-hidden', lang('Text'));
};
// The free preview's mark, at the size of the text around it.
$preview_pill = function () use ($span) {
    return $span(lang('Free preview'), 'd-inline-block rounded-pill px-2 lh-base fw-semibold text-nowrap bg-success-subtle text-success-emphasis', lang('Preview'));
};
// A course's level: three bars filled up to it (a picture only), then its
// name.
$level_meter = function ($key) use ($el, $span, $attr, $levels) {
    $n = array_search($key, array_keys($levels), true) + 1;
    $bars = array();
    foreach (array('', 'pg-courses-mid', 'pg-courses-tall') as $i => $size) {
        $bars[] = $el('span', trim('pg-courses-bar ' . $size . ($i < $n ? ' pg-courses-on' : '')), lang('Bar'));
    }
    return $el('span', 'd-inline-flex align-items-center gap-2 small fw-semibold text-body-emphasis', lang('Level'), array(
        $el('span', 'pg-courses-level', lang('Level Meter'), $bars, array('_attrs' => array($attr('aria-hidden', 'true')))),
        $span($levels[$key], '', lang('Text')),
    ));
};
// A subject as a pill link to its section of the courses page: its icon and
// name on its soft colour, so the colour never names it alone.
$subject_link = function ($key) use ($el, $icon, $span, $subjects) {
    $s = $subjects[$key];
    return $el('li', '', $s['title'], array(
        $el('a', 'btn rounded-pill border-0 d-inline-flex align-items-center gap-2 px-3 py-1 bg-' . $s['color'] . '-subtle text-' . $s['color'] . '-emphasis', $s['title'], array(
            $icon($s['icon']),
            $span($s['title'], '', lang('Text')),
        ), array('href' => '{{page:courses}}#subject-' . $key)),
    ));
};

// A course as a card of the shelf: its picture with the length on it, its
// subject, title (the card's link to its page), what it teaches, its level,
// the instructor and the price.
$course_card = function ($key) use ($courses, $instructors, $subjects, $el, $col, $image, $para, $span, $icon, $link, $photo, $portrait, $attr, $level_meter) {
    $c = $courses[$key];
    $s = $subjects[$c['subject']];
    $t = $instructors[$c['instructor']];
    return $col(array(
        $el('article', 'pg-courses-card card h-100 border-0 shadow-sm rounded-4 overflow-hidden', $c['title'], array(
            $el('div', 'position-relative', lang('Picture'), array(
                $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo('course-' . $key, 800, 450), $c['title'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                )),
                $el('span', 'position-absolute bottom-0 end-0 m-3 badge rounded-pill bg-body text-body-emphasis d-inline-flex align-items-center gap-1 shadow-sm', lang('Length'), array(
                    $icon('bi-clock'),
                    $span($c['length'], '', lang('Text')),
                )),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'd-flex align-items-center gap-2 small fw-semibold text-body-secondary mb-2', lang('Subject'), array(
                    $icon($s['icon'], 'text-' . $s['color'] . '-emphasis'),
                    $span($s['title'], '', lang('Text')),
                )),
                $el('h3', 'h5 fw-bold', lang('Course Title'), array(
                    $link($c['title'], '{{page:course_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($c['summary'], 'small text-body-secondary mb-3'),
                $el('div', 'mb-3', lang('Level'), array($level_meter($c['level']))),
                $el('div', 'd-flex align-items-center gap-2 border-top pt-3 mt-auto', lang('Card Footer'), array(
                    $image($portrait($t['name'], 64), $t['name'], 'pg-courses-face rounded-circle object-fit-cover', array('width' => '32', 'height' => '32', '_attrs' => array($attr('loading', 'lazy')))),
                    $para($t['name'], 'small fw-semibold text-body-emphasis mb-0'),
                    $para($c['price'], 'pg-courses-price ms-auto fs-5 fw-bold text-body-emphasis mb-0'),
                )),
            )),
        )),
    ), '6', '4');
};

// A lesson on a course's path: the free preview a link with its pill, the
// others locked; what it covers and its length.
$lesson_row = function ($l, $open) use ($el, $icon, $para, $link, $minutes, $locked, $preview_pill) {
    return $el('li', 'd-flex align-items-start gap-3 py-3 border-top', $l['title'], array(
        $icon($open ? 'bi-play-circle-fill' : 'bi-lock', ($open ? 'text-primary-emphasis' : 'text-body-secondary') . ' fs-5 lh-1 mt-1'),
        $el('div', 'flex-grow-1', lang('Lesson'), array(
            $open
                ? $el('p', 'd-flex flex-wrap align-items-center column-gap-2 row-gap-1 fw-semibold mb-1', lang('Lesson Title'), array(
                    $link($l['title'], '{{page:lesson_' . $l['key'] . '}}', 'link-body-emphasis'),
                    $el('span', 'small', lang('Preview'), array($preview_pill())),
                ))
                : $para($l['title'], 'fw-semibold text-body-emphasis mb-1'),
            $para($l['text'], 'small text-body-secondary mb-0'),
        )),
        $el('div', 'flex-shrink-0 text-end', lang('Lesson Details'), $open ? array($minutes($l['minutes'])) : array($minutes($l['minutes']), $locked())),
    ));
};
// A course's curriculum on its dashed path: each module with its number (a
// picture only: the heading says it), title, lessons and minutes, then the
// course's project as the last stop.
$course_path = function ($key) use ($courses, $course_lessons, $el, $heading, $para, $span, $icon, $attr, $line_detail, $lesson_row) {
    $c = $courses[$key];
    $lessons = $course_lessons($key);
    $hidden = array('_attrs' => array($attr('aria-hidden', 'true')));
    $items = array();
    foreach ($c['modules'] as $mn => $m) {
        $rows = array();
        $total = 0;
        foreach ($m['lessons'] as $l) {
            $rows[] = $lesson_row($l, $l['key'] === $lessons[0]['key']);
            $total += $l['minutes'];
        }
        $items[] = $el('li', 'pg-courses-module', $m['title'], array(
            $el('span', 'pg-courses-marker bg-body border border-2 border-primary text-body-emphasis', lang('Marker'), array($span((string)($mn + 1), '', lang('Text'))), $hidden),
            $heading('h3', sprintf(lang('Module %d: %s'), $mn + 1, $m['title']), 'h5 fw-bold mb-1'),
            $el('p', 'd-flex flex-wrap column-gap-3 small text-body-secondary mb-2', lang('Module Details'), array(
                $line_detail('bi-collection-play', sprintf(lang('%d lessons'), count($m['lessons']))),
                $line_detail('bi-clock', sprintf(lang('%d min'), $total)),
            )),
            $el('ol', 'list-unstyled mb-0', lang('Lessons'), $rows),
        ));
    }
    $items[] = $el('li', 'pg-courses-module pg-courses-project', lang('Your project'), array(
        $el('span', 'pg-courses-marker text-bg-primary', lang('Marker'), array($icon('bi-flag-fill')), $hidden),
        $heading('h3', lang('Your project'), 'h5 fw-bold mb-2'),
        $para($c['assignment'], 'mb-2'),
        $line_detail('bi-chat-left-text', lang('Written feedback from your instructor'), 'small text-body-secondary gap-2'),
    ));
    return $el('ol', 'pg-courses-modules', lang('Modules'), $items);
};

// The classroom hero: the school's line and the way in, beside a lesson
// player: the first course's free lesson with its instructor, and the
// lessons up next, locked until one enrols. The words rise and the player
// grows as the page opens.
$classroom_hero = function () use ($courses, $instructors, $course_lessons, $el, $heading, $para, $link, $icon, $image, $photo, $portrait, $container, $row, $col, $attr, $fx, $line_detail, $second_button, $minutes, $locked, $preview_pill) {
    $c = $courses['web'];
    $t = $instructors[$c['instructor']];
    $lessons = $course_lessons('web');
    $free = $lessons[0];
    $next = array();
    foreach (array_slice($lessons, 1, 3) as $l) {
        $next[] = $el('li', 'd-flex align-items-center gap-3 py-2 border-top', $l['title'], array(
            $icon('bi-lock', 'text-body-secondary'),
            $para($l['title'], 'flex-grow-1 small fw-semibold text-body-emphasis mb-0'),
            $minutes($l['minutes']),
            $locked(),
        ));
    }
    $words = $fx($el('div', '', lang('Hero Text'), array(
        $el('p', 'd-inline-flex align-items-center gap-2 small text-uppercase fw-semibold text-body-secondary mb-3', lang('Tagline'), array(
            $icon('bi-play-circle', 'text-primary-emphasis'),
            $el('span', '', lang('Text'), array(), array('text' => lang('Learn online, at your own pace'))),
        )),
        $heading('h1', lang('Learn a skill you will use on Monday'), 'display-4 fw-bold mb-3'),
        $para(lang('Short video lessons, a project in every course and instructors who answer your questions. Start with a free lesson; keep the course once you buy it.'), 'lead text-body-secondary mb-4'),
        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
            $link(lang('Browse courses'), '{{page:courses}}', 'btn btn-primary btn-lg px-4'),
            $second_button(lang('Watch a free lesson'), '{{page:lesson_' . $free['key'] . '}}', 'btn-lg'),
        )),
        $el('ul', 'list-unstyled d-flex flex-wrap column-gap-4 row-gap-2 small text-body-secondary mb-0', lang('Promises'), array(
            $el('li', '', lang('First lesson free'), array($line_detail('bi-unlock', lang('First lesson free'), 'gap-2'))),
            $el('li', '', lang('No end date'), array($line_detail('bi-infinity', lang('No end date'), 'gap-2'))),
            $el('li', '', lang('Instructors who answer'), array($line_detail('bi-chat-dots', lang('Instructors who answer'), 'gap-2'))),
        )),
    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800));
    $player = $fx($el('div', 'pg-courses-desk', lang('Desk'), array(
        $el('article', 'pg-courses-player card border-0 shadow-lg rounded-4 overflow-hidden', lang('Lesson Player'), array(
            $el('div', 'pg-courses-screen', lang('Screen'), array(
                $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture'), array(
                    $image($photo('lesson-' . $free['key'], 1200, 675), lang('An instructor\'s screen with a web page and its code side by side'), 'object-fit-cover'),
                )),
                $el('a', 'pg-courses-play btn btn-primary rounded-circle', lang('Play the free lesson'), array($icon('bi-play-fill')), array(
                    'href' => '{{page:lesson_' . $free['key'] . '}}', '_attrs' => array($attr('aria-label', lang('Play the free lesson'))),
                )),
            )),
            $el('div', 'pg-courses-scrub', lang('Progress'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $el('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 small text-body-secondary mb-2', lang('Course Line'), array(
                    $line_detail('bi-collection-play', $c['title']),
                    $preview_pill(),
                )),
                $heading('h2', $free['title'], 'h4 fw-bold mb-3'),
                $el('div', 'd-flex align-items-center gap-3 mb-4', lang('Instructor'), array(
                    $image($portrait($t['name'], 80), $t['name'], 'pg-courses-face rounded-circle object-fit-cover', array('width' => '40', 'height' => '40')),
                    $el('div', 'flex-grow-1', lang('Text'), array(
                        $para($t['name'], 'small fw-semibold text-body-emphasis mb-0'),
                        $para($t['role'], 'small text-body-secondary mb-0'),
                    )),
                    $minutes($free['minutes']),
                )),
                $heading('h3', lang('Up next'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
                $el('ol', 'list-unstyled mb-0', lang('Lessons'), $next),
            )),
        )),
    )), array('effect' => 'scale', 'trigger' => 'load', 'duration' => 900, 'delay' => 200));
    return $el('section', 'pg-courses-hero py-5 overflow-hidden', lang('Hero'), array(
        $container(array(
            $row(array(
                $col(array($words), '', '6'),
                $col(array($player), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        ), 'py-lg-4'),
    ));
};

// The school's figures, in a row of four.
$figure_row = function ($class = '') use ($figures, $el, $row, $col, $span) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Figure'), array(
                $span($f['value'], 'd-block display-6 fw-bold text-primary', lang('Number')),
                $span($f['label'], 'small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// What students say: sample words the school replaces with its own.
$testimonial = function ($quote, $who, $what) use ($el, $para, $col) {
    return $col(array(
        $el('figure', 'h-100 p-4 rounded-4 bg-body mb-0', lang('Testimonial'), array(
            $el('blockquote', 'mb-3', lang('Quote'), array($para($quote, 'mb-0'))),
            $el('figcaption', 'small', lang('Author'), array(
                $para($who, 'fw-semibold mb-0'),
                $para($what, 'text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};

// An instructor as a small card, linked to the profile on the instructors
// page.
$instructor_card = function ($key) use ($instructors, $subjects, $el, $col, $image, $heading, $para, $portrait, $attr) {
    $i = $instructors[$key];
    return $col(array(
        $el('a', 'card h-100 border-0 text-center bg-transparent text-decoration-none link-body-emphasis pco-lift', $i['name'], array(
            $el('div', 'card-body p-3', lang('Card Body'), array(
                $image($portrait($i['name'], 320), $i['name'], 'rounded-circle object-fit-cover mb-3', array('width' => '144', 'height' => '144', '_attrs' => array($attr('loading', 'lazy')))),
                $heading('h3', $i['name'], 'h6 fw-bold mb-1'),
                $para($i['role'], 'small text-body-secondary mb-0'),
                $para($subjects[$i['subject']]['title'], 'small fw-semibold text-primary mb-0'),
            )),
        ), array('href' => '{{page:instructors}}#instructor-' . $key)),
    ), '4', '4', '', '12');
};


$all_course_cards = array();
foreach (array_keys($courses) as $key) $all_course_cards[] = $course_card($key);
$instructor_cards = array();
foreach (array_keys($instructors) as $key) $instructor_cards[] = $instructor_card($key);
$subject_links = array();
foreach (array_keys($subjects) as $key) $subject_links[] = $subject_link($key);

// The course the home opens up: its facts beside its curriculum.
$inside = $courses['web'];
$inside_lessons = $course_lessons('web');
$fact = function ($term, $value) use ($el, $span) {
    return $el('div', '', $term, array(
        $el('dt', 'small fw-normal text-body-secondary', $term, array(), array('text' => $term)),
        $el('dd', 'fw-semibold text-body-emphasis', $term, is_array($value) ? $value : array($span($value, '', lang('Text')))),
    ));
};

// The home: the classroom hero, the course shelf, one course opened up
// module by module, the instructors, what students say and the blog.
$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Practical online courses in web development, data and design. Watch the first lesson of any course free, then learn at your own pace.'),
    'tree'             => $page(array(
        $classroom_hero(),

        // The courses, under their subjects as links.
        $el('section', 'pg-courses-shelf py-5', lang('Courses'), array(
            $container(array(
                $fade_in($row(array(
                    $col(array(
                        $block_eyebrow(lang('Start here')),
                        $heading('h2', lang('Our courses'), 'display-6 fw-bold mb-3'),
                        $para(lang('Every course starts with a free lesson. Pick a subject, watch, and enrol when it fits.'), 'lead text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $el('nav', '', lang('Subjects'), array(
                            $el('ul', 'list-unstyled d-flex flex-wrap gap-2 justify-content-lg-end mb-0', lang('Subjects'), $subject_links),
                        ), array('_attrs' => array($attr('aria-label', lang('Subjects'))))),
                    ), '', '6'),
                ), '4', '', 'align-items-end mb-5')),
                $rise($row($all_course_cards, '4')),
                $el('div', 'text-center mt-5', lang('Footer'), array($second_button(lang('All courses'), '{{page:courses}}'))),
            )),
        )),

        // Inside a course: the first course's facts beside its modules and
        // lessons on a path, the project at the end.
        $el('section', 'py-5 bg-body-tertiary', lang('Curriculum'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $fade_in($el('div', '', lang('Course'), array(
                            $block_eyebrow(lang('Inside a course')),
                            $heading('h2', $inside['title'], 'display-6 fw-bold mb-3'),
                            $para($inside['summary'], 'text-body-secondary mb-4'),
                            $el('dl', 'pg-courses-facts mb-4', lang('Facts'), array(
                                $fact(lang('Level'), array($level_meter($inside['level']))),
                                $fact(lang('Instructor'), $instructors[$inside['instructor']]['name']),
                                $fact(lang('Length'), $inside['length']),
                                $fact(lang('Access'), lang('No end date')),
                            )),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Watch the free lesson'), '{{page:lesson_' . $inside_lessons[0]['key'] . '}}', 'btn btn-primary px-4'),
                                $second_button(lang('See the course'), '{{page:course_web}}'),
                            )),
                        ))),
                    ), '', '4'),
                    $col(array($rise($course_path('web'), 120)), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),

        // The instructors.
        $el('section', 'py-5', lang('Instructors'), array(
            $container(array(
                $fade_in($section_header(lang('Who teaches'), lang('Meet the instructors'), lang('All instructors'), '{{page:instructors}}')),
                $rise($row($instructor_cards, '4')),
            )),
        )),

        // What students say: three sample cards, and the reviews from
        // Google beside them once the place is set.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $fade_in($section_header(lang('Our students'), lang('What students say'))),
                $rise($row(array(
                    $testimonial(lang('“I had tried to learn HTML three times. This time I published my site in the second week.”'), lang('Deniz K.'), lang('Build Your First Website')),
                    $testimonial(lang('“The pivot table lesson alone saved me an hour every Monday.”'), lang('Aylin M.'), lang('Data Analysis with Spreadsheets')),
                    $testimonial(lang('“Maya\'s feedback on my wireframes was worth the whole course.”'), lang('Tom R.'), lang('UX Design Fundamentals')),
                ), '4')),
                $widget('google_reviews'),
            )),
        )),

        // The newest posts.
        $el('section', 'py-5', lang('From the Blog'), array(
            $container(array(
                $fade_in($section_header(lang('Blog'), lang('Latest posts'), lang('All posts'), '{{page:blog}}')),
                $widget('home_posts'),
            )),
        )),

        $shared('enrol_band'),
    )),
);

// ── Courses ─────────────────────────────────────────────────────────────
// A section for each subject (#subject-<key>) with its courses, as cards of
// the Online Courses shelf.
$subject_sections = array();
foreach ($subjects as $skey => $s) {
    $cards = array();
    foreach ($courses as $ckey => $c) {
        if ($c['subject'] === $skey) $cards[] = $course_card($ckey);
    }
    $subject_sections[] = $el('section', 'pg-courses-shelf py-4', $s['title'], array(
        $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Subject'), array(
            $icon_tile($s['icon']),
            $el('div', '', lang('Text'), array(
                $heading('h2', $s['title'], 'h4 fw-bold mb-1'),
                $para($s['text'], 'text-body-secondary mb-0'),
            )),
        )),
        $rise($row($cards, '4')),
    ), $searchable('subject-' . $skey, $s['topics']));
}

$public_pages[] = array(
    'key'              => 'courses',
    'name'             => lang('courses'),
    'folder'           => 'public',
    'title'            => lang('Courses'),
    'meta_description' => lang('All our online courses by subject: what each teaches, its level, its length and its price.'),
    'tree'             => $page(array(
        $page_header(lang('Courses'), lang('Every course starts with a free lesson. Pick a subject, watch, and enrol when it fits.')),
        $el('section', 'py-4', lang('Subjects'), array($container($subject_sections))),
        $shared('enrol_band'),
    )),
);

// ── One page per course ─────────────────────────────────────────────────
// What it teaches, its curriculum on the Online Courses path (the modules,
// the first lesson linked as the free preview, the project as the last
// stop), the instructor, and the box with the ways in.
foreach ($courses as $key => $c) {
    $teacher = $instructors[$c['instructor']];
    $lessons = $course_lessons($key);

    $public_pages[] = array(
        'key'              => 'course_' . $key,
        'name'             => $c['slug'],
        'folder'           => 'public',
        'title'            => $c['title'],
        'meta_description' => $c['summary'],
        'tree'             => $page(array(
            $page_header($c['title'], $c['summary'], array(
                $el('div', 'd-flex flex-wrap align-items-center gap-3 small mt-3', lang('Course Details'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1 rounded-pill px-2 lh-base fw-semibold bg-' . $subjects[$c['subject']]['color'] . '-subtle text-' . $subjects[$c['subject']]['color'] . '-emphasis', lang('Subject'), array(
                        $icon($subjects[$c['subject']]['icon']),
                        $span($subjects[$c['subject']]['title'], '', lang('Text')),
                    )),
                    $level_meter($c['level']),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Instructor'), array($icon('bi-person'), $span($teacher['name'], '', lang('Text')))),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Lessons'), array($icon('bi-collection-play'), $span(sprintf(lang('%d lessons'), count($lessons)), '', lang('Text')))),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Length'), array($icon('bi-clock'), $span($c['length'], '', lang('Text')))),
                )),
            )),
            $el('section', 'py-5', lang('Course'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'mb-5', lang('What You Will Learn'), array(
                                $heading('h2', lang('What you will learn'), 'h4 fw-bold mb-3'),
                                $check_list($c['outcomes']),
                            ), $searchable('learn-' . $key, $subjects[$c['subject']]['topics'])),
                            $el('div', 'mb-5', lang('Curriculum'), array(
                                $heading('h2', lang('Curriculum'), 'h4 fw-bold mb-4'),
                                $rise($course_path($key), 120),
                            ), array('id' => 'curriculum')),
                            $el('div', 'd-flex gap-3 align-items-start', lang('Instructor'), array(
                                $image($portrait($teacher['name'], 160), $teacher['name'], 'rounded-circle object-fit-cover flex-shrink-0', array('width' => '80', 'height' => '80', '_attrs' => array($attr('loading', 'lazy')))),
                                $el('div', '', lang('Text'), array(
                                    $para(lang('Your instructor'), 'small text-uppercase fw-semibold text-primary mb-1 pco-eyebrow'),
                                    $heading('h2', $teacher['name'], 'h5 fw-bold mb-1'),
                                    $para($teacher['role'], 'small text-body-secondary mb-2'),
                                    $para($teacher['bio'], 'mb-2'),
                                    $link(lang('More about the instructor'), '{{page:instructors}}#instructor-' . $c['instructor'], 'small fw-semibold'),
                                )),
                            )),
                        ), '', '8'),
                        $col(array(
                            $el('aside', 'card border-0 shadow-sm rounded-4 position-sticky top-0', lang('Enrol Box'), array(
                                $el('div', 'card-body p-4', lang('Card Body'), array(
                                    $span($c['price'], 'd-block display-6 fw-bold mb-1', lang('Price')),
                                    $para(lang('One payment, no end date. Watch on any device.'), 'small text-body-secondary mb-3'),
                                    $widget('buy_' . $key),
                                    $el('div', 'd-grid gap-2', lang('Buttons'), array(
                                        $link(lang('Watch the free lesson'), '{{page:lesson_' . $lessons[0]['key'] . '}}', 'btn btn-outline-primary'),
                                        $link(lang('Request enrolment'), '{{page:enrolment}}?course=' . rawurlencode($c['title']), 'btn btn-outline-secondary'),
                                        $link(lang('Already enrolled? Open the classroom'), '{{page:learn_' . $key . '}}', 'btn btn-link'),
                                    )),
                                    $para(lang('Members get a discount code for every course.'), 'small text-body-secondary mt-3 mb-0'),
                                )),
                            )),
                        ), '', '4'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
            $shared('enrol_band'),
        )),
    );
}

// ── Instructors ─────────────────────────────────────────────────────────
$instructor_profiles = array();
foreach ($instructors as $key => $i) {
    $taught = array();
    foreach ($courses as $ckey => $c) {
        if ($c['instructor'] === $key) $taught[] = $el('li', 'mb-1', lang('List Item'), array($link($c['title'], '{{page:course_' . $ckey . '}}', 'fw-semibold')));
    }
    $instructor_profiles[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden', $i['name'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $image($portrait($i['name']), $i['name'], 'rounded-circle object-fit-cover mb-3', array('width' => '120', 'height' => '120', '_attrs' => array($attr('loading', 'lazy')))),
                $el('div', 'mb-2', lang('Badges'), array($subject_badge($i['subject']))),
                $heading('h2', $i['name'], 'h5 fw-bold mb-1'),
                $para($i['role'], 'small text-primary mb-2'),
                $para($i['bio'], 'small text-body-secondary mb-3'),
                $para(lang('Teaches'), 'small text-uppercase fw-semibold text-body-secondary mb-1'),
                $el('ul', 'list-unstyled small mb-0', lang('Courses'), $taught),
            )),
        ), $searchable('instructor-' . $key, $i['role'] . ', ' . $subjects[$i['subject']]['title'])),
    ), '6', '4');
}

$public_pages[] = array(
    'key'              => 'instructors',
    'name'             => lang('instructors'),
    'folder'           => 'public',
    'title'            => lang('Instructors'),
    'meta_description' => lang('The instructors who teach our courses: what they do every day and what they teach.'),
    'tree'             => $page(array(
        $page_header(lang('Instructors'), lang('People who do the work every day and like explaining it.')),
        $el('section', 'py-5', lang('Instructors'), array($container(array($row($instructor_profiles, '4'))))),
        $el('section', 'py-5 bg-body-tertiary', lang('Teach With Us'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Want to teach a course?'), 'h4 fw-bold mb-2'),
                        $para(lang('Tell us what you would teach and who it is for. We help with the plan, the recording and the students\' questions.'), 'text-body-secondary mb-0'),
                    ), '', '8'),
                    $col(array(
                        $link(lang('Write to us'), '{{page:contact}}', 'btn btn-outline-primary px-4'),
                    ), '', '4', '', '12', 'text-lg-end'),
                ), '4', '', 'align-items-center'),
            )),
        ), $searchable('teach-with-us', lang('teach, instructor, become an instructor, create a course'))),
        $shared('enrol_band'),
    )),
);

// ── Pricing ─────────────────────────────────────────────────────────────
// Each course on its own (from the cheapest, in the base currency), the
// membership, and training for teams.
$price_rows = array();
$cheapest = null;
foreach ($courses as $key => $c) {
    if ($cheapest === null || $amount_in_base($c['prices']) < $amount_in_base($cheapest)) $cheapest = $c['prices'];
    $price_rows[] = $el('li', 'list-group-item d-flex justify-content-between align-items-center gap-3 px-0 bg-transparent', $c['title'], array(
        $link($c['title'], '{{page:course_' . $key . '}}', 'link-body-emphasis'),
        $span($c['price'], 'fw-bold text-nowrap', lang('Price')),
    ));
}
$plan_card = function ($title, $price, $text, $children, $featured) use ($el, $heading, $para, $span) {
    return $el('div', 'card h-100 rounded-4 ' . ($featured ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), $title, array(
        $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array_merge(array(
            $heading('h2', $title, 'h5 fw-bold mb-1'),
            $span($price, 'd-block fs-3 fw-bold text-primary mb-2', lang('Price')),
            $para($text, 'small text-body-secondary mb-3'),
        ), $children)),
    ));
};

$public_pages[] = array(
    'key'              => 'pricing',
    'name'             => lang('pricing'),
    'folder'           => 'public',
    'title'            => lang('Pricing'),
    'meta_description' => lang('Buy a course once and keep it, or take the membership for the library, the live sessions and a discount on every course.'),
    'tree'             => $page(array(
        $page_header(lang('Pricing'), lang('No subscription needed: buy the course you want and keep it.')),
        $el('section', 'py-5', lang('Plans'), array(
            $container(array(
                $row(array(
                    $col(array($plan_card(lang('One course'), sprintf(lang('From %s'), $money($cheapest)), lang('Pay once for a course and keep it, with every lesson, its project and the instructor\'s feedback.'), array(
                        $el('ul', 'list-group list-group-flush mb-3', lang('Courses'), $price_rows),
                        $link(lang('Browse courses'), '{{page:courses}}', 'btn btn-outline-primary mt-auto'),
                    ), false)), '', '4'),
                    $col(array($plan_card($membership['title'], $membership['price'], $membership['text'], array(
                        $check_list($membership['items'], 'list-unstyled mb-4'),
                        $widget('buy_membership'),
                        $link(lang('What is in the library?'), '{{page:library}}', 'btn btn-link mt-auto'),
                    ), true)), '', '4'),
                    $col(array($plan_card(lang('Teams'), lang('On request'), lang('Enrol a team in one or more courses with one invoice, and follow their progress with us.'), array(
                        $check_list(array(lang('One invoice for the whole team'), lang('Enrolment by the staff, no card needed'), lang('A call with the instructor for the team')), 'list-unstyled mb-4'),
                        $link(lang('Request enrolment'), '{{page:enrolment}}', 'btn btn-outline-primary mt-auto'),
                    ), false)), '', '4'),
                ), '4'),
            )),
        ), $searchable('plans', lang('price, cost, membership, subscription, team, invoice'))),
        $el('section', 'py-5 bg-body-tertiary', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Questions about paying'), 'h4 fw-bold mb-3'),
                        $link(lang('All questions'), '{{page:faq}}', 'btn btn-outline-primary px-4'),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('How long can I watch a course I bought?'), lang('As long as the course is online. A bought course has no end date.'), lang('access, how long, expire, lifetime'), 'co-pay'),
                            $faq(2, lang('Does the membership renew by itself?'), lang('No. The membership runs for a year from the day you buy it; buy it again to renew.'), lang('renew, cancel, subscription, automatic'), 'co-pay'),
                            $faq(3, lang('Can I get my money back?'), lang('Yes, within 14 days of buying, if you have watched less than a third of the course. Write to us from your order.'), lang('refund, money back, return'), 'co-pay'),
                        ), array('id' => 'co-pay')),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('enrol_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('Who we are, how our courses are made and why every course ends with a project.'),
    'tree'             => $page(array(
        $page_header(lang('About'), lang('A small school of people who teach what they do every day.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('An instructor recording a lesson at a desk with a camera and a microphone'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1 pco-eyebrow'),
                        $heading('h2', lang('From evening classes to a school online'), 'h3 fw-bold mb-3'),
                        $para(lang('We began with evening classes for friends who wanted to change careers. When the classes filled up, we recorded them and kept what worked: short lessons, one project, and answers from a real person.'), 'text-body-secondary'),
                        $para(lang('Every course is planned, recorded and kept up to date by its instructor, and every project gets written feedback.'), 'text-body-secondary mb-4'),
                        $figure_row('text-start'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('story, school, team, history'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How We Teach'), array(
            $container(array(
                $section_header(lang('How we teach'), lang('Three promises')),
                $row(array(
                    $col(array($icon_tile('bi-stopwatch', 'mb-3'), $heading('h3', lang('Short lessons'), 'h5 fw-bold mb-2'), $para(lang('Most lessons take under twenty minutes, so one fits into a lunch break.'), 'text-body-secondary mb-0')), '', '4'),
                    $col(array($icon_tile('bi-hammer', 'mb-3'), $heading('h3', lang('A real project'), 'h5 fw-bold mb-2'), $para(lang('Every course ends with something you made and can show.'), 'text-body-secondary mb-0')), '', '4'),
                    $col(array($icon_tile('bi-chat-heart', 'mb-3'), $heading('h3', lang('A person who answers'), 'h5 fw-bold mb-2'), $para(lang('Ask under any lesson; the instructor or a teaching assistant answers within two working days.'), 'text-body-secondary mb-0')), '', '4'),
                ), '4'),
            )),
        ), $searchable('how-we-teach', lang('method, lessons, project, feedback, support'))),
        $shared('enrol_band'),
    )),
);

// ── FAQ ─────────────────────────────────────────────────────────────────
$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('FAQ'),
    'meta_description' => lang('Answers to common questions about our courses: access, devices, the membership, projects, teams and refunds.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Not here? Write to us and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Where do I find the courses I bought?'), lang('Sign in and open My Courses: the classroom of every course you are enrolled in opens from there.'), lang('my courses, classroom, find, sign in')),
                            $faq(2, lang('Can I watch on my phone?'), lang('Yes. The lessons play in any modern browser on a phone, a tablet or a computer.'), lang('phone, mobile, tablet, device, app')),
                            $faq(3, lang('Do I get a certificate?'), lang('Not automatically. Once your project is passed, ask us under it and we write a letter of completion.'), lang('certificate, diploma, completion')),
                            $faq(4, lang('What does the membership include?'), lang('The members\' library of workshops and recordings, the monthly live sessions and a discount code for every course. Courses themselves are bought on their own.'), lang('membership, library, live, discount')),
                            $faq(5, lang('How does the project work?'), lang('Send it from the course\'s classroom. The instructor writes feedback under it; you can send a new version until it passes.'), lang('assignment, project, homework, feedback, grade')),
                            $faq(6, lang('Can my company pay for a team?'), lang('Yes. Send an enrolment request with the names; we enrol everyone and send one invoice.'), lang('company, team, invoice, corporate, group')),
                            $faq(7, lang('Can I get my money back?'), lang('Yes, within 14 days of buying, if you have watched less than a third of the course.'), lang('refund, money back, return')),
                        ), array('id' => 'co-faq')),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
        $shared('enrol_band'),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────
$public_widgets = array(
    // Google reviews of the school, beside the sample
    // testimonials of the home page. The widget draws nothing until a
    // Places key and the Place ID are saved, and this layout shows only
    // while Google returns reviews. Google asks for each review's author and
    // its link, and for "Google Maps" beside what is shown without a map.
    'google_reviews' => array(
        'page'   => 'home',
        'slug'   => lang('google-reviews'),
        'config' => array('regionType' => 'google_reviews'),
        'tree'   => $widget_root(array(
            $el('div', 'mt-5 pt-4 border-top', lang('Google Reviews'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Rating'), array(
                    $heading('h3', lang('Reviews on Google'), 'h5 fw-bold mb-0 me-2'),
                    $icon('bi-star-fill', 'text-warning'),
                    $span(lang('4.8'), 'fw-bold', lang('Average Rating'), $bind('__place_rating')),
                    $el('span', 'small text-body-secondary', lang('Number of ratings'), array(
                        $span('120', '', lang('Number'), $bind('__place_review_count')),
                        $span(' ' . lang('ratings'), '', lang('Label')),
                    )),
                )),
                $row(array($loop(array($col(array(
                    $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Review'), array(
                        $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Author'), array(
                            $image('', '', 'rounded-circle flex-shrink-0 object-fit-cover', array('width' => '40', 'height' => '40',
                                '_attrs' => array($attr('referrerpolicy', 'no-referrer')),
                                '_bindings' => array('src' => '__review_author_photo', 'eo_visible_if' => 'review_has_photo'))),
                            $el('div', 'small', lang('Name and time'), array(
                                $link(lang('Author name'), '#', 'd-block fw-semibold text-body text-decoration-none', array(),
                                    array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__review_author_url', 'text' => '__review_author_name'))),
                                $span(lang('2 weeks ago'), 'text-body-secondary', lang('When'), $bind('__review_relative_time')),
                            )),
                        )),
                        $el('p', 'small mb-2', lang('Review rating'), array(
                            $icon('bi-star-fill', 'text-warning'),
                            $span('5', 'ms-1', lang('Rating'), $bind('__review_rating')),
                            $span('/5', '', lang('Out of')),
                        )),
                        $el('p', 'mb-3', lang('Review text'), array(), array('text' => lang('Review text'),
                            '_bindings' => array('text' => '__review_text', 'eo_visible_if' => 'review_has_text'))),
                        $link(lang('Read on Google Maps'), '#', 'small mt-auto', array(),
                            array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__review_url'))),
                    )),
                ), '6', '4')))), '4'),
                $el('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mt-4 small', lang('Attribution'), array(
                    $link(lang('See all reviews on Google Maps'), '#', '', array(),
                        array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__place_maps_url'))),
                    $span('Google Maps', 'text-body-secondary notranslate', lang('Google attribution'),
                        array('_attrs' => array($attr('translate', 'no')), '_bindings' => array('text' => '__google_attribution'))),
                )),
            ), $show_when('has_reviews')),
        )),
    ),
);

return array('pages' => $public_pages, 'widgets' => $public_widgets);
