<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Art Gallery", its public pages: home, about, visit, art
 * advisory and the questions. Included after parts.php and cards.php in the
 * same scope (their builders, cards and the gallery's data); returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($gallery, $opening_hours, $artists,
 * $exhibitions, $works, $advisory, $buying_steps, $figures,
 * $testimonials), so a change made there reaches every page here. No page
 * here links a shop page: the template stands on a site without the shop.
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

// A picture in a thin frame of the given ratio, loaded lazily unless it is
// the first thing a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' overflow-hidden bg-body-tertiary pgl-frame', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pgl-eyebrow text-uppercase fw-semibold text-body-secondary mb-2'),
        $heading('h2', $title, 'pgl-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $row(array(
                $col(array_merge($words, $more), '', '5', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '7'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line: the gallery's values and the
// ways to reach it.
$feature_tile = function ($icon_name, $title, $text, $lg = '4') use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 border', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pgl-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        )),
    ), '6', $lg);
};

// The gallery in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pgl-display display-5 mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The opening hours as a short list, for the visit page.
$hours_list = function ($class = 'list-unstyled mb-0') use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'pgl-price fw-semibold text-end', lang('Hours')),
        ));
    }
    return $el('ul', $class, lang('Opening Hours'), $items);
};

// Buying a work in four numbered steps.
$buying_step_list = function () use ($el, $step, $buying_steps) {
    $items = array();
    foreach ($buying_steps as $n => $s) $items[] = $step($n + 1, $s['title'], $s['text']);
    return $el('ol', 'list-unstyled mb-0', lang('Steps'), $items);
};

