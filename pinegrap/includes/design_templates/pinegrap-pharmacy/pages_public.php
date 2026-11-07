<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Pharmacy", its public pages: home, the services and one
 * page per service, the health checks, being on duty, about, the questions
 * and getting here. Included after parts.php in the same scope (its
 * builders and the pharmacy's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($pharmacy, $opening_hours,
 * $duty_facts, $duty_tips, $services, $checks, $pharmacists, $ranges,
 * $prescription_steps, $figures, $testimonials), so a change made there
 * reaches every page here. No page here links a shop page: the template
 * stands on a site without the shop.
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
        $para($eyebrow, 'prx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'prx-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the values, the ways to the
// pharmacy and what a check holds.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'prx-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The pharmacy in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'prx-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The opening hours as a short list, for the location and on-duty pages.
$hours_list = function ($class = 'list-unstyled mb-0') use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'prx-price fw-semibold text-end', lang('Hours')),
        ));
    }
    return $el('ul', $class, lang('Opening Hours'), $items);
};

// The emergency number as a large button that calls it.
$emergency_button = function ($class = 'btn btn-danger btn-lg rounded-pill px-4') use ($el, $icon, $span, $pharmacy, $tel) {
    return $el('a', $class . ' d-inline-flex align-items-center gap-2', lang('Emergency Number'), array(
        $icon('bi-telephone-plus'),
        $span(sprintf(lang('Call %s'), $pharmacy['emergency']), 'text-nowrap', lang('Link Text')),
    ), array('href' => $tel($pharmacy['emergency'])));
};

// A prescription request's steps, numbered.
$prescription_step_list = function () use ($el, $step, $prescription_steps) {
    $items = array();
    foreach ($prescription_steps as $n => $s) $items[] = $step($n + 1, $s['title'], $s['text']);
    return $el('ol', 'list-unstyled mb-0', lang('Steps'), $items);
};

