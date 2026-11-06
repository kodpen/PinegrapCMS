<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its public pages: home, visit (opening hours,
 * admission, getting there, facilities), support (membership and other
 * ways to help), about and the questions page. Included after parts.php in
 * the same scope (its builders and the museum's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($museum, $opening_hours, $admission,
 * $tiers, $departments, $facilities, $getting_there, $people, $figures,
 * $testimonials) and every price from its price tables through $money(),
 * so the visit and support pages show the figure the shop will charge.
 * No page here links a shop page: tickets and membership are sold on the
 * shop's own pages, and these pages stand on a site without the shop.
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
    return $el('div', 'ratio ratio-' . $ratio . ' overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pmz-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pmz-display h2 mb-3'),
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

// A tile with an icon, a title and a line: facilities, the way there and
// the ways to help.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 bg-body border', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pmz-display h5 mb-2'),
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
                    $heading('h2', $title, 'pmz-display h4 mb-1'),
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
$department_cols = array();
foreach (array_keys($departments) as $key) $department_cols[] = $department_card($key);
$admission_cols = array();
foreach (array_keys($admission) as $key) $admission_cols[] = $admission_card($key);
$tier_cols = array();
foreach (array_keys($tiers) as $key) $tier_cols[] = $tier_card($key);
$facility_tiles = array();
foreach ($facilities as $f) $facility_tiles[] = $feature_tile($f['icon'], $f['title'], $f['text']);
$way_tiles = array();
foreach ($getting_there as $g) $way_tiles[] = $feature_tile($g['icon'], $g['title'], $g['text'], '3');
$person_cols = array();
foreach (array_keys($people) as $n) $person_cols[] = $person_card($n);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// The other ways to help (the support page): each opens the contact page,
// where the museum's team answers.
$help_ways = array(
    array('icon' => 'bi-heart', 'title' => lang('Make a gift'),
        'text' => lang('Every gift, large or small, helps us care for the collection and keep the first gallery free.')),
    array('icon' => 'bi-people', 'title' => lang('Volunteer'),
        'text' => lang('Welcome visitors, guide tours or help in the store: training is given and no experience is needed.')),
    array('icon' => 'bi-briefcase', 'title' => lang('Partner with us'),
        'text' => lang('Companies sponsor exhibitions and school visits, and hold their events in our galleries.')),
    array('icon' => 'bi-feather', 'title' => lang('Leave a legacy'),
        'text' => lang('A gift in your will lets future visitors see what you love about the museum.')),
);
$help_tiles = array();
foreach ($help_ways as $h) {
    $help_tiles[] = $feature_tile($h['icon'], $h['title'], $h['text'], '3', array(
        $link(lang('Talk to us'), '{{page:contact}}', 'd-inline-block small fw-semibold link-primary mt-3'),
    ));
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Exhibitions, a collection of forty thousand works, talks, tours and family days in a city museum open to everyone.'),
    'tree'             => $page(array(
        // The way in: what is on, the two ways on and a picture of the
        // galleries.
        $el('section', 'pmz-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Art, history and ideas'), 'pmz-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Come and look closer'), 'pmz-display display-3 mb-3'),
                        $para($museum['tagline'] . '.', 'lead text-body-secondary mb-2'),
                        $para($museum['hours'] . '.', 'small fw-semibold mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book Timed Entry'), '{{page:book_online}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-outline-secondary btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A long gallery with paintings on red walls and a bench in the middle'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $figures_band(),

        // The exhibitions on now, read from the Exhibitions collection.
        $el('section', 'py-5', lang('Now On'), array(
            $container(array(
                $section_header(lang('Exhibitions'), lang('Now on at the museum'), lang('All Exhibitions'), '{{page:exhibitions}}'),
                $widget('home_exhibitions'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Collection'), array(
            $container(array(
                $section_header(lang('The collection'), lang('Explore the collection'), lang('Collection Highlights'), '{{page:collection}}'),
                $row($department_cols, '4'),
            )),
        ), $searchable('mz-home-collection', lang('art, paintings, sculpture, objects, artworks, archive'))),

        $el('section', 'py-5', lang('Plan Your Visit'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Opening hours'), 'pmz-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('Plan your visit'), 'pmz-display h2 mb-4'),
                        $hours_list('mb-4'),
                        $para($museum['free'] . '.', 'mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Tickets and Prices'), '{{page:visit}}', 'btn btn-primary px-4'),
                            $link(lang('Book Timed Entry'), '{{page:book_online}}', 'btn btn-outline-secondary px-4'),
                        )),
                    ), '', '5'),
                    $col(array($framed($photo('home-entrance', 1200, 900), lang('The museum\'s stone steps and columns on the square'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('mz-home-visit', lang('opening times, hours, open today, closed, tickets, price'))),

        // The coming events, read from the Events collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Up'), array(
            $container(array(
                $section_header(lang('Events'), lang('Talks, tours and family days'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        $story_band('mz-home-learning', lang('Learning'), lang('A museum for every school'),
            array(
                lang('Sessions for every age, led by our learning team in the galleries and the studio, from stories for the youngest to a look behind the scenes for older students.'),
                lang('School groups from the city visit free; tell us what you are studying and we will plan the visit with you.'),
            ),
            $photo('home-learning', 1200, 900), lang('Sketchbooks and pencils on a gallery bench'), true,
            array($el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                $link(lang('Learning'), '{{page:learning}}', 'btn btn-primary px-4'),
                $link(lang('Book a School Visit'), '{{page:school_visit}}', 'btn btn-outline-secondary px-4'),
            ))),
            lang('schools, education, pupils, students, teachers, workshops')),

        // What visitors say: sample words for the museum to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our visitors'), lang('What visitors say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from the museum'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Visit ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'visit',
    'name'             => lang('visit'),
    'folder'           => 'public',
    'title'            => lang('Plan Your Visit'),
    'meta_description' => lang('Opening hours, admission prices, timed entry, how to get here, access and what you will find in the building.'),
    'tree'             => $page(array(
        $page_header(lang('Plan Your Visit'), $museum['hours'] . '.'),
        $el('section', 'py-5', lang('Opening Hours'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Opening hours'), 'pmz-display h3 mb-3'),
                        $hours_list(),
                    ), '', '5'),
                    $col(array(
                        $heading('h2', lang('Find us'), 'pmz-display h3 mb-3'),
                        $para($museum['address'], 'lead mb-2'),
                        $para($museum['phone'] . ' · ' . $museum['email'], 'text-body-secondary mb-4'),
                        $framed($photo('visit-square', 1200, 675), lang('The museum seen across the square'), '16x9'),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('mz-hours', lang('opening times, open today, closed, holidays, address, location, map'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Admission'), array(
            $container(array(
                $section_header(lang('Admission'), lang('Tickets and prices')),
                $row($admission_cols, '4'),
                $para($museum['free'] . '. ' . lang('Buy your ticket at the desk when you arrive, or book a timed entry to skip the queue on busy days.'), 'mt-4 mb-3'),
                $link(lang('Book Timed Entry'), '{{page:book_online}}', 'btn btn-primary px-4'),
            )),
        ), $searchable('mz-admission', lang('tickets, price, cost, entry fee, free, discount, concession, family ticket'))),

        $el('section', 'py-5', lang('Getting Here'), array(
            $container(array(
                $section_header(lang('Getting here'), lang('How to reach us')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('mz-getting-here', lang('directions, transport, parking, metro, bus, bike'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Facilities'), array(
            $container(array(
                $section_header(lang('In the building'), lang('Facilities and access')),
                $row($facility_tiles, '4'),
            )),
        ), $searchable('mz-facilities', lang('cafe, shop, lockers, wheelchair, accessible, toilets, baby changing, wifi'))),

        $ask_band(lang('Coming with a group or with special needs?'), lang('Write to us before your visit and we will help you plan it.')),
    )),
);

// ── Support ─────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'support',
    'name'             => lang('support-us'),
    'folder'           => 'public',
    'title'            => lang('Support Us'),
    'meta_description' => lang('Become a member, make a gift or volunteer: the ways you can help the museum care for its collection and stay open to all.'),
    'tree'             => $page(array(
        $page_header(lang('Support Us'), lang('The museum is a charity: members, donors and volunteers keep it open to everyone.')),
        $el('section', 'py-5', lang('Membership'), array(
            $container(array(
                $section_header(lang('Membership'), lang('Become a member')),
                $para(lang('A year of free entry to every exhibition, previews before anyone else and a little off in the shop and the café, while your fee helps the museum.'), 'lead text-body-secondary mb-4'),
                $row($tier_cols, '4'),
                $para(lang('Join at the desk on your next visit, or write to us and we will send you the form.'), 'mt-4 mb-0'),
            )),
        ), $searchable('mz-membership', lang('member, membership, friends, patron, annual pass, free entry'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Other Ways to Help'), array(
            $container(array(
                $section_header(lang('Get involved'), lang('Other ways to help')),
                $row($help_tiles, '4'),
            )),
        ), $searchable('mz-help', lang('donate, donation, volunteer, sponsor, corporate, legacy, will'))),

        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our visitors'), lang('Why people support us')),
                $row($testimonial_cols, '4'),
            )),
        )),

        $ask_band(lang('A question about giving?'), lang('Our team will gladly talk it through with you.')),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A city museum that grew from one family\'s gift: its story, its collection and the people who look after it.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $museum['tagline'] . '.'),
        $story_band('mz-our-story', lang('Our story'), lang('It began with a gift'),
            array($museum['story'], $museum['licence'] . '.'),
            $photo('about-first-gallery', 1200, 900), lang('The first gallery, hung with the paintings of the founding gift'), false, array(),
            lang('history, founders, trust, charity, who we are')),
        $figures_band(),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $section_header(lang('Our people'), lang('Who looks after the museum')),
                $row($person_cols, '4'),
            )),
        ), $searchable('mz-team', lang('director, curators, staff, conservation, learning team'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Collection'), array(
            $container(array(
                $section_header(lang('The collection'), lang('What we care for'), lang('Collection Highlights'), '{{page:collection}}'),
                $row($department_cols, '4'),
            )),
        )),
        $ask_band(lang('Researching the collection?'), lang('Write to us to see a work in store or to ask about a loan.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: visiting, and membership and groups. Each question is a
// search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pmz-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about tickets, timed entry, bags, photography, access, membership, school groups and events.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Visiting, membership and groups. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('mz-faq-visiting', lang('Visiting'), array(
                            array(lang('Do I need to book in advance?'), lang('Not for the collection: buy your ticket at the desk. For a ticketed exhibition on a weekend, book a timed entry so you do not wait.'), lang('booking, timed entry, queue, reservation')),
                            array(lang('How long should I plan for a visit?'), lang('Two hours for the highlights of the collection, and an hour more for a ticketed exhibition.'), lang('duration, time, how long')),
                            array(lang('Can I take photographs?'), lang('Yes, for your own use and without flash, except where a sign asks you not to.'), lang('photos, camera, flash, phone')),
                            array(lang('Can I bring a large bag?'), lang('Large bags, umbrellas and suitcases go in the free lockers by the entrance.'), lang('luggage, cloakroom, locker, backpack')),
                            array(lang('Is the museum accessible?'), lang('Every gallery is step-free, with lifts, wheelchairs to borrow and a hearing loop at the desk. A companion of a disabled visitor enters free.'), lang('wheelchair, disabled, access, lift, carer')),
                        )),
                        $faq_group('mz-faq-groups', lang('Membership and groups'), array(
                            array(lang('What does membership give me?'), lang('A year of free entry to every exhibition, previews before each opening and a discount in the shop and the café.'), lang('member, benefits, annual pass')),
                            array(lang('How do I book a school visit?'), lang('Send the school visit form with the session you would like and a few possible days; it opens an account where you follow the request.'), lang('school, class, pupils, education, teacher')),
                            array(lang('How do I book a place at an event?'), lang('Open the event and send the form for the places you need; we confirm by e-mail and you find the booking in your account.'), lang('event, talk, workshop, tour, booking, place')),
                            array(lang('Can I hire the museum for an event?'), lang('Yes, the courtyard and some galleries can be hired in the evening. Write to us with your date and the number of guests.'), lang('venue hire, private event, corporate, wedding')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its exhibitions (home_exhibitions) come with the
// exhibitions, its events (home_events) with the events, its news
// (home_posts) and the Google reviews (google_reviews) with the standard
// pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
