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
 * Every figure comes from parts.php ($library, $branches, $floors,
 * $loan_rules, $services, $digital, $rooms, $staff, $figures,
 * $testimonials and the pick lists), so a change made there reaches every
 * page here. The home opens with the Library: Shelf Hero and carries the
 * Card and Loans; the visit page draws the week of the three libraries on
 * Hours: Seasons; the Central Library's page lists its floors on the
 * Locations: Floor Directory.
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

// A section header that fades in as it scrolls into view.
$header_in = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($fx, $fade_in, $section_header) {
    return $fx($section_header($eyebrow, $title, $more_text, $more_href), $fade_in);
};

// The cards and tiles of the shared data, in their order.
$service_tiles = array();
foreach ($services as $s) $service_tiles[] = $feature_tile($s['icon'], $s['title'], $s['text']);
$digital_tiles = array();
foreach ($digital as $s) $digital_tiles[] = $feature_tile($s['icon'], $s['title'], $s['text'], '3');
$branch_cols = array();
foreach (array_keys($branches) as $key) $branch_cols[] = $branch_card($key);
$room_cols = array();
foreach (array_keys($rooms) as $key) $room_cols[] = $room_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$staff_cols = array();
foreach (array_keys($staff) as $n) $staff_cols[] = $staff_card($n);


// ── The blocks ──────────────────────────────────────────────────────────
// The trees below draw the palette's blocks (Library: Shelf Hero, Library:
// Card and Loans, Hours: Seasons, Locations: Floor Directory) with the
// library's data: the same structure, classes and interactions, so the
// rules the template ships in User Styles style them as they style a
// dropped block. Headings and eyebrows wear the look's plb- classes.

// A cell of a table: a heading cell names its scope (a column or a row).
$cell = function ($tag, $children, $class = '', $scope = '') use ($el, $attr) {
    $extra = ($scope !== '') ? array('_attrs' => array($attr('scope', $scope))) : array();
    return $el($tag, $class, $tag === 'th' ? lang('Header Cell') : lang('Cell'), $children, $extra);
};
// A table's scroll box: keyboard users reach it and hear its name. It is
// positioned, so a visually hidden caption or heading (position:absolute)
// stays inside it and gives a phone no sideways scroll.
$table_box = function ($table, $label) use ($el, $attr) {
    return $el('div', 'table-responsive position-relative rounded-4 border bg-body p-2 p-md-3 mb-4', lang('Table Wrap'), array($table),
        array('_attrs' => array($attr('role', 'region'), $attr('aria-label', $label), $attr('tabindex', '0'))));
};

