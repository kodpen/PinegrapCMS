<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "City Hall", its public pages: home, the services
 * directory and one page per service area, the mayor, the council, the
 * departments, the city, the service points, the useful numbers and the
 * questions page. Included after parts.php in the same scope (its builders
 * and the city's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($city, $service_areas, $departments,
 * $mayor, $committees, $council, $neighbourhoods, $service_points,
 * $useful_numbers, $figures), so a change made there reaches every page
 * here.
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

// A picture in a framed ratio, loaded lazily unless it is the first thing
// a page shows.
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
        $para($eyebrow, 'pcy-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pcy-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the city's pillars, the neighbourhoods
// and the council's committees.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pcy-display h5 mb-2'),
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
                    $heading('h2', $title, 'pcy-display h4 mb-1'),
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


// What the city hall stands for (the home and the city page).
$pillars = array(
    array('icon' => 'bi-chat-square-text', 'title' => lang('Every request answered'),
        'text' => lang('Requests and complaints are sent to the right department the same day and followed until they are resolved.')),
    array('icon' => 'bi-eye', 'title' => lang('Open council'),
        'text' => lang('Council meetings are open to the public, and the agenda and decisions are published as notices.')),
    array('icon' => 'bi-tree', 'title' => lang('A greener city'),
        'text' => lang('New parks and street trees every year, and recycling bins in every street.')),
    array('icon' => 'bi-universal-access', 'title' => lang('A city for everyone'),
        'text' => lang('Social support, accessible transport and services close to home in every neighbourhood.')),
);

// The cards and tiles of the shared data, in their order.
$pillar_tiles = array();
foreach ($pillars as $p) $pillar_tiles[] = $feature_tile($p['icon'], $p['title'], $p['text']);
$area_cols = array();
foreach (array_keys($service_areas) as $key) $area_cols[] = $area_card($key);
$department_cols = array();
foreach (array_keys($departments) as $key) $department_cols[] = $department_card($key);
$council_cols = array();
foreach (array_keys($council) as $n) $council_cols[] = $council_card($n);
$committee_tiles = array();
foreach ($committees as $c) $committee_tiles[] = $feature_tile('bi-people', $c['title'], $c['text']);
$point_cols = array();
foreach (array_keys($service_points) as $key) $point_cols[] = $point_card($key);
$number_cols = array();
foreach (array_keys($useful_numbers) as $n) $number_cols[] = $number_card($n);
$neighbourhood_tiles = array();
foreach ($neighbourhoods as $name) {
    $neighbourhood_tiles[] = $col(array(
        $el('div', 'd-flex align-items-center gap-2 h-100 p-3 rounded-3 bg-body border', $name, array(
            $icon('bi-geo-alt', 'text-primary'),
            $span($name, 'fw-semibold', lang('Name')),
        )),
    ), '4', '3', '', '6');
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Your city hall online: services, public notices and tenders, events, the council and requests answered by the right department.'),
    'tree'             => $page(array(
        // The way in: the things residents come to do, each a tile of its
        // own, and the citizen line (City Hall: Task Hero).
        $task_hero(lang('Welcome to your city hall'), lang('How can we help you today?'), $city['tagline'] . '.', array(
            array('bi-exclamation-triangle', lang('Report a problem'), lang('A pothole, a dark street or rubbish left behind'), '{{page:request}}'),
            array('bi-calendar-check', lang('Book a meeting'), lang('Meet the mayor or a department'), '{{page:meet}}'),
            array('bi-megaphone', lang('Public Notices'), lang('Tenders, council decisions and vacancies'), '{{page:notices}}'),
            array('bi-people', lang('Council meetings'), lang('The agenda and the decisions'), '{{page:council}}'),
            array('bi-geo-alt', lang('Service points'), lang('Where to go and when they are open'), '{{page:service_points}}'),
            array('bi-telephone', lang('Useful numbers'), lang('Faults, emergencies and the citizen line'), '{{page:numbers}}'),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('City Services'), array(
            $container(array(
                $section_header(lang('What we do'), lang('City services'), lang('All Services'), '{{page:services}}'),
                $rise($row($area_cols, '4')),
            )),
        ), $searchable('cy-home-services', lang('permits, roads, rubbish, recycling, social help, courses, parks, licences, wedding'))),

        // The featured notices, read from the Notices collection.
        $el('section', 'py-5', lang('Public Notices'), array(
            $container(array(
                $section_header(lang('Notices and tenders'), lang('Latest public notices'), lang('All Notices'), '{{page:notices}}'),
                $widget('home_notices'),
            )),
        )),

        // The next council session and its agenda (City Hall: Council
        // Agenda).
        $council_agenda('cy-home-council', array(
            $link(lang('Council Decisions'), '{{page:notices}}?' . $query(array('type' => $notice_types['decision'])), 'btn btn-primary px-4'),
            $link(lang('Meet the Council'), '{{page:council}}', 'btn btn-outline-secondary px-4'),
        ), 'py-5 bg-body-tertiary'),

        // The featured events, read from the Events collection.
        $el('section', 'py-5', lang('Coming Up'), array(
            $container(array(
                $section_header(lang('Events'), lang('What\'s on in the city'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        $story_band('cy-home-mayor', lang('From the mayor'), $mayor['name'],
            array($mayor['message']),
            $portrait($mayor['name'], 900), $mayor['name'], true,
            array($el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                $link(lang('The Mayor'), '{{page:mayor}}', 'btn btn-primary px-4'),
                $link(lang('Meet the Mayor\'s Office'), '{{page:meet}}', 'btn btn-outline-secondary px-4'),
            ))),
            lang('mayor, message, meeting, appointment')),

        $el('section', 'py-5 bg-body-tertiary', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from the city'), lang('All News'), '{{page:blog}}'),
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
    'title'            => lang('City Services'),
    'meta_description' => lang('Every service of the city hall by area: building and zoning, roads, waste, social support, culture, parks, licences and ceremonies.'),
    'tree'             => $page(array(
        $page_header(lang('City Services'), lang('Find a service by area: what it is, the documents to bring and where to apply.')),
        $el('section', 'py-5', lang('City Services'), array(
            $container(array(
                $rise($row($area_cols, '4')),
            )),
        ), $searchable('cy-services', lang('permits, roads, rubbish, recycling, social help, courses, parks, licences, wedding, funeral'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How a Request Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $section_header(lang('Step by step'), lang('How a request works')),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Send your request'), lang('Choose the topic, say where and what, and add a photo if it helps.')),
                            $step(2, lang('We send it to the department'), lang('The citizen desk reads it the same day and forwards it to the department in charge.')),
                            $step(3, lang('Follow every answer'), lang('The state of your request and every message from us wait in My Requests.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-3 bg-body border shadow-sm', lang('Call to Action'), array(
                            $heading('h3', lang('Something to report?'), 'pcy-display h5 mb-2'),
                            $para(lang('A pothole, a dark street, rubbish left behind or a broken bench: tell us and we will send a team.'), 'small text-body-secondary mb-3'),
                            $link(lang('Make a Request'), '{{page:request}}', 'btn btn-primary px-4'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('cy-services-how', lang('complaint, report a problem, follow up, track'))),
    )),
);

// One page per service area: what the area covers, then each service as a
// guide (where to apply, the documents to bring, the request form with the
// area chosen) beside the department that runs it. Its address is a translated slug, so a Turkish
// site reads Turkish addresses.
$area_slugs = array(
    'building' => lang('building-and-zoning'),
    'roads'    => lang('roads-and-transport'),
    'waste'    => lang('waste-and-recycling'),
    'social'   => lang('social-support'),
    'culture'  => lang('culture-and-sport'),
    'parks'    => lang('parks-and-environment'),
    'civil'    => lang('licences-and-ceremonies'),
);
foreach ($service_areas as $key => $a) {
    $public_pages[] = array(
        'key'              => 'service_' . $key,
        'name'             => $area_slugs[$key],
        'folder'           => 'public',
        'title'            => $a['title'],
        'meta_description' => $a['summary'],
        'tree'             => $page(array(
            $page_header($a['title'], $a['summary']),
            $el('section', 'pt-5', $a['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($a['icon'], 'mb-3'),
                            $para($a['text'], 'lead mb-0'),
                        ), '', '8'),
                    ), '4'),
                )),
            ), $searchable('cy-area-' . $key)),
            // Each service as a guide beside the department in charge
            // (City Hall: Service Guide).
            $service_guide($key),
        )),
    );
}

// ── The mayor ───────────────────────────────────────────────────────────

$deputies = array();
foreach ($council as $n => $m) {
    if ($m['role'] === lang('Deputy Mayor')) $deputies[] = $council_card($n);
}
$public_pages[] = array(
    'key'              => 'mayor',
    'name'             => lang('mayor'),
    'folder'           => 'public',
    'title'            => lang('The Mayor'),
    'meta_description' => lang('The mayor of the city, a message to residents, the deputy mayors and how to ask for a meeting with the mayor\'s office.'),
    'tree'             => $page(array(
        $page_header(lang('The Mayor'), $mayor['name'] . ', ' . $mayor['role']),
        $story_band('cy-mayor', lang('A message to residents'), $mayor['name'],
            array($mayor['message'], $mayor['bio']),
            $portrait($mayor['name'], 900), $mayor['name'], false,
            array($link(lang('Meet the Mayor\'s Office'), '{{page:meet}}', 'btn btn-primary px-4')),
            lang('mayor, biography, message, appointment')),
        $el('section', 'py-5 bg-body-tertiary', lang('Deputy Mayors'), array(
            $container(array(
                $section_header(lang('The mayor\'s team'), lang('Deputy mayors'), lang('Council'), '{{page:council}}'),
                $row($deputies, '4'),
            )),
        ), $searchable('cy-mayor-deputies', lang('deputy mayor, vice mayor'))),
        $ask_band(lang('Would you like to meet the mayor\'s office?'), lang('Send a meeting request with your subject, or write to us with a question.')),
    )),
);

// ── The council ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'council',
    'name'             => lang('council'),
    'folder'           => 'public',
    'title'            => lang('City Council'),
    'meta_description' => lang('The members of the city council, its committees, when it meets and where to read its agenda and decisions.'),
    'tree'             => $page(array(
        $page_header(lang('City Council'), lang('The members elected by the residents, the committees that prepare their work and the next session\'s agenda.')),
        // The next session and its agenda first: what residents come to
        // this page for (City Hall: Council Agenda).
        $council_agenda('cy-council-agenda', array(
            $link(lang('Agenda and Decisions'), '{{page:notices}}?' . $query(array('type' => $notice_types['decision'])), 'btn btn-primary px-4'),
            $link(lang('Book a meeting'), '{{page:meet}}', 'btn btn-outline-secondary px-4'),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Council Members'), array(
            $container(array(
                $section_header(lang('Elected by the residents'), lang('Council members')),
                $rise($row($council_cols, '4')),
            )),
        ), $searchable('cy-council', lang('councillors, members, deputy mayor, elected'))),
        $el('section', 'py-5', lang('Committees'), array(
            $container(array(
                $section_header(lang('Where the work is prepared'), lang('Committees')),
                $row($committee_tiles, '4'),
            )),
        ), $searchable('cy-committees', lang('commission, budget, zoning, social, culture'))),
    )),
);

// ── Departments ─────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'departments',
    'name'             => lang('departments'),
    'folder'           => 'public',
    'title'            => lang('City Departments'),
    'meta_description' => lang('The departments of the city hall: who leads each one, where to find it and its phone number.'),
    'tree'             => $page(array(
        $page_header(lang('City Departments'), $city['hours'] . '.'),
        $el('section', 'py-5', lang('City Departments'), array(
            $container(array(
                $row($department_cols, '4'),
            )),
        ), $searchable('cy-departments', lang('directorate, office, head of department, phone, floor'))),
        $ask_band(lang('Not sure which department to ask?'), lang('Send a request: the citizen desk forwards it to the right department the same day.')),
    )),
);

// ── The city ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'city',
    'name'             => lang('our-city'),
    'folder'           => 'public',
    'title'            => lang('Our City'),
    'meta_description' => lang('The city in short: its story, its neighbourhoods, its figures and what the city hall commits to its residents.'),
    'tree'             => $page(array(
        $page_header(lang('Our City'), $city['tagline'] . '.'),
        $story_band('cy-city-story', lang('Our story'), lang('A city that grew around a river crossing'),
            array($city['story']),
            $photo('city-old-town', 1200, 900), lang('Roofs of the old town seen from the hill'), false, array(),
            lang('history, about the city, harbour, old town')),
        $figures_band(),
        $el('section', 'py-5', lang('Neighbourhoods'), array(
            $container(array(
                $section_header(lang('Where we live'), lang('Neighbourhoods'), lang('Service Points'), '{{page:service_points}}'),
                $row($neighbourhood_tiles, '3'),
            )),
        ), $searchable('cy-neighbourhoods', lang('district, quarter, area, neighbourhood'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Commitments'), array(
            $container(array(
                $section_header(lang('How we work'), lang('Our commitments to residents')),
                $row($pillar_tiles, '4'),
            )),
        ), $searchable('cy-city-pillars', lang('transparency, accountability, environment, accessibility'))),
    )),
);

// ── Service points ──────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'service_points',
    'name'             => lang('service-points'),
    'folder'           => 'public',
    'title'            => lang('Service Points'),
    'meta_description' => lang('Where to find the city hall in person: addresses, phone numbers and opening hours of every service point.'),
    'tree'             => $page(array(
        $page_header(lang('Service Points'), lang('Requests, social support and course sign-ups at the city hall and close to home.')),
        $el('section', 'py-5', lang('Service Points'), array(
            $container(array(
                $row($point_cols, '4'),
            )),
        ), $searchable('cy-points', lang('opening hours, address, office, citizen desk, branch'))),
        $ask_band(lang('Cannot come in person?'), lang('Most requests can be sent online and followed from your account.')),
    )),
);

// ── Useful numbers ──────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'numbers',
    'name'             => lang('useful-numbers'),
    'folder'           => 'public',
    'title'            => lang('Useful Numbers'),
    'meta_description' => lang('Emergency and city hall numbers: the citizen line, water faults, street lights, funeral services and animal care.'),
    'tree'             => $page(array(
        $page_header(lang('Useful Numbers'), $city['line_hours'] . '.'),
        $el('section', 'py-5', lang('Useful Numbers'), array(
            $container(array(
                $row($number_cols, '4'),
            )),
        ), $searchable('cy-numbers', lang('phone, hotline, emergency, call, fault'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: requests and city life. Each question is a search
// section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pcy-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about requests and complaints, permits, rubbish collection, local taxes, council meetings and tenders.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Requests, permits and city life. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('cy-faq-requests', lang('Requests and complaints'), array(
                            array(lang('How do I report a problem in my street?'), lang('Send a request with the topic, the place and a photo if you have one. It opens an account for you, where you follow every answer.'), lang('report, complaint, pothole, street light, rubbish')),
                            array(lang('How long does an answer take?'), lang('The citizen desk forwards every request the same day, and most are answered within a week.'), lang('response time, how long, wait')),
                            array(lang('Can I send a request by phone?'), lang('Yes: the citizen line takes calls around the clock, and the citizen desk at the city hall is open on weekdays and Saturday mornings.'), lang('phone, call, hotline, desk')),
                            array(lang('Who sees my request?'), lang('Only you and the city hall staff who handle it. Your details are used only to answer you.'), lang('privacy, personal data, confidential')),
                        )),
                        $faq_group('cy-faq-city', lang('City life'), array(
                            array(lang('When is rubbish collected?'), lang('Every day in the city centre and every other day in the outer neighbourhoods. Bulky items are collected on request.'), lang('rubbish, garbage, waste, collection day')),
                            array(lang('How do I pay local taxes?'), lang('At the Finance office in the city hall, at any service point, or through the national e-government service.'), lang('property tax, fees, pay, payment')),
                            array(lang('Can I attend a council meeting?'), lang('Yes. Meetings are open to the public; the agenda is published as a notice a week before.'), lang('council meeting, agenda, attend, public')),
                            array(lang('Where are tenders published?'), lang('Every tender is published as a public notice with its reference number, its deadline and where to get the documents.'), lang('tender, bid, procurement, contract')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured notices (home_notices) and events
// (home_events) come with the collections, its news (home_posts) with the
// standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