// The cards of the shared data, in their order.
$service_cols = array();
foreach (array_keys($services) as $key) $service_cols[] = $service_card($key);
$check_cols = array();
foreach (array_keys($checks) as $key) $check_cols[] = $check_card($key);
$duty_cols = array();
foreach (array_keys($duty_facts) as $key) $duty_cols[] = $duty_card($key);
$range_cols = array();
foreach (array_keys($ranges) as $key) $range_cols[] = $range_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Your neighbourhood pharmacy: prescriptions ready to collect or delivered, flu and travel vaccinations, health checks and advice from our pharmacists.'),
    'tree'             => $page(array(
        // The way in: the pharmacy in a sentence, the two things most
        // customers come for and a photo of the counter.
        $el('section', 'prx-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($pharmacy['region'], 'prx-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Good advice, close to home'), 'prx-display display-4 mb-3'),
                        $para($pharmacy['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Send a Prescription'), '{{page:prescription}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Book Online'), '{{page:book_online}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A pharmacist explaining a medicine to a customer at the counter'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $section_header(lang('What we do'), lang('More than a counter'), lang('All services'), '{{page:services}}'),
                $row($service_cols, '4'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Health Checks'), array(
            $container(array(
                $section_header(lang('In ten minutes'), lang('Know your numbers'), lang('All health checks'), '{{page:health_checks}}'),
                $row($check_cols, '4'),
            )),
        )),

        // The pharmacists, read from the Pharmacists collection.
        $el('section', 'py-5', lang('Our Pharmacists'), array(
            $container(array(
                $section_header(lang('The team'), lang('Pharmacists who take the time'), lang('Meet the team'), '{{page:team}}'),
                $widget('home_team'),
            )),
        )),

        // Being on duty, in a band of its own.
        $el('section', 'py-5 bg-body-tertiary', lang('On Duty'), array(
            $container(array(
                $section_header(lang('Nights and holidays'), lang('When we are closed'), lang('On Duty'), '{{page:on_duty}}'),
                $row($duty_cols, '4'),
            )),
        ), $searchable('rx-home-on-duty', lang('night, Sunday, holiday, open late, duty pharmacy'))),

        $el('section', 'py-5', lang('On Our Shelves'), array(
            $container(array(
                $section_header(lang('On our shelves'), lang('Health products chosen by pharmacists')),
                $row($range_cols, '4'),
            )),
        )),

        // What customers say: sample words for the pharmacy to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Customer Stories'), array(
            $container(array(
                $section_header(lang('Our customers'), lang('What our customers say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From Health Advice'), array(
            $container(array(
                $section_header(lang('Health Advice'), lang('Advice from our pharmacists'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Our Services'),
    'meta_description' => lang('Prescriptions, vaccinations, health checks, medicine reviews, travel health and home delivery at your neighbourhood pharmacy, with prices.'),
    'tree'             => $page(array(
        $page_header(lang('Our Services'), lang('Prescriptions ready when you are, a private room for vaccinations and checks, and a pharmacist with time for your questions.')),
        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $row($service_cols, '4'),
            )),
        ), $searchable('rx-services', lang('medicine, price, cost, flu jab, vaccine, blood pressure, delivery'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('A prescription, step by step'), 'prx-display h3 mb-4'),
                        $prescription_step_list(),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('Not sure what you need?'), 'prx-display h4 mb-2'),
                            $para(lang('Ask a pharmacist: we answer in writing, usually the same day, and tell you whether to see a doctor.'), 'mb-3'),
                            $link(lang('Ask a Pharmacist'), '{{page:ask}}', 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('rx-prescription-steps', lang('repeat prescription, collect, ready, order'))),
    )),
);

// One page per service: what it is, what it covers, its price, the
// pharmacists who do it and the service's next step. Its address is a
// translated slug, so a Turkish site reads Turkish addresses.
$service_slugs = array(
    'prescriptions'   => lang('prescriptions'),
    'vaccinations'    => lang('vaccinations'),
    'health_checks'   => lang('health-check-service'),
    'medicine_review' => lang('medicine-reviews'),
    'travel_health'   => lang('travel-health'),
    'delivery'        => lang('home-delivery'),
);
foreach ($services as $key => $s) {
    $pharmacist_lines = array();
    foreach ($pharmacists as $p) {
        if (in_array($key, $p['services'], true)) {
            $pharmacist_lines[] = $p['name'] . ' · ' . $p['role'];
        }
    }
    $buttons = array($service_action($key));
    $buttons[0]['props']['cssClass'] = 'btn btn-primary rounded-pill px-4';
    $buttons[] = $link(lang('Ask a Pharmacist'), '{{page:ask}}', 'btn btn-outline-primary rounded-pill px-4');
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
                            $heading('h2', lang('What it covers'), 'prx-display h4 mb-3'),
                            $check_list($s['points'], 'list-unstyled mb-4'),
                            $para($service_price($key), 'prx-price h5 fw-bold mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
                        ), '', '6'),
                        $col(array($framed($photo('service-' . $key, 1200, 900), $s['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('rx-service-' . str_replace('_', '-', $key))),
            $el('section', 'py-5 bg-body-tertiary', lang('Our Pharmacists'), array(
                $container(array(
                    $section_header(lang('Who does it'), lang('The pharmacists for this service'), lang('Meet the team'),
                        '{{page:team}}?' . $query(array('service' => $s['title']))),
                    $check_list($pharmacist_lines),
                )),
            )),
        )),
    );
}

// ── Health checks ───────────────────────────────────────────────────────

$check_tiles = array(
    $feature_tile('bi-door-closed', lang('In private'), lang('Every check is done in our consultation room, with the door closed.')),
    $feature_tile('bi-journal-medical', lang('Written down'), lang('You take your figures home on a card; bring it next time to see how they change.')),
    $feature_tile('bi-person-check', lang('The next step'), lang('When a figure needs a doctor, we tell you so plainly and write a note for your visit.')),
);

$public_pages[] = array(
    'key'              => 'health_checks',
    'name'             => lang('health-checks'),
    'folder'           => 'public',
    'title'            => lang('Health Checks'),
    'meta_description' => lang('Blood pressure, blood sugar, cholesterol and weight checks in a private room at the pharmacy, with prices and online booking.'),
    'tree'             => $page(array(
        $page_header(lang('Health Checks'), lang('A few minutes in our consultation room, your figures explained in plain words and written down to take home.'), array(
            $el('div', 'mt-3', lang('Buttons'), array(
                $link(lang('Book a check'), '{{page:book_online}}', 'btn btn-primary rounded-pill px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Checks'), array(
            $container(array(
                $row($check_cols, '4'),
            )),
        ), $searchable('rx-checks', lang('blood pressure, sugar, glucose, cholesterol, weight, BMI, measure, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row($check_tiles, '4'),
            )),
        )),
        $el('section', 'py-5', lang('Before Your Check'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Before your check'), 'prx-display h3 mb-3'),
                        $check_list(array(
                            lang('For blood sugar and cholesterol, come before breakfast if you can'),
                            lang('Avoid coffee and cigarettes for half an hour before a blood pressure check'),
                            lang('Bring a list of the medicines you take'),
                            lang('A health check does not replace a visit to your doctor'),
                        )),
                    ), '', '7'),
                    $col(array($framed($photo('check', 1200, 900), lang('A pharmacist measuring a customer\'s blood pressure'))), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('rx-check-tips', lang('fasting, prepare, before, coffee'))),
    )),
);

// ── On duty ─────────────────────────────────────────────────────────────
// What being on duty means, where to go when the pharmacy is closed and
// what an emergency calls for. No date is printed: the rota is the
// chamber's and changes every month, so the window notice keeps it.

$public_pages[] = array(
    'key'              => 'on_duty',
    'name'             => lang('on-duty-pharmacy'),
    'folder'           => 'public',
    'title'            => lang('On Duty'),
    'meta_description' => lang('Our duty nights and holidays, how to find the pharmacy on duty tonight when we are closed, and when to call 112 instead.'),
    'tree'             => $page(array(
        $page_header(lang('On Duty'), lang('Pharmacies take turns to stay open at night and on holidays, so there is always one on duty near you.'), array(
            $el('div', 'mt-3', lang('Buttons'), array($emergency_button())),
        )),
        $el('section', 'py-5', lang('On Duty'), array(
            $container(array(
                $row($duty_cols, '4'),
            )),
        ), $searchable('rx-on-duty', lang('duty pharmacy, night, Sunday, holiday, open now, late'))),
        $el('section', 'py-5 bg-body-tertiary', lang('At Night'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('At the night window'), 'prx-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Ring the night bell'), lang('The bell is at the side window; the pharmacist on duty answers within a few minutes.')),
                            $step(2, lang('Show your prescription'), lang('Medicines are handed out through the window, with the same advice as by day.')),
                            $step(3, lang('Pay the duty fee'), lang('A small fee set by the chamber is added at night and on holidays.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('What to bring'), 'prx-display h3 mb-3'),
                        $check_list($duty_tips, 'list-unstyled mb-4'),
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list(),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('rx-night-window', lang('night bell, window, fee, identity card'))),
    ), false),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-chat-heart', 'title' => lang('Time to explain'), 'text' => lang('Every medicine leaves the counter with a word on how to take it, and no question is too small.')),
    array('icon' => 'bi-shield-lock', 'title' => lang('Discreet'), 'text' => lang('A private room for checks, vaccinations and the conversations that are nobody else\'s business.')),
    array('icon' => 'bi-mortarboard', 'title' => lang('Always learning'), 'text' => lang('Our pharmacists train every year and share what they learn in our Health Advice articles.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Three generations behind the same counter: the story of the pharmacy, its pharmacists and the way we look after our customers.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $pharmacy['tagline'] . '.'),
        $story_band('rx-our-story', lang('Our story'), lang('Three generations behind one counter'),
            array($pharmacy['story']),
            $photo('about', 1200, 900), lang('The bright shop floor of the pharmacy with its wooden shelves'), false, array(),
            lang('history, family, owner, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we look after you')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('rx-values', lang('values, approach, privacy, advice'))),
        $el('section', 'py-5 bg-body-tertiary', lang('On Our Shelves'), array(
            $container(array(
                $section_header(lang('On our shelves'), lang('Health products chosen by pharmacists')),
                $row($range_cols, '4'),
            )),
        ), $searchable('rx-ranges', lang('vitamins, skin care, sun cream, baby, first aid, blood pressure monitor'))),
        $el('section', 'py-5', lang('Our Pharmacists'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Meet the team'), 'prx-display h4 mb-1'),
                        $para(lang('Four pharmacists and two assistants who know the neighbourhood and its medicines.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Our Pharmacists'), '{{page:team}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: prescriptions, checks and vaccinations, nights and
// emergencies. Each question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'prx-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about sending a prescription, home delivery, vaccinations, health checks and finding the pharmacy on duty at night.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Prescriptions, checks and nights on duty. Not answered here? Ask a pharmacist.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('rx-faq-prescriptions', lang('Prescriptions'), array(
                            array(lang('How do I send a prescription?'), lang('Send a prescription request with the prescription number or the medicines. We tell you when the bag is ready; you show the original prescription when you collect it.'), lang('send, order, repeat, number, online')),
                            array(lang('How do I follow my request?'), lang('Sending a form opens an account for you. Under My Account you see the state of each request and can write to the pharmacy there.'), lang('my account, status, track, ready')),
                            array(lang('Can you deliver my medicines?'), lang('Yes, in the neighbourhood. Choose home delivery in the request; we call before we come and collect the original prescription at the door.'), lang('delivery, home, bring, courier')),
                        )),
                        $faq_group('rx-faq-checks', lang('Checks and vaccinations'), array(
                            array(lang('Do I need an appointment for a vaccination?'), lang('Booking online saves you waiting, but on a quiet afternoon we vaccinate without one too.'), lang('flu jab, appointment, walk in')),
                            array(lang('Are the results of a health check stored?'), lang('No. We write your figures on a card for you to keep; nothing is stored on this site.'), lang('results, privacy, data, record')),
                        )),
                        $faq_group('rx-faq-night', lang('Nights and emergencies'), array(
                            array(lang('Where do I get medicine at night?'), lang('At the pharmacy on duty tonight: its name is on the notice in our window and on the chamber of pharmacists\' list.'), lang('night, Sunday, holiday, duty pharmacy')),
                            array(lang('When should I call 112 instead?'), lang('For chest pain, breathing difficulty, a serious injury, a poisoning or anyone who is not responding. Do not wait for a pharmacy.'), lang('emergency, ambulance, urgent, poison')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// ── Getting here ────────────────────────────────────────────────────────

$ways_here = array(
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('Stop for a few minutes in front of the door while you collect your medicines.')),
    array('icon' => 'bi-bus-front', 'title' => lang('By bus'), 'text' => lang('The Market Square stop is across the road; the entrance has no step.')),
    array('icon' => 'bi-person-walking', 'title' => lang('On foot'), 'text' => lang('Linden Street leaves the market square by the fountain; look for the green cross.')),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'location',
    'name'             => lang('getting-here'),
    'folder'           => 'public',
    'title'            => lang('Getting Here'),
    'meta_description' => lang('Where the pharmacy is, its opening hours and phone lines, and how to reach it by car, bus or on foot.'),
    'tree'             => $page(array(
        $page_header(lang('Getting Here'), $pharmacy['region'] . '.'),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Find us'), 'prx-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $pharmacy['address'], 'prx-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($pharmacy['phone'], $tel($pharmacy['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-2', lang('Delivery Line'), array(
                                $icon('bi-bicycle', 'text-primary'),
                                $link($pharmacy['delivery'], $tel($pharmacy['delivery']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($pharmacy['email'], 'mailto:' . $pharmacy['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list('list-unstyled mb-4'),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($pharmacy['address']), 'btn btn-outline-primary rounded-pill px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('location', 1400, 1050), lang('The pharmacy\'s shop front with its green cross on Linden Street'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('rx-address', lang('address, map, directions, opening hours, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('rx-travel', lang('parking, bus, car, transport, wheelchair'))),
    )),
);

// The home page's lists: its pharmacists (home_team) come with the team,
// its articles (home_posts) and the Google reviews (google_reviews) with
// the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