// The home's way in, the Library: Shelf Hero block: the library in a
// sentence and the two ways on rise on load; beside them a bookcase whose
// shelf fills with spines set down one after another (a drawing screen
// readers skip), then the Central Library's weekday hours fade in; the
// formats follow as links into the catalogue, each with its filter set.
$shelf_hero = function () use ($el, $heading, $para, $link, $span, $icon, $time, $container, $row, $col, $fx, $attr, $query,
    $library, $branches, $formats, $hours_times, $clock) {
    $spines = array();
    foreach (array(
        array(lang('Poems'), 'text-bg-primary'),
        array(lang('Local History'), 'pg-library-mid bg-body-secondary text-body-emphasis'),
        array(lang('Mysteries'), 'text-bg-secondary'),
        array(lang('Cookbook'), 'pg-library-low bg-primary-subtle text-primary-emphasis'),
        array(lang('Fairy Tales'), 'pg-library-mid text-bg-primary'),
        array(lang('Science Fiction'), 'bg-body text-body-emphasis border'),
        array(lang('Stars'), 'pg-library-low text-bg-secondary'),
        array(lang('Gardening'), 'bg-primary-subtle text-primary-emphasis'),
        array(lang('Short Stories'), 'text-bg-primary'),
        array(lang('Birds'), 'pg-library-mid bg-body-secondary text-body-emphasis'),
        array(lang('Novels'), 'pg-library-low text-bg-secondary'),
    ) as $b) {
        $spines[] = $el('div', 'pg-library-spine ' . $b[1], lang('Book Spine'), array($span($b[0], 'pg-library-title', lang('Text'))));
    }
    $central = $branches['central'];
    $weekday = $central['hours'][0];
    $times = $hours_times($weekday['hours']);
    $formats_links = array();
    foreach ($formats as $f) {
        $formats_links[] = $el('li', '', $f['title'], array(
            $el('a', 'btn btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2', $f['title'], array(
                $icon($f['icon']),
                $span($f['title'], '', lang('Text')),
            ), array('href' => '{{page:catalogue}}?' . $query(array('format' => $f['title'])))),
        ));
    }
    $words = $fx($el('div', '', lang('Body'), array(
        $para(lang('Your public library'), 'plb-eyebrow text-uppercase fw-semibold text-primary mb-3'),
        $heading('h1', lang('Something good to read, watch and do'), 'plb-display display-3 mb-4'),
        $para($library['tagline'] . '.', 'lead text-body-secondary mb-4'),
        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
            $link(lang('Browse the Catalogue'), '{{page:catalogue}}', 'btn btn-primary btn-lg px-4'),
            $link(lang('Join the Library'), '{{page:join}}', 'btn btn-outline-primary btn-lg px-4'),
        )),
    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800));
    $bookcase = $el('div', 'p-4 p-lg-5 rounded-4 bg-body-tertiary', lang('Bookcase'), array(
        $fx($el('div', 'pg-library-shelf', lang('Shelf'), $spines, array('_attrs' => array($attr('aria-hidden', 'true')))),
            array('effect' => 'slide-down', 'trigger' => 'load', 'duration' => 500, 'delay' => 200, 'stagger' => 60, 'distance' => 30)),
        $fx($el('div', 'd-flex flex-wrap align-items-center gap-3 mt-4 p-3 rounded-4 bg-body shadow-sm', lang('Opening Hours'), array(
            $icon('bi-door-open', 'text-primary fs-2'),
            $el('div', '', lang('Text'), array(
                $span($weekday['days'], 'd-block small text-body-secondary mb-1', lang('Days')),
                $el('div', 'pg-library-figure fs-4 fw-bold text-body-emphasis', lang('Hours'), array(
                    $time($clock($times[0]), $times[0], '', lang('Opening Time')),
                    $span(' – ', '', lang('Text')),
                    $time($clock($times[1]), $times[1], '', lang('Closing Time')),
                )),
            )),
            $span($central['title'], 'small fw-semibold ms-sm-auto', lang('Branch')),
        )), array('effect' => 'fade', 'trigger' => 'load', 'duration' => 600, 'delay' => 900)),
    ));
    return $el('section', 'plb-hero py-5 overflow-hidden', lang('Library Hero'), array(
        $container(array(
            $row(array(
                $col(array($words), '', '6'),
                $col(array($bookcase), '', '6'),
            ), '4', '', 'align-items-center g-lg-5'),
            $el('div', 'mt-5 pt-4 border-top', lang('Formats'), array(
                $heading('h2', lang('Browse by format'), 'h6 text-uppercase fw-semibold text-body-secondary mb-3'),
                $fx($el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Formats'), $formats_links),
                    array('effect' => 'fade', 'trigger' => 'load', 'duration' => 600, 'delay' => 1100, 'stagger' => 80)),
            )),
        ), 'py-lg-4'),
    ));
};

