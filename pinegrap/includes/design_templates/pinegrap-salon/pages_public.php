<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Beauty Salon", its public pages: home, services and
 * prices, the team, the lookbook and the about page. Included after
 * parts.php in the same scope (its builders and the salon's data); returns
 * the list of pages.
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

// ── Home ────────────────────────────────────────────────────────────────
// The way to book, the categories, the featured services, the team, how
// booking works, a strip of the lookbook, what clients say and the newest
// posts.

// A category card of the home page, linked to its section of the services
// page.
$category_cards = array();
foreach ($categories as $key => $c) {
    $category_cards[] = $col(array(
        $el('a', 'card h-100 border-0 bg-body-tertiary rounded-4 text-decoration-none link-body-emphasis', $c['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($c['icon'], 'mb-3'),
                $heading('h3', $c['title'], 'h5 fw-semibold mb-2'),
                $para($c['text'], 'small text-body-secondary mb-3'),
                $el('span', 'd-inline-flex align-items-center gap-1 fw-semibold small text-primary', lang('Link Text'), array(
                    $span(lang('See prices'), '', lang('Text')),
                    $icon('bi-arrow-right'),
                )),
            )),
        ), array('href' => '{{page:services}}#category-' . $key)),
    ), '6', '3');
}

// A team member as a small card: the portrait, the name and the role.
$member_cards = array();
foreach ($team as $key => $t) {
    $member_cards[] = $col(array(
        $el('a', 'card h-100 border-0 text-center bg-transparent text-decoration-none link-body-emphasis', $t['name'], array(
            $el('div', 'card-body p-3', lang('Card Body'), array(
                $image($portrait($t['name'], 320), $t['name'], 'rounded-circle object-fit-cover mb-3', array('width' => '144', 'height' => '144', '_attrs' => array($attr('loading', 'lazy')))),
                $heading('h3', $t['name'], 'h6 fw-semibold mb-1'),
                $para($t['role'], 'small text-primary mb-0'),
            )),
        ), array('href' => '{{page:team}}#member-' . $key)),
    ), '', '3', '', '6');
}

// A picture of the lookbook, with what was done under it.
$look = function ($seed, $caption, $class = '') use ($el, $image, $para, $photo, $attr) {
    return $el('figure', trim('mb-0 ' . $class), lang('Picture'), array(
        $el('div', 'ratio ratio-1x1 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
            $image($photo($seed, 800, 800), $caption, 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
        )),
        $el('figcaption', 'small text-body-secondary mt-2', lang('Caption'), array(), array('text' => $caption)),
    ));
};

// What clients say.
$testimonial = function ($quote, $who, $what) use ($el, $para, $stars, $col) {
    return $col(array(
        $el('figure', 'h-100 p-4 rounded-4 bg-body mb-0', lang('Testimonial'), array(
            $stars(),
            $el('blockquote', 'mb-3', lang('Quote'), array($para($quote, 'mb-0'))),
            $el('figcaption', 'small', lang('Author'), array(
                $para($who, 'fw-semibold mb-0'),
                $para($what, 'text-body-secondary mb-0'),
            )),
        )),
    ), '', '4');
};

