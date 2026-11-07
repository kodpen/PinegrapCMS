<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Theatre", its public pages: home, your visit (box
 * office hours, ticket bands, access performances, getting there,
 * facilities), support (friends and other ways to help), about and the
 * questions page. Included after parts.php in the same scope (its builders
 * and the theatre's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($theatre, $box_office_hours, $tickets,
 * $tiers, $genres, $access_types, $facilities, $getting_there, $people,
 * $figures, $testimonials) and every price from its price tables through
 * $money(), so the visit and support pages show the figure the shop will
 * charge. No page here links a shop page: tickets and friends are sold on
 * the shop's own pages, and these pages stand on a site without the shop.
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
        $para($eyebrow, 'pth-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pth-display h2 mb-3'),
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
            $heading('h3', $title, 'pth-display h5 mb-2'),
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
                    $heading('h2', $title, 'pth-display h4 mb-1'),
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
$genre_cols = array();
foreach (array_keys($genres) as $key) $genre_cols[] = $genre_card($key);
$access_cols = array();
foreach (array_keys($access_types) as $key) $access_cols[] = $access_card($key);
$ticket_cols = array();
foreach (array_keys($tickets) as $key) $ticket_cols[] = $ticket_card($key);
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
$stage_lines = array();
foreach ($stages as $s) $stage_lines[] = $s['title'] . ': ' . $s['seats'];