// The library card, the Library: Card and Loans block: the card (a
// drawing screen readers skip) slides in with its barcode; beside it what
// a card lends, format by format, in a table that scrolls sideways on a
// narrow phone, three facts and the ways to join and to learn more.
$card_band = function () use ($el, $heading, $para, $link, $span, $icon, $container, $row, $col, $fx, $attr, $searchable,
    $cell, $table_box, $loan_rules, $testimonials) {
    $rows = array();
    foreach ($loan_rules as $r) {
        $rows[] = $el('tr', '', $r['format'], array(
            $cell('th', array($el('div', 'd-flex align-items-center gap-2', lang('Format'), array(
                $icon($r['icon'], 'text-primary'),
                $span($r['format'], '', lang('Text')),
            ))), 'fw-semibold', 'row'),
            $cell('td', array($span($r['count'], '', lang('Text'))), 'pg-library-figure'),
            $cell('td', array($span($r['loan'], '', lang('Text'))), 'text-nowrap'),
            $cell('td', array($span($r['renew'], '', lang('Text'))), 'text-nowrap'),
        ));
    }
    $table = $el('table', 'table align-middle mb-0 pg-library-figure', lang('Loans Table'), array(
        $el('caption', 'visually-hidden', lang('Table Caption'), array($span(lang('What a library card lends, and for how long'), '', lang('Text')))),
        $el('thead', '', lang('Table Head'), array($el('tr', '', lang('Head Row'), array(
            $cell('th', array($span(lang('Format'), '', lang('Text'))), '', 'col'),
            $cell('th', array($span(lang('Items at once'), '', lang('Text'))), 'text-nowrap', 'col'),
            $cell('th', array($span(lang('Loan'), '', lang('Text'))), '', 'col'),
            $cell('th', array($span(lang('Renewals'), '', lang('Text'))), '', 'col'),
        )))),
        $el('tbody', '', lang('Table Body'), $rows),
    ));
    $tick = function ($icon_name, $text) use ($el, $icon, $span) {
        return $el('li', 'd-flex align-items-center gap-2', lang('Item'), array($icon($icon_name, 'text-primary'), $span($text, '', lang('Text'))));
    };
    $card = $fx($el('div', 'pg-library-card d-flex flex-column justify-content-between gap-3 p-4 rounded-4 text-bg-primary shadow-lg', lang('Card'), array(
        $el('div', 'd-flex align-items-start justify-content-between gap-3', lang('Card Top'), array(
            $el('div', 'd-flex align-items-center gap-2', lang('Library Name'), array(
                $icon('bi-book', 'fs-4'),
                $span('{{site_name}}', 'plb-brand', lang('Name')),
            )),
            $span(lang('Library Card'), 'small fw-semibold text-uppercase', lang('Text')),
        )),
        $span($testimonials[0]['name'], 'fs-4 fw-semibold', lang('Name')),
        $el('div', 'p-2 rounded-3 bg-body text-body-emphasis', lang('Barcode'), array(
            $el('div', 'pg-library-barcode', lang('Barcode Lines')),
            $span('7731 0458 2264', 'pg-library-figure d-block text-center small font-monospace mt-1', lang('Text')),
        )),
    ), array('_attrs' => array($attr('aria-hidden', 'true')))), array('effect' => 'slide-right', 'trigger' => 'view', 'duration' => 900, 'distance' => 80));
    return $el('section', 'py-5 bg-body-tertiary overflow-hidden', lang('Library Card and Loans'), array(
        $container(array(
            $row(array(
                $col(array($card), '', '5'),
                $col(array(
                    $fx($el('div', '', lang('Body'), array(
                        $para(lang('Library card'), 'plb-eyebrow text-uppercase fw-semibold text-body-secondary mb-2'),
                        $heading('h2', lang('One free card opens the whole library'), 'plb-display display-6 mb-3'),
                        $para(lang('Ask for a card online and collect it at any branch with proof of your address. Children under sixteen join with a parent or carer.'), 'lead text-body-secondary mb-4'),
                    )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700)),
                    $table_box($table, lang('What a library card lends')),
                    $el('ul', 'list-unstyled d-flex flex-wrap gap-3 gap-lg-4 small mb-4', lang('Facts'), array(
                        $tick('bi-arrow-repeat', lang('Renew at the desk or by phone')),
                        $tick('bi-tablet', lang('eBooks return by themselves')),
                        $tick('bi-emoji-smile', lang('No charges on a young reader\'s card')),
                    )),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $link(lang('Join the Library'), '{{page:join}}', 'btn btn-primary btn-lg px-4'),
                        $link(lang('How Borrowing Works'), '{{page:faq}}', 'btn btn-secondary btn-lg px-4'),
                    )),
                ), '', '7'),
            ), '4', '', 'align-items-center g-lg-5'),
        ), 'py-lg-4'),
    ), $searchable('lib-home-card', lang('library card, membership, how many books, loan period, renew, late fees')));
};

