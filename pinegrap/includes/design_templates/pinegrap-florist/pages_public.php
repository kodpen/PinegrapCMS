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
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
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
            $row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
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
    use ($el, $heading, $para, $container, $row, $col, $step, $framed, $searchable) {
    $items = array();
    foreach (array_values($steps) as $n => $s) $items[] = $step($n + 1, $s[0], $s[1]);
    return $el('section', $class, lang('How It Works'), array(
        $container(array(
            $row(array(
                $col(array(
                    $para($eyebrow, 'pfl-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', $title, 'pfl-display h2 mb-4'),
                    $el('ol', 'list-unstyled mb-4', lang('Steps'), $items),
                    $button,
                ), '', '6'),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
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
$plan_cols = array();
foreach (array_keys($plans) as $key) $plan_cols[] = $plan_card($key);
$event_cols = array();
foreach (array_keys($event_services) as $key) $event_cols[] = $event_card($key);
$zone_cols = array();
foreach (array_keys($zones) as $key) $zone_cols[] = $zone_card($key);
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
        // The way in: the florist in a sentence, the cut-off, the two ways
        // on and a photo of the studio's bench.
        $el('section', 'py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Local florist and flower studio'), 'pfl-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Flowers that say it for you'), 'pfl-display display-3 mb-3'),
                        $para($florist['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Order Flowers'), '{{page:order_request}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('See the Bouquets'), '{{page:bouquets}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                        $el('p', 'd-flex align-items-center gap-2 small text-body-secondary mt-4 mb-0', lang('Same-day delivery'), array(
                            $icon('bi-truck', 'text-primary'),
                            $span($cutoff_text() . '.', '', lang('Text')),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A florist tying a bouquet of garden roses on a wooden bench'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Bouquets'), array(
            $container(array(
                $section_header(lang('Made this morning'), lang('Our favourite bouquets'), lang('All bouquets'), '{{page:bouquets}}'),
                $row($featured_bouquet_cols, '4'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Special Occasions'), array(
            $container(array(
                $section_header(lang('For every moment'), lang('Flowers for the occasion'), lang('All occasions'), '{{page:occasions}}'),
                $row($occasion_cols, '4'),
            )),
        )),

        $el('section', 'py-5', lang('Subscriptions'), array(
            $container(array(
                $section_header(lang('Never an empty vase'), lang('Flower subscriptions'), lang('How subscriptions work'), '{{page:subscriptions}}'),
                $row($plan_cols, '4'),
            )),
        )),

        // Wedding and event flowers, and the featured work read from the
        // Our Work collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Weddings and Events'), array(
            $container(array(
                $section_header(lang('Weddings and events'), lang('Flowers for the big days'), lang('Wedding and event flowers'), '{{page:events}}'),
                $row($event_cols, '4', '', 'mb-5'),
                $section_header(lang('Our Work'), lang('Recent weddings and events'), lang('All Our Work'), '{{page:work}}'),
                $widget('home_work'),
            )),
        )),

        $el('section', 'py-5', lang('Why Choose Us'), array(
            $container(array(
                $section_header(lang('Why customers choose us'), lang('Fresh, local and by hand'), lang('About Us'), '{{page:about}}'),
                $row($why_tiles, '4'),
            )),
        ), $searchable('fl-why-us', lang('fresh, same day, hand written card, guarantee, replacement'))),

        $el('section', 'py-5 bg-body-tertiary', lang('In Season'), array(
            $container(array(
                $section_header(lang('What is in season'), lang('The flowers of each season'), lang('Flower Care'), '{{page:care}}'),
                $row($season_cols, '4'),
            )),
        ), $searchable('fl-home-seasons', lang('seasonal flowers, tulips, peonies, dahlias, amaryllis'))),

        // What customers say: sample words for the florist to replace, and
        // beside them its own Google reviews, which show once Google
        // Reviews is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Kind words'), lang('What customers say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Journal'), array(
            $container(array(
                $section_header(lang('Flower Journal'), lang('Ideas and advice'), lang('All articles'), '{{page:blog}}'),
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
                $row($bouquet_cols, '4'),
                $para(lang('Prices are for the regular size; the stems may change with the season, the look and colours stay.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('fl-bouquets', lang('flowers, roses, bunch, arrangement, price'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Sizes'), array(
            $container(array(
                $section_header(lang('Bigger moments'), lang('Three sizes')),
                $row($size_cols, '4'),
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

$card_tips = array(
    lang('A few words are enough: their name, the occasion and yours'),
    lang('We write the card by hand and tuck it into the bouquet'),
    lang('Leave your name off for a surprise; we never tell'),
);

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
                $row($occasion_cols, '4'),
            )),
        ), $searchable('fl-occasions', lang('birthday, anniversary, thank you, baby, get well, funeral, sympathy'))),
        $story_band('fl-card-message', lang('The card'), lang('Your words, written by hand'),
            array(lang('Every bouquet goes with a card. Write your message with the order and we copy it by hand.')),
            $photo('occasions-card', 1200, 900), lang('A handwritten card tucked into a bouquet'), false,
            array($check_list($card_tips, 'list-unstyled mb-4'), $link(lang('Order Flowers'), '{{page:order_request}}', 'btn btn-primary rounded-pill px-4')),
            lang('card message, note, gift message, anonymous'), 'py-5 bg-body-tertiary'),
    )),
);

// ── Subscriptions ───────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'subscriptions',
    'name'             => lang('subscriptions'),
    'folder'           => 'public',
    'title'            => lang('Flower Subscriptions'),
    'meta_description' => lang('Fresh seasonal flowers every week, every two weeks or every month, delivered free on the day you choose.'),
    'tree'             => $page(array(
        $page_header(lang('Flower Subscriptions'), lang('Fresh flowers on your table without a thought: choose how often, we choose the flowers.')),
        $el('section', 'py-5', lang('Subscriptions'), array(
            $container(array(
                $row($plan_cols, '4'),
                $para(lang('Subscriptions run for three months; we write before the end and you choose whether to go on.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('fl-plans', lang('subscription, weekly flowers, monthly flowers, price, plan'))),
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
                $row($event_cols, '4'),
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

$slot_items = array();
foreach ($slots as $s) {
    $slot_items[] = $el('li', 'd-flex align-items-center gap-2 mb-2', lang('List Item'), array(
        $icon($s['icon'], 'text-primary'),
        $span($s['title'], '', lang('Text')),
    ));
}

$public_pages[] = array(
    'key'              => 'delivery',
    'name'             => lang('delivery'),
    'folder'           => 'public',
    'title'            => lang('Delivery'),
    'meta_description' => lang('Where we deliver, what it costs, same-day delivery and the delivery times you can choose.'),
    'tree'             => $page(array(
        $page_header(lang('Delivery'), $cutoff_text() . '. ' . lang('We deliver by our own van, with the flowers in water.')),
        $el('section', 'py-5', lang('Delivery Areas'), array(
            $container(array(
                $section_header(lang('Where we deliver'), lang('Delivery areas')),
                $row($zone_cols, '4'),
            )),
        ), $searchable('fl-zones', lang('delivery cost, shipping, areas, postcode, same day, next day'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Delivery Times'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('When it arrives'), 'pfl-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('Choose a delivery time'), 'pfl-display h2 mb-3'),
                        $para(lang('Choose the day and the part of the day with your order; we text the recipient when the van is on its way.'), 'text-body-secondary mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Delivery Times'), $slot_items),
                        $para(lang('Nobody at home? We leave the flowers with a neighbour or bring them back the next morning, and let you know.'), 'small text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array($framed($photo('delivery-van', 1200, 900), lang('A delivery van with flowers in buckets'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('fl-delivery-times', lang('delivery time, morning, afternoon, evening, not at home'))),
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
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), $care_items),
                    ), '', '6'),
                    $col(array($framed($photo('care-vase', 1200, 900), lang('Hands trimming flower stems over a vase'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('fl-care', lang('care, fresh, last longer, water, vase, wilting'))),
        $el('section', 'py-5 bg-body-tertiary', lang('In Season'), array(
            $container(array(
                $section_header(lang('What is in season'), lang('The flowers of each season')),
                $row($season_cols, '4'),
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
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we work')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('fl-values', lang('sustainable, local, foam free, values'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Why Choose Us'), array(
            $container(array(
                $section_header(lang('Why customers choose us'), lang('Fresh, local and by hand')),
                $row($why_tiles, '4'),
            )),
        ), $searchable('fl-about-why', lang('fresh, same day, hand written card, guarantee, replacement'))),
        $ask_band(lang('Come and see us'), lang('The studio is open every day but Sunday afternoon; come in and choose your stems.')),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: ordering and delivery, subscriptions, weddings and
// events. Each question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pfl-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
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
