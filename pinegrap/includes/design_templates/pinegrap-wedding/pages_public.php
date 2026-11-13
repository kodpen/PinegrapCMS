<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Wedding Planner and Venue", its public pages: home,
 * about, the planning services, the venue packages, the venue and its
 * spaces, the gallery, the planning timeline and the questions page.
 * Included after parts.php in the same scope (its builders and the
 * planner's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($planner, $services, $packages,
 * $spaces, $gallery, $timeline, $why_us, $figures, $testimonials,
 * $deposit), so a change made there reaches every page here. No page here
 * links a shop page: the template stands on a site without the shop.
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

// A picture in a rounded frame of the given ratio, loaded lazily unless it
// is the first thing a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A node with an interaction (props._fx, played by pg_fx; reduced motion
// is honoured there).
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// A list or a row whose items rise in one after another on view.
$rise = function ($node, $stagger = 90) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger);
    return $node;
};

// The Wedding blocks' ornament: two hairlines and a heart between them
// (pg-wedding-rule), decoration only.
$ornament = function ($class) use ($el, $icon, $attr) {
    return $el('div', trim('pg-wedding-rule ' . $class), lang('Divider'), array($icon('bi-suit-heart')),
        array('_attrs' => array($attr('aria-hidden', 'true'))));
};

// Wedding: Invitation Hero. The garden laid for dinner as a wide photo and
// the invitation card over its lower edge (pg-wedding-invite): the
// planner's line, the two ways on and its facts as a description list.
// The photo fades in and the card rises, both on load.
$invitation_hero = function () use ($el, $heading, $para, $link, $image, $icon, $span, $container, $photo, $planner, $ornament, $fx) {
    $facts = array(
        array('bi-calendar-heart', lang('Weddings'), lang('One a day')),
        array('bi-people', lang('Guests'), lang('From 40 to 300')),
        array('bi-tree', lang('Spaces'), lang('Four, indoors and out')),
    );
    $details = array();
    foreach ($facts as $f) {
        $details[] = $el('div', 'col', $f[1], array(
            $icon($f[0], 'd-block fs-4 text-primary mb-1'),
            $el('dt', 'small text-uppercase fw-semibold text-body-secondary', lang('Term'), array($span($f[1], '', lang('Text')))),
            $el('dd', 'fw-semibold mb-0', lang('Value'), array($span($f[2], '', lang('Text')))),
        ));
    }
    $picture = $el('div', 'ratio ratio-21x9 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
        $image($photo('hero', 1600, 690), lang('Long tables laid for a wedding dinner under olive trees'), 'object-fit-cover'),
    ));
    $card = $el('div', 'pg-wedding-invite position-relative col-lg-8 col-xl-7 mx-auto mt-4 p-4 p-md-5 rounded-4 bg-body shadow text-center', lang('Invitation Card'), array(
        $para(lang('Planning and a venue by the sea'), 'pwd-eyebrow small text-uppercase fw-semibold text-primary mb-3'),
        $heading('h1', lang('Your day, beautifully planned'), 'pwd-display display-4 mb-3'),
        $ornament('col-8 col-md-6 mx-auto mb-3'),
        $para($planner['tagline'] . '.', 'lead text-body-secondary mb-4'),
        $el('dl', 'row row-cols-1 row-cols-sm-3 g-3 mb-4', lang('Details'), $details),
        $el('div', 'd-flex flex-wrap justify-content-center gap-2', lang('Buttons'), array(
            $link(lang('Book a Venue Visit'), '{{page:book_visit}}', 'btn btn-primary btn-lg rounded-pill px-4'),
            $link(lang('See the Packages'), '{{page:packages}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
        )),
    ));
    return $el('section', 'py-5 overflow-hidden', lang('Hero'), array(
        $container(array(
            $fx($picture, array('effect' => 'fade', 'trigger' => 'load', 'duration' => 1000)),
            $fx($card, array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700, 'delay' => 300)),
        )),
    ));
};

