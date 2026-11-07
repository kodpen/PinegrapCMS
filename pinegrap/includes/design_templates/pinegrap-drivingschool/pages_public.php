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
$licence_cols = array();
foreach (array_keys($licences) as $key) $licence_cols[] = $licence_card($key);
$instructor_cols = array();
foreach (array_keys($instructors) as $n) $instructor_cols[] = $instructor_card($n);
$topic_tiles = array();
foreach ($theory_topics as $t) $topic_tiles[] = $feature_tile($t['icon'], $t['title'], $t['text'], '4');
$test_day_items = array();
foreach ($test_day as $n => $s) $test_day_items[] = $step($n + 1, $s['title'], $s['text']);
$branch_tiles = array();
foreach ($branches as $b) {
    $branch_tiles[] = $feature_tile('bi-geo-alt', $b['title'], $b['text'], '6', array(
        $para($b['address'], 'small fw-semibold mt-3 mb-0'),
    ));
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A driving school for cars, automatics, motorcycles, light trucks and trailers: courses with clear prices and patient instructors.'),
    'tree'             => $page(array(
        // The way in, on a dark band: the school in a sentence, the two
        // ways on, and a lesson in the car.
        $el('section', 'pdr-hero py-5 overflow-hidden bg-body text-body', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Driving lessons and courses'), 'pdr-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Learn to drive with confidence'), 'pdr-display display-3 mb-3'),
                        $para($school['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Enrol Now'), '{{page:enrol}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Courses and Prices'), '{{page:courses}}', 'btn btn-outline-light btn-lg px-4'),
                        )),
                    ), '', '6'),
                    $col(array($framed($photo('home-hero', 1200, 900), lang('A learner driver and an instructor in a dual-control car'), '4x3', false)), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array_merge($searchable('drv-welcome', lang('driving school, driving lessons, learner driver, driving instructor')),
            array('_attrs' => array($attr('data-bs-theme', 'dark'))))),

        $figures_band(),

        $el('section', 'py-5', lang('Licences'), array(
            $container(array(
                $section_header(lang('What you can learn'), lang('Choose your licence'), lang('All Licences'), '{{page:licences}}'),
                $row($licence_cols, '4'),
            )),
        ), $searchable('drv-home-licences', lang('car licence, automatic, motorcycle, truck, trailer, licence class'))),

        // The featured courses, read from the Courses collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Featured Courses'), array(
            $container(array(
                $section_header(lang('Courses and prices'), lang('Our most chosen courses'), lang('All Courses'), '{{page:courses}}'),
                $widget('home_courses'),
            )),
        )),

        $el('section', 'py-5', lang('Why Learn with Us'), array(
            $container(array(
                $section_header(lang('The way we teach'), lang('Why learn with us'), lang('About Us'), '{{page:about}}'),
                $row($why_tiles, '4'),
            )),
        ), $searchable('drv-why-us', lang('same instructor, dual control, pick-up, price, cheap driving lessons'))),

        $story_band('drv-home-theory', lang('Theory test'), lang('Pass the theory first'),
            array(lang('Our theory classes cover every topic of the test in short evening sessions, and the practice app lets you try the questions at home.')),
            $photo('home-theory', 1200, 900), lang('Learners in the theory classroom'), true,
            array($link(lang('About the Theory Test'), '{{page:theory}}', 'btn btn-outline-secondary px-4')),
            lang('theory test, highway code, road signs, practice questions'), 'py-5 bg-body-tertiary'),

        // The featured pass stories, read from the Pass Stories collection,
        // and beside them the school's own Google reviews, which show once
        // Google Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Pass Stories'), array(
            $container(array(
                $section_header(lang('Our learners'), lang('They passed with us'), lang('All Pass Stories'), '{{page:stories}}'),
                $widget('home_stories'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the News'), array(
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
        $el('section', 'py-5', lang('Licences'), array(
            $container(array(
                $row($licence_cols, '4'),
            )),
        ), $searchable('drv-licences', lang('licence class, B, A2, C1, BE, car, motorcycle, truck, trailer'))),
        $ask_band(lang('Not sure which licence you need?'), lang('Tell us what you want to drive and we tell you the class, the minimum age and the lessons it takes.')),
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
                            $check_list($l['points'], 'list-unstyled mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('See the courses'), '{{page:courses}}?' . $query(array('licence' => $l['title'])), 'btn btn-primary px-4'),
                                $link(lang('Book a Lesson'), '{{page:lesson_request}}?' . $query(array('licence' => $l['title'])), 'btn btn-outline-secondary px-4'),
                            )),
                        ), '', '6'),
                        $col(array($framed($photo('licence-' . $key, 1200, 900), $l['title'])), '', '6'),
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
        $el('section', 'py-5', lang('Topics'), array(
            $container(array(
                $section_header(lang('What the test covers'), lang('Six topics to know')),
                $row($topic_tiles, '4'),
            )),
        ), $searchable('drv-theory-topics', lang('road signs, highway code, hazard perception, first aid, questions'))),
        $story_band('drv-theory-classes', lang('Theory classes'), lang('Learn it in the classroom'),
            array(lang('Our theory teacher runs short evening classes at the main branch, one topic at a time, with questions like the ones in the test.'),
                lang('Every course learner also gets the practice app, so the questions can be tried at home until they feel easy.')),
            $photo('theory-class', 1200, 900), lang('A theory class going through road signs'), false,
            array($link(lang('Enrol Now'), '{{page:enrol}}', 'btn btn-primary px-4')),
            lang('theory classes, evening classes, practice app, mock questions'), 'py-5 bg-body-tertiary'),
        $el('section', 'py-5', lang('Tips'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How to prepare'), 'pdr-display h3 mb-3'),
                        $check_list(array(
                            lang('Study one topic a day instead of everything the night before'),
                            lang('Try the practice questions until you pass them three times in a row'),
                            lang('Read each question to the end before you choose an answer'),
                            lang('Watch the road as a passenger and name the signs you see'),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        ), $searchable('drv-theory-tips', lang('tips, prepare, revise, study, pass first time'))),
        $ask_band(lang('A question about the theory test?'), lang('Write to us and our theory teacher answers, usually the same day.')),
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
        $el('section', 'py-5', lang('Test Day'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How the practical test goes'), 'pdr-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-4', lang('Steps'), $test_day_items),
                        $link(lang('Book a Lesson'), '{{page:lesson_request}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('practical-test', 1200, 900), lang('A learner driver at a junction with the examiner beside them'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('drv-test-day', lang('driving test, test day, examiner, test route, result'))),
        $el('section', 'py-5 bg-body-tertiary', lang('What the Examiner Checks'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('What the examiner checks'), 'pdr-display h3 mb-3'),
                        $check_list(array(
                            lang('Mirrors and signals before every change of speed or direction'),
                            lang('Speed that suits the road, the limit and the weather'),
                            lang('Safe junctions and roundabouts'),
                            lang('One manoeuvre done slowly and under control'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('How we get you ready'), 'pdr-display h3 mb-3'),
                        $check_list(array(
                            lang('A mock test with the same timing as the real one'),
                            lang('Lessons on the roads around the test centre'),
                            lang('The car you learned in, for the test itself'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('drv-test-checks', lang('faults, mirrors, manoeuvre, mock test, test centre'))),
        $ask_band(lang('Nervous about the test?'), lang('Write to us: a refresher lesson or a mock test often makes the difference.')),
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