// The featured services: one of each category, with its price.
$featured = array();
foreach (array('balayage', 'mani', 'facial', 'bridal') as $key) {
    $s = $services[$key];
    $featured[] = $el('li', 'list-group-item d-flex justify-content-between align-items-start gap-3 px-0 py-3 bg-transparent', $s['title'], array(
        $el('div', '', lang('Text'), array(
            $heading('h3', $s['title'], 'h6 fw-semibold mb-1'),
            $para($s['duration'], 'small text-body-secondary mb-0'),
        )),
        $span($s['price'], 'fw-semibold text-nowrap', lang('Price')),
    ));
}

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Hair, nails, skin and make-up by a small team. See the prices, choose your stylist and book online.'),
    'tree'             => $page(array(
        // The way to book: the words and buttons on the left, a picture of
        // the studio with the hours on the right.
        $el('section', 'py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Hair · Nails · Skin · Make-up'), 'small text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Feel like yourself, only more so'), 'display-4 fw-semibold mb-3'),
                        $para(lang('A calm studio and a small team who listen first. Choose your service and stylist online; we confirm your time by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('Book Now'), '{{page:appointment}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Services & Prices'), '{{page:services}}', 'btn btn-outline-secondary btn-lg rounded-pill px-4'),
                        )),
                        $el('div', 'd-flex align-items-center gap-3', lang('Rating'), array(
                            $stars(),
                            $span(lang('4.9 from 320 client reviews'), 'small text-body-secondary', lang('Text')),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'position-relative', lang('Picture Area'), array(
                            $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden shadow-sm', lang('Picture Frame'), array(
                                $image($photo('hero', 1200, 900), lang('The studio, with two styling chairs by the window'), 'object-fit-cover'),
                            )),
                            $el('div', 'card border-0 shadow position-absolute bottom-0 start-0 m-3', lang('Hours Card'), array(
                                $el('div', 'card-body py-3 px-4', lang('Card Body'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                        $icon('bi-clock', 'text-primary'),
                                        $span(lang('Opening hours'), '', lang('Text')),
                                    )),
                                    $hours(),
                                )),
                            )),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The categories.
        $el('section', 'py-5 border-top', lang('Services'), array(
            $container(array(
                $section_header(lang('What we do'), lang('Our services'), lang('All services and prices'), '{{page:services}}'),
                $row($category_cards, '4'),
            )),
        )),

        // The featured services beside a picture.
        $el('section', 'py-5 bg-body-tertiary', lang('Favourites'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-1x1 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('favourites', 900, 900), lang('A colourist painting balayage onto long hair'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '5'),
                    $col(array(
                        $para(lang('Client favourites'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What clients book most'), 'h3 fw-semibold mb-3'),
                        $el('ul', 'list-group list-group-flush mb-4', lang('List'), $featured),
                        $link(lang('Book a favourite'), '{{page:appointment}}', 'btn btn-primary rounded-pill px-4'),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The team.
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The team'), lang('Meet your stylists'), lang('The whole team'), '{{page:team}}'),
                $row($member_cards, '4'),
            )),
        )),

        // How booking works.
        $el('section', 'py-5 border-top', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Booking online'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Booked in three steps'), 'h3 fw-semibold mb-3'),
                        $para(lang('No account needed to begin: the form opens one for you, so you can follow your booking and write to us afterwards.'), 'text-body-secondary mb-4'),
                        $link(lang('Book Now'), '{{page:appointment}}', 'btn btn-primary rounded-pill px-4'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Choose what you would like'), lang('The service, a stylist if you have one in mind, the day and a time of day.')),
                            $step(2, lang('We confirm by e-mail'), lang('We check the diary and confirm your time, usually within the hour.')),
                            $step(3, lang('Relax and enjoy'), lang('Arrive five minutes early. Need to change something? Write under your booking.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // A strip of the lookbook.
        $el('section', 'py-5 bg-body-tertiary', lang('Lookbook'), array(
            $container(array(
                $section_header(lang('Recent work'), lang('From the lookbook'), lang('See the lookbook'), '{{page:lookbook}}'),
                $row(array(
                    $col(array($look('look-1', lang('Soft caramel balayage'))), '', '3', '', '6'),
                    $col(array($look('look-2', lang('Textured bob'))), '', '3', '', '6'),
                    $col(array($look('look-3', lang('Milky nude gel nails'))), '', '3', '', '6'),
                    $col(array($look('look-4', lang('Glowing bridal make-up'))), '', '3', '', '6'),
                ), '4'),
            )),
        )),

        // What clients say.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What clients say')),
                $row(array(
                    $testimonial(lang('“Selin finally gave me the blonde I had been describing for years, and it grows out beautifully.”'), lang('Ece T.'), lang('Balayage & Highlights')),
                    $testimonial(lang('“My gel manicure lasted three full weeks. The studio is so calm I nearly fell asleep.”'), lang('Zeynep A.'), lang('Gel Manicure')),
                    $testimonial(lang('“Leyla did my wedding make-up. It looked perfect in every photo and lasted until the last dance.”'), lang('Burcu and Can Y.'), lang('Bridal Make-up')),
                ), '4'),
            )),
        )),

        // The blog's three newest posts.
        $el('section', 'py-5 border-top', lang('From the Blog'), array(
            $container(array(
                $section_header(lang('Beauty notes'), lang('From our blog'), lang('All posts'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('booking_band'),
    )),
);

// ── Services & Prices ───────────────────────────────────────────────────
// One section for each category, its services as a price list; the
// packages and the common questions under them.
$category_sections = array();
$n = 0;
foreach ($categories as $key => $c) {
    $rows = array();
    foreach ($services as $skey => $s) {
        if ($s['category'] !== $key) continue;
        $rows[] = $el('li', 'list-group-item d-flex flex-wrap justify-content-between align-items-start gap-2 px-0 py-3 bg-transparent', $s['title'], array(
            $el('div', 'me-auto', lang('Text'), array(
                $heading('h3', $s['title'], 'h6 fw-semibold mb-1'),
                $para($s['text'], 'small text-body-secondary mb-1'),
                $el('span', 'd-inline-flex align-items-center gap-1 small text-body-secondary', lang('Duration'), array($icon('bi-clock'), $span($s['duration'], '', lang('Text')))),
            )),
            $span($s['price'], 'fw-semibold text-nowrap', lang('Price')),
        ));
    }
    $category_sections[] = $el('section', 'py-5' . ($n % 2 ? ' bg-body-tertiary' : ''), $c['title'], array(
        $container(array(
            $row(array(
                $col(array(
                    $icon_tile($c['icon'], 'mb-3'),
                    $heading('h2', $c['title'], 'h3 fw-semibold mb-2'),
                    $para($c['text'], 'text-body-secondary mb-4'),
                    $link(lang('Book this treatment'), '{{page:appointment}}', 'btn btn-outline-primary rounded-pill px-4'),
                ), '', '4'),
                $col(array(
                    $el('ul', 'list-group list-group-flush', lang('Price List'), $rows),
                ), '', '8'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable('category-' . $key, $c['topics']));
    $n++;
}

$package_cards = array();
foreach ($packages as $p) {
    $is_featured = !empty($p['featured']);
    $package_cards[] = $col(array(
        $el('div', 'card h-100 rounded-4 ' . ($is_featured ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), $p['title'], array_merge(
            $is_featured ? array($el('div', 'card-header bg-primary-subtle text-primary-emphasis text-center small fw-semibold border-0', lang('Badge'), array(), array('text' => lang('Most chosen')))) : array(),
            array($el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $heading('h3', $p['title'], 'h5 fw-semibold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $para($p['price'], 'display-6 fw-semibold mb-3'),
                $check_list($p['items'], 'list-unstyled small mb-4'),
                $link(lang('Book this package'), '{{page:appointment}}', 'btn rounded-pill ' . ($is_featured ? 'btn-primary' : 'btn-outline-primary') . ' mt-auto'),
            )))
        )),
    ), '', '4');
}

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Services & Prices'),
    'meta_description' => lang('Cuts, colour, nails, facials, brows and make-up: what each service includes, how long it takes and what it costs.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Services & Prices'), lang('What each service includes, how long it takes and what it costs. Your stylist confirms the price before starting.'))),
        $category_sections,
        array(
            $el('section', 'py-5 border-top', lang('Packages'), array(
                $container(array(
                    $section_header(lang('Treat yourself'), lang('Packages')),
                    $row($package_cards, '4', '', 'align-items-stretch'),
                )),
            ), $searchable('packages', lang('package, pamper day, bridal package, gift'))),
            $el('section', 'py-5 bg-body-tertiary', lang('FAQ'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $para(lang('Good to know'), 'small text-uppercase fw-semibold text-primary mb-1'),
                            $heading('h2', lang('Before your visit'), 'h3 fw-semibold mb-3'),
                            $para(lang('Anything else? Write to us and the answer comes to your inbox.'), 'text-body-secondary mb-3'),
                            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-outline-primary rounded-pill px-4'),
                        ), '', '4'),
                        $col(array(
                            $el('div', 'accordion', lang('Questions'), array(
                                $faq(1, lang('Do I need a patch test before colour?'), lang('Yes, for a first colour or lash tint we do a small patch test at least 48 hours before; it takes five minutes.'), lang('patch test, allergy, colour, tint')),
                                $faq(2, lang('Can I change or cancel my booking?'), lang('Of course. Write under your booking or call us at least 24 hours before, and we find you another time.'), lang('cancel, change, reschedule, booking')),
                                $faq(3, lang('Why is the price shown as "From"?'), lang('Longer or thicker hair needs more time and product. Your stylist tells you the exact price before starting.'), lang('price, cost, from, long hair')),
                                $faq(4, lang('Do you offer gift vouchers?'), lang('Yes, for any amount or service. Ask us by message and we prepare one for you.'), lang('gift, voucher, present')),
                            ), array('id' => 'sl-faq')),
                        ), '', '8'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
            $shared('booking_band'),
        )
    )),
);

// ── Team ────────────────────────────────────────────────────────────────
// A profile for each, with what they do, the days they are in and the way
// to book with them.
$member_profiles = array();
foreach ($team as $key => $t) {
    $tags = array();
    foreach ($t['categories'] as $c) $tags[] = $span($categories[$c]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Badge'));
    $member_profiles[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden', $t['name'], array(
            $el('div', 'row g-0 h-100', lang('Card Row'), array(
                $el('div', 'col-sm-5', lang('Portrait'), array(
                    $el('div', 'ratio ratio-1x1 h-100 bg-primary-subtle', lang('Picture Frame'), array(
                        $image($portrait($t['name']), $t['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                    )),
                )),
                $el('div', 'col-sm-7', lang('Details'), array(
                    $el('div', 'card-body d-flex flex-column h-100 p-4', lang('Card Body'), array(
                        $el('div', 'd-flex flex-wrap gap-1 mb-2', lang('Badges'), $tags),
                        $heading('h2', $t['name'], 'h5 fw-semibold mb-1'),
                        $para($t['role'], 'small text-primary mb-2'),
                        $para($t['bio'], 'small text-body-secondary mb-3'),
                        $el('p', 'd-flex align-items-start gap-2 small mb-3', lang('Days'), array($icon('bi-calendar-week', 'text-primary'), $span($t['days'], '', lang('Text')))),
                        $el('a', 'btn btn-sm btn-outline-primary rounded-pill align-self-start mt-auto', lang('Link'), array(
                            $span(lang('Book with me'), '', lang('Link Text')),
                            $span($t['name'], 'visually-hidden', lang('Name')),
                        ), array('href' => '{{page:appointment}}')),
                    )),
                )),
            )),
        ), $searchable('member-' . $key, $t['role'])),
    ), '', '6');
}

$public_pages[] = array(
    'key'              => 'team',
    'name'             => lang('team'),
    'folder'           => 'public',
    'title'            => lang('Our Team'),
    'meta_description' => lang('The stylists, nail artist and skin therapist of the salon, what each does best and the days they are in.'),
    'tree'             => $page(array(
        $page_header(lang('Our Team'), lang('A small team who remember how you like it. Choose one, or let us find the first free time.')),
        $el('section', 'py-5', lang('Team'), array(
            $container(array(
                $row($member_profiles, '4'),
                $el('div', 'alert alert-light border d-flex gap-2 mt-4 mb-0', lang('Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('No preference? Choose "No preference" on the booking form and we book you with the first stylist free for the service.'), '', lang('Text')),
                )),
            )),
        )),
        $shared('booking_band'),
    )),
);

// ── Lookbook ────────────────────────────────────────────────────────────
// Recent work by category: each a search section, the pictures in a grid.
$looks = array(
    'hair'   => array(lang('Soft caramel balayage'), lang('Textured bob'), lang('Copper all-over colour'), lang('Curls cut dry')),
    'nails'  => array(lang('Milky nude gel nails'), lang('Fine-line nail art'), lang('Classic red pedicure'), lang('Chrome French tips')),
    'skin'   => array(lang('Laminated brows'), lang('Lash lift and tint'), lang('Glow facial, after')),
    'makeup' => array(lang('Glowing bridal make-up'), lang('Evening smoky eye'), lang('Soft editorial look')),
);
$look_sections = array();
foreach ($looks as $key => $captions) {
    $cols = array();
    foreach ($captions as $i => $caption) {
        $cols[] = $col(array($look($key . '-' . ($i + 1), $caption)), '4', '3', '', '6');
    }
    $look_sections[] = $el('section', 'py-4', $categories[$key]['title'], array(
        $heading('h2', $categories[$key]['title'], 'h4 fw-semibold mb-3'),
        $row($cols, '3'),
    ), $searchable('looks-' . $key, $categories[$key]['topics']));
}

$public_pages[] = array(
    'key'              => 'lookbook',
    'name'             => lang('lookbook'),
    'folder'           => 'public',
    'title'            => lang('Lookbook'),
    'meta_description' => lang('Recent hair, nail, brow and make-up work from the salon, to bring along as an idea for your own visit.'),
    'tree'             => $page(array(
        $page_header(lang('Lookbook'), lang('Recent work from the studio. Seen something you like? Mention it on the booking form.')),
        $el('div', 'py-4', lang('Looks'), array(
            $container($look_sections),
        )),
        $shared('booking_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────
$value = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('div', 'h-100', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'h5 fw-semibold mb-2'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('How the salon began, what we care about and the products we use.'),
    'tree'             => $page(array(
        $page_header(lang('About'), lang('A small studio that started with one chair and a simple idea: listen first.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('The salon\'s founder at work by the window'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('From one chair to a small studio'), 'h3 fw-semibold mb-3'),
                        $para(lang('Selin opened the salon in 2014 with one chair and a list of regulars. Today four of us share the studio, and many of those first clients still come every month.'), 'text-body-secondary'),
                        $para(lang('We keep the diary unhurried, so there is time for a proper consultation and a cup of tea, and we never book two clients into the same chair.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('story, founder, history'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('Our promise')),
                $row(array(
                    $value('bi-ear', lang('We listen first'), lang('Every visit starts with a consultation, so you leave with the look you asked for.')),
                    $value('bi-flower1', lang('Kind products'), lang('Vegan, cruelty-free colour and care, without harsh smells.')),
                    $value('bi-shield-check', lang('Clean and safe'), lang('Tools sterilised after every client and single-use files for nails.')),
                    $value('bi-recycle', lang('Less waste'), lang('We recycle foils and colour tubes and refill our own bottles.')),
                ), '4'),
            )),
        ), $searchable('values', lang('vegan, cruelty-free, hygiene, sterilised, products'))),
        $shared('booking_band'),
    )),
);

return $public_pages;