// The other ways to help (the support page): each opens the contact page,
// where the theatre's team answers.
$help_ways = array(
    array('icon' => 'bi-heart', 'title' => lang('Make a gift'),
        'text' => lang('Every gift helps us make new work and keep a part of every house at a price anyone can pay.')),
    array('icon' => 'bi-star', 'title' => lang('Name a seat'),
        'text' => lang('Put your name, or the name of someone you love, on a seat in the Main House.')),
    array('icon' => 'bi-briefcase', 'title' => lang('Partner with us'),
        'text' => lang('Companies sponsor a production or the youth theatre, and entertain their guests at our shows.')),
    array('icon' => 'bi-people', 'title' => lang('Volunteer'),
        'text' => lang('Welcome audiences as an usher, help backstage or at the youth theatre: training is given.')),
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
    'meta_description' => lang('New plays, musicals, dance, opera and family shows on two stages, with a youth theatre and spaces to hire.'),
    'tree'             => $page(array(
        // The way in: what the theatre is, the two ways on and a picture
        // of the stage.
        $el('section', 'pth-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Plays, music and dance'), 'pth-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Your night at the theatre starts here'), 'pth-display display-3 mb-3'),
                        $para($theatre['tagline'] . '.', 'lead text-body-secondary mb-2'),
                        $para($theatre['hours'] . '.', 'small fw-semibold mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Find a Performance'), '{{page:whats_on}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('See the Season'), '{{page:season}}', 'btn btn-outline-secondary btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A stage lit in warm light seen from the stalls, with red seats in the foreground'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $figures_band(),

        // The productions playing now, read from the Productions collection.
        $el('section', 'py-5', lang('Now Playing'), array(
            $container(array(
                $section_header(lang('The Season'), lang('Now playing'), lang('The Whole Season'), '{{page:season}}'),
                $widget('home_productions'),
            )),
        )),

        // The next performances, read from the Performances collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Up'), array(
            $container(array(
                $section_header(lang('What\'s On'), lang('The next performances'), lang('All Performances'), '{{page:whats_on}}'),
                $widget('home_performances'),
            )),
        )),

        $el('section', 'py-5', lang('Genres'), array(
            $container(array(
                $section_header(lang('Something for everyone'), lang('Find your kind of show')),
                $row($genre_cols, '4'),
            )),
        ), $searchable('th-home-genres', lang('plays, musicals, comedy, dance, ballet, opera, children, family'))),

        $story_band('th-home-visit', lang('Your Visit'), lang('Before the curtain goes up'),
            array(
                $theatre['doors'] . '. ' . $theatre['late'] . '.',
                lang('Order interval drinks at the bar, borrow a headset at the cloakroom and ask our ushers anything: they are there to help.'),
            ),
            $photo('home-foyer', 1200, 900), lang('A busy foyer bar before a show, with people talking under warm lights'), false,
            array($el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-primary px-4'),
                $link(lang('Access Performances'), '{{page:visit}}#th-access', 'btn btn-outline-secondary px-4'),
            ))),
            lang('box office, opening times, doors, bar, access, parking'), 'py-5 bg-body-tertiary'),

        $story_band('th-home-learning', lang('Learning'), lang('A stage for young people'),
            array(
                lang('Hundreds of young people act, write and make theatre with us every week, from Saturday mornings for the youngest to new plays made by the Youth Company.'),
                lang('Schools come for backstage tours, workshops and a play made in a single day.'),
            ),
            $photo('home-learning', 1200, 900), lang('Young actors rehearsing on a bare stage'), true,
            array($el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                $link(lang('Learning'), '{{page:learning}}', 'btn btn-primary px-4'),
                $link(lang('Book a School Workshop'), '{{page:school_group}}', 'btn btn-outline-secondary px-4'),
            ))),
            lang('youth theatre, drama classes, schools, education, workshops, children')),

        $story_band('th-home-hire', lang('Hire a Space'), lang('Your event on our stage'),
            array(
                lang('Two stages, a rehearsal room and a foyer bar for conferences, launches, parties and filming, with our technical team on hand.'),
            ),
            $photo('home-hire', 1200, 900), lang('An empty auditorium with a lectern on stage'), false,
            array($link(lang('Hire a Space'), '{{page:hire}}', 'btn btn-primary px-4')),
            lang('venue hire, conference, meeting room, private event, filming'), 'py-5 bg-body-tertiary'),

        // What audiences say: sample words for the theatre to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our audiences'), lang('What audiences say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from the theatre'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Your visit ──────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'visit',
    'name'             => lang('visit'),
    'folder'           => 'public',
    'title'            => lang('Plan Your Visit'),
    'meta_description' => lang('Box office hours, ticket prices, access performances, how to get here and what you will find in the building.'),
    'tree'             => $page(array(
        $page_header(lang('Plan Your Visit'), $theatre['hours'] . '.'),
        $el('section', 'py-5', lang('Box Office'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Box office'), 'pth-display h3 mb-3'),
                        $hours_list(),
                    ), '', '5'),
                    $col(array(
                        $heading('h2', lang('Find us'), 'pth-display h3 mb-3'),
                        $para($theatre['address'], 'lead mb-2'),
                        $para($theatre['phone'] . ' · ' . $theatre['email'], 'text-body-secondary mb-4'),
                        $framed($photo('visit-front', 1200, 675), lang('The theatre\'s lit front at night, seen across the square'), '16x9'),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('th-box-office', lang('box office, opening times, phone, address, location, map, doors'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Tickets'), array(
            $container(array(
                $section_header(lang('Seats'), lang('Ticket prices')),
                $row($ticket_cols, '4'),
                $para(implode('. ', $stage_lines) . '.', 'small text-body-secondary mt-4 mb-2'),
                $para(lang('Prices are for most performances; first nights and some visiting companies may differ. Book at the box office, on the phone or ask for a performance\'s date below.'), 'mb-3'),
                $link(lang('Find a Performance'), '{{page:whats_on}}', 'btn btn-primary px-4'),
            )),
        ), $searchable('th-tickets', lang('tickets, prices, seats, stalls, circle, balcony, discount, concession, students'))),

        $el('section', 'py-5', lang('Access Performances'), array(
            $container(array(
                $section_header(lang('Access'), lang('Access performances')),
                $para(lang('Each production has relaxed, captioned, audio-described and sign language interpreted performances. Choose one to see its dates.'), 'lead text-body-secondary mb-4'),
                $row($access_cols, '4'),
            )),
        ), $searchable('th-access', lang('accessibility, relaxed, captions, subtitles, audio description, sign language, deaf, blind, autism'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Getting Here'), array(
            $container(array(
                $section_header(lang('Getting here'), lang('How to reach us')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('th-getting-here', lang('directions, transport, parking, metro, bus, bike'))),

        $el('section', 'py-5', lang('Facilities'), array(
            $container(array(
                $section_header(lang('In the building'), lang('Facilities')),
                $row($facility_tiles, '4'),
            )),
        ), $searchable('th-facilities', lang('bar, cafe, food, cloakroom, wheelchair, toilets, hearing, families'))),

        $ask_band(lang('Coming with a group or with access needs?'), lang('Write to us before your visit and we will find the right seats for you.')),
    )),
);

// ── Support ─────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'support',
    'name'             => lang('support-us'),
    'folder'           => 'public',
    'title'            => lang('Support Us'),
    'meta_description' => lang('Become a friend, make a gift, name a seat or volunteer: the ways you can help the theatre make new work for everyone.'),
    'tree'             => $page(array(
        $page_header(lang('Support Us'), lang('The theatre is a charity: friends, donors and volunteers keep its doors open to everyone.')),
        $el('section', 'py-5', lang('Friends'), array(
            $container(array(
                $section_header(lang('Friends of the theatre'), lang('Become a friend')),
                $para(lang('Book before tickets go on sale, pay no booking fees and get closer to the work, while your fee helps the theatre.'), 'lead text-body-secondary mb-4'),
                $row($tier_cols, '4'),
                $para(lang('Join at the box office on your next visit, or write to us and we will send you the form.'), 'mt-4 mb-0'),
            )),
        ), $searchable('th-friends', lang('friends, membership, member, patron, priority booking, annual'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Other Ways to Help'), array(
            $container(array(
                $section_header(lang('Get involved'), lang('Other ways to help')),
                $row($help_tiles, '4'),
            )),
        ), $searchable('th-help', lang('donate, donation, volunteer, usher, sponsor, corporate, seat naming'))),

        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our audiences'), lang('Why people support us')),
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
    'meta_description' => lang('A producing theatre that began in an old corn exchange: its story, its two stages and the people who make the shows.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $theatre['tagline'] . '.'),
        $story_band('th-our-story', lang('Our story'), lang('It began with a company of actors'),
            array($theatre['story'], $theatre['licence'] . '.'),
            $photo('about-hall', 1200, 900), lang('The old corn exchange hall, now the theatre\'s Main House'), false, array(),
            lang('history, founders, charity, company, who we are')),
        $figures_band(),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $section_header(lang('Our people'), lang('Who makes the shows')),
                $row($person_cols, '4'),
            )),
        ), $searchable('th-team', lang('artistic director, staff, directors, crew, box office'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Genres'), array(
            $container(array(
                $section_header(lang('On our stages'), lang('What we put on'), lang('The Whole Season'), '{{page:season}}'),
                $row($genre_cols, '4'),
            )),
        )),
        $ask_band(lang('A writer, a company or a performer?'), lang('Write to us about your work: we read every script and proposal sent.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: your visit, and friends, groups and hire. Each question
// is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pth-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about tickets, latecomers, children, access performances, friends, school groups and hiring a space.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Your visit, friends, groups and hire. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('th-faq-visit', lang('Your Visit'), array(
                            array(lang('How do I book tickets?'), lang('At the box office, on the phone or, where the theatre sells online, from the performance\'s page. Friends book before tickets go on sale.'), lang('booking, buy tickets, box office, reservation')),
                            array(lang('What if I arrive late?'), lang('Latecomers are seated at a suitable break, which may be the interval. Please arrive in good time.'), lang('late, latecomers, start time, curtain')),
                            array(lang('Can I bring children?'), lang('Each production gives an age guidance on its page. Every child needs a ticket, and family shows have relaxed performances.'), lang('kids, children, age, baby, family')),
                            array(lang('Can I exchange my ticket?'), lang('Yes, for another performance of the same production up to two days before, if seats are left.'), lang('exchange, refund, change date, cancel')),
                            array(lang('Is the theatre accessible?'), lang('Every level is step-free, with wheelchair spaces, headsets for every performance and access performances for each production. A companion of a disabled visitor comes free.'), lang('wheelchair, disabled, access, hearing, carer')),
                        )),
                        $faq_group('th-faq-groups', lang('Friends, groups and hire'), array(
                            array(lang('What does a friend get?'), lang('Priority booking before tickets go on sale, no booking fees, a little off in the bar and invitations to open rehearsals.'), lang('friends, membership, benefits, priority')),
                            array(lang('How do I book a school workshop?'), lang('Send the school group form with the workshop you would like and a few possible days; it opens an account where you follow the request.'), lang('school, class, pupils, education, teacher')),
                            array(lang('How does my child join the youth theatre?'), lang('Choose a group by age on the learning page and ask for a place; we write when one is free.'), lang('youth theatre, drama classes, kids, join')),
                            array(lang('Can I hire the theatre for an event?'), lang('Yes, both stages, the rehearsal room and the foyer bar can be hired. Send the hire form with your date and the number of guests.'), lang('venue hire, private event, conference, corporate')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its productions (home_productions) come with the
// productions, its performances (home_performances) with the performances,
// its news (home_posts) and the Google reviews (google_reviews) with the
// standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
