<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Gym", its public pages: home, the group classes, the
 * week's timetable, the membership plans, the gym floor, about, the
 * questions and getting here. Included after parts.php in the same scope
 * (its builders and the gym's data); returns array('pages' => ...,
 * 'widgets' => ...).
 *
 * Every figure comes from parts.php ($gym, $days, $opening_hours,
 * $focuses, $classes, $trainers, $timetable, $plans, $facilities,
 * $figures, $testimonials), so a change made there reaches every page
 * here. No page here links a shop page: the template stands on a site
 * without the shop, and a plan is joined through the join request.
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
        $para($eyebrow, 'pgy-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pgy-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the facilities, the values and
// the ways to the gym.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pgy-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The gym in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pgy-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The opening hours as a short list, for the location page and the
// timetable.
$hours_list = function ($class = 'list-unstyled mb-0') use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'pgy-price fw-semibold', lang('Hours')),
        ));
    }
    return $el('ul', $class, lang('Opening Hours'), $items);
};

// The cards of the shared data, in their order.
$facility_tiles = array();
foreach ($facilities as $f) $facility_tiles[] = $feature_tile($f['icon'], $f['title'], $f['text']);
$plan_cards_row = array();
foreach (array_keys($plans) as $key) $plan_cards_row[] = $plan_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$class_cards_all = array();
foreach (array_keys($classes) as $key) $class_cards_all[] = $class_card($key);
// One tile per focus, linking the trainers who coach it.
$focus_tiles = array();
foreach ($focuses as $f) {
    $focus_tiles[] = $feature_tile($f['icon'], $f['title'], $f['text'], '4', array(
        $link(lang('Meet the coaches'), '{{page:trainers}}?' . $query(array('focus' => $f['title'])), 'd-inline-block small fw-semibold link-primary mt-3'),
    ));
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A neighbourhood gym with a weights floor, three studios, group classes every day, personal training and a free trial pass.'),
    'tree'             => $page(array(
        // The way in: the gym in a sentence, the two ways on and a photo
        // of a class.
        $el('section', 'pgy-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($gym['region'], 'pgy-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Stronger every week'), 'pgy-display display-3 mb-3'),
                        $para($gym['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Get a Free Pass'), '{{page:join}}?' . $query(array('plan' => lang('Free trial pass'))), 'btn btn-primary btn-lg px-4'),
                            $link(lang('See the timetable'), '{{page:timetable}}', 'btn btn-outline-primary btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A group class lifting kettlebells together in the main studio'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Group Classes'), array(
            $container(array(
                $section_header(lang('Train together'), lang('Classes for every goal'), lang('All classes'), '{{page:classes}}'),
                $row($class_cards_all, '4'),
            )),
        )),

        // The trainers, read from the Trainers collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Our Trainers'), array(
            $container(array(
                $section_header(lang('The team'), lang('Coaches who know your name'), lang('All trainers'), '{{page:trainers}}'),
                $widget('home_trainers'),
            )),
        )),

        $el('section', 'py-5', lang('Membership Plans'), array(
            $container(array(
                $section_header(lang('Membership'), lang('A plan for how you train'), lang('Compare the plans'), '{{page:plans}}'),
                $row($plan_cards_row, '4'),
            )),
        )),

        $story_band('gy-home-pt', lang('Personal Training'), lang('One coach, one plan, your goal'),
            array(lang('An hour with a coach who watches every rep and writes the next step for you. Pick a free time online and come as you are.')),
            $photo('home-pt', 1200, 900), lang('A coach spotting a member on the bench press'), true,
            array($link(lang('Book a session'), '{{page:book_online}}', 'btn btn-outline-primary px-4')),
            '', 'py-5 bg-body-tertiary'),

        $el('section', 'py-5', lang('The Gym Floor'), array(
            $container(array(
                $section_header(lang('Inside'), lang('Room to train your way'), lang('See the gym'), '{{page:facilities}}'),
                $row($facility_tiles, '4'),
            )),
        ), $searchable('gy-home-facilities', lang('weights, studios, sauna, showers, lockers, equipment'))),

        // What members say: sample words for the gym to replace, and beside
        // them its own Google reviews, which show once Google Reviews is set
        // up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Member Stories'), array(
            $container(array(
                $section_header(lang('Our members'), lang('What members say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From Training Notes'), array(
            $container(array(
                $section_header(lang('Training Notes'), lang('Tips from the coaches'), lang('All notes'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Group classes ───────────────────────────────────────────────────────
// A section per focus with its classes, then how a class works.
$focus_sections = array();
$n = 0;
foreach ($focuses as $fkey => $f) {
    $cards = array();
    foreach ($classes as $ckey => $c) {
        if ($c['focus'] === $fkey) $cards[] = $class_card($ckey);
    }
    $focus_sections[] = $el('section', 'py-5' . ($n % 2 ? ' bg-body-tertiary' : ''), $f['title'], array(
        $container(array(
            $section_header(lang('Focus'), $f['title'], lang('Meet the coaches'), '{{page:trainers}}?' . $query(array('focus' => $f['title']))),
            $para($f['text'], 'text-body-secondary mb-4'),
            $row($cards, '4'),
        )),
    ), $searchable('gy-classes-' . $fkey, lang('class, workout, session, group training')));
    $n++;
}

$public_pages[] = array(
    'key'              => 'classes',
    'name'             => lang('group-classes'),
    'folder'           => 'public',
    'title'            => lang('Group Classes'),
    'meta_description' => lang('HIIT, cycling, boxing, barbell and kettlebell strength, yoga and Pilates: group classes for every level, every day of the week.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Group Classes'), lang('Strength, cardio and calmer classes for every level. Book your place, come ten minutes early and bring water.'), array(
            $link(lang('See the timetable'), '{{page:timetable}}', 'btn btn-primary px-4 mt-3'),
        ))),
        $focus_sections,
        array($el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How a class works'), 'pgy-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Pick a class and a time'), lang('From the timetable or a class above; the request opens with your choice made.')),
                            $step(2, lang('We confirm your place'), lang('The front desk answers the same day; you read the answer under My Class Bookings.')),
                            $step(3, lang('Come ten minutes early'), lang('Bring water and a towel; the coach sets up the room and shows each move.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-3 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('Never been to a class?'), 'pgy-display h4 mb-2'),
                            $para(lang('Your first class is on us. Ask for a free trial pass and tell us which class you would like to try.'), 'mb-3'),
                            $link(lang('Get a Free Pass'), '{{page:join}}?' . $query(array('plan' => lang('Free trial pass'))), 'btn btn-primary px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('gy-how-classes', lang('booking, first class, beginner, what to bring')))),
    )),
);

// ── Timetable ───────────────────────────────────────────────────────────
// The week day by day, Monday first; each class opens the booking request
// with the class, the day and the time chosen.
$day_cols = array();
foreach (array_keys($days) as $day) $day_cols[] = $col(array($timetable_day($day)), '6', '4', '', '12', 'mb-0');

$public_pages[] = array(
    'key'              => 'timetable',
    'name'             => lang('class-timetable'),
    'folder'           => 'public',
    'title'            => lang('Class Timetable'),
    'meta_description' => lang('The week of group classes at the gym, day by day, with the coach, the studio and the length of every class.'),
    'tree'             => $page(array(
        $page_header(lang('Class Timetable'), lang('Every class of the week with its coach and studio. Choose Book to ask for your place.')),
        $el('section', 'py-5', lang('The Week'), array(
            $container(array(
                $row($day_cols, '4'),
            )),
        ), $searchable('gy-timetable', lang('schedule, times, week, calendar, when'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Opening hours'), 'pgy-display h3 mb-3'),
                        $hours_list(),
                    ), '', '5'),
                    $col(array(
                        $heading('h2', lang('Before you book'), 'pgy-display h3 mb-3'),
                        $check_list(array(
                            lang('Members book their place; a free trial pass books one class'),
                            lang('Cancel up to two hours before the class so someone else can come'),
                            lang('Classes change on public holidays; we say so in the notice bar'),
                            lang('Every class is open to every level; the coach shows an easier and a harder way'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('gy-timetable-rules', lang('opening hours, cancel, holidays, rules'))),
    )),
);

// ── Membership plans ────────────────────────────────────────────────────
// The plans, what every plan includes and how joining works. Each plan
// opens the join request with the plan chosen; the shop's membership
// pages are linked from the shop pages only.
$public_pages[] = array(
    'key'              => 'plans',
    'name'             => lang('membership'),
    'folder'           => 'public',
    'title'            => lang('Membership Plans'),
    'meta_description' => lang('Off-peak, unlimited or unlimited with personal training: monthly plans with no joining fee and a free trial pass first.'),
    'tree'             => $page(array(
        $page_header(lang('Membership Plans'), lang('Monthly plans with no joining fee. Try a class for free first, then choose the plan that fits your week.')),
        $el('section', 'py-5', lang('Plans'), array(
            $container(array(
                $row($plan_cards_row, '4'),
            )),
        ), $searchable('gy-plans', lang('price, prices, cost, monthly, membership fee'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Every plan includes'), 'pgy-display h3 mb-3'),
                        $check_list(array(
                            lang('No joining fee and no contract longer than a month'),
                            lang('A welcome session with a coach in your first week'),
                            lang('Showers, lockers and the sauna'),
                            lang('Cancel before the next month starts, at the front desk or by e-mail'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('How joining works'), 'pgy-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose a plan or a trial pass'), lang('Open the request from a plan or choose it in the form.')),
                            $step(2, lang('We call or write back'), lang('The front desk answers within a day and books your welcome session.')),
                            $step(3, lang('Start training'), lang('Come to your first visit; we set up your membership at the desk.')),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('gy-plan-terms', lang('contract, cancel, joining fee, terms, freeze'))),
    )),
);

// ── The gym floor ───────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'facilities',
    'name'             => lang('the-gym'),
    'folder'           => 'public',
    'title'            => lang('The Gym Floor'),
    'meta_description' => lang('A weights floor with ten platforms, three studios, a cardio zone, a sauna, changing rooms and a juice bar.'),
    'tree'             => $page(array(
        $page_header(lang('The Gym Floor'), lang('Everything you need to train, and room to do it without queuing for a rack.')),
        $el('section', 'py-5', lang('Facilities'), array(
            $container(array(
                $row($facility_tiles, '4'),
            )),
        ), $searchable('gy-facilities', lang('equipment, weights, machines, sauna, showers, lockers, parking'))),
        $story_band('gy-floor', lang('Weights Floor'), lang('Lift with room to spare'),
            array($facilities['floor']['text'], lang('A coach is on the floor at the busy hours to check a lift or show a new move.')),
            $photo('floor', 1200, 900), lang('Lifting platforms and racks on the weights floor'), false, array(),
            lang('barbell, dumbbells, racks, platform, free weights'), 'py-5 bg-body-tertiary'),
        $story_band('gy-studios', lang('Three Studios'), lang('A room for every class'),
            array($facilities['studios']['text']),
            $photo('studios', 1200, 900), lang('The dark cycle studio with rows of bikes'), true,
            array($link(lang('See the timetable'), '{{page:timetable}}', 'btn btn-outline-primary px-4'))),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-people', 'title' => lang('A team, not a crowd'), 'text' => lang('Classes stay small enough for the coach to know every name and every old injury.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Safe before heavy'), 'text' => lang('Good form first: we add the weight when the move is right, never the other way round.')),
    array('icon' => 'bi-emoji-smile', 'title' => lang('Every body welcome'), 'text' => lang('First visit or tenth year, any age and any shape: the welcome is the same.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Two coaches, an old print works and a timetable of classes from early morning to late evening: the story of the gym.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $gym['tagline'] . '.'),
        $story_band('gy-our-story', lang('Our story'), lang('From a print works to a training floor'),
            array($gym['story']),
            $photo('about', 1200, 900), lang('The brick hall of the gym with its high windows'), false, array(),
            lang('history, founders, owners, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we coach')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('gy-values', lang('values, coaching, community, beginners'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Training Focus'), array(
            $container(array(
                $section_header(lang('What we train'), lang('Three ways to get fitter'), lang('Our Trainers'), '{{page:trainers}}'),
                $row($focus_tiles, '4'),
            )),
        )),
        $el('section', 'py-5', lang('Write to Us'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('A question before your first visit?'), 'pgy-display h4 mb-1'),
                        $para(lang('Write to us and a coach answers, usually the same day.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: joining, classes, the gym. Each question is a search
// section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pgy-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about the free trial pass, joining and cancelling, booking a class, what to bring, personal training and the changing rooms.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Joining, classes and the gym. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('gy-faq-joining', lang('Joining'), array(
                            array(lang('Can I try the gym before I join?'), lang('Yes: ask for a free trial pass, come to any class and use the floor that day. There is nothing to pay and nothing to sign.'), lang('trial, free, try, visit')),
                            array(lang('Is there a contract?'), lang('No. Plans run a month at a time with no joining fee; stop before the next month starts.'), lang('contract, cancel, joining fee, notice')),
                            array(lang('How do I follow my request?'), lang('Sending a form opens an account for you. Under My Account you see the state of each request and can write to the front desk there.'), lang('my account, status, track')),
                        )),
                        $faq_group('gy-faq-classes', lang('Classes and booking'), array(
                            array(lang('Do I need to book a class?'), lang('Yes, so the coach can plan the room. Ask for a place from the timetable; the front desk confirms it the same day.'), lang('book, booking, reserve, place')),
                            array(lang('I am a beginner. Which class should I take?'), lang('Any class marked Every level or Good for beginners. Tell the coach it is your first time and they show you an easier way of each move.'), lang('beginner, first time, level, easy')),
                            array(lang('What should I bring?'), lang('Comfortable clothes, trainers with clean soles, a water bottle and a towel. Mats, weights and gloves are ours.'), lang('bring, clothes, shoes, towel, gloves')),
                            array(lang('Can I cancel my place?'), lang('Yes, up to two hours before the class: write on your booking and the place goes to someone else.'), lang('cancel, change, late')),
                        )),
                        $faq_group('gy-faq-gym', lang('At the gym'), array(
                            array(lang('Is personal training extra?'), lang('Unlimited Plus includes two sessions a month; on any other plan you can book a session at a free time online.'), lang('personal trainer, PT, coach, one to one')),
                            array(lang('Are there showers and lockers?'), lang('Yes: hot showers at every hour, lockers with your own code, hair dryers and towels for hire.'), lang('showers, lockers, changing rooms, towel')),
                            array(lang('How old do I need to be?'), lang('Members are 16 or older; from 14 with a parent\'s signature and a welcome session.'), lang('age, teenager, children, minimum age')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// ── Getting here ────────────────────────────────────────────────────────

$ways_here = array(
    array('icon' => 'bi-train-front', 'title' => lang('By train'), 'text' => lang('Get off at the river station and walk five minutes along the river to Foundry Street.')),
    array('icon' => 'bi-bicycle', 'title' => lang('By bike'), 'text' => lang('Bike racks at the door, and a locker for your helmet while you train.')),
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('Members park free for two hours in the yard behind the gym; enter from the side street.')),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'location',
    'name'             => lang('getting-here'),
    'folder'           => 'public',
    'title'            => lang('Getting Here'),
    'meta_description' => lang('Where the gym is, its opening hours and how to reach it by train, bike or car.'),
    'tree'             => $page(array(
        $page_header(lang('Getting Here'), $gym['region'] . '.'),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Find us'), 'pgy-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $gym['address'], 'pgy-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($gym['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $gym['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($gym['email'], 'mailto:' . $gym['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list('list-unstyled mb-4'),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($gym['address']), 'btn btn-outline-primary px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('location', 1400, 1050), lang('The gym\'s brick front on Foundry Street'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('gy-address', lang('address, map, directions, opening hours, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('gy-travel', lang('train, parking, bike, car, transport'))),
    )),
);

// The home page's lists: its trainers (home_trainers) come with the
// trainers, its notes (home_posts) and the Google reviews (google_reviews)
// with the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