// The cards of the shared data, in their order.
$advisory_cols = array();
foreach (array_keys($advisory) as $key) $advisory_cols[] = $advisory_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// The mediums as links that open the artists of each.
$medium_links = array();
foreach ($mediums as $title) {
    $medium_links[] = $link($title, '{{page:artists}}?' . $query(array('medium' => $title)), 'btn btn-outline-secondary rounded-0');
}

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Contemporary art by the artists we represent: exhibitions now on and coming soon, works to see and buy, private viewings and art advisory.'),
    'tree'             => $page(array(
        // The way in: the exhibition now on, in a large picture with its
        // title, and the two things most visitors come for.
        $el('section', 'pgl-hero py-5', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Now On'), 'pgl-eyebrow text-uppercase fw-semibold text-body-secondary mb-3'),
                        $heading('h1', $exhibitions['after_hours']['title'], 'pgl-display display-3 mb-2'),
                        $para($artists['lena']['name'], 'h5 fw-normal mb-3'),
                        $para($exhibitions['after_hours']['summary'], 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('See the Exhibitions'), '{{page:exhibitions}}', 'btn btn-primary btn-lg rounded-0 px-4'),
                            $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-outline-primary btn-lg rounded-0 px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A large painting of an empty room at dusk hanging on a white gallery wall'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The exhibitions now on and coming, read from the Exhibitions
        // collection.
        $el('section', 'py-5 border-top', lang('Exhibitions'), array(
            $container(array(
                $section_header(lang('On the walls'), lang('Exhibitions now on and coming soon'), lang('All exhibitions'), '{{page:exhibitions}}'),
                $widget('home_exhibitions'),
            )),
        )),

        // The artists the gallery represents, read from the Artists
        // collection, with the mediums they work in.
        $el('section', 'py-5 bg-body-tertiary', lang('Artists'), array(
            $container(array(
                $section_header(lang('The artists'), lang('Artists we represent'), lang('All artists'), '{{page:artists}}'),
                $widget('home_artists'),
                $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Mediums'), $medium_links),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $story_band('gl-home-buying', lang('Collecting'), lang('A work for your wall'),
            array(lang('Every work on the walls and in our store room can be seen up close, reserved for a few days and taken home to try. Prices are given openly, and nobody will hurry you.')),
            $photo('collecting', 1200, 900), lang('A collector looking closely at a small ceramic vessel on a plinth'), true,
            array($link(lang('Browse the Works'), '{{page:works}}', 'btn btn-primary rounded-0 px-4')),
            lang('buy, price, collect, reserve, first work')),

        $el('section', 'py-5 bg-body-tertiary', lang('Art Advisory'), array(
            $container(array(
                $section_header(lang('Beyond the exhibitions'), lang('Art advisory'), lang('How we can help'), '{{page:advisory}}'),
                $row(array_slice($advisory_cols, 0, 3), '4'),
            )),
        )),

        // What collectors say: sample words for the gallery to replace.
        $el('section', 'py-5', lang('Collector Stories'), array(
            $container(array(
                $section_header(lang('Collectors'), lang('What collectors say')),
                $row($testimonial_cols, '4'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Journal'), array(
            $container(array(
                $section_header(lang('Journal'), lang('Studio visits and conversations'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-people', 'title' => lang('Artists first'), 'text' => lang('We show only the artists we represent and follow each of them for years, not for one season.')),
    array('icon' => 'bi-door-open', 'title' => lang('Open to everyone'), 'text' => lang('Entry is always free, every opening night is open to all, and no question about a work is a silly one.')),
    array('icon' => 'bi-eye', 'title' => lang('Prices in the open'), 'text' => lang('Every work has its price on the list in the room; ask and we tell you, whoever you are.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A contemporary art gallery in a former print workshop: our story, the artists we represent and how we work with collectors.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $gallery['tagline'] . '.'),
        $story_band('gl-our-story', lang('Our story'), lang('From a print workshop to a gallery'),
            array($gallery['story']),
            $photo('about', 1200, 900), lang('The long white front room of the gallery with paintings on both walls'), false, array(),
            lang('history, who we are, founded, building')),
        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('How we work'), lang('What we believe in')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('gl-values', lang('values, approach, prices, free entry'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Artists'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Meet the artists'), 'pgl-display h4 mb-1'),
                        $para(lang('Painters, sculptors, a photographer, a draughtswoman and a potter, each with a studio of their own.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Artists'), '{{page:artists}}', 'btn btn-primary rounded-0 px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── Visit ───────────────────────────────────────────────────────────────

$ways_here = array(
    array('icon' => 'bi-train-front', 'title' => lang('By tram'), 'text' => lang('The Old Harbour stop is a two-minute walk; follow the harbour wall to the foundry chimney.')),
    array('icon' => 'bi-bicycle', 'title' => lang('By bicycle'), 'text' => lang('Racks stand in the courtyard beside the sculptures, inside the gate.')),
    array('icon' => 'bi-universal-access', 'title' => lang('Access'), 'text' => $gallery['access'] . '.'),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'visit',
    'name'             => lang('visit'),
    'folder'           => 'public',
    'title'            => lang('Visit'),
    'meta_description' => lang('Where the gallery is, its opening hours and free entry, how to get here and how to book a private viewing outside the opening hours.'),
    'tree'             => $page(array(
        $page_header(lang('Visit'), lang('Entry is free. Walk in during opening hours, or book a private viewing for a quiet look at any time.')),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Find us'), 'pgl-eyebrow text-uppercase fw-semibold text-body-secondary mb-2'),
                        $heading('h2', $gallery['address'], 'pgl-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone'),
                                $link($gallery['phone'], $tel($gallery['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope'),
                                $link($gallery['email'], 'mailto:' . $gallery['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list('list-unstyled mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book a Private Viewing'), '{{page:viewing}}', 'btn btn-primary rounded-0 px-4'),
                            $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($gallery['address']), 'btn btn-outline-primary rounded-0 px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                        )),
                    ), '', '5'),
                    $col(array($framed($photo('visit', 1400, 1050), lang('The gallery\'s brick front with its tall windows and the foundry chimney behind'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('gl-address', lang('address, map, directions, opening hours, free entry, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('gl-travel', lang('tram, bicycle, parking, wheelchair, lift, access'))),
        $story_band('gl-openings', lang('Opening nights'), lang('Every exhibition opens with a night for everyone'),
            array(lang('The artist is there, the doors are open to anyone and there is no list to be on. Opening nights are announced on each exhibition\'s page and in the newsletter.')),
            $photo('opening', 1200, 900), lang('Visitors talking in front of large photographs on an opening night'), true,
            array($link(lang('Subscribe to the Newsletter'), '{{page:newsletter}}', 'btn btn-outline-primary rounded-0 px-4')),
            lang('opening, private view, vernissage, event, invitation')),
    )),
);

// ── Art advisory ────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'advisory',
    'name'             => lang('art-advisory'),
    'folder'           => 'public',
    'title'            => lang('Art Advisory'),
    'meta_description' => lang('Help with a first work, a growing collection, art for a workplace, a commission or framing: what the gallery does beyond its exhibitions.'),
    'tree'             => $page(array(
        $page_header(lang('Art Advisory'), lang('Whether it is your first work or your fiftieth, we help you find, buy, frame and hang it.')),
        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $row($advisory_cols, '4'),
            )),
        ), $searchable('gl-advisory', lang('advice, consultant, corporate, office, commission, framing, installation'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Buying a Work'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Collecting'), 'pgl-eyebrow text-uppercase fw-semibold text-body-secondary mb-2'),
                        $heading('h2', lang('How buying a work goes'), 'pgl-display h2 mb-3'),
                        $para(lang('No pressure and no hidden costs: a work is yours only when you are sure, and it arrives framed, insured and with its certificate.'), 'text-body-secondary mb-4'),
                        $link(lang('Ask About a Work'), '{{page:enquire}}', 'btn btn-primary rounded-0 px-4'),
                    ), '', '5'),
                    $col(array($buying_step_list()), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('gl-buying', lang('buy, reserve, payment, delivery, certificate, insurance'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: visiting, buying and the artists. Each question is a
// search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pgl-display h4 mb-3'),
        $el('div', 'accordion accordion-flush', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about visiting the gallery, opening nights, buying and reserving a work, prices, delivery and commissions.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Visiting, buying and the artists. Not answered here? Ask us about any work.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('gl-faq-visit', lang('Visiting'), array(
                            array(lang('Is there an entry fee?'), lang('No. Entry to every exhibition is free, and so is every opening night.'), lang('ticket, price, free, admission')),
                            array(lang('Can I see the works outside opening hours?'), lang('Yes: book a private viewing for a morning, an afternoon or an evening, alone or with a few people.'), lang('appointment, private view, Sunday, Monday')),
                        )),
                        $faq_group('gl-faq-buying', lang('Buying a work'), array(
                            array(lang('How do I know the price of a work?'), lang('Most prices are on the work\'s page and on the list in the room. For a work marked "price on request", send an enquiry and we answer the same day.'), lang('price, cost, how much, price list')),
                            array(lang('Can I reserve a work?'), lang('Yes, for a few days while you decide. Ask from the work\'s page; nothing is paid before you are sure.'), lang('hold, reserve, deposit')),
                            array(lang('Do you deliver and hang works?'), lang('We frame, pack, insure and deliver every work, and our technician hangs or sets it if you wish.'), lang('delivery, shipping, framing, installation, abroad')),
                        )),
                        $faq_group('gl-faq-artists', lang('The artists'), array(
                            array(lang('Can I commission a work?'), lang('Yes. Tell us the place, the size and the occasion; we agree the brief and the price with the artist before they begin.'), lang('commission, custom, bespoke')),
                            array(lang('Can I meet the artists?'), lang('At every opening night, and on a studio visit we arrange for collectors.'), lang('studio visit, meet, talk')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its exhibitions (home_exhibitions) come with the
// exhibitions, its artists (home_artists) with the artists and its
// articles (home_posts) with the standard pages; this part places no
// widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
