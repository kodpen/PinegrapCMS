<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Public Library", its public pages: home, visit (the
 * branches and their hours) and one page per branch, children and teens,
 * the digital library, rooms and spaces, about and the questions page.
 * Included after parts.php in the same scope (its builders and the
 * library's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($library, $branches, $services,
 * $digital, $rooms, $staff, $figures, $testimonials and the pick lists),
 * so a change made there reaches every page here.
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
    return $el('div', 'ratio ratio-' . $ratio . ' rounded overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'plb-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'plb-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the services, the digital
// library, getting there and the ways children and teens use the library.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'plb-display h5 mb-2'),
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
                    $heading('h2', $title, 'plb-display h4 mb-1'),
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

// A row of buttons that open a list with a filter set: the catalogue by
// format or audience, the events by kind.
$filter_links = function ($page_key, $param, $titles, $class = 'd-flex flex-wrap gap-2') use ($el, $link, $query) {
    $links = array();
    foreach ($titles as $title) {
        $links[] = $link($title, '{{page:' . $page_key . '}}?' . $query(array($param => $title)), 'btn btn-sm btn-outline-primary');
    }
    return $el('div', $class, lang('Filter Links'), $links);
};

// A branch's hours beside its name and address, for the visit page.
$hours_card = function ($key) use ($el, $heading, $para, $link, $col, $hours_list, $branches) {
    $b = $branches[$key];
    return $col(array(
        $el('div', 'h-100 p-4 rounded bg-body border shadow-sm', $b['title'], array(
            $heading('h3', $b['title'], 'plb-display h5 mb-1'),
            $para($b['address'], 'small text-body-secondary mb-3'),
            $hours_list($key, 'small mb-3'),
            $link(lang('About this branch'), '{{page:branch_' . $key . '}}', 'link-primary fw-semibold small'),
        )),
    ), '6', '4');
};

// The cards and tiles of the shared data, in their order.
$service_tiles = array();
foreach ($services as $s) $service_tiles[] = $feature_tile($s['icon'], $s['title'], $s['text']);
$digital_tiles = array();
foreach ($digital as $s) $digital_tiles[] = $feature_tile($s['icon'], $s['title'], $s['text'], '3');
$branch_cols = array();
foreach (array_keys($branches) as $key) $branch_cols[] = $branch_card($key);
$hours_cols = array();
foreach (array_keys($branches) as $key) $hours_cols[] = $hours_card($key);
$room_cols = array();
foreach (array_keys($rooms) as $key) $room_cols[] = $room_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$staff_cols = array();
foreach (array_keys($staff) as $n) $staff_cols[] = $staff_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Your public library: borrow books, films and eBooks for free, join a reading club, book a room and find your nearest branch.'),
    'tree'             => $page(array(
        // The way in: the library in a sentence, the ways on and a
        // picture of the reading room.
        $el('section', 'plb-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Your public library'), 'plb-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Something good to read, watch and do'), 'plb-display display-4 mb-3'),
                        $para($library['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Browse the Catalogue'), '{{page:catalogue}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Join the Library'), '{{page:join}}', 'btn btn-outline-secondary btn-lg px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('Tall shelves of books in a bright reading room'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $figures_band(),

        // The new arrivals, read from the Catalogue collection.
        $el('section', 'py-5', lang('New Arrivals'), array(
            $container(array(
                $section_header(lang('Just in'), lang('New on our shelves'), lang('All Titles'), '{{page:catalogue}}'),
                $widget('home_arrivals'),
                $filter_links('catalogue', 'format', $format_titles, 'd-flex flex-wrap gap-2 mt-4'),
            )),
        ), $searchable('lib-home-arrivals', lang('new books, new arrivals, latest, recently added'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Branches'), array(
            $container(array(
                $section_header(lang('Three libraries'), lang('Find your branch'), lang('Opening Hours'), '{{page:visit}}'),
                $row($branch_cols, '4'),
            )),
        ), $searchable('lib-home-branches', lang('opening hours, address, location, near me, open today'))),

        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $section_header(lang('Free with your card'), lang('What the library offers'), lang('Digital Library'), '{{page:digital}}'),
                $row($service_tiles, '4'),
            )),
        ), $searchable('lib-home-services', lang('borrow, loan, renew, wifi, computers, printing, study'))),

        // The featured events, read from the Events collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Up'), array(
            $container(array(
                $section_header(lang('Events'), lang('Coming up at the library'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // What readers say: sample words for the library to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our readers'), lang('What readers say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Latest News'), array(
            $container(array(
                $section_header(lang('News'), lang('News from the library'), lang('All News'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Visit ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'visit',
    'name'             => lang('visit-us'),
    'folder'           => 'public',
    'title'            => lang('Visit Us'),
    'meta_description' => lang('Opening hours and addresses of our three libraries, how to get there and what every branch offers.'),
    'tree'             => $page(array(
        $page_header(lang('Visit Us'), lang('Three libraries, open to everyone. You need a card only to borrow.')),
        $el('section', 'py-5', lang('Opening Hours'), array(
            $container(array(
                $section_header(lang('When we are open'), lang('Opening hours')),
                $row($hours_cols, '4'),
            )),
        ), $searchable('lib-hours', lang('opening times, open today, closed, sunday, late night'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Branches'), array(
            $container(array(
                $section_header(lang('Our libraries'), lang('Find your branch')),
                $row($branch_cols, '4'),
            )),
        ), $searchable('lib-branches', lang('address, location, map, near me'))),
        $el('section', 'py-5', lang('Getting Here'), array(
            $container(array(
                $section_header(lang('Before you come'), lang('Getting here and getting in')),
                $row(array(
                    $feature_tile('bi-bus-front', lang('By bus and train'), lang('Every branch is a short walk from a bus stop; Hillside is next to the station.')),
                    $feature_tile('bi-bicycle', lang('By bike'), lang('Bike racks stand at every entrance, under cover at the Central Library.')),
                    $feature_tile('bi-universal-access', lang('Access for everyone'), lang('Step-free entrances, lifts, hearing loops at the desks and large-print guides.')),
                ), '4'),
            )),
        ), $searchable('lib-getting-here', lang('directions, parking, bus, wheelchair, accessible, step-free'))),
        $ask_band(lang('A question before you visit?'), lang('Call any branch or write to us and a librarian answers.')),
    )),
);

// One page per branch: what it is, its hours, what it has, its rooms and
// the ways to join and to book. Its address is a translated slug, so a
// Turkish site reads Turkish addresses.
$branch_slugs = array(
    'central'   => lang('central-library'),
    'riverside' => lang('riverside-branch'),
    'hillside'  => lang('hillside-branch'),
);
foreach ($branches as $key => $b) {
    $branch_rooms = array();
    foreach ($rooms as $room_key => $r) {
        if ($r['branch'] === $key) $branch_rooms[] = $room_card($room_key);
    }
    $sections = array(
        $page_header($b['title'], $b['summary'], array(
            $el('p', 'd-flex flex-wrap gap-3 small text-body-secondary mt-3 mb-0', lang('Branch Facts'), array(
                $span($b['address'], '', lang('Address')),
                $span($b['phone'], '', lang('Phone')),
                $span($b['short'], '', lang('Opening Hours')),
            )),
        )),
        $el('section', 'py-5', $b['title'], array(
            $container(array(
                $row(array(
                    $col(array(
                        $icon_tile($b['icon'], 'mb-3'),
                        $para($b['text'], 'lead mb-4'),
                        $heading('h2', lang('At this branch'), 'plb-display h4 mb-3'),
                        $check_list($b['points'], 'list-unstyled mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Join the Library'), '{{page:join}}', 'btn btn-primary px-4'),
                            $link(lang('See the events'), '{{page:events}}', 'btn btn-outline-secondary px-4'),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded bg-body-tertiary', lang('Opening Hours'), array(
                            $heading('h2', lang('Opening hours'), 'plb-display h5 mb-3'),
                            $hours_list($key, 'mb-0'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('lib-branch-' . $key, lang('opening hours, address, phone'))),
    );
    if ($branch_rooms) {
        $sections[] = $el('section', 'py-5 bg-body-tertiary', lang('Rooms to Book'), array(
            $container(array(
                $section_header(lang('Rooms and spaces'), lang('Rooms to book here'), lang('All Rooms'), '{{page:spaces}}'),
                $row($branch_rooms, '4'),
            )),
        ));
    }
    $public_pages[] = array(
        'key'              => 'branch_' . $key,
        'name'             => $branch_slugs[$key],
        'folder'           => 'public',
        'title'            => $b['title'],
        'meta_description' => $b['summary'],
        'tree'             => $page($sections),
    );
}

// ── Children and teens ──────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'children',
    'name'             => lang('children-and-teens'),
    'folder'           => 'public',
    'title'            => lang('Children and Teens'),
    'meta_description' => lang('Picture books, story times, homework help, teen writers and a free young reader\'s card for every child.'),
    'tree'             => $page(array(
        $page_header(lang('Children and Teens'), lang('From the first picture book to the last exam: a library card is free for every child.')),
        $el('section', 'py-5', lang('For Children'), array(
            $container(array(
                $section_header(lang('Ages 0 to 11'), lang('For children and families')),
                $row(array(
                    $feature_tile('bi-balloon', lang('Story times'), lang('Stories, songs and crafts every week for the under-sixes and their grown-ups.')),
                    $feature_tile('bi-book-half', lang('Books to grow with'), lang('Board books, picture books and first chapter books in every branch.')),
                    $feature_tile('bi-sun', lang('Summer reading challenge'), lang('Read six books over the holidays and collect a medal and a certificate.')),
                ), '4'),
                $el('div', 'mt-4', lang('Buttons'), array(
                    $filter_links('catalogue', 'audience', array($audience_titles[2])),
                )),
            )),
        ), $searchable('lib-children', lang('kids, baby, toddler, story time, picture books, summer reading'))),
        $el('section', 'py-5 bg-body-tertiary', lang('For Teens'), array(
            $container(array(
                $section_header(lang('Ages 12 to 17'), lang('For teens')),
                $row(array(
                    $feature_tile('bi-pencil', lang('Writers\' workshop'), lang('Write stories, poems and comics with a published writer in term time.')),
                    $feature_tile('bi-lamp', lang('Homework and revision'), lang('Quiet desks, Wi-Fi and study guides, open late on weekdays.')),
                    $feature_tile('bi-controller', lang('Graphic novels and games'), lang('Manga, comics and board games to borrow or play at the library.')),
                ), '4'),
                $el('div', 'mt-4', lang('Buttons'), array(
                    $filter_links('catalogue', 'audience', array($audience_titles[1])),
                )),
            )),
        ), $searchable('lib-teens', lang('teenagers, young adult, homework, revision, exams, comics, manga'))),
        $story_band('lib-young-card', lang('A card of their own'), lang('A young reader\'s card is free'),
            array(
                lang('Children under sixteen join with a parent or carer. Their card borrows children\'s and teen books, films and eBooks, and never runs up a charge.'),
                lang('Ask for it online, then collect it at any branch with the grown-up who signed for it.'),
            ),
            $photo('children-corner', 1200, 900), lang('Low shelves and cushions in the children\'s corner'), false,
            array($el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                $link(lang('Join the Library'), '{{page:join}}', 'btn btn-primary px-4'),
                $link(lang('Story Times'), '{{page:events}}?' . $query(array('kind' => $event_kind_titles[2])), 'btn btn-outline-secondary px-4'),
            ))),
            lang('children\'s card, junior card, membership for kids')),
    )),
);

// ── Digital library ─────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'digital',
    'name'             => lang('digital-library'),
    'folder'           => 'public',
    'title'            => lang('Digital Library'),
    'meta_description' => lang('eBooks, audiobooks, newspapers, magazines and online courses, free with your library card at home or on the go.'),
    'tree'             => $page(array(
        $page_header(lang('Digital Library'), lang('Read, listen and learn wherever you are: your library card opens all of it.')),
        $el('section', 'py-5', lang('Digital Library'), array(
            $container(array(
                $row($digital_tiles, '4'),
            )),
        ), $searchable('lib-digital', lang('ebook, e-book, audiobook, online, app, kindle, magazines, newspapers'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Start'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Start in three steps'), 'plb-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Get your card'), lang('Ask for a card online or at any branch. It is free.')),
                            $step(2, lang('Install the app'), lang('Download the library\'s reading app on your phone, tablet or e-reader.')),
                            $step(3, lang('Sign in and borrow'), lang('Sign in with your card number and borrow up to ten titles at a time.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Need a hand?'), 'plb-display h3 mb-3'),
                        $para(lang('Our digital help sessions show you how to borrow eBooks, use online forms or make a video call. Bring your own device or use one of ours.'), 'text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Digital Help'), '{{page:events}}?' . $query(array('kind' => $event_kind_titles[4])), 'btn btn-primary px-4'),
                            $link(lang('Join the Library'), '{{page:join}}', 'btn btn-outline-secondary px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('lib-digital-start', lang('how to borrow ebooks, app, sign in, help, computer class'))),
        $el('section', 'py-5', lang('In the Catalogue'), array(
            $container(array(
                $section_header(lang('Browse'), lang('Digital titles in the catalogue')),
                $filter_links('catalogue', 'format', array($format_titles[2], $format_titles[3])),
            )),
        )),
    )),
);

// ── Rooms and spaces ────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'spaces',
    'name'             => lang('rooms-and-spaces'),
    'folder'           => 'public',
    'title'            => lang('Rooms and Spaces'),
    'meta_description' => lang('Book a study room, a meeting room, the activity room or the community hall at one of our libraries.'),
    'tree'             => $page(array(
        $page_header(lang('Rooms and Spaces'), lang('Rooms for study groups, community groups, classes and events, in three libraries.')),
        $el('section', 'py-5', lang('Rooms and Spaces'), array(
            $container(array(
                $row($room_cols, '4'),
            )),
        ), $searchable('lib-rooms', lang('room hire, meeting room, study room, hall, venue, book a room'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How Booking Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How booking works'), 'plb-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Send a request'), lang('Choose the room, tell us what it is for and when you would like it.')),
                            $step(2, lang('We confirm'), lang('A librarian checks the day and answers in your account within two working days.')),
                            $step(3, lang('Come in'), lang('Collect the key at the desk; leave the room as you found it.')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Good to know'), 'plb-display h3 mb-3'),
                        $check_list(array(
                            lang('Study rooms are free for card holders'),
                            lang('Community groups and charities pay a reduced rate'),
                            lang('Rooms are booked in the library\'s opening hours'),
                            lang('Food and drink only in rooms with a kitchenette'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('lib-room-rules', lang('price, cost, rules, cancel, key'))),
        $ask_band(lang('Planning something bigger?'), lang('Talks, concerts or exhibitions in the community hall: write to us and we plan it with you.')),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A public library that began in one reading room above the market hall and now has three libraries open to everyone.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $library['tagline'] . '.'),
        $story_band('lib-our-story', lang('Our story'), lang('From one reading room to three libraries'),
            array($library['story'], $library['licence'] . '.'),
            $photo('about-reading-room', 1200, 900), lang('The old reading room with its long wooden tables'), false, array(),
            lang('history, who we are, council, public library')),
        $figures_band(),
        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $section_header(lang('Free with your card'), lang('What the library offers')),
                $row($service_tiles, '4'),
            )),
        ), $searchable('lib-about-services', lang('borrow, loan, renew, wifi, computers, printing, home delivery'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Librarians'), array(
            $container(array(
                $section_header(lang('Our people'), lang('Meet the librarians')),
                $row($staff_cols, '4'),
            )),
        ), $searchable('lib-about-staff', lang('staff, librarian, team, head of libraries'))),
        $ask_band(lang('Would you like to help?'), lang('Volunteers read with children, run digital help and bring books to readers at home. Write to us.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: membership and borrowing, visiting and spaces. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'plb-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about library cards, borrowing and renewing, reservations, eBooks, opening hours and booking a room.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Cards, borrowing and visiting. Not answered here? Ask a librarian.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('lib-faq-borrowing', lang('Cards and borrowing'), array(
                            array(lang('Who can join the library?'), lang('Everyone who lives, works or studies in town, at any age. Joining is free; children under sixteen join with a parent or carer.'), lang('membership, join, sign up, library card, free')),
                            array(lang('What do I need to get a card?'), lang('Ask for a card online and bring proof of your address when you collect it: a bill, a bank letter or a rental contract.'), lang('id, proof of address, documents')),
                            array(lang('How many items can I borrow, and for how long?'), lang('Up to fifteen items for three weeks. Renew them at the desk or by phone if nobody else is waiting for them.'), lang('loan period, renew, renewal, how long, limit')),
                            array(lang('Can I reserve a title that is on loan?'), lang('Yes: open the title in the catalogue and send a reservation. We tell you in your account when it is ready to collect.'), lang('reserve, hold, request a book, waiting')),
                        )),
                        $faq_group('lib-faq-visiting', lang('Visiting and spaces'), array(
                            array(lang('Do I need a card to come in?'), lang('No. Anyone may come in to read, study, use the Wi-Fi or join an event; a card is needed only to borrow.'), lang('visit, entry, free, guest')),
                            array(lang('Can I book a room?'), lang('Yes: choose a room on the rooms page and send a request. A librarian confirms the booking in your account.'), lang('room hire, meeting room, study room, hall')),
                            array(lang('Do events cost anything?'), lang('Almost all of our events are free. Some ask you to book a place so we know how many chairs to set out.'), lang('events, price, free, booking, tickets')),
                            array(lang('Where do I return books when the library is closed?'), lang('Every branch has a returns box by the door. Items returned overnight are checked in the next morning.'), lang('return, drop box, closed, after hours')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its new arrivals (home_arrivals) come with the
// catalogue, its events (home_events) with the events, its news
// (home_posts) and the Google reviews (google_reviews) with the standard
// pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
