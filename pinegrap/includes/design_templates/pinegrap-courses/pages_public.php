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

// A course as a card of the lists: its picture, subject and level, title,
// summary, instructor, length and price, linked to its overview page.
$course_card = function ($key) use ($courses, $instructors, $course_lessons, $el, $col, $image, $heading, $para, $span, $icon, $photo, $attr, $subject_badge, $level_badge) {
    $c = $courses[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pco-lift', $c['title'], array(
            $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('course-' . $key, 800, 450), $c['title'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap gap-2 mb-2', lang('Badges'), array($subject_badge($c['subject']), $level_badge($c['level']))),
                $el('h3', 'h5 fw-bold position-static', lang('Course Title'), array(
                    $el('a', 'stretched-link link-body-emphasis text-decoration-none', lang('Link'), array(), array('text' => $c['title'], 'href' => '{{page:course_' . $key . '}}')),
                )),
                $para($c['summary'], 'small text-body-secondary'),
                $para($instructors[$c['instructor']]['name'], 'small fw-semibold mb-3'),
                $el('div', 'd-flex flex-wrap align-items-center gap-3 small text-body-secondary mt-auto', lang('Course Details'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Lessons'), array($icon('bi-collection-play'), $span(sprintf(lang('%d lessons'), count($course_lessons($key))), '', lang('Text')))),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Length'), array($icon('bi-clock'), $span($c['length'], '', lang('Text')))),
                    $span($c['price'], 'ms-auto fs-5 fw-bold text-body', lang('Price')),
                )),
            )),
        )),
    ), '6', '4');
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

