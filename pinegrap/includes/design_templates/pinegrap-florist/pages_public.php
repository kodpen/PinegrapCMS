<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Florist", its public pages: home, the bouquets, the
 * occasions, the flower subscriptions, wedding and event flowers,
 * delivery, flower care, about and the questions page. Included after
 * parts.php in the same scope (its builders and the florist's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($florist, $bouquets, $occasions,
 * $sizes, $plans, $event_services, $zones, $slots, $seasons, $care,
 * $why_us, $figures, $testimonials), so a change made there reaches every
 * page here. No page here links a shop page: the template stands on a site
 * without the shop, and every bouquet is ordered through the order request.
 *
 * The home, the occasions, the subscriptions and the delivery page are
 * drawn as the designer's blocks draw them (Florist: Bouquet Hero, Card
 * Message, Flower Subscription; Locations: Zones), filled with the
 * florist's own data; the blocks' rules come with the template's styles.
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

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $rise, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pfl-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pfl-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $rise($row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'), 150),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line: why customers choose the
// florist and its values.
$feature_tile = function ($icon_name, $title, $text, $lg = '3') use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pfl-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        )),
    ), '6', $lg);
};

// The florist in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pfl-display display-5 text-primary mb-1'),
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
                    $heading('h2', $title, 'pfl-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
            )),
        )),
    ));
};