// Wedding: Real Wedding Story. One sample wedding told in a band: three
// of its pictures (pg-wedding-mosaic), the couple, their day in facts,
// their own words and the suppliers who made it. The same words and
// pictures as its record in the Real Weddings form.
$wedding_story = function ($key, $id, $topics) use ($el, $heading, $para, $link, $image, $icon, $span, $container, $row, $col, $attr, $photo, $weddings, $spaces, $seasons, $fx, $searchable) {
    $w = $weddings[$key];
    $lazy = array('_attrs' => array($attr('loading', 'lazy')));
    $facts = array();
    foreach (array(
        array('bi-tree', $spaces[$w['space']]['title'], lang('Venue Space')),
        array('bi-sun', $seasons[$w['season']]['title'], lang('Season')),
        array('bi-people', $w['guests'] . ' ' . lang('guests'), lang('Guests')),
    ) as $f) {
        $facts[] = $el('li', 'd-flex align-items-center gap-2', $f[2], array(
            $icon($f[0], 'text-primary'),
            $span($f[2], 'visually-hidden', lang('Label')),
            $span($f[1], 'fw-semibold', lang('Value')),
        ));
    }
    $suppliers = array();
    foreach ($w['suppliers'] as $s) {
        $suppliers[] = $el('dt', 'col-5 fw-normal text-body-secondary', lang('Role'), array($span($s[0], '', lang('Text'))));
        $suppliers[] = $el('dd', 'col-7 fw-semibold', lang('Supplier'), array($span($s[1], '', lang('Text'))));
    }
    $mosaic = $el('div', 'pg-wedding-mosaic', lang('Picture Mosaic'), array(
        $image($photo('wedding-' . $key, 720, 960), $w['couple'], 'rounded-4', $lazy),
        $image($photo('wedding-' . $key . '-2', 480, 360), $w['couple'], 'rounded-4', $lazy),
        $image($photo('wedding-' . $key . '-3', 480, 360), $w['couple'], 'rounded-4', $lazy),
    ));
    $body = $el('div', '', lang('Story Body'), array(
        $para(lang('Real wedding'), 'pwd-eyebrow small text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $w['couple'], 'pwd-display display-6 mb-3'),
        $el('ul', 'list-unstyled d-flex flex-wrap gap-3 mb-3', lang('Wedding Facts'), $facts),
        $para($w['summary'], 'text-body-secondary mb-4'),
        $el('figure', 'border-start border-3 border-primary ps-3 mb-4', lang('Quote'), array(
            $el('blockquote', 'blockquote mb-2', lang('Quote Text'), array($para($w['quote'], 'pwd-quote mb-0'))),
            $el('figcaption', 'small text-body-secondary', lang('Caption'), array($span($w['couple'], '', lang('Text')))),
        )),
        $heading('h3', lang('The suppliers'), 'h6 text-uppercase fw-semibold mb-2'),
        $el('dl', 'row small mb-4', lang('Suppliers'), $suppliers),
        $link(lang('All Real Weddings'), '{{page:weddings}}', 'btn btn-outline-primary rounded-pill px-4'),
    ));
    return $el('section', 'py-5', lang('Real Wedding Story'), array(
        $container(array(
            $row(array(
                $col(array($fx($mosaic, array('effect' => 'fade', 'trigger' => 'view', 'duration' => 900))), '', '6'),
                $col(array($fx($body, array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => 80))), '', '6'),
            ), '4', '', 'align-items-center g-lg-5'),
        )),
    ), $searchable($id, $topics));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pwd-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pwd-display h2 mb-3'),
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

// A tile with an icon, a title and a line: why couples choose the planner
// and its values.
$feature_tile = function ($icon_name, $title, $text, $lg = '3') use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pwd-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        )),
    ), '6', $lg);
};

// The planner in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pwd-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'pwd-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
            )),
        )),
    ));
};

