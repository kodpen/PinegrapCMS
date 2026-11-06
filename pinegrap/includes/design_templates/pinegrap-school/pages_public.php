<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Private School", its public pages: home, the programmes
 * and one page per programme, a day at school, the approach, the campus,
 * the staff, about, fees and the questions page. Included after parts.php
 * in the same scope (its builders and the school's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($school, $programmes, $classes, $day,
 * $approach, $facilities, $staff, $accreditations, $why_us, $figures,
 * $testimonials), so a change made there reaches every page here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}


// A picture in a framed ratio, loaded lazily unless it is the first thing
// a page shows.
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
        $para($eyebrow, 'psh-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'psh-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the approach, why families
// choose the school, the campus and the accreditations.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'psh-display h5 mb-2'),
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
                    $heading('h2', $title, 'psh-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
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

// The day hour by hour: the time in a badge, what happens and a line.
$day_list = function ($items) use ($el, $heading, $para, $span) {
    $children = array();
    foreach ($items as $s) {
        $children[] = $el('li', 'd-flex gap-3 mb-4', lang('Step'), array(
            $span($s['time'], 'badge rounded-pill text-bg-primary fs-6 align-self-start', lang('Time')),
            $el('div', '', lang('Text'), array(
                $heading('h3', $s['title'], 'h6 fw-bold mb-1'),
                $para($s['text'], 'text-body-secondary mb-0'),
            )),
        ));
    }
    return $el('ol', 'list-unstyled mb-0', lang('A Day at School'), $children);
};

// A class group: its name, its programme and its ages.
$class_tile = function ($c) use ($el, $heading, $para, $col, $programmes) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $c['name'], array(
            $para($programmes[$c['programme']]['title'], 'psh-eyebrow small text-uppercase fw-semibold text-primary mb-1'),
            $heading('h3', $c['name'], 'psh-display h5 mb-1'),
            $para($c['ages'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// The cards and tiles of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$approach_tiles = array();
foreach ($approach as $a) $approach_tiles[] = $feature_tile($a['icon'], $a['title'], $a['text']);
$facility_tiles = array();
foreach ($facilities as $f) $facility_tiles[] = $feature_tile($f['icon'], $f['title'], $f['text'], '4');
$accreditation_tiles = array();
foreach ($accreditations as $a) $accreditation_tiles[] = $feature_tile($a['icon'], $a['title'], $a['text']);
$programme_cols = array();
foreach (array_keys($programmes) as $key) $programme_cols[] = $programme_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$staff_cols = array();
foreach (array_keys($staff) as $n) $staff_cols[] = $staff_card($n);
$class_cols = array();
foreach ($classes as $c) $class_cols[] = $class_tile($c);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A private nursery, kindergarten and primary school with small groups, a big garden and teachers who know every child.'),
    'tree'             => $page(array(
        // The way in: the school in a sentence, the two ways on and a
        // picture of the garden.
        $el('section', 'psh-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Nursery, kindergarten and primary school'), 'psh-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('A school where every child is known'), 'psh-display display-3 mb-3'),
                        $para($school['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Apply Now'), '{{page:apply}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Book a School Visit'), '{{page:visit}}', 'btn btn-outline-secondary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('The school garden with a vegetable patch and a wooden playhouse'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $figures_band(),

        $el('section', 'py-5', lang('Programmes'), array(
            $container(array(
                $section_header(lang('From two to ten'), lang('Our programmes'), lang('All Programmes'), '{{page:programmes}}'),
                $row($programme_cols, '4'),
            )),
        ), $searchable('sh-home-programmes', lang('nursery, preschool, kindergarten, primary, after-school, summer camp'))),

        $el('section', 'py-5 bg-body-tertiary', lang('A Day at School'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Hour by hour'), 'psh-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('A day in kindergarten'), 'psh-display h2 mb-4'),
                        $day_list(array_slice($day, 0, 5)),
                        $link(lang('See the whole day'), '{{page:day}}', 'btn btn-outline-secondary rounded-pill px-4 mt-2'),
                    ), '', '6'),
                    $col(array($framed($photo('home-day', 1200, 900), lang('Paint pots and paper on a classroom table'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('sh-home-day', lang('daily routine, timetable, schedule, lunch, nap'))),

        $el('section', 'py-5', lang('Why Families Choose Us'), array(
            $container(array(
                $section_header(lang('Our promise'), lang('Why families choose us'), lang('About Us'), '{{page:about}}'),
                $row($why_tiles, '4'),
            )),
        ), $searchable('sh-why-us', lang('small classes, safe, teachers, long day, reports'))),

        // The featured events, read from the Events collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Up'), array(
            $container(array(
                $section_header(lang('Events'), lang('Coming up at school'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // What parents say: sample words for the school to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our families'), lang('What parents say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from school'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Programmes ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'programmes',
    'name'             => lang('programmes'),
    'folder'           => 'public',
    'title'            => lang('Programmes'),
    'meta_description' => lang('Nursery, kindergarten, primary school, after-school clubs and summer school for children from two to ten.'),
    'tree'             => $page(array(
        $page_header(lang('Programmes'), lang('Five programmes, one school: a child can start in nursery and leave us ready for secondary school.')),
        $el('section', 'py-5', lang('Programmes'), array(
            $container(array(
                $row($programme_cols, '4'),
            )),
        ), $searchable('sh-programmes', lang('nursery, preschool, kindergarten, primary, after-school, summer camp'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Classes'), array(
            $container(array(
                $section_header(lang('Class groups'), lang('Our classes'), lang('A Day at School'), '{{page:day}}'),
                $row($class_cols, '4'),
            )),
        ), $searchable('sh-classes', lang('class, group, year, age group'))),
        $ask_band(lang('Not sure which programme fits?'), lang('Tell us about your child and our admissions team calls you back.')),
    )),
);

// One page per programme: what it is, its ages, groups and hours, what a
// child does there, the fees line, its events and the application with
// the programme chosen. Its address is a translated slug, so a Turkish
// site reads Turkish addresses.
$programme_slugs = array(
    'nursery'      => lang('nursery'),
    'kindergarten' => lang('kindergarten'),
    'primary'      => lang('primary-school'),
    'clubs'        => lang('after-school-clubs'),
    'summer'       => lang('summer-school'),
);
foreach ($programmes as $key => $p) {
    $chosen = $query(array('programme' => $p['title']));
    $programme_classes = array();
    foreach ($classes as $c) {
        if ($c['programme'] === $key) $programme_classes[] = $class_tile($c);
    }
    $sections = array(
        $page_header($p['title'], $p['summary'], array($programme_facts($key, 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mt-3 mb-0'))),
        $el('section', 'py-5', $p['title'], array(
            $container(array(
                $row(array(
                    $col(array(
                        $icon_tile($p['icon'], 'mb-3'),
                        $para($p['text'], 'lead mb-4'),
                        $heading('h2', lang('What your child does here'), 'psh-display h4 mb-3'),
                        $check_list($p['points'], 'list-unstyled mb-4'),
                        $para($fee_text, 'small text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Apply Now'), '{{page:apply}}?' . $chosen, 'btn btn-primary rounded-pill px-4'),
                            $link(lang('See the events'), '{{page:events}}?' . $chosen, 'btn btn-outline-secondary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array($framed($photo('programme-' . $key, 1200, 900), $p['title'])), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('sh-programme-' . $key)),
    );
    if ($programme_classes) {
        $sections[] = $el('section', 'py-5 bg-body-tertiary', lang('Our Classes'), array(
            $container(array(
                $section_header(lang('Class groups'), lang('Our classes')),
                $row($programme_classes, '4'),
            )),
        ));
    }
    $public_pages[] = array(
        'key'              => 'programme_' . $key,
        'name'             => $programme_slugs[$key],
        'folder'           => 'public',
        'title'            => $p['title'],
        'meta_description' => $p['summary'],
        'tree'             => $page($sections),
    );
}

// ── A day at school ─────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'day',
    'name'             => lang('a-day-at-school'),
    'folder'           => 'public',
    'title'            => lang('A Day at School'),
    'meta_description' => lang('A day in our kindergarten hour by hour: free play, projects, the garden, lunch from our kitchen, rest and music.'),
    'tree'             => $page(array(
        $page_header(lang('A Day at School'), lang('What a day in kindergarten looks like, from the goodbye at the door to home time.')),
        $el('section', 'py-5', lang('A Day at School'), array(
            $container(array(
                $row(array(
                    $col(array($day_list($day)), '', '6'),
                    $col(array(
                        $framed($photo('day-garden', 1200, 900), lang('Rain boots in a row by the garden door')),
                        $framed($photo('day-lunch', 1200, 900), lang('A table laid for lunch in the dining room'), '4x3'),
                    ), '', '6', '', '12', 'd-flex flex-column gap-4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('sh-day', lang('daily routine, timetable, schedule, lunch, nap, pick-up, drop-off'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Classes'), array(
            $container(array(
                $section_header(lang('Class groups'), lang('Our classes')),
                $row($class_cols, '4'),
            )),
        )),
        $ask_band(lang('A question about the day?'), lang('Meals, naps, allergies or pick-up times: write to us and the office answers.')),
    )),
);

// ── Approach ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'approach',
    'name'             => lang('our-approach'),
    'folder'           => 'public',
    'title'            => lang('Our Approach'),
    'meta_description' => lang('How we teach: small groups, learning outdoors, projects that start from children\'s questions and families as partners.'),
    'tree'             => $page(array(
        $page_header(lang('Our Approach'), lang('What we believe about how children learn, and how it shapes every day at school.')),
        $el('section', 'py-5', lang('Our Approach'), array(
            $container(array(
                $row($approach_tiles, '4'),
            )),
        ), $searchable('sh-approach', lang('philosophy, teaching method, play-based, outdoor learning, project-based'))),
        $story_band('sh-families', lang('Families as partners'), lang('You know your child best'),
            array(
                lang('A word at the door every morning, a note home every day for the youngest and a meeting with the teacher every term.'),
                lang('Parents read with the classes, help on trips and run the spring fair with us.'),
            ),
            $photo('approach-families', 1200, 900), lang('Children\'s drawings pinned on a classroom wall'), true, array(),
            lang('parents, meetings, reports, communication'), 'py-5 bg-body-tertiary'),
        $ask_band(lang('Want to see it for yourself?'), lang('Book a visit on an ordinary school day, or write to us with your questions.')),
    )),
);

// ── Campus ──────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'campus',
    'name'             => lang('campus'),
    'folder'           => 'public',
    'title'            => lang('Our Campus'),
    'meta_description' => lang('Our garden, art studio, library, science lab, music room and kitchen: the places where children spend their day.'),
    'tree'             => $page(array(
        $page_header(lang('Our Campus'), lang('Bright classrooms, a big garden and the rooms where children paint, read, sing and experiment.')),
        $el('section', 'py-5', lang('Our Campus'), array(
            $container(array(
                $row($facility_tiles, '4'),
            )),
        ), $searchable('sh-campus', lang('facilities, garden, playground, library, lab, kitchen, building'))),
        $story_band('sh-safe', lang('Safety'), lang('A safe place to grow'),
            array($why_us[3]['text'], lang('Children are collected only by the adults their family names, and every visitor signs in at the office.')),
            $photo('campus-entrance', 1200, 900), lang('The school entrance with a gate and a covered porch'), false,
            array($link(lang('Book a School Visit'), '{{page:visit}}', 'btn btn-primary rounded-pill px-4')),
            lang('security, safeguarding, gate, pick-up, nurse'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Staff ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'staff_team',
    'name'             => lang('our-teachers'),
    'folder'           => 'public',
    'title'            => lang('Our Teachers'),
    'meta_description' => lang('The teachers and staff who look after your child: early years, primary, music and our school nurse.'),
    'tree'             => $page(array(
        $page_header(lang('Our Teachers'), lang('The people your child spends the day with, and the team behind them.')),
        $el('section', 'py-5', lang('Our Teachers'), array(
            $container(array(
                $row($staff_cols, '4'),
            )),
        ), $searchable('sh-staff', lang('teachers, staff, head teacher, principal, nurse'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Accreditations'), array(
            $container(array(
                $section_header(lang('Trusted'), lang('Checked, trained and licensed')),
                $row($accreditation_tiles, '4'),
            )),
        ), $searchable('sh-staff-trust', lang('licence, first aid, safeguarding, background check'))),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A private school started by two teachers in a house with a garden: small groups and teachers who know every child.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $school['tagline'] . '.'),
        $story_band('sh-our-story', lang('Our story'), lang('A house, a garden and eight children'),
            array($school['story'], $school['licence'] . '.'),
            $photo('about-house', 1200, 900), lang('The old house at the heart of the school, with its garden'), false, array(),
            lang('history, founders, who we are')),
        $figures_band(),
        $el('section', 'py-5', lang('Why Families Choose Us'), array(
            $container(array(
                $section_header(lang('Our promise'), lang('Why families choose us')),
                $row($why_tiles, '4'),
            )),
        ), $searchable('sh-about-why', lang('small classes, safe, teachers, long day, reports'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Teachers'), array(
            $container(array(
                $section_header(lang('Our people'), lang('Who leads the school'), lang('Our Teachers'), '{{page:staff_team}}'),
                $row(array_slice($staff_cols, 0, 3), '4'),
            )),
        )),
        $ask_band(lang('Would you like to meet us?'), lang('Come to an open morning or write to us to arrange a visit.')),
    )),
);

// ── Fees ────────────────────────────────────────────────────────────────
// No figure: fees change every school year, so the page says what the fee
// covers and how to ask for this year's.

$public_pages[] = array(
    'key'              => 'fees',
    'name'             => lang('fees'),
    'folder'           => 'public',
    'title'            => lang('Fees'),
    'meta_description' => lang('What our fees cover, how they are paid and how to ask for this year\'s fees.'),
    'tree'             => $page(array(
        $page_header(lang('Fees'), $fee_text),
        $el('section', 'py-5', lang('Fees'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('What the fee covers'), 'psh-display h3 mb-3'),
                        $check_list(array(
                            lang('Teaching, materials and books'),
                            lang('Lunch and snacks from our own kitchen'),
                            lang('Trips during the school day'),
                            lang('The school nurse and accident insurance'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('How it is paid'), 'psh-display h3 mb-3'),
                        $check_list(array(
                            lang('A registration fee when a place is accepted'),
                            lang('The year\'s fee in one payment or in monthly instalments'),
                            lang('A discount for brothers and sisters'),
                            lang('Clubs and summer school booked and paid separately'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
                $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                    $link(lang('Apply Now'), '{{page:apply}}', 'btn btn-primary rounded-pill px-4'),
                    $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-outline-secondary rounded-pill px-4'),
                )),
            )),
        ), $searchable('sh-fees', lang('tuition, price, cost, payment, instalment, discount, sibling'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: admissions and school life. Each question is a search
// section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'psh-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about admissions, visits, the first weeks, meals, naps, pick-up times and how we keep families informed.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Admissions and school life. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('sh-faq-admissions', lang('Admissions'), array(
                            array(lang('When can my child start?'), lang('Most children start at the beginning of the school year, but we take children during the year whenever a place is free.'), lang('start date, enrolment, intake, join')),
                            array(lang('How do I apply for a place?'), lang('Send the application form. It opens an account for you, where you follow the application and can write to us.'), lang('apply, enrol, register, admission')),
                            array(lang('Can we visit before we apply?'), lang('Yes, and we recommend it: book a visit on an ordinary school day or come to an open morning.'), lang('visit, tour, open day, see the school')),
                            array(lang('What happens if there is no place?'), lang('Your child goes on the waiting list for the programme, and we call you as soon as a place is free.'), lang('waiting list, full, no place')),
                        )),
                        $faq_group('sh-faq-life', lang('School life'), array(
                            array(lang('How do you settle a new child?'), lang('A key teacher plans the first weeks with you: short days at first, a parent nearby, then a little longer each day.'), lang('settling in, first days, separation, crying')),
                            array(lang('What do the children eat?'), lang('A warm lunch and two snacks cooked in our own kitchen. Allergies and diets are planned with the nurse and the family.'), lang('food, lunch, menu, allergy, diet')),
                            array(lang('Do the children nap?'), lang('The youngest sleep after lunch in a quiet room; the older children rest with books and stories.'), lang('sleep, nap, rest')),
                            array(lang('How will I know how my child is doing?'), lang('A note every day for the youngest, a meeting every term and a written report at the end of each term.'), lang('report, progress, meeting, communication')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured events (home_events) come with the
// events, its news (home_posts) and the Google reviews (google_reviews)
// with the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