// The visit page's opening hours, the Hours: Seasons block with a column
// per branch where the block has one per season: a row per day of the
// week, a closed day said in a word; the three notes rise on view.
$week_table = function () use ($el, $heading, $para, $link, $span, $icon, $container, $row, $col, $fx, $rise, $fade_in, $searchable,
    $cell, $table_box, $branches, $week_days, $hours_times) {
    $head = array($cell('th', array($span(lang('Day'), 'visually-hidden', lang('Text'))), '', 'col'));
    foreach ($branches as $key => $b) {
        $head[] = $cell('th', array($el('div', 'd-flex align-items-center gap-2', lang('Branch'), array(
            $icon($b['icon'], 'text-primary fs-5'),
            $el('div', '', lang('Branch Name'), array(
                $link($b['title'], '{{page:branch_' . $key . '}}', 'd-block fw-bold link-body-emphasis text-decoration-none'),
                $span($b['phone'], 'd-block small fw-normal text-body-secondary', lang('Phone')),
            )),
        ))), 'text-nowrap', 'col');
    }
    $rows = array();
    foreach ($week_days as $n => $day) {
        $cells = array($cell('th', array($span($day, '', lang('Text'))), 'fw-normal', 'row'));
        foreach ($branches as $b) {
            $times = null;
            foreach ($b['hours'] as $h) {
                if (in_array($n, $h['on'], true)) $times = $hours_times($h['hours']);
            }
            $cells[] = $cell('td', array($times === null
                ? $span(lang('Closed'), 'text-body-secondary', lang('Text'))
                : $span($times[0] . ' – ' . $times[1], 'fw-semibold', lang('Text'))), 'text-nowrap');
        }
        $rows[] = $el('tr', '', $day, $cells);
    }
    $table = $el('table', 'table align-middle mb-0 pg-hours-table', lang('Hours Table'), array(
        $el('caption', 'visually-hidden', lang('Table Caption'), array($span(lang('Opening hours of the three libraries, day by day'), '', lang('Text')))),
        $el('thead', '', lang('Table Head'), array($el('tr', '', lang('Head Row'), $head))),
        $el('tbody', '', lang('Table Body'), $rows),
    ));
    $note = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon, $col) {
        return $col(array(
            $el('div', 'd-flex gap-3 h-100 p-3 rounded-3 bg-body', lang('Note Card'), array(
                $icon($icon_name, 'fs-4 text-primary flex-shrink-0'),
                $el('div', '', lang('Note Copy'), array(
                    $heading('h3', $title, 'h6 fw-bold mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
        ), '', '4');
    };
    return $el('section', 'py-5 bg-body-tertiary', lang('Hours (Seasons)'), array(
        $container(array(
            $fx($el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), array(
                $el('div', '', lang('Section Title'), array(
                    $heading('h2', lang('Opening hours, day by day'), 'plb-display h2 mb-1'),
                    $para(lang('The three libraries side by side. Everyone may come in; a card is needed only to borrow.'), 'text-body-secondary mb-0'),
                )),
                $link(lang('Find a Branch'), '#lib-branches', 'btn btn-primary'),
            )), $fade_in),
            $table_box($table, lang('Opening hours, day by day')),
            $rise($row(array(
                $note('bi-moon-stars', lang('Late openings'), lang('The Central Library stays open until 20:00 on weekdays, the Hillside Branch on Thursdays.')),
                $note('bi-box-arrow-in-down', lang('Returns when we are closed'), lang('Every branch has a returns box by the door; items are checked in the next morning.')),
                $note('bi-calendar-x', lang('Public holidays'), lang('The libraries close on public holidays; the days are posted here a week ahead.')),
            ), '4', '', 'mt-1')),
        )),
    ), $searchable('lib-hours', lang('opening times, open today, closed, sunday, late night')));
};

// A branch's floors, the Locations: Floor Directory block: from the top
// floor down, each with its level, its name and line, what is on it and
// who may go up; the floors rise one after another on view.
$floor_directory = function ($key) use ($el, $heading, $para, $span, $icon, $container, $fx, $rise, $fade_in, $searchable, $floors) {
    $items = array();
    foreach ($floors[$key] as $f) {
        $pills = array();
        foreach ($f['items'] as $text) {
            $pills[] = $el('li', '', $text, array($span($text, 'badge rounded-pill text-wrap bg-body-secondary text-body-emphasis fw-normal', lang('Text'))));
        }
        $items[] = $el('li', 'd-flex gap-4 py-4 border-bottom', $f['name'], array(
            $span($f['level'], 'pg-floor-level rounded-3 bg-primary-subtle text-primary-emphasis fs-4 fw-bold', lang('Level')),
            $el('div', 'flex-grow-1', lang('Floor Text'), array(
                $heading('h3', $f['name'], 'plb-display h5 mb-1'),
                $para($f['text'], 'text-body-secondary mb-3'),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-3', lang('On This Floor'), $pills),
                $el('div', 'd-flex align-items-center gap-2 small fw-semibold', lang('Access'), array(
                    $icon('bi-key', 'text-primary'),
                    $span($f['access'], '', lang('Text')),
                )),
            )),
        ));
    }
    return $el('section', 'py-5', lang('Locations (Floor Directory)'), array(
        $container(array(
            $fx($el('div', 'text-center mb-5', lang('Section Header'), array(
                $heading('h2', lang('Floor by floor'), 'plb-display h2 mb-2'),
                $para(lang('Where everything is, with a lift to every floor.'), 'lead text-body-secondary mb-0'),
            )), $fade_in),
            $el('div', 'col-lg-10 mx-auto', lang('Directory'), array(
                $rise($el('ol', 'list-unstyled mb-0 border-top', lang('Floors'), $items), 100),
            )),
        )),
    ), $searchable('lib-floors-' . $key, lang('floor plan, where is, lift, quiet floor, archive, computers')));
};

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Your public library: borrow books, films and eBooks for free, join a reading club, book a room and find your nearest branch.'),
    'tree'             => $page(array(
        // The way in, then the new arrivals, what a card lends, the
        // branches, the coming events, what readers say and the news.
        $shelf_hero(),

        // The new arrivals, read from the Catalogue collection.
        $el('section', 'py-5', lang('New Arrivals'), array(
            $container(array(
                $header_in(lang('Just in'), lang('New on our shelves'), lang('All Titles'), '{{page:catalogue}}'),
                $widget('home_arrivals'),
            )),
        ), $searchable('lib-home-arrivals', lang('new books, new arrivals, latest, recently added'))),

        $card_band(),

        $el('section', 'py-5', lang('Branches'), array(
            $container(array(
                $header_in(lang('Three libraries'), lang('Find your branch'), lang('Opening Hours'), '{{page:visit}}'),
                $rise($row($branch_cols, '4'), 120),
            )),
        ), $searchable('lib-home-branches', lang('opening hours, address, location, near me, open today'))),

        // The featured events, read from the Events collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Up'), array(
            $container(array(
                $header_in(lang('Events'), lang('Coming up at the library'), lang('All Events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // What readers say: sample words for the library to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $header_in(lang('Our readers'), lang('What readers say')),
                $rise($row($testimonial_cols, '4')),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Latest News'), array(
            $container(array(
                $header_in(lang('News'), lang('News from the library'), lang('All News'), '{{page:blog}}'),
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
        $week_table(),
        $el('section', 'py-5', lang('Branches'), array(
            $container(array(
                $header_in(lang('Our libraries'), lang('Find your branch')),
                $rise($row($branch_cols, '4'), 120),
            )),
        ), $searchable('lib-branches', lang('address, location, map, near me'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Getting Here'), array(
            $container(array(
                $header_in(lang('Before you come'), lang('Getting here and getting in')),
                $rise($row(array(
                    $feature_tile('bi-bus-front', lang('By bus and train'), lang('Every branch is a short walk from a bus stop; Hillside is next to the station.')),
                    $feature_tile('bi-bicycle', lang('By bike'), lang('Bike racks stand at every entrance, under cover at the Central Library.')),
                    $feature_tile('bi-universal-access', lang('Access for everyone'), lang('Step-free entrances, lifts, hearing loops at the desks and large-print guides.')),
                ), '4')),
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
    if (isset($floors[$key])) $sections[] = $floor_directory($key);
    if ($branch_rooms) {
        $sections[] = $el('section', 'py-5 bg-body-tertiary', lang('Rooms to Book'), array(
            $container(array(
                $header_in(lang('Rooms and spaces'), lang('Rooms to book here'), lang('All Rooms'), '{{page:spaces}}'),
                $rise($row($branch_rooms, '4'), 120),
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
                $header_in(lang('Ages 0 to 11'), lang('For children and families')),
                $rise($row(array(
                    $feature_tile('bi-balloon', lang('Story times'), lang('Stories, songs and crafts every week for the under-sixes and their grown-ups.')),
                    $feature_tile('bi-book-half', lang('Books to grow with'), lang('Board books, picture books and first chapter books in every branch.')),
                    $feature_tile('bi-sun', lang('Summer reading challenge'), lang('Read six books over the holidays and collect a medal and a certificate.')),
                ), '4')),
                $el('div', 'mt-4', lang('Buttons'), array(
                    $filter_links('catalogue', 'audience', array($audience_titles[2])),
                )),
            )),
        ), $searchable('lib-children', lang('kids, baby, toddler, story time, picture books, summer reading'))),
        $el('section', 'py-5 bg-body-tertiary', lang('For Teens'), array(
            $container(array(
                $header_in(lang('Ages 12 to 17'), lang('For teens')),
                $rise($row(array(
                    $feature_tile('bi-pencil', lang('Writers\' workshop'), lang('Write stories, poems and comics with a published writer in term time.')),
                    $feature_tile('bi-lamp', lang('Homework and revision'), lang('Quiet desks, Wi-Fi and study guides, open late on weekdays.')),
                    $feature_tile('bi-controller', lang('Graphic novels and games'), lang('Manga, comics and board games to borrow or play at the library.')),
                ), '4')),
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
                $rise($row($digital_tiles, '4')),
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
                $header_in(lang('Browse'), lang('Digital titles in the catalogue')),
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
                $rise($row($room_cols, '4'), 120),
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
                $header_in(lang('Free with your card'), lang('What the library offers')),
                $rise($row($service_tiles, '4')),
            )),
        ), $searchable('lib-about-services', lang('borrow, loan, renew, wifi, computers, printing, home delivery'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Librarians'), array(
            $container(array(
                $header_in(lang('Our people'), lang('Meet the librarians')),
                $rise($row($staff_cols, '4')),
            )),
        ), $searchable('lib-about-staff', lang('staff, librarian, team, head of libraries'))),
        $ask_band(lang('Would you like to help?'), lang('Volunteers read with children, run digital help and bring books to readers at home. Write to us.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Two accordions: membership and borrowing, visiting and spaces. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq, $fx, $fade_in) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'plb-display h4 mb-3'),
        $fx($el('div', 'accordion', lang('Questions'), $children, array('id' => $id)), $fade_in),
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