// A subject as a card of the home page, linked to its section of the
// courses page.
$subject_cards = array();
foreach ($subjects as $key => $s) {
    $subject_cards[] = $col(array(
        $el('a', 'card h-100 border-0 bg-body-tertiary rounded-4 text-decoration-none link-body-emphasis', $s['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3'),
                $heading('h3', $s['title'], 'h5 fw-bold mb-2'),
                $para($s['text'], 'small text-body-secondary mb-0'),
            )),
        ), array('href' => '{{page:courses}}#subject-' . $key)),
    ), '', '4');
}

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Practical online courses in web development, data and design. Watch the first lesson of any course free, then learn at your own pace.'),
    'tree'             => $page(array(
        // The school: what it teaches, the way to start and a picture.
        $el('section', 'py-5 overflow-hidden pco-hero', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('p', 'd-flex flex-wrap align-items-center gap-2 small fw-semibold text-primary mb-3', lang('Tagline'), array(
                            $icon('bi-play-circle'),
                            $span(lang('Learn online, at your own pace'), '', lang('Text')),
                        )),
                        $heading('h1', lang('Learn a skill you will use on Monday'), 'display-4 fw-bold mb-3'),
                        $para(lang('Short video lessons, a project in every course and instructors who answer your questions. Start with a free lesson; keep the course once you buy it.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Browse courses'), '{{page:courses}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Watch a free lesson'), '{{page:lesson_web_1}}', 'btn btn-outline-secondary btn-lg px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden shadow-sm', lang('Picture Frame'), array(
                            $image($photo('hero', 1200, 900), lang('A student taking notes beside a laptop showing a video lesson'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The school in figures.
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        // The courses.
        $el('section', 'py-5', lang('Courses'), array(
            $container(array(
                $section_header(lang('Start here'), lang('Our courses'), lang('All courses'), '{{page:courses}}'),
                $row($all_course_cards, '4'),
            )),
        )),

        // The subjects.
        $el('section', 'py-5 bg-body-tertiary', lang('Subjects'), array(
            $container(array(
                $section_header(lang('What you can learn'), lang('Subjects')),
                $row($subject_cards, '4'),
            )),
        )),

        // How learning works.
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('How it works'), 'small text-uppercase fw-semibold text-primary mb-1 pco-eyebrow'),
                        $heading('h2', lang('From the first lesson to your own project'), 'h3 fw-bold mb-3'),
                        $para(lang('Every course is short videos, a few pages to read and one project the instructor looks at with you.'), 'text-body-secondary mb-4'),
                        $link(lang('Questions? Read the FAQ'), '{{page:faq}}', 'btn btn-outline-primary px-4'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Watch the free lesson'), lang('The first lesson of every course is open to everyone, no account needed.')),
                            $step(2, lang('Enrol'), lang('Buy the course or ask us to enrol you; its classroom opens in your account.')),
                            $step(3, lang('Learn and ask'), lang('Watch the lessons in any order and ask under each one; the instructor answers.')),
                            $step(4, lang('Send your project'), lang('Hand in the course assignment and get written feedback.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The instructors.
        $el('section', 'py-5 bg-body-tertiary', lang('Instructors'), array(
            $container(array(
                $section_header(lang('Who teaches'), lang('Meet the instructors'), lang('All instructors'), '{{page:instructors}}'),
                $row($instructor_cards, '4'),
            )),
        )),

        // What students say: three sample cards, and the reviews from
        // Google beside them once the place is set.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our students'), lang('What students say')),
                $row(array(
                    $testimonial(lang('“I had tried to learn HTML three times. This time I published my site in the second week.”'), lang('Deniz K.'), lang('Build Your First Website')),
                    $testimonial(lang('“The pivot table lesson alone saved me an hour every Monday.”'), lang('Aylin M.'), lang('Data Analysis with Spreadsheets')),
                    $testimonial(lang('“Maya\'s feedback on my wireframes was worth the whole course.”'), lang('Tom R.'), lang('UX Design Fundamentals')),
                ), '4'),
                $widget('google_reviews'),
            )),
        )),

        // The newest posts.
        $el('section', 'py-5 border-top', lang('From the Blog'), array(
            $container(array(
                $section_header(lang('Blog'), lang('Latest posts'), lang('All posts'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('enrol_band'),
    )),
);

// ── Courses ─────────────────────────────────────────────────────────────
// A section for each subject (#subject-<key>) with its courses.
$subject_sections = array();
foreach ($subjects as $skey => $s) {
    $cards = array();
    foreach ($courses as $ckey => $c) {
        if ($c['subject'] === $skey) $cards[] = $course_card($ckey);
    }
    $subject_sections[] = $el('section', 'py-4', $s['title'], array(
        $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Subject'), array(
            $icon_tile($s['icon']),
            $el('div', '', lang('Text'), array(
                $heading('h2', $s['title'], 'h4 fw-bold mb-1'),
                $para($s['text'], 'text-body-secondary mb-0'),
            )),
        )),
        $row($cards, '4'),
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
// What it teaches, its curriculum by module (the first lesson linked as
// the free preview), the instructor, the assignment, and the box with the
// ways in.
foreach ($courses as $key => $c) {
    $teacher = $instructors[$c['instructor']];
    $lessons = $course_lessons($key);
    $modules = array();
    foreach ($c['modules'] as $mn => $m) {
        $items = array();
        foreach ($m['lessons'] as $l) {
            $is_preview = ($l['key'] === $lessons[0]['key']);
            $title = $is_preview
                ? $link($l['title'], '{{page:lesson_' . $l['key'] . '}}', 'fw-semibold link-body-emphasis')
                : $span($l['title'], 'fw-semibold', lang('Lesson Title'));
            $meta = array($span(sprintf(lang('%d min'), $l['minutes']), 'small text-body-secondary text-nowrap', lang('Length')));
            if ($is_preview) array_unshift($meta, $span(lang('Free preview'), 'badge rounded-pill text-bg-success', lang('Preview')));
            $items[] = $el('li', 'list-group-item d-flex align-items-start gap-3 px-0 bg-transparent', $l['title'], array(
                $icon($is_preview ? 'bi-play-circle' : 'bi-lock', 'text-primary mt-1'),
                $el('div', 'flex-grow-1', lang('Lesson'), array($title, $para($l['text'], 'small text-body-secondary mb-0'))),
                $el('div', 'd-flex flex-column align-items-end gap-1', lang('Lesson Details'), $meta),
            ));
        }
        $modules[] = $el('div', 'mb-4', $m['title'], array(
            $heading('h3', sprintf(lang('Module %d: %s'), $mn + 1, $m['title']), 'h6 text-uppercase small fw-bold text-body-secondary mb-2'),
            $el('ul', 'list-group list-group-flush', lang('Lessons'), $items),
        ));
    }

    $public_pages[] = array(
        'key'              => 'course_' . $key,
        'name'             => $c['slug'],
        'folder'           => 'public',
        'title'            => $c['title'],
        'meta_description' => $c['summary'],
        'tree'             => $page(array(
            $page_header($c['title'], $c['summary'], array(
                $el('div', 'd-flex flex-wrap align-items-center gap-3 small mt-3', lang('Course Details'), array(
                    $subject_badge($c['subject']),
                    $level_badge($c['level']),
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
                                $heading('h2', lang('Curriculum'), 'h4 fw-bold mb-3'),
                                $el('div', '', lang('Modules'), $modules),
                            ), array('id' => 'curriculum')),
                            $el('div', 'p-4 rounded-4 bg-body-tertiary mb-5', lang('Assignment'), array(
                                $heading('h2', lang('Your project'), 'h5 fw-bold mb-2'),
                                $para($c['assignment'], 'mb-0'),
                            )),
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
// Each course on its own, the membership, and training for teams.
$price_rows = array();
foreach ($courses as $key => $c) {
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
                    $col(array($plan_card(lang('One course'), lang('From $79'), lang('Pay once for a course and keep it, with every lesson, its project and the instructor\'s feedback.'), array(
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