// The cards of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$service_cols = array();
foreach (array_keys($services) as $key) $service_cols[] = $service_card($key);
$package_cols = array();
foreach (array_keys($packages) as $key) $package_cols[] = $package_card($key);
$space_cols = array();
foreach (array_keys($spaces) as $key) $space_cols[] = $space_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$stage_items = array();
foreach (array_keys($timeline) as $n) $stage_items[] = $timeline_stage($n);
$deposit_text = $money($deposit);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Wedding planning and an olive estate by the sea for one wedding a day: packages, spaces, real weddings and venue visits.'),
    'tree'             => $page(array(
        // The way in: the invitation over the garden laid for dinner.
        $invitation_hero(),

        $el('section', 'py-5', lang('Planning Services'), array(
            $container(array(
                $section_header(lang('How we help'), lang('Planning your way'), lang('All services'), '{{page:services}}'),
                $rise($row($service_cols, '4')),
            )),
        )),

        // One real wedding told in full, then the featured ones read from
        // the Real Weddings collection.
        $wedding_story('selin-emre', 'wd-home-story', lang('wedding stories, inspiration, suppliers, photographer, florist')),

        $el('section', 'py-5 bg-body-tertiary', lang('Real Weddings'), array(
            $container(array(
                $section_header(lang('Love stories'), lang('Real weddings at the estate'), lang('All Real Weddings'), '{{page:weddings}}'),
                $widget('home_weddings'),
            )),
        )),

        $el('section', 'py-5', lang('The Venue'), array(
            $container(array(
                $section_header(lang('One estate, four spaces'), lang('A space for every wedding'), lang('See the venue'), '{{page:venue}}'),
                $rise($row($space_cols, '4')),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Packages'), array(
            $container(array(
                $section_header(lang('Clear prices'), lang('Venue packages'), lang('Compare packages'), '{{page:packages}}'),
                $rise($row($package_cols, '4')),
            )),
        )),

        // What couples say: sample words for the planner to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Kind words'), lang('What couples say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Journal'), array(
            $container(array(
                $section_header(lang('Wedding Journal'), lang('Ideas and advice'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-ear', 'title' => lang('We listen first'), 'text' => lang('The first meeting is about you, not about us: your story, your people and the day you picture.')),
    array('icon' => 'bi-basket', 'title' => lang('Local and seasonal'), 'text' => lang('Flowers from nearby growers, food from the region and a menu that follows the season.')),
    array('icon' => 'bi-recycle', 'title' => lang('Light on the land'), 'text' => lang('Reusable decor, no single-use plastic at the venue and the leftover flowers sent to a care home.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text'], '4');

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Wedding planners with their own olive estate by the sea: our story, our values and the team that plans your day.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $planner['tagline'] . '.'),
        $story_band('wd-our-story', lang('Our story'), lang('From friends\' weddings to an olive estate'),
            array($planner['story'], lang('Today a small team of planners, a chef and a venue crew keep the same rule: one wedding a day, planned as if it were our own.')),
            $photo('about-estate', 1200, 900), lang('The old olive estate seen from the garden'), false, array(),
            lang('history, founders, team, who we are, estate')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we plan')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('wd-values', lang('sustainable, local, seasonal, values'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Why Choose Us'), array(
            $container(array(
                $section_header(lang('Why couples choose us'), lang('One team, one wedding a day')),
                $row($why_tiles, '4'),
            )),
        ), $searchable('wd-about-why', lang('exclusive, one wedding a day, planner, team, prices'))),
        $ask_band(lang('Would you like to meet us?'), lang('Write to us or book a visit; we would love to hear about your plans.')),
    )),
);

// ── Planning services ───────────────────────────────────────────────────

$service_steps = array(
    array(lang('Tell us about your day'), lang('Send an enquiry with your date, your guests and what you have in mind; it opens an account for you.')),
    array(lang('Meet us'), lang('A first consultation at the estate or by video call, free and without obligation.')),
    array(lang('Receive your proposal'), lang('A written proposal with the service, the package and the price for your guests.')),
    array(lang('Hold your date'), sprintf(lang('A deposit of %s holds the date; the rest is paid in parts before the wedding.'), $deposit_text)),
);
$service_step_items = array();
foreach ($service_steps as $n => $s) $service_step_items[] = $step($n + 1, $s[0], $s[1]);

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('planning'),
    'folder'           => 'public',
    'title'            => lang('Planning Services'),
    'meta_description' => lang('Full planning, partial planning or day coordination: what each includes and what it costs.'),
    'tree'             => $page(array(
        $page_header(lang('Planning Services'), lang('Choose how much you would like us to do; every service comes with the same team on the day.')),
        $el('section', 'py-5', lang('Planning Services'), array(
            $container(array(
                $row($service_cols, '4'),
                $para(lang('Prices are starting prices; your proposal gives the price for your wedding.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('wd-services', lang('wedding planner, coordinator, full service, day of, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Step by step'), 'pwd-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('From enquiry to your day'), 'pwd-display h2 mb-4'),
                        $el('ol', 'list-unstyled mb-4', lang('Steps'), $service_step_items),
                        $link(lang('Enquire'), '{{page:enquiry}}', 'btn btn-primary rounded-pill px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('services-meeting', 1200, 900), lang('A couple and a planner around a table with samples'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('wd-how-it-works', lang('consultation, proposal, deposit, payment, booking'))),
        $ask_band(lang('Not sure which service suits you?'), lang('Tell us what you have planned so far and we will suggest one.')),
    )),
);

// ── Packages ────────────────────────────────────────────────────────────

// A row of the comparison table for each package, its button opening the
// enquiry with the package chosen.
$package_rows = array();
foreach ($packages as $key => $p) {
    $package_rows[] = $el('tr', '', $p['title'], array(
        $el('th', 'fw-semibold', lang('Package'), array(), array('text' => $p['title'], '_attrs' => array($attr('scope', 'row')))),
        $el('td', 'text-nowrap', lang('Guests'), array(), array('text' => $guests_text($key))),
        $el('td', '', lang('Venue Space'), array(), array('text' => $spaces[$p['space']]['title'])),
        $el('td', 'pwd-price fw-semibold text-nowrap', lang('Price'), array(), array('text' => $money($package_prices[$key]))),
        $el('td', 'text-end', lang('Action'), array(
            $link(lang('Ask about this package'), '{{page:enquiry}}?' . $query(array('package' => $p['title'])), 'btn btn-sm btn-outline-primary rounded-pill px-3 text-nowrap'),
        )),
    ));
}

$public_pages[] = array(
    'key'              => 'packages',
    'name'             => lang('packages'),
    'folder'           => 'public',
    'title'            => lang('Wedding Packages'),
    'meta_description' => lang('Intimate, Classic or Grand: what each venue package includes and its price per guest.'),
    'tree'             => $page(array(
        $page_header(lang('Wedding Packages'), lang('Three ways to celebrate at the estate, priced per guest. Every package can be shaped to you.')),
        $el('section', 'py-5', lang('Packages'), array(
            $container(array(
                $rise($row($package_cols, '4')),
            )),
        ), $searchable('wd-packages', lang('package, all inclusive, menu, price per person, what is included'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Package Prices'), array(
            $container(array(
                $section_header(lang('At a glance'), lang('Compare the packages')),
                $el('div', 'table-responsive position-relative', lang('Table'), array(
                    $el('table', 'table align-middle mb-0', lang('Price Table'), array(
                        $el('thead', '', lang('Table Head'), array(
                            $el('tr', '', lang('Row'), array(
                                $el('th', '', lang('Package'), array(), array('text' => lang('Package'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Guests'), array(), array('text' => lang('Guests'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Venue Space'), array(), array('text' => lang('Where'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Price'), array(), array('text' => lang('From, per guest'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Action'), array($span(lang('Ask about this package'), 'visually-hidden', lang('Text'))), array('_attrs' => array($attr('scope', 'col')))),
                            )),
                        )),
                        $el('tbody', '', lang('Table Body'), $package_rows),
                    )),
                )),
                $para(sprintf(lang('Prices are per guest and include taxes; a deposit of %s holds your date.'), $deposit_text), 'small text-body-secondary mt-3 mb-0'),
            )),
        ), $searchable('wd-prices', lang('price, prices, cost, how much, per person, deposit'))),
    )),
);

// ── The venue ───────────────────────────────────────────────────────────

$venue_facts = array(
    lang('One wedding a day: the whole estate is yours'),
    lang('Ceremony and celebration in one place'),
    lang('Parking for guests and a shuttle from the town'),
    lang('Rooms for the couple to get ready'),
    lang('Music outdoors until midnight, indoors until two'),
);

$public_pages[] = array(
    'key'              => 'venue',
    'name'             => lang('venue'),
    'folder'           => 'public',
    'title'            => lang('The Venue'),
    'meta_description' => lang('An olive estate by the sea with four spaces for weddings of forty to three hundred guests.'),
    'tree'             => $page(array(
        $page_header(lang('The Venue'), lang('An old olive estate above the bay, with a space for every size of wedding.')),
        $el('section', 'py-5', lang('Spaces'), array(
            $container(array(
                $section_header(lang('One estate, four spaces'), lang('Our spaces')),
                $rise($row($space_cols, '4')),
            )),
        ), $searchable('wd-spaces', lang('capacity, how many guests, indoor, outdoor, garden, hall, terrace'))),
        $story_band('wd-venue-estate', lang('The estate'), lang('Everything in one place'),
            array(lang('Guests arrive to drinks in the garden, the ceremony is under the trees or above the sea, and nobody needs to drive between the vows and the party.')),
            $photo('venue-estate', 1200, 900), lang('The estate\'s garden and the sea beyond it'), true,
            array($check_list($venue_facts, 'list-unstyled mb-4'), $link(lang('Book a Venue Visit'), '{{page:book_visit}}', 'btn btn-primary rounded-pill px-4')),
            lang('parking, shuttle, getting ready, music, curfew'), 'py-5 bg-body-tertiary'),
        $el('section', 'py-5', lang('Gallery'), array(
            $container(array(
                $section_header(lang('A first look'), lang('The estate in pictures'), lang('Open the gallery'), '{{page:gallery}}'),
                $rise($row(array($gallery_item(0), $gallery_item(2), $gallery_item(4), $gallery_item(6)), '3'), 120),
            )),
        )),
    )),
);

// ── Gallery ─────────────────────────────────────────────────────────────

// The pictures grouped by space, each group under the space's name.
$gallery_groups = array();
foreach ($spaces as $skey => $s) {
    $items = array();
    foreach ($gallery as $n => $g) {
        if ($g['space'] === $skey) $items[] = $gallery_item($n);
    }
    if (!$items) continue;
    $gallery_groups[] = $el('div', 'mb-5', $s['title'], array(
        $heading('h2', $s['title'], 'pwd-display h4 mb-3'),
        $row($items, '3'),
    ), $searchable('wd-gallery-' . $skey));
}

$public_pages[] = array(
    'key'              => 'gallery',
    'name'             => lang('gallery'),
    'folder'           => 'public',
    'title'            => lang('Gallery'),
    'meta_description' => lang('Pictures of the Olive Garden, the Grand Hall, the Sea Terrace and the Orangery dressed for weddings.'),
    'tree'             => $page(array(
        $page_header(lang('Gallery'), lang('The estate\'s spaces dressed for weddings through the seasons.')),
        $el('section', 'py-5', lang('Gallery'), array(
            $container($gallery_groups),
        )),
        $ask_band(lang('See it for yourself'), lang('Pictures say a lot; a walk through the estate says more.')),
    )),
);

// ── Planning timeline ───────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'timeline',
    'name'             => lang('planning-timeline'),
    'folder'           => 'public',
    'title'            => lang('Planning Timeline'),
    'meta_description' => lang('What to do and when, from twelve months before the wedding to the week of the day.'),
    'tree'             => $page(array(
        $page_header(lang('Planning Timeline'), lang('What to do and when, from the first idea to the last week.')),
        // Wedding: Planning Checklist. A card per stage under the
        // ornament, rising on view one after another, and the way to the
        // planning services under them.
        $el('section', 'py-5 bg-body-tertiary', lang('Timeline'), array(
            $container(array(
                $el('div', 'text-center col-lg-8 mx-auto mb-5', lang('Section Header'), array(
                    $para(lang('Planning with us'), 'pwd-eyebrow small text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', lang('From the first idea to the last dance'), 'pwd-display display-6 mb-3'),
                    $ornament('col-6 col-md-4 mx-auto mb-3'),
                    $para(lang('A wedding is a year of small decisions. Our timeline shows what to do and when, from choosing the date to handing everything over in the last week.'), 'lead text-body-secondary mb-0'),
                )),
                $rise($el('ol', 'list-unstyled row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4', lang('Timeline'), $stage_items), 100),
                // Beside the note the button keeps its one line (flex-shrink-0);
                // stacked on a phone its label may wrap rather than run past
                // the 320 px screen.
                $el('div', 'd-flex flex-column flex-sm-row align-items-sm-center justify-content-center gap-3 text-center', lang('Checklist Foot'), array(
                    $para(lang('With full or partial planning, your planner keeps this list with you and reminds you of each step in time.'), 'small text-body-secondary mb-0'),
                    $link(lang('See the planning services'), '{{page:services}}', 'btn btn-outline-primary rounded-pill px-4 flex-shrink-0'),
                )),
            )),
        ), $searchable('wd-timeline', lang('checklist, when to book, schedule, to do, months before, planner, reminder'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: the venue, planning, payments. Each question is a
// search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pwd-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about the venue, guest numbers, planning, the deposit and changing the date.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('The venue, planning and payments. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('wd-faq-venue', lang('The venue'), array(
                            array(lang('How many guests can the estate hold?'), lang('From forty guests in the Orangery to three hundred standing in the Grand Hall; the venue page shows each space\'s size.'), lang('capacity, guests, how many, size')),
                            array(lang('Is there a plan for rain?'), lang('Yes: every outdoor wedding has the Grand Hall or the Orangery kept for it, at no extra cost.'), lang('rain, weather, indoor, backup')),
                            array(lang('Can we visit before we decide?'), lang('Of course: book a venue visit at a free time and a planner shows you the estate.'), lang('visit, viewing, tour, see the venue')),
                            array(lang('Can we bring our own suppliers?'), lang('Yes, with the venue\'s own kitchen for food; we are glad to suggest suppliers we know well.'), lang('own suppliers, vendors, photographer, florist, catering')),
                        )),
                        $faq_group('wd-faq-planning', lang('Planning'), array(
                            array(lang('How far ahead should we book?'), lang('Most couples book nine to twelve months ahead; for a summer Saturday, earlier is safer.'), lang('when to book, how early, availability, dates')),
                            array(lang('Is my enquiry a booking of the date?'), lang('Not yet: a planner answers within a day and the date is yours once the proposal is agreed and the deposit paid.'), lang('confirmation, reservation, hold, date')),
                            array(lang('How do we follow our enquiry?'), lang('Sending the form opens an account for you. Under My Account you see the state of your enquiry and can write to us there.'), lang('my account, status, follow, message')),
                            array(lang('Do you plan weddings elsewhere?'), lang('Our planning services are for weddings at the estate, so we can promise the same team from start to finish.'), lang('other venue, destination wedding, elsewhere')),
                        )),
                        $faq_group('wd-faq-payments', lang('Payments'), array(
                            array(lang('How much is the deposit?'), sprintf(lang('%s holds your date once the proposal is agreed; the rest is paid in parts before the wedding.'), $deposit_text), lang('deposit, down payment, balance, instalments')),
                            array(lang('Can we change the date?'), lang('Once, at no cost, to a free date within a year, if you tell us more than three months before.'), lang('change date, postpone, move')),
                            array(lang('What if we need to cancel?'), lang('The deposit is kept; payments made after it are returned in part depending on how close the date is, as the proposal sets out.'), lang('cancel, cancellation, refund')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured weddings (home_weddings) come with
// the Real Weddings, its journal (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
