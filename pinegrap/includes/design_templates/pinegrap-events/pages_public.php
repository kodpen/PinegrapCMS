<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Events and Conferences", its public pages: home, the
 * programme, the speakers, the venue and the about page, and the Google
 * Reviews widget the home page places beside its sample testimonials.
 * Included after parts.php in the same scope (its builders and the
 * conference's data); returns array('pages' => ..., 'widgets' => ...).
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

// A speaker as a small card: the portrait, the name, the role and company,
// linked to the speaker's profile on the speakers page.
$speaker_card = function ($key) use ($speakers, $el, $col, $image, $heading, $para, $portrait, $attr) {
    $s = $speakers[$key];
    return $col(array(
        $el('a', 'card h-100 border-0 text-center bg-transparent text-decoration-none link-body-emphasis pev-lift', $s['name'], array(
            $el('div', 'card-body p-3', lang('Card Body'), array(
                $image($portrait($s['name'], 320), $s['name'], 'rounded-circle object-fit-cover mb-3', array('width' => '144', 'height' => '144', '_attrs' => array($attr('loading', 'lazy')))),
                $heading('h3', $s['name'], 'h6 fw-bold mb-1'),
                $para($s['role'], 'small text-body-secondary mb-0'),
                $para($s['company'], 'small fw-semibold text-primary mb-0'),
            )),
        ), array('href' => '{{page:speakers}}#speaker-' . $key)),
    ), '4', '3', '', '6');
};

// The figures of the conference, in a row of four.
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

// What attendees say: sample words the organiser replaces with its own.
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

// The sponsors of one tier as a row of logos; the headline partner larger.
$sponsor_row = function ($tier) use ($sponsors, $el, $row, $col, $image, $logo, $heading, $attr) {
    $cols = array();
    $big = $tier === 'headline';
    foreach ($sponsors[$tier]['names'] as $name) {
        $cols[] = $col(array(
            $el('div', 'd-flex align-items-center justify-content-center h-100 p-3 rounded-3 border bg-body', $name, array(
                $image($logo($name), $name, 'img-fluid', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
        ), $big ? '6' : '4', $big ? '4' : '3', '', '6');
    }
    return $el('div', 'mb-4', $sponsors[$tier]['title'], array(
        $heading('h3', $sponsors[$tier]['title'], 'h6 text-uppercase small fw-bold text-body-secondary mb-3'),
        $row($cols, '3', $big ? 'center' : ''),
    ));
};

// The featured speakers: the two keynotes and two from the tracks.
$featured_speakers = array();
foreach (array('elif', 'zeynep', 'marcus', 'daniel') as $key) $featured_speakers[] = $speaker_card($key);

// A track as a card of the home page, linked to its section of the about
// page.
$track_cards = array();
foreach ($tracks as $key => $t) {
    $track_cards[] = $col(array(
        $el('a', 'card h-100 border-0 bg-body-tertiary rounded-4 text-decoration-none link-body-emphasis', $t['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($t['icon'], 'mb-3'),
                $heading('h3', $t['title'], 'h5 fw-bold mb-2'),
                $para($t['text'], 'small text-body-secondary mb-0'),
            )),
        ), array('href' => '{{page:about}}#track-' . $key)),
    ), '6', '3');
}

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Two days of talks and workshops for people who build products, and meetups all year. See the programme and register.'),
    'tree'             => $page(array(
        // The conference: what, where, the way to register and a picture of
        // the main hall.
        $el('section', 'py-5 overflow-hidden pev-hero', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('p', 'd-flex flex-wrap align-items-center gap-2 small fw-semibold text-primary mb-3', lang('Event Line'), array(
                            $icon('bi-calendar2-week'),
                            $span(lang('Two days in autumn'), '', lang('Text')),
                            $span('·', 'text-body-secondary', lang('Separator')),
                            $icon('bi-geo-alt'),
                            $span($venue['name'], '', lang('Text')),
                        )),
                        $heading('h1', lang('The conference for people who build products'), 'display-4 fw-bold mb-3'),
                        $para(lang('Keynotes, four tracks and hands-on workshops by the people behind products you use every day. Meet them, and the 1,200 others who come to learn.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Register'), '{{page:registration}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('See the programme'), '{{page:programme}}', 'btn btn-outline-secondary btn-lg px-4'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden shadow-sm', lang('Picture Frame'), array(
                            $image($photo('hero', 1200, 900), lang('A full main hall listening to a keynote on a large stage'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The conference in figures.
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        // The tracks.
        $el('section', 'py-5', lang('Tracks'), array(
            $container(array(
                $section_header(lang('Four tracks'), lang('Pick your path through the day'), lang('About the tracks'), '{{page:about}}#tracks'),
                $row($track_cards, '4'),
            )),
        )),

        // The featured speakers.
        $el('section', 'py-5 bg-body-tertiary', lang('Speakers'), array(
            $container(array(
                $section_header(lang('On stage'), lang('Featured speakers'), lang('All speakers'), '{{page:speakers}}'),
                $row($featured_speakers, '4'),
            )),
        )),

        // The coming events: meetups, workshops and webinars between two
        // conferences (records of the Events form, from today on).
        $el('section', 'py-5', lang('Coming Events'), array(
            $container(array(
                $section_header(lang('All year round'), lang('Coming events'), lang('All events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // How registering works.
        $el('section', 'py-5 border-top', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Registering'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Registered in three steps'), 'h3 fw-bold mb-3'),
                        $para(lang('No account needed to begin: the form opens one for you, so you can follow your registration and write to us afterwards.'), 'text-body-secondary mb-4'),
                        $link(lang('Register'), '{{page:registration}}', 'btn btn-primary px-4'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Choose the event and your pass'), lang('The conference or one of the events, a pass, and anything we should know.')),
                            $step(2, lang('We confirm by e-mail'), lang('Your confirmation comes with the details of the day, usually within a working day.')),
                            $step(3, lang('Come and enjoy'), lang('Bring the confirmation on your phone. Need to change something? Write under your registration.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // What attendees say: three sample cards, and the reviews from
        // Google beside them once the place is set.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our attendees'), lang('What attendees say')),
                $row(array(
                    $testimonial(lang('“The best two days of the year for our team. We came back with a list of things to try on Monday.”'), lang('Ece T.'), lang('Product Manager')),
                    $testimonial(lang('“Talks that go deeper than the slides, and speakers who stay for the questions in the corridor.”'), lang('Mert A.'), lang('Software Engineer')),
                    $testimonial(lang('“I came for the workshops and left with three people I now call when I am stuck.”'), lang('Laura S.'), lang('Design Lead')),
                ), '4'),
                $widget('google_reviews'),
            )),
        )),

        // The sponsors.
        $el('section', 'py-5', lang('Sponsors'), array(
            $container(array(
                $section_header(lang('Made possible by'), lang('Our sponsors'), lang('Become a sponsor'), '{{page:contact}}'),
                $sponsor_row('headline'),
                $sponsor_row('gold'),
            )),
        )),

        // The newest news.
        $el('section', 'py-5 border-top', lang('From the Blog'), array(
            $container(array(
                $section_header(lang('News'), lang('Latest news'), lang('All news'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('register_band'),
    )),
);

// ── Programme ───────────────────────────────────────────────────────────
// A tab for each day; each day a list of its sessions by time, every
// session with its kind, track, speaker and room. Each day is a search
// section, and so is each talk with a speaker.
$session_row = function ($s) use ($el, $span, $heading, $para, $link, $icon, $track_badge, $session_kinds, $speakers, $searchable) {
    $k = $session_kinds[$s['kind']];
    $is_break = in_array($s['kind'], array('break', 'social'), true);
    $meta = array(
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Kind'), array($icon($k['icon']), $span($k['label'], '', lang('Text')))),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Room'), array($icon('bi-door-open'), $span($s['room'], '', lang('Text')))),
    );
    if ($s['track'] !== '') array_unshift($meta, $track_badge($s['track']));
    $body = array(
        $heading('h3', $s['title'], 'h6 fw-bold mb-1'),
        $el('div', 'd-flex flex-wrap align-items-center gap-3 small text-body-secondary', lang('Session Details'), $meta),
    );
    if ($s['speaker'] !== '') {
        $p = $speakers[$s['speaker']];
        $body[] = $el('p', 'small mt-2 mb-0', lang('Speaker'), array(
            $link($p['name'], '{{page:speakers}}#speaker-' . $s['speaker'], 'fw-semibold link-body-emphasis'),
            $span(' · ' . $p['role'] . ', ' . $p['company'], 'text-body-secondary', lang('Text')),
        ));
    }
    $extra = $s['speaker'] !== '' ? $searchable('session-' . $s['key'], $speakers[$s['speaker']]['name']) : array('id' => 'session-' . $s['key']);
    return $el('li', 'list-group-item d-flex gap-3 gap-md-4 px-0 py-3 bg-transparent' . ($is_break ? ' text-body-secondary' : ''), $s['title'], array(
        $span($s['time'], 'fw-bold text-nowrap' . ($is_break ? '' : ' text-primary'), lang('Time')),
        $el('div', 'flex-grow-1', lang('Session'), $body),
    ), $extra);
};

$day_tabs = array();
$day_panes = array();
$first = true;
foreach ($programme as $key => $day) {
    $day_tabs[] = $el('li', 'nav-item', lang('Nav Item'), array(
        $el('button', 'nav-link' . ($first ? ' active' : ''), $day['title'], array(), array(
            'text'   => $day['title'],
            'id'     => $key . '-tab',
            '_attrs' => array(
                $attr('type', 'button'),
                $attr('role', 'tab'),
                $attr('data-bs-toggle', 'tab'),
                $attr('data-bs-target', '#' . $key),
                $attr('aria-controls', $key),
                $attr('aria-selected', $first ? 'true' : 'false'),
            ),
        )),
    ), array('_attrs' => array($attr('role', 'presentation'))));
    $rows = array();
    foreach ($day['sessions'] as $s) $rows[] = $session_row($s);
    $day_panes[] = $el('div', 'tab-pane fade' . ($first ? ' show active' : ''), $day['title'], array(
        $heading('h2', $day['theme'], 'small text-uppercase fw-semibold text-primary mb-2'),
        $el('ul', 'list-group list-group-flush', lang('Sessions'), $rows),
    ), array_merge($searchable($key, $day['theme']), array('_attrs' => array(
        $attr('role', 'tabpanel'), $attr('aria-labelledby', $key . '-tab'), $attr('tabindex', '0'),
    ))));
    $first = false;
}

$legend = array();
foreach ($tracks as $key => $t) $legend[] = $track_badge($key);

$public_pages[] = array(
    'key'              => 'programme',
    'name'             => lang('programme'),
    'folder'           => 'public',
    'title'            => lang('Programme'),
    'meta_description' => lang('The conference programme by day: keynotes, four tracks side by side, workshops, the breaks and the evening reception.'),
    'tree'             => $page(array(
        $page_header(lang('Programme'), lang('Two days, four tracks and a keynote to open each morning. Choose your sessions on the day; every talk is recorded.')),
        $el('section', 'py-5', lang('Programme'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('ul', 'nav nav-pills gap-2 mb-3', lang('Days'), $day_tabs, array('id' => 'ev-days', '_attrs' => array($attr('role', 'tablist')))),
                        $el('div', 'd-flex flex-wrap align-items-center gap-2 small mb-4', lang('Tracks'), array_merge(
                            array($span(lang('Tracks:'), 'text-body-secondary me-1', lang('Label'))),
                            $legend
                        )),
                        $el('div', 'tab-content', lang('Day Panes'), $day_panes),
                    ), '', '9'),
                ), '', 'center'),
            )),
        )),
        $shared('register_band'),
    )),
);

// ── Speakers ────────────────────────────────────────────────────────────
// A profile for each: the portrait, the role, the track, what they talk
// about and a link to the session in the programme.
$session_titles = array();
foreach ($programme as $dkey => $day) {
    foreach ($day['sessions'] as $s) $session_titles[$s['key']] = array('title' => $s['title'], 'day' => $day['title'], 'time' => $s['time']);
}
$speaker_profiles = array();
foreach ($speakers as $key => $s) {
    $talk = $session_titles[$s['session']];
    $speaker_profiles[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden', $s['name'], array(
            $el('div', 'row g-0 h-100', lang('Card Row'), array(
                $el('div', 'col-sm-5', lang('Portrait'), array(
                    $el('div', 'ratio ratio-1x1 h-100 bg-primary-subtle', lang('Picture Frame'), array(
                        $image($portrait($s['name']), $s['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                    )),
                )),
                $el('div', 'col-sm-7', lang('Details'), array(
                    $el('div', 'card-body d-flex flex-column h-100 p-4', lang('Card Body'), array(
                        $el('div', 'mb-2', lang('Badges'), array($track_badge($s['track']))),
                        $heading('h2', $s['name'], 'h5 fw-bold mb-1'),
                        $para($s['role'] . ' · ' . $s['company'], 'small text-primary mb-2'),
                        $para($s['bio'], 'small text-body-secondary mb-3'),
                        $el('p', 'd-flex align-items-start gap-2 small mb-0 mt-auto', lang('Session'), array(
                            $icon('bi-mic', 'text-primary mt-1'),
                            $el('span', '', lang('Text'), array(
                                $link($talk['title'], '{{page:programme}}#session-' . $s['session'], 'fw-semibold link-body-emphasis'),
                                $span(' · ' . $talk['day'] . ', ' . $talk['time'], 'text-body-secondary', lang('When')),
                            )),
                        )),
                    )),
                )),
            )),
        ), $searchable('speaker-' . $key, $s['company'] . ', ' . $tracks[$s['track']]['title'])),
    ), '', '6');
}

$public_pages[] = array(
    'key'              => 'speakers',
    'name'             => lang('our-speakers'),
    'folder'           => 'public',
    'title'            => lang('Speakers'),
    'meta_description' => lang('The speakers of the conference: who they are, where they work and what they will talk about.'),
    'tree'             => $page(array(
        $page_header(lang('Speakers'), lang('People who build, run and grow products, here to share what worked and what did not.')),
        $el('section', 'py-5', lang('Speakers'), array(
            $container(array(
                $row($speaker_profiles, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Call for Speakers'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Want to speak next time?'), 'h4 fw-bold mb-2'),
                        $para(lang('We keep a third of the programme for talks from the community. Tell us what you would like to share and why it matters.'), 'text-body-secondary mb-0'),
                    ), '', '8'),
                    $col(array(
                        $link(lang('Propose a talk'), '{{page:contact}}', 'btn btn-outline-primary px-4'),
                    ), '', '4', '', '12', 'text-lg-end'),
                ), '4', '', 'align-items-center'),
            )),
        ), $searchable('call-for-speakers', lang('call for papers, propose a talk, speak, CFP'))),
        $shared('register_band'),
    )),
);

// ── Venue & Travel ──────────────────────────────────────────────────────
$room_items = array();
foreach ($venue['rooms'] as $r) {
    $room_items[] = $el('li', 'list-group-item d-flex justify-content-between gap-3 px-0 bg-transparent', $r[0], array(
        $span($r[0], 'fw-semibold', lang('Room')),
        $span($r[1], 'small text-body-secondary text-end', lang('Text')),
    ));
}
$travel_cards = array();
foreach ($venue['travel'] as $t) {
    $travel_cards[] = $col(array(
        $el('div', 'h-100', $t['title'], array(
            $icon_tile($t['icon'], 'mb-3'),
            $heading('h3', $t['title'], 'h6 fw-bold mb-2'),
            $para($t['text'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
}
$hotel_cards = array();
foreach ($venue['hotels'] as $h) {
    $hotel_cards[] = $col(array(
        $el('div', 'card h-100 border-0 shadow-sm rounded-4', $h['name'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $heading('h3', $h['name'], 'h6 fw-bold mb-1'),
                $el('p', 'd-flex align-items-center gap-1 small text-primary mb-2', lang('Distance'), array($icon('bi-signpost'), $span($h['distance'], '', lang('Text')))),
                $para($h['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
}

$public_pages[] = array(
    'key'              => 'venue',
    'name'             => lang('venue'),
    'folder'           => 'public',
    'title'            => lang('Venue & Travel'),
    'meta_description' => lang('Where the conference takes place, how to get there by metro, from the airport or by car, and where to stay nearby.'),
    'tree'             => $page(array(
        $page_header(lang('Venue & Travel'), lang('A congress centre on the water, five minutes from the metro.')),
        $el('section', 'py-5', lang('The Venue'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('venue', 1200, 900), lang('The congress centre seen from the quay at dusk'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('The venue'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', $venue['name'], 'h3 fw-bold mb-2'),
                        $el('p', 'd-flex align-items-start gap-2 text-body-secondary mb-4', lang('Address'), array($icon('bi-geo-alt', 'text-primary mt-1'), $span($venue['address'], '', lang('Text')))),
                        $el('ul', 'list-group list-group-flush mb-4', lang('Rooms'), $room_items),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($venue['address']), 'btn btn-outline-primary px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('the-venue', lang('venue, address, location, rooms, halls'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Getting There'), array(
            $container(array(
                $section_header(lang('Getting there'), lang('How to reach the venue')),
                $row($travel_cards, '4'),
            )),
        ), $searchable('getting-there', lang('metro, airport, taxi, parking, car park, bus'))),
        $el('section', 'py-5', lang('Where to Stay'), array(
            $container(array(
                $section_header(lang('Where to stay'), lang('Hotels nearby')),
                $row($hotel_cards, '4'),
            )),
        ), $searchable('where-to-stay', lang('hotel, accommodation, room rate, stay'))),
        $el('section', 'py-5 bg-body-tertiary', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Good to know'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Before you come'), 'h3 fw-bold mb-3'),
                        $para(lang('Anything else? Write to us and the answer comes to your inbox.'), 'text-body-secondary mb-3'),
                        $link(lang('Contact us'), '{{page:contact}}', 'btn btn-outline-primary px-4'),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('Is the venue accessible?'), lang('Yes: step-free entrances, lifts to every floor, wheelchair places in every hall and a quiet room. Tell us your needs on the registration form.'), lang('accessible, wheelchair, step-free, quiet room')),
                            $faq(2, lang('Can I pass my ticket to a colleague?'), lang('Yes, until a week before the conference. Write under your registration with your colleague\'s name and e-mail.'), lang('transfer, ticket, colleague, name change')),
                            $faq(3, lang('Is food included?'), lang('Breakfast, lunch, coffee and snacks are included with every pass, with vegetarian, vegan and gluten-free choices.'), lang('food, lunch, vegan, vegetarian, gluten-free, dietary')),
                            $faq(4, lang('Will the talks be recorded?'), lang('Every talk in the halls is recorded; attendees get the recordings by e-mail within two weeks.'), lang('recording, video, slides')),
                        ), array('id' => 'ev-faq')),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $shared('register_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
// Who organises the conference, the tracks (#tracks, each #track-<key>)
// and the sponsors (#sponsors).
$track_items = array();
foreach ($tracks as $key => $t) {
    $track_items[] = $col(array(
        $el('div', 'd-flex gap-3 h-100', $t['title'], array(
            $icon_tile($t['icon']),
            $el('div', '', lang('Text'), array(
                $heading('h3', $t['title'], 'h5 fw-bold mb-1'),
                $para($t['text'], 'text-body-secondary mb-0'),
            )),
        ), $searchable('track-' . $key, $t['topics'])),
    ), '6');
}

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('Who organises the conference, the four tracks, and the sponsors who make it possible.'),
    'tree'             => $page(array(
        $page_header(lang('About'), lang('A conference made by a small team for the people who build products, and the sponsors who support it.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('Attendees talking in the foyer between two sessions'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('From a meetup of 40 to a conference of 1,200'), 'h3 fw-bold mb-3'),
                        $para(lang('It began as an evening meetup in a borrowed office. The questions after each talk ran longer than the talks, so we gave them a whole day, and then two.'), 'text-body-secondary'),
                        $para(lang('We still pick every talk ourselves, pay our speakers\' travel and keep the tickets affordable, so the people who build products can learn from each other.'), 'text-body-secondary mb-4'),
                        $figure_row('text-start'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('story, organiser, history, team'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Tracks'), array(
            $container(array(
                $section_header(lang('The programme'), lang('Four tracks'), lang('See the programme'), '{{page:programme}}'),
                $row($track_items, '4'),
            )),
        ), array('id' => 'tracks')),
        $el('section', 'py-5', lang('Sponsors'), array(
            $container(array(
                $section_header(lang('Made possible by'), lang('Our sponsors')),
                $sponsor_row('headline'),
                $sponsor_row('gold'),
                $sponsor_row('partner'),
                $el('div', 'p-4 rounded-4 bg-primary-subtle d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-2', lang('Become a Sponsor'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h3', lang('Become a sponsor'), 'h5 fw-bold mb-1'),
                        $para(lang('Meet the people who build products: a stand, a talk slot or the evening reception. Ask for the sponsorship pack.'), 'small mb-0'),
                    )),
                    $link(lang('Ask for the pack'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        ), $searchable('sponsors', lang('sponsor, sponsorship, partner, stand, exhibit'))),
        $shared('register_band'),
    )),
);

// ── Widgets ─────────────────────────────────────────────────────────────
$public_widgets = array(
    // Google reviews of the venue or the organiser, beside the sample
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