// A numbered how-it-works list beside a picture, with a button under it.
$steps_band = function ($id, $eyebrow, $title, $steps, $button, $picture, $alt, $topics, $class = 'py-5 bg-body-tertiary')
    use ($el, $heading, $para, $container, $row, $col, $step, $rise, $fade_in, $fx, $framed, $searchable) {
    $items = array();
    foreach (array_values($steps) as $n => $s) $items[] = $step($n + 1, $s[0], $s[1]);
    return $el('section', $class, lang('How It Works'), array(
        $container(array(
            $row(array(
                $col(array(
                    $fx($el('div', '', lang('Section Title'), array(
                        $para($eyebrow, 'pfl-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $title, 'pfl-display h2 mb-4'),
                    )), $fade_in),
                    $rise($el('ol', 'list-unstyled mb-4', lang('Steps'), $items), 120),
                    $button,
                ), '', '6'),
                $col(array($fx($framed($picture, $alt), $fade_in)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A section header that fades in as it scrolls in.
$header_in = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($fx, $section_header, $fade_in) {
    return $fx($section_header($eyebrow, $title, $more_text, $more_href), $fade_in);
};

// The gift card of the Florist blocks: who it is for, the message in
// italics and who it is from, the words a customer types when ordering.
$note = function ($to, $message, $from, $class = '') use ($el, $para, $span) {
    return $el('div', trim('pg-florist-note ' . $class), lang('Gift Card'), array(
        $span($to, 'd-block fw-semibold mb-2', lang('Text')),
        $para($message, 'fst-italic mb-3'),
        $span($from, 'd-block text-end fw-semibold', lang('Text')),
    ));
};

// The home's way in, as the Florist: Bouquet Hero block draws it: the words
// rise on load with the last order time for delivery today under them; the
// bouquet stands in an arched frame that grows into place and the gift card
// slides up into it a moment later; the occasions follow, one after another,
// as ways into the order request with the occasion chosen.
$hero = function () use ($el, $heading, $para, $link, $span, $icon, $image, $attr, $container, $row, $col, $fx, $note, $florist, $occasions, $photo, $query) {
    $ways = array();
    foreach ($occasions as $o) {
        $ways[] = $el('li', '', $o['title'], array(
            $el('a', 'btn btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2', lang('Link'), array(
                $icon($o['icon']),
                $span($o['title'], '', lang('Text')),
            ), array('href' => '{{page:order_request}}?' . $query(array('occasion' => $o['title'])))),
        ));
    }
    $cutoff = $el('time', 'pg-florist-figure fs-3 fw-bold text-body-emphasis', lang('Last Order Time'), array(),
        array('text' => $florist['cutoff'], '_attrs' => array($attr('datetime', $florist['cutoff']))));
    return $el('section', 'py-5 overflow-hidden', lang('Florist Hero'), array(
        $container(array(
            $row(array(
                $col(array(
                    $fx($el('div', '', lang('Body'), array(
                        $para(lang('Local florist and flower studio'), 'pfl-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Flowers that say it for you'), 'pfl-display display-3 mb-4'),
                        $para($florist['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('Order Flowers'), '{{page:order_request}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('See the Bouquets'), '{{page:bouquets}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                        $el('div', 'd-inline-flex align-items-center gap-3 px-4 py-3 rounded-4 bg-body-tertiary', lang('Same-day Delivery'), array(
                            $icon('bi-truck', 'text-primary fs-2'),
                            $el('div', '', lang('Text'), array(
                                $span(lang('Last order for delivery today'), 'd-block small text-body-secondary mb-1', lang('Text')),
                                $cutoff,
                            )),
                        )),
                    )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800)),
                ), '', '6'),
                $col(array(
                    $el('div', 'pg-florist-frame', lang('Picture Frame'), array(
                        $fx($el('div', 'pg-florist-arch shadow-lg', lang('Picture'), array(
                            $image($photo('hero', 960, 1200), lang('A hand-tied bouquet of sunflowers and white roses wrapped in paper'), 'w-100'),
                        )), array('effect' => 'scale', 'trigger' => 'load', 'duration' => 1000)),
                        $fx($el('div', 'pg-florist-tuck', lang('Card Holder'), array(
                            $note(lang('Dear Mira,'), lang('Happy birthday! Wishing you days as bright as these sunflowers.'), lang('With love, Sam')),
                        )), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 800, 'delay' => 700, 'distance' => 60)),
                    )),
                ), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
            $el('div', 'mt-5 pt-4 border-top', lang('Special Occasions'), array(
                $heading('h2', lang('Who are the flowers for?'), 'h6 text-uppercase fw-semibold text-body-secondary mb-3'),
                $fx($el('ul', 'list-unstyled d-flex flex-wrap gap-2 mb-0', lang('Special Occasions'), $ways),
                    array('effect' => 'fade', 'trigger' => 'load', 'duration' => 600, 'delay' => 1000, 'stagger' => 80)),
            )),
        ), 'py-lg-4'),
    ));
};

// The flower subscriptions, as the Florist: Flower Subscription block
// draws them: the plans side by side, rising one after another as they
// scroll in, and a note under them. $header is the band's heading, or null
// under a page header that names it already.
$subscription_band = function ($header, $props = array()) use ($el, $span, $icon, $container, $fx, $plans, $plan_card) {
    $items = array();
    foreach (array_keys($plans) as $key) $items[] = $plan_card($key);
    $children = $header ? array($header) : array();
    $children[] = $fx($el('ul', 'list-unstyled row row-cols-1 row-cols-lg-3 g-4 mb-0', lang('Plans'), $items),
        array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => 120, 'distance' => 40));
    $children[] = $el('div', 'd-flex align-items-center justify-content-center gap-2 mt-4 small text-body-secondary text-center', lang('Note'), array(
        $icon('bi-info-circle'),
        $span(lang('Subscriptions run for three months; we write before the end and you choose whether to go on.'), '', lang('Text')),
    ));
    return $el('section', 'py-5', lang('Florist Flower Subscription'), array($container($children)), $props);
};

// The card message, as the Florist: Card Message block draws it: the gift
// card drawn large with a sample message grows into place as it scrolls
// in; beside it what the card is and sample words for four occasions, which
// rise one after another. $props carries the band's search section.
$card_band = function ($props = array()) use ($el, $heading, $para, $link, $span, $icon, $container, $row, $col, $fx, $note, $occasions) {
    $samples = array(
        'birthday'  => lang('Happy birthday! Here is to a year as bright as these flowers.'),
        'thank_you' => lang('Thank you for everything. It meant more than flowers can say.'),
        'new_baby'  => lang('Welcome to the world, little one, and congratulations to you both.'),
        'sympathy'  => lang('Thinking of you, and holding you close in our thoughts.'),
    );
    $items = array();
    foreach ($samples as $key => $words) {
        $items[] = $el('li', 'col', $occasions[$key]['title'], array(
            $el('div', 'h-100 p-3 p-lg-4 rounded-4 bg-body shadow-sm', lang('Message'), array(
                $el('div', 'd-flex align-items-center gap-2 mb-2 text-primary', lang('Title'), array(
                    $icon($occasions[$key]['icon']),
                    $heading('h3', $occasions[$key]['title'], 'h6 fw-semibold mb-0'),
                )),
                $para($words, 'fst-italic small mb-0'),
            )),
        ));
    }
    $fact = function ($icon_name, $text) use ($el, $icon, $span) {
        return $el('li', 'd-flex align-items-center gap-2', lang('Item'), array($icon($icon_name, 'text-primary'), $span($text, '', lang('Text'))));
    };
    return $el('section', 'py-5 bg-body-tertiary overflow-hidden', lang('Florist Card Message'), array(
        $container(array(
            $row(array(
                $col(array(
                    $fx($el('div', 'px-3 px-sm-5 px-lg-0', lang('Card Holder'), array(
                        $note(lang('Dear Ada,'), lang('Thank you for the long dinners, the stories and the room by the sea. We miss you already.'), lang('Love, Jon and Lea'), 'p-4 p-lg-5 fs-5'),
                    )), array('effect' => 'scale', 'trigger' => 'view', 'duration' => 900)),
                ), '5'),
                $col(array(
                    $fx($el('div', '', lang('Body'), array(
                        $para(lang('The card'), 'pfl-eyebrow text-uppercase fw-semibold text-body-secondary mb-2'),
                        $heading('h2', lang('Your words, written by hand'), 'pfl-display display-6 mb-3'),
                        $para(lang('Every bouquet goes with a card. Write your message with the order and we copy it by hand.'), 'lead text-body-secondary mb-4'),
                        $el('ul', 'list-unstyled d-flex flex-wrap gap-3 gap-lg-4 small mb-0', lang('Facts'), array(
                            $fact('bi-pen', lang('Written in ink by the florist')),
                            $fact('bi-envelope-heart', lang('Tucked into the flowers')),
                            $fact('bi-incognito', lang('Unsigned, if it is a surprise')),
                        )),
                    )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700)),
                    $para(lang('Not sure what to write? Start from one of these.'), 'fw-semibold mt-5 mb-3'),
                    $fx($el('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 g-3 mb-4', lang('Sample Messages'), $items),
                        array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 600, 'stagger' => 120, 'distance' => 30)),
                    $link(lang('Order Flowers'), '{{page:order_request}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                ), '6', '', '', '12', 'offset-lg-1'),
            ), '4', '', 'g-lg-5 align-items-center'),
        ), 'py-lg-4'),
    ), $props);
};

// Where and when the florist delivers, as the Locations: Zones block draws
// it: the areas as rings around the studio beside their list, the delivery
// times and the last order time for delivery today; the two columns rise
// one after the other. The drawing is decoration: the list says it.
$zones_band = function () use ($el, $heading, $para, $span, $icon, $attr, $container, $row, $col, $rise, $header_in, $searchable, $zones, $zone_card, $zone_rings, $slots, $florist) {
    $list = array();
    $titles = array();
    foreach ($zones as $key => $z) {
        $list[] = $zone_card($key);
        $titles[] = $z['title'];
    }
    $time = function ($value, $name, $class = '') use ($el, $attr) {
        return $el('time', $class, $name, array(), array('text' => $value, '_attrs' => array($attr('datetime', $value))));
    };
    $times = array();
    foreach ($slots as $s) {
        $times[] = $el('li', 'col', $s['name'], array(
            $el('div', 'h-100 p-3 rounded-3 border text-center', lang('Time Slot'), array(
                $icon($s['icon'], 'd-block text-primary fs-4 mb-1'),
                $span($s['name'], 'd-block fw-semibold', lang('Text')),
                $el('div', 'small text-body-secondary', lang('Hours'), array(
                    $time($s['from'], lang('Start Time')),
                    $span('–', 'mx-1', lang('Separator')),
                    $time($s['to'], lang('End Time')),
                )),
            )),
        ));
    }
    $ring = function ($n, $children, $class = '') use ($el, $zone_rings, $titles) {
        return $el('div', trim('pg-zone-ring ' . $class . ' ' . $zone_rings[$n]), isset($titles[$n - 1]) ? $titles[$n - 1] : lang('Zones'), $children);
    };
    $map = $el('div', 'pg-zone-map', lang('Zone Drawing'), array(
        $ring(3, array($ring(2, array($ring(1, array($icon('bi-geo-alt-fill', 'fs-3')), 'pg-zone-z1'))))),
    ), array('_attrs' => array($attr('aria-hidden', 'true'))));
    return $el('section', 'py-5', lang('Locations (Zones)'), array(
        $container(array(
            $header_in(lang('Where we deliver'), lang('Delivery areas')),
            $rise($row(array(
                $col(array($map), '5'),
                $col(array(
                    $el('ul', 'pg-zone-list list-unstyled mb-4 border-top', lang('Zones'), $list),
                    $heading('h3', lang('Time slots'), 'h6 fw-semibold text-uppercase text-body-secondary mb-3'),
                    $el('ul', 'pg-zone-list list-unstyled row row-cols-1 row-cols-sm-3 g-3 mb-4', lang('Time Slots'), $times),
                    $el('div', 'pg-zone-list d-flex align-items-center gap-3 p-3 rounded-3 bg-primary-subtle text-primary-emphasis', lang('Cut-off Time'), array(
                        $icon('bi-clock-history', 'fs-3'),
                        $el('div', '', lang('Text'), array(
                            $el('div', 'd-flex flex-wrap align-items-baseline gap-2', lang('Title'), array(
                                $span(lang('Same day for orders until'), 'fw-semibold', lang('Text')),
                                $time($florist['cutoff'], lang('Cut-off Time'), 'fs-5 fw-bold'),
                            )),
                            $para(lang('Orders after this time are seen to the next day.'), 'small mb-0'),
                        )),
                    )),
                ), '7'),
            ), '4', '', 'g-lg-5 align-items-center'), 150),
        )),
    ), $searchable('fl-zones', lang('delivery cost, shipping, areas, postcode, same day, next day')));
};

// The cards of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$bouquet_cols = array();
$featured_bouquet_cols = array();
foreach ($bouquets as $key => $b) {
    $bouquet_cols[] = $bouquet_card($key);
    if ($b['featured']) $featured_bouquet_cols[] = $bouquet_card($key);
}
$occasion_cols = array();
foreach (array_keys($occasions) as $key) $occasion_cols[] = $occasion_card($key);
$event_cols = array();
foreach (array_keys($event_services) as $key) $event_cols[] = $event_card($key);
$season_cols = array();
foreach (array_keys($seasons) as $key) $season_cols[] = $season_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$custom_bouquet = end($bouquet_choices);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Hand-tied bouquets delivered today, flower subscriptions and wedding flowers from a local florist.'),
    'tree'             => $page(array(
        // The way in (Florist: Bouquet Hero), then the bouquets made this
        // morning, the subscriptions (Florist: Flower Subscription) and the
        // card message (Florist: Card Message).
        $hero(),

        $el('section', 'py-5 bg-body-tertiary', lang('Bouquets'), array(
            $container(array(
                $header_in(lang('Made this morning'), lang('Our favourite bouquets'), lang('All bouquets'), '{{page:bouquets}}'),
                $rise($row($featured_bouquet_cols, '4')),
            )),
        )),

        $subscription_band($header_in(lang('Never an empty vase'), lang('Flower subscriptions'), lang('How subscriptions work'), '{{page:subscriptions}}')),

        $card_band(),

        // Wedding and event flowers, and the featured work read from the
        // Our Work collection.
        $el('section', 'py-5', lang('Weddings and Events'), array(
            $container(array(
                $header_in(lang('Weddings and events'), lang('Flowers for the big days'), lang('Wedding and event flowers'), '{{page:events}}'),
                $rise($row($event_cols, '4', '', 'mb-5')),
                $header_in(lang('Our Work'), lang('Recent weddings and events'), lang('All Our Work'), '{{page:work}}'),
                $widget('home_work'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Why Choose Us'), array(
            $container(array(
                $header_in(lang('Why customers choose us'), lang('Fresh, local and by hand'), lang('About Us'), '{{page:about}}'),
                $rise($row($why_tiles, '4')),
            )),
        ), $searchable('fl-why-us', lang('fresh, same day, hand written card, guarantee, replacement'))),

        // What customers say: sample words for the florist to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $header_in(lang('Kind words'), lang('What customers say')),
                $rise($row($testimonial_cols, '4')),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Journal'), array(
            $container(array(
                $header_in(lang('Flower Journal'), lang('Ideas and advice'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Bouquets ────────────────────────────────────────────────────────────

$size_cols = array();
foreach ($sizes as $s) {
    $size_cols[] = $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border text-center', $s['title'], array(
            $heading('h3', $s['title'], 'pfl-display h4 mb-1'),
            $para($s['text'], 'small text-body-secondary mb-0'),
        )),
    ), '4');
}

$public_pages[] = array(
    'key'              => 'bouquets',
    'name'             => lang('bouquets'),
    'folder'           => 'public',
    'title'            => lang('Bouquets'),
    'meta_description' => lang('Hand-tied bouquets, roses in a vase and flowers in a box, arranged this morning and delivered today.'),
    'tree'             => $page(array(
        $page_header(lang('Bouquets'), lang('Every bouquet is arranged by hand on the day it leaves, with the freshest stems of the morning.')),
        $el('section', 'py-5', lang('Bouquets'), array(
            $container(array(
                $rise($row($bouquet_cols, '4')),
                $para(lang('Prices are for the regular size; the stems may change with the season, the look and colours stay.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('fl-bouquets', lang('flowers, roses, bunch, arrangement, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Sizes'), array(
            $container(array(
                $header_in(lang('Bigger moments'), lang('Three sizes')),
                $rise($row($size_cols, '4'), 120),
            )),
        ), $searchable('fl-sizes', lang('size, large, bigger, deluxe, grand'))),
        $story_band('fl-made-for-you', lang('Made for you'), lang('A bouquet of your own'),
            array(lang('Tell us who it is for, their favourite colours and your budget, and we arrange a bouquet nobody else has.')),
            $photo('bouquets-custom', 1200, 900), lang('A florist choosing stems from buckets of fresh flowers'), true,
            array($link(lang('Ask for a bouquet of your own'), '{{page:order_request}}?' . $query(array('bouquet' => $custom_bouquet)), 'btn btn-primary rounded-pill px-4')),
            lang('custom, bespoke, personal, colours, budget')),
    )),
);

// ── Occasions ───────────────────────────────────────────────────────────
// The occasions, then the card message (Florist: Card Message).

$public_pages[] = array(
    'key'              => 'occasions',
    'name'             => lang('occasions'),
    'folder'           => 'public',
    'title'            => lang('Special Occasions'),
    'meta_description' => lang('Flowers for a birthday, love, thanks, a new baby, a get-well wish or sympathy, delivered with your message.'),
    'tree'             => $page(array(
        $page_header(lang('Special Occasions'), lang('Whatever you want to say, there are flowers for it. Choose the occasion and we suggest the rest.')),
        $el('section', 'py-5', lang('Special Occasions'), array(
            $container(array(
                $rise($row($occasion_cols, '4')),
            )),
        ), $searchable('fl-occasions', lang('birthday, anniversary, thank you, baby, get well, funeral, sympathy'))),
        $card_band($searchable('fl-card-message', lang('card message, note, gift message, anonymous'))),
    )),
);

// ── Subscriptions ───────────────────────────────────────────────────────
// The plans (Florist: Flower Subscription), how a subscription goes and a
// word for offices.

$public_pages[] = array(
    'key'              => 'subscriptions',
    'name'             => lang('subscriptions'),
    'folder'           => 'public',
    'title'            => lang('Flower Subscriptions'),
    'meta_description' => lang('Fresh seasonal flowers every week, every two weeks or every month, delivered free on the day you choose.'),
    'tree'             => $page(array(
        $page_header(lang('Flower Subscriptions'), lang('Fresh flowers on your table without a thought: choose how often, we choose the flowers.')),
        $subscription_band(null, $searchable('fl-plans', lang('subscription, weekly flowers, monthly flowers, price, plan'))),
        $steps_band('fl-subscription-steps', lang('Step by step'), lang('How a subscription works'), array(
            array(lang('Choose a plan'), lang('Weekly, every two weeks or monthly, for you or as a gift.')),
            array(lang('Tell us the day'), lang('Pick the weekday and the time; we deliver free in our areas.')),
            array(lang('We confirm'), lang('We write within a day to confirm the first delivery and the payment.')),
            array(lang('Enjoy the season'), lang('Each bouquet is different, made from the best stems of that week.')),
        ), $link(lang('Start a Subscription'), '{{page:subscribe}}', 'btn btn-primary rounded-pill px-4'),
            $photo('subscriptions-table', 1200, 900), lang('A vase of seasonal flowers on a kitchen table'),
            lang('how it works, start, gift subscription, pause, skip')),
        $ask_band(lang('Flowers for your office?'), lang('Business flowers are changed every week and billed once a month; ask us for a proposal.')),
    )),
);

// ── Wedding and event flowers ───────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'events',
    'name'             => lang('weddings-and-events'),
    'folder'           => 'public',
    'title'            => lang('Wedding and Event Flowers'),
    'meta_description' => lang('Bridal bouquets, ceremony flowers, table centrepieces and flowers for events and businesses.'),
    'tree'             => $page(array(
        $page_header(lang('Wedding and Event Flowers'), lang('From one bridal bouquet to a room full of flowers, planned with you and set up on the day.')),
        $el('section', 'py-5', lang('Weddings and Events'), array(
            $container(array(
                $rise($row($event_cols, '4')),
                $para(lang('Prices are starting prices; your proposal gives the price for your day.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('fl-event-services', lang('wedding florist, bridal bouquet, centrepieces, party flowers, corporate'))),
        $steps_band('fl-event-steps', lang('Step by step'), lang('From the first idea to the day'), array(
            array(lang('Tell us about it'), lang('Send an enquiry with the date, the place and the number of guests.')),
            array(lang('Meet us'), lang('A free consultation at the studio or by video call, with samples and a mood board.')),
            array(lang('Receive your proposal'), lang('A written proposal with every arrangement and its price.')),
            array(lang('Leave it with us'), lang('We bring, set up and later collect everything on the day.')),
        ), $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
            $link(lang('Ask about your event'), '{{page:event_enquiry}}', 'btn btn-primary rounded-pill px-4'),
            $link(lang('Book a Consultation'), '{{page:consultation}}', 'btn btn-outline-primary rounded-pill px-4'),
        )), $photo('events-consultation', 1200, 900), lang('A florist showing flower samples to a couple'),
            lang('consultation, proposal, how it works, booking')),
        $story_band('fl-events-work', lang('Our Work'), lang('See what we have made'),
            array(lang('Weddings in the garden, dinners by candlelight and a hotel lobby through the seasons: pictures and the flowers we used.')),
            $photo('events-work', 1200, 900), lang('A table with low runners of peonies and candles'), true,
            array($link(lang('All Our Work'), '{{page:work}}', 'btn btn-outline-primary rounded-pill px-4')),
            lang('portfolio, gallery, pictures, examples')),
    )),
);

// ── Delivery ────────────────────────────────────────────────────────────
// The areas, the times and the cut-off (Locations: Zones), then what
// happens on the day.

$public_pages[] = array(
    'key'              => 'delivery',
    'name'             => lang('delivery'),
    'folder'           => 'public',
    'title'            => lang('Delivery'),
    'meta_description' => lang('Where we deliver, what it costs, same-day delivery and the delivery times you can choose.'),
    'tree'             => $page(array(
        $page_header(lang('Delivery'), $cutoff_text() . '. ' . lang('We deliver by our own van, with the flowers in water.')),
        $zones_band(),
        $story_band('fl-delivery-times', lang('When it arrives'), lang('Choose a delivery time'), array(
            lang('Choose the day and the part of the day with your order; we text the recipient when the van is on its way.'),
            lang('Nobody at home? We leave the flowers with a neighbour or bring them back the next morning, and let you know.'),
        ), $photo('delivery-van', 1200, 900), lang('A delivery van with flowers in buckets'), true, array(),
            lang('delivery time, morning, afternoon, evening, not at home'), 'py-5 bg-body-tertiary'),
        $ask_band(lang('Further away?'), lang('For a town outside our areas or an early delivery, write to us and we find a way.')),
    )),
);

// ── Flower care ─────────────────────────────────────────────────────────

$care_items = array();
foreach ($care as $n => $c) $care_items[] = $step($n + 1, $c['title'], $c['text']);

$public_pages[] = array(
    'key'              => 'care',
    'name'             => lang('flower-care'),
    'folder'           => 'public',
    'title'            => lang('Flower Care'),
    'meta_description' => lang('How to keep cut flowers fresh for longer, and which flowers are at their best in each season.'),
    'tree'             => $page(array(
        $page_header(lang('Flower Care'), lang('Five small habits that keep a bouquet fresh for days longer.')),
        $el('section', 'py-5', lang('Flower Care'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $rise($el('ol', 'list-unstyled mb-0', lang('Steps'), $care_items), 120),
                    ), '', '6'),
                    $col(array($fx($framed($photo('care-vase', 1200, 900), lang('Hands trimming flower stems over a vase')), $fade_in)), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('fl-care', lang('care, fresh, last longer, water, vase, wilting'))),
        $el('section', 'py-5 bg-body-tertiary', lang('In Season'), array(
            $container(array(
                $header_in(lang('What is in season'), lang('The flowers of each season')),
                $rise($row($season_cols, '4')),
            )),
        ), $searchable('fl-seasons', lang('seasonal flowers, spring, summer, autumn, winter'))),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-geo', 'title' => lang('Grown nearby'), 'text' => lang('Most of our stems come from growers within a morning\'s drive.')),
    array('icon' => 'bi-recycle', 'title' => lang('No floral foam'), 'text' => lang('We arrange in water, moss and reusable frames, and wrap in paper.')),
    array('icon' => 'bi-heart', 'title' => lang('Nothing wasted'), 'text' => lang('Flowers left at the end of the day go to a care home down the road.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text'], '4');

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A local flower studio: our story, how we work with growers, and the team that arranges your flowers.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $florist['tagline'] . '.'),
        $story_band('fl-our-story', lang('Our story'), lang('From a market stall to a flower studio'),
            array($florist['story'], lang('Today a small team of florists and drivers keeps the same rule: nothing leaves the studio unless we would be glad to receive it.')),
            $photo('about-studio', 1200, 900), lang('The flower studio with buckets of fresh stems'), false, array(),
            lang('history, founders, team, who we are, studio')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($rise($figure_row(), 120))),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $header_in(lang('What we care about'), lang('How we work')),
                $rise($row($about_value_tiles, '4'), 120),
            )),
        ), $searchable('fl-values', lang('sustainable, local, foam free, values'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Why Choose Us'), array(
            $container(array(
                $header_in(lang('Why customers choose us'), lang('Fresh, local and by hand')),
                $rise($row($why_tiles, '4')),
            )),
        ), $searchable('fl-about-why', lang('fresh, same day, hand written card, guarantee, replacement'))),
        $ask_band(lang('Come and see us'), lang('The studio is open every day but Sunday afternoon; come in and choose your stems.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: ordering and delivery, subscriptions, weddings and
// events. Each question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq, $fade_in) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pfl-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id, '_fx' => $fade_in)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about ordering, same-day delivery, the card message, subscriptions and wedding flowers.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Ordering, delivery, subscriptions and events. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('fl-faq-orders', lang('Ordering and delivery'), array(
                            array(lang('Can you deliver today?'), $cutoff_text() . '. ' . lang('Same-day delivery covers the city centre and the rest of the city; nearby towns get their flowers the next day.'), lang('same day, today, urgent, last minute')),
                            array(lang('Is my order request a confirmed order?'), lang('Not yet: we answer within the hour in opening times to confirm the bouquet, the delivery and the payment.'), lang('confirmation, payment, order status')),
                            array(lang('How do I follow my order?'), lang('Sending the form opens an account for you. Under My Account you see the state of your request and can write to us there.'), lang('my account, status, follow, message, tracking')),
                            array(lang('What if the flowers are not right?'), lang('Tell us within a day with a photo and we replace them, no questions asked.'), lang('complaint, refund, replace, wilted, damaged')),
                        )),
                        $faq_group('fl-faq-subscriptions', lang('Subscriptions'), array(
                            array(lang('Can I give a subscription as a gift?'), lang('Yes: give the recipient\'s address and we send a card with the first bouquet.'), lang('gift subscription, present')),
                            array(lang('Can I skip a delivery?'), lang('Tell us two days before and we skip it or move it to another day.'), lang('pause, skip, holiday, away')),
                            array(lang('What happens after three months?'), lang('We write a week before the end and you choose whether to go on.'), lang('renew, end, cancel subscription')),
                        )),
                        $faq_group('fl-faq-events', lang('Weddings and events'), array(
                            array(lang('How far ahead should we book wedding flowers?'), lang('Six to twelve months ahead for a summer Saturday; for a smaller event a month is often enough.'), lang('when to book, how early, availability')),
                            array(lang('Do you set up at the venue?'), lang('Yes: we bring and set up every arrangement on the day and collect the vases afterwards.'), lang('setup, installation, venue, collection')),
                            array(lang('Can we see samples first?'), lang('Book a consultation and we make a sample centrepiece and a bouquet in your colours.'), lang('samples, mock up, trial, consultation')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured work (home_work) comes with Our
// Work, its journal (home_posts) and the Google reviews (google_reviews)
// with the standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
