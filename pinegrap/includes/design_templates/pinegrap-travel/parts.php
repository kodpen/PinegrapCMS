<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Travel Agency", part 1 of its files: the node builders,
 * the agency's own data (the regions, the destinations, the travel styles,
 * the sample tours with their itineraries and prices, what a tour
 * includes, the gift cards, why travellers choose the agency, what they
 * say and the answers of the forms' pick lists) and the parts every page
 * repeats (account bar, header, footer, trip band, destination, tour and
 * traveller cards, tour facts, itinerary days, page shells, section
 * headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-travel.php puts them together.
 *
 * Every structural node carries its role in '_label' (the designer's own
 * label, shown beside the element's tag in the layer tree).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Node builders ───────────────────────────────────────────────────────
// Same shapes the editor's createNode() produces; the editor gives every
// node its id when the tabs open. $name is the node's label in the layer
// tree ('_label'): the tag stays visible beside it.
$el = function ($tag, $class, $name, $children = array(), $extra = array()) {
    $props = array('tag' => $tag, 'cssClass' => $class);
    if ($name !== '') $props['_label'] = $name;
    return array('type' => 'semantic', 'props' => array_merge($props, $extra), 'children' => $children);
};
$heading = function ($tag, $text, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'heading', 'tag' => $tag, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$para = function ($text, $class = '', $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'paragraph', 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$link = function ($text, $href, $class, $attrs = array(), $extra = array()) {
    $props = array('contentType' => 'link', 'text' => $text, 'href' => $href, 'cssClass' => $class);
    if ($attrs) $props['_attrs'] = $attrs;
    return array('type' => 'content', 'props' => array_merge($props, $extra), 'children' => array());
};
$image = function ($src, $alt, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'image', 'src' => $src, 'alt' => $alt,
        'fluid' => false, 'rounded' => false, 'cssClass' => $class), $extra), 'children' => array());
};
$attr = function ($name, $value) {
    return array('name' => $name, 'value' => $value);
};
// A decorative icon: the words beside it say what it means.
$icon = function ($name, $class = '') use ($el, $attr) {
    return $el('i', trim('bi ' . $name . ' ' . $class), lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
$span = function ($text, $class, $name, $extra = array()) use ($el) {
    return $el('span', $class, $name, array(), array_merge(array('text' => $text), $extra));
};
$container = function ($children, $class = '') {
    return array('type' => 'container', 'props' => array('fluid' => false, 'cssClass' => $class), 'children' => $children);
};
// A gutter above 4 is wider than the container's side padding and pushes a
// phone screen sideways; wider gaps are given from lg up (g-4 g-lg-5).
$row = function ($children, $gutter = '', $justify = '', $class = '') {
    return array('type' => 'row', 'props' => array('gutter' => $gutter, 'justify' => $justify, 'align' => '', 'cssClass' => $class), 'children' => $children);
};
$col = function ($children, $md = '', $lg = '', $sm = '', $xs = '12', $class = '') {
    return array('type' => 'col', 'props' => array('xs' => $xs, 'sm' => $sm, 'md' => $md, 'lg' => $lg, 'xl' => '', 'xxl' => '',
                                                  'offsetXs' => '', 'offsetMd' => '', 'order' => '', 'cssClass' => $class), 'children' => $children);
};
$root = function ($children) {
    return array('type' => 'root', 'props' => array('cssClass' => 'd-flex flex-column min-vh-100'), 'children' => $children);
};
$widget_root = function ($children) {
    return array('type' => 'root', 'props' => array(), 'children' => $children);
};
$loop = function ($children) {
    return array('type' => 'loop_area', 'props' => array(), 'children' => $children);
};
$widget = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateWidget' => $key), 'children' => array());
};
$shared = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateShared' => $key), 'children' => array());
};
$bind = function ($token) {
    return array('_bindings' => array('text' => $token));
};
$show_when = function ($flag) {
    return array('_bindings' => array('eo_visible_if' => $flag));
};
// A part of a page the site search can point a result at (Search and SERP in
// the editor's options): its anchor, the switch, and the topics a visitor
// may search for that the part does not say itself.
$searchable = function ($id, $topics = '') {
    $props = array('id' => $id, 'searchSection' => true);
    if ($topics !== '') $props['searchKeywords'] = $topics;
    return $props;
};
// The language switcher button: the server draws it for each request and
// leaves it out while the site has a single language.
$lang_switcher = function ($class = '', $variant = 'secondary') {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => true, 'size' => 'sm',
        'align' => 'lg-end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};

// A sample picture with a fixed seed, so the same picture comes back on
// every opening until the agency puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-travel-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e0f2fe/075985?text=' . rawurlencode($initials !== '' ? $initials : 'T');
};

// ── The agency: its destinations, its tours and what they include ──────

// The agency itself. The about page tells its story; the footer, the trip
// band and the contact lines use the address, phone and opening hours.
$agency = array(
    'tagline' => lang('Small-group tours and tailor-made journeys, planned by people who have walked every route'),
    'story'   => lang('Two guides who met on a mountain path started the agency to travel the way they liked best: in small groups, with local guides, staying in family-run places and taking the time to see a place properly. Every tour we sell is one we have made ourselves.'),
    'address' => lang('Harbour Street 8, Second Floor, Old Town'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'trips@example.com',
    'hours'   => lang('Monday to Saturday, 9:00 to 18:00'),
    'licence' => lang('A licensed travel agency; every tour is covered by a package travel guarantee'),
);

// The regions, in the order the destination list filters them. Each
// answer's value is its own words, so a filter link (?region=) reads as it
// shows.
$regions = array(
    'mediterranean' => array('icon' => 'bi-sun', 'title' => lang('Mediterranean')),
    'asia'          => array('icon' => 'bi-flower3', 'title' => lang('Asia')),
    'africa'        => array('icon' => 'bi-compass', 'title' => lang('Africa')),
    'americas'      => array('icon' => 'bi-globe-americas', 'title' => lang('The Americas')),
);

// The sample destinations: the records the Destinations form starts with.
// 'season' is the best time to go in words, never a month.
$destinations = array(
    'cappadocia' => array('name' => lang('Cappadocia'), 'country' => lang('Türkiye'), 'region' => 'mediterranean',
        'summary' => lang('Fairy chimneys, cave hotels and balloons over the valleys at sunrise.'),
        'season'  => lang('Best in spring and autumn'), 'featured' => true),
    'amalfi'     => array('name' => lang('Amalfi Coast'), 'country' => lang('Italy'), 'region' => 'mediterranean',
        'summary' => lang('Lemon groves, villages on the cliffs and boats between the coves.'),
        'season'  => lang('Best from late spring to early autumn'), 'featured' => false),
    'kyoto'      => array('name' => lang('Kyoto'), 'country' => lang('Japan'), 'region' => 'asia',
        'summary' => lang('Temples in the hills, quiet gardens and the old tea houses of Gion.'),
        'season'  => lang('Best in spring and autumn'), 'featured' => true),
    'bali'       => array('name' => lang('Bali'), 'country' => lang('Indonesia'), 'region' => 'asia',
        'summary' => lang('Rice terraces, volcano walks and long days on the island\'s beaches.'),
        'season'  => lang('Best in the dry season'), 'featured' => false),
    'marrakech'  => array('name' => lang('Marrakech and the Atlas'), 'country' => lang('Morocco'), 'region' => 'africa',
        'summary' => lang('The souks of the old city, Berber villages and a night under the desert stars.'),
        'season'  => lang('Best in spring and autumn'), 'featured' => true),
    'patagonia'  => array('name' => lang('Patagonia'), 'country' => lang('Argentina and Chile'), 'region' => 'americas',
        'summary' => lang('Granite peaks, blue glaciers and trails at the end of the world.'),
        'season'  => lang('Best in the southern summer'), 'featured' => false),
);

// The travel styles, the tour list's second filter.
$styles = array(
    'culture' => array('icon' => 'bi-bank', 'title' => lang('Culture and History')),
    'nature'  => array('icon' => 'bi-tree', 'title' => lang('Nature and Hiking')),
    'beach'   => array('icon' => 'bi-water', 'title' => lang('Beach and Islands')),
    'food'    => array('icon' => 'bi-egg-fried', 'title' => lang('Food and Wine')),
);

// The sample tours: the records the Tours form starts with. 'days' and
// 'group' (the largest group) are the numbers the lists sort on; the
// itinerary has one entry per day; departures are words, never a date.
$tours = array(
    'cappadocia' => array('name' => lang('Cappadocia Valleys and Balloons'), 'destination' => 'cappadocia', 'style' => 'culture',
        'days' => 4, 'group' => 12, 'departures' => lang('Every Friday'),
        'summary'    => lang('Four days among the fairy chimneys: valley walks, an underground city and a balloon flight at sunrise.'),
        'highlights' => array(lang('A balloon flight over the valleys at sunrise'), lang('Two nights in a cave hotel'), lang('The underground city of Derinkuyu'), lang('Pottery in Avanos with a master potter')),
        'itinerary'  => array(
            array(lang('Arrival in Göreme'), lang('Transfer from the airport, a welcome dinner and a short walk to the sunset point.')),
            array(lang('Rose and Red Valleys'), lang('A morning walk through the valleys, cave churches and lunch in a village.')),
            array(lang('Balloons and the underground city'), lang('The balloon flight at dawn, then the underground city and the pottery town of Avanos.')),
            array(lang('Farewell breakfast'), lang('A last breakfast on the terrace and the transfer to the airport.')),
        ), 'featured' => true),
    'amalfi' => array('name' => lang('Amalfi Coast by Boat and Path'), 'destination' => 'amalfi', 'style' => 'beach',
        'days' => 5, 'group' => 10, 'departures' => lang('Every Saturday'),
        'summary'    => lang('Five days between the cliffs and the sea: the Path of the Gods, a boat to Capri and long lunches.'),
        'highlights' => array(lang('A day on a private boat to Capri'), lang('The Path of the Gods above Positano'), lang('A cooking class in a lemon grove'), lang('Four nights in a family hotel in Amalfi')),
        'itinerary'  => array(
            array(lang('Arrival in Amalfi'), lang('Transfer from Naples, a walk through the old town and dinner by the harbour.')),
            array(lang('Path of the Gods'), lang('A guided walk high above the sea down to Positano, and the ferry back.')),
            array(lang('Capri by boat'), lang('A day on the boat with swimming stops in the coves and an afternoon on the island.')),
            array(lang('Ravello and the lemon groves'), lang('The gardens of Ravello and a cooking class among the lemon trees.')),
            array(lang('Departure'), lang('Breakfast and the transfer to Naples.')),
        ), 'featured' => false),
    'kyoto' => array('name' => lang('Kyoto Temples and Tea'), 'destination' => 'kyoto', 'style' => 'culture',
        'days' => 6, 'group' => 12, 'departures' => lang('Twice a month'),
        'summary'    => lang('Six days in the old capital: temples at dawn, a tea ceremony, a night in a ryokan and a day in Nara.'),
        'highlights' => array(lang('A tea ceremony with a tea master'), lang('A night in a traditional ryokan'), lang('The thousand gates of Fushimi Inari before the crowds'), lang('A day trip to Nara and its deer park')),
        'itinerary'  => array(
            array(lang('Arrival in Kyoto'), lang('The train from the airport and an evening walk in Gion.')),
            array(lang('Eastern temples'), lang('Kiyomizu-dera in the morning and the Philosopher\'s Path in the afternoon.')),
            array(lang('Fushimi Inari and tea'), lang('The shrine\'s gates at dawn and a tea ceremony in the afternoon.')),
            array(lang('Day trip to Nara'), lang('The great Buddha of Tōdai-ji and the deer of the park.')),
            array(lang('Arashiyama and the ryokan'), lang('The bamboo grove, a river boat and a night in a ryokan with dinner.')),
            array(lang('Departure'), lang('Breakfast in the ryokan and the train to the airport.')),
        ), 'featured' => true),
    'bali' => array('name' => lang('Bali Rice Terraces and Beaches'), 'destination' => 'bali', 'style' => 'nature',
        'days' => 7, 'group' => 12, 'departures' => lang('Every Sunday'),
        'summary'    => lang('A week on the island: Ubud\'s rice terraces, a volcano at sunrise and three days by the sea.'),
        'highlights' => array(lang('Sunrise on Mount Batur'), lang('A cycle ride through the rice terraces'), lang('A Balinese cooking class in a village home'), lang('Three nights by the beach')),
        'itinerary'  => array(
            array(lang('Arrival in Ubud'), lang('Transfer from the airport and a welcome dinner.')),
            array(lang('Rice terraces'), lang('A cycle ride through the terraces and the villages around Ubud.')),
            array(lang('Mount Batur'), lang('A walk up the volcano for the sunrise and a rest at the hot springs.')),
            array(lang('Temples and cooking'), lang('The water temple of Tirta Empul and a cooking class in a village home.')),
            array(lang('To the coast'), lang('The drive to the beach and a free afternoon.')),
            array(lang('Beach day'), lang('A free day, or a boat to the reef to swim with the turtles.')),
            array(lang('Departure'), lang('Breakfast and the transfer to the airport.')),
        ), 'featured' => false),
    'marrakech' => array('name' => lang('Marrakech, Atlas and Desert'), 'destination' => 'marrakech', 'style' => 'nature',
        'days' => 6, 'group' => 12, 'departures' => lang('Every Thursday'),
        'summary'    => lang('Six days from the souks of Marrakech over the Atlas mountains to a camp in the dunes.'),
        'highlights' => array(lang('A night in a desert camp under the stars'), lang('A walk to a Berber village in the Atlas'), lang('The souks with a local guide'), lang('The kasbah of Aït Benhaddou')),
        'itinerary'  => array(
            array(lang('Arrival in Marrakech'), lang('Transfer to the riad and dinner on the square at dusk.')),
            array(lang('The old city'), lang('The souks, the gardens and the palaces with a local guide.')),
            array(lang('Over the Atlas'), lang('The road over the mountains to the kasbah of Aït Benhaddou.')),
            array(lang('Into the desert'), lang('Camels at sunset and a night in a camp among the dunes.')),
            array(lang('Back through the valleys'), lang('The gorges and the valley of roses on the way back to Marrakech.')),
            array(lang('Departure'), lang('Breakfast in the riad and the transfer to the airport.')),
        ), 'featured' => true),
    'patagonia' => array('name' => lang('Patagonia Peaks and Glaciers'), 'destination' => 'patagonia', 'style' => 'nature',
        'days' => 7, 'group' => 10, 'departures' => lang('Every other Monday'),
        'summary'    => lang('A week of trails under Fitz Roy and Torres del Paine, with a day on the Perito Moreno glacier.'),
        'highlights' => array(lang('A walk on the Perito Moreno glacier'), lang('The trail to the base of Fitz Roy'), lang('The towers of Torres del Paine'), lang('Small lodges at the start of the trails')),
        'itinerary'  => array(
            array(lang('Arrival in El Calafate'), lang('Transfer from the airport and a briefing on the week\'s trails.')),
            array(lang('Perito Moreno'), lang('A day at the glacier, with a walk on the ice in crampons.')),
            array(lang('To El Chaltén'), lang('The drive north and a short walk to a viewpoint.')),
            array(lang('Fitz Roy'), lang('The long trail to the lake at the foot of Fitz Roy.')),
            array(lang('Across to Chile'), lang('The border crossing to Torres del Paine.')),
            array(lang('Torres del Paine'), lang('The walk to the base of the towers.')),
            array(lang('Departure'), lang('The transfer to Puerto Natales and the flight home.')),
        ), 'featured' => false),
);

// A tour's price per traveller, sharing a double room, per currency. The
// tour list, the tour page and the Tours form's Price field show it.
$tour_prices = array(
    'cappadocia' => array('USD' => 890, 'EUR' => 820, 'GBP' => 710, 'TRY' => 32000),
    'amalfi'     => array('USD' => 1690, 'EUR' => 1560, 'GBP' => 1350, 'TRY' => 61000),
    'kyoto'      => array('USD' => 2390, 'EUR' => 2200, 'GBP' => 1900, 'TRY' => 86000),
    'bali'       => array('USD' => 1490, 'EUR' => 1370, 'GBP' => 1190, 'TRY' => 54000),
    'marrakech'  => array('USD' => 1190, 'EUR' => 1100, 'GBP' => 950, 'TRY' => 43000),
    'patagonia'  => array('USD' => 3290, 'EUR' => 3030, 'GBP' => 2620, 'TRY' => 118000),
);

// The deposit per traveller that holds the places once a booking is
// confirmed (the shop sells it per tour, with the shop).
$deposit = array('USD' => 200, 'EUR' => 180, 'GBP' => 160, 'TRY' => 7000);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A tour's price and a deposit therefore show one amount wherever they
// appear.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A tour's "From … per traveller" words, the value of its Price field.
$price_text = function ($key) use ($tour_prices, $money) {
    return sprintf(lang('From %s per traveller'), $money($tour_prices[$key]));
};
// A tour's length in words: "4 days, 3 nights".
$length_text = function ($key) use ($tours) {
    $days = (int)$tours[$key]['days'];
    return sprintf(lang('%1$d days, %2$d nights'), $days, $days - 1);
};
// A tour's itinerary as the value of its Itinerary field: one line per
// day, the day's number, its title and what it holds.
$itinerary_text = function ($key) use ($tours) {
    $lines = array();
    foreach ($tours[$key]['itinerary'] as $n => $day) {
        $lines[] = sprintf(lang('Day %1$d — %2$s: %3$s'), $n + 1, $day[0], $day[1]);
    }
    return implode("\n", $lines);
};

// What every tour includes and what it leaves to the traveller (the tour
// page and the travel information page).
$included = array(
    lang('Every night\'s stay, sharing a double room'),
    lang('Breakfast every day and the meals the itinerary names'),
    lang('A tour leader and local guides'),
    lang('Transfers and transport during the tour'),
    lang('Entry to every sight the itinerary names'),
);
$not_included = array(
    lang('Flights to the start and from the end of the tour'),
    lang('Travel insurance'),
    lang('Visas, where the country asks for one'),
    lang('Tips and personal spending'),
);

// The deals. Each opens the booking request with the deal chosen (?deal=);
// the terms are what the agency confirms by hand.
$deals = array(
    'early' => array('icon' => 'bi-calendar-check', 'title' => lang('Early Booking'), 'badge' => lang('Save 10%'),
        'text'  => lang('Book any tour at least ninety days before it leaves.'),
        'terms' => lang('The saving is taken off the tour price when we confirm your places.')),
    'group' => array('icon' => 'bi-people', 'title' => lang('Friends Travel Together'), 'badge' => lang('Save 5% each'),
        'text'  => lang('Book four places or more on the same departure.'),
        'terms' => lang('For every traveller in one booking request of four or more.')),
    'solo'  => array('icon' => 'bi-person', 'title' => lang('No Single Supplement'), 'badge' => lang('Solo travellers'),
        'text'  => lang('Travel alone and share a room with another traveller of the group, or pay no extra for a room of your own on chosen departures.'),
        'terms' => lang('On the departures we mark when we confirm; ask for it in your request.')),
);

// Why travellers choose the agency (the home and the about page).
$why_us = array(
    array('icon' => 'bi-people', 'title' => lang('Small groups'),
        'text' => lang('Never more than twelve travellers, so every place has room for us.')),
    array('icon' => 'bi-person-badge', 'title' => lang('Local guides'),
        'text' => lang('Guides who live where you travel and show you their own town.')),
    array('icon' => 'bi-house-heart', 'title' => lang('Family-run stays'),
        'text' => lang('Small hotels, guesthouses and lodges run by the families who own them.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Protected booking'),
        'text' => lang('A licensed agency: your money is protected from deposit to return.')),
);

// The gift cards the shop sells (with the shop), with their prices. The
// SKUs name the template, so the products are its own.
$gift_cards = array(
    'gift250' => array('sku' => 'TRAVEL-GIFT-250', 'icon' => 'bi-gift', 'title' => lang('Travel Gift Card'),
        'text'  => lang('A gift card towards any tour or tailor-made journey, valid for two years.'),
        'price' => array('USD' => 250, 'EUR' => 250, 'GBP' => 250, 'TRY' => 10000)),
    'gift500' => array('sku' => 'TRAVEL-GIFT-500', 'icon' => 'bi-gift', 'title' => lang('Travel Gift Card for Two'),
        'text'  => lang('A larger gift card for a journey for two, valid for two years.'),
        'price' => array('USD' => 500, 'EUR' => 500, 'GBP' => 500, 'TRY' => 20000)),
);

// The agency in four figures (the home and about pages).
$figures = array(
    array('value' => '6', 'label' => lang('Destinations on four continents')),
    array('value' => '12', 'label' => lang('Travellers at most in a group')),
    array('value' => '4.9', 'label' => lang('Average traveller rating')),
    array('value' => '15', 'label' => lang('Years of guiding')),
);

// What travellers say, each about one of the sample tours.
$testimonials = array(
    array('tour' => 'cappadocia', 'name' => lang('Elif Yıldız'), 'role' => lang('Travelled on Cappadocia Valleys and Balloons'),
        'quote' => lang('The balloon at sunrise was everything we hoped for, and the valley walks were the surprise of the trip.')),
    array('tour' => 'kyoto', 'name' => lang('Mark Turner'), 'role' => lang('Travelled on Kyoto Temples and Tea'),
        'quote' => lang('Our guide took us to the temples before the crowds and to restaurants we would never have found.')),
    array('tour' => 'marrakech', 'name' => lang('Ayşe Kara'), 'role' => lang('Travelled on Marrakech, Atlas and Desert'),
        'quote' => lang('A night in the desert camp under more stars than I have ever seen. Every detail was taken care of.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the traveller chose.
$region_titles = array();
foreach ($regions as $r) $region_titles[] = $r['title'];
$destination_names = array();
foreach ($destinations as $d) $destination_names[] = $d['name'];
$style_titles = array();
foreach ($styles as $s) $style_titles[] = $s['title'];
$tour_names = array();
foreach ($tours as $t) $tour_names[] = $t['name'];
$booking_tour_choices = array_merge($tour_names, array(lang('Not decided yet')));
$deal_choices = array(lang('No deal'));
foreach ($deals as $o) $deal_choices[] = $o['title'];
$room_choices = array(
    array(lang('Sharing a double room'), lang('Sharing a double room')),
    array(lang('Sharing a twin room'), lang('Sharing a twin room')),
    array(lang('A single room'), lang('A single room')),
);
// The states of a booking request, set by the agency in the panel.
$booking_statuses = array(lang('Request received'), lang('Confirmed'), lang('Waiting for you'), lang('Declined'), lang('Cancelled'));
$trip_kinds = array(lang('Honeymoon'), lang('Family holiday'), lang('Group of friends'), lang('Adventure'), lang('Something else'));
$destination_choices = array_merge($destination_names, array(lang('Somewhere else'), lang('Not sure yet')));
$length_choices = array(lang('Up to a week'), lang('One to two weeks'), lang('More than two weeks'));
// The budget bands in the shop's currency, as the tour prices are written.
$budget_low = $money(array('USD' => 1500, 'EUR' => 1400, 'GBP' => 1200, 'TRY' => 55000));
$budget_high = $money(array('USD' => 3000, 'EUR' => 2800, 'GBP' => 2400, 'TRY' => 110000));
$budget_choices = array(
    sprintf(lang('Up to %s per traveller'), $budget_low),
    sprintf(lang('%1$s to %2$s per traveller'), $budget_low, $budget_high),
    sprintf(lang('More than %s per traveller'), $budget_high),
    lang('Not sure yet'),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a tailor-made enquiry, set by the agency in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Proposal sent'), lang('Booked'), lang('Declined'));

// The query string of a link that opens a list with a filter set
// (?region=, ?destination=, ?style=) or a form with a choice made (?tour=,
// ?deal=): the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the trip band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('trip_band');
    $children[] = $shared('footer');
    return $root($children);
};

// The top of the inner pages: where the page sits, its title and one
// sentence, and whatever the page adds under them.
$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-body-tertiary', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'ptv-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'ptv-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'ptv-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the why-us points are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what travellers say.
$stars = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex gap-1 text-warning mb-2', lang('Rating'), array(
        $span(lang('Five out of five stars'), 'visually-hidden', lang('Text')),
        $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'),
    ));
};

// A numbered step of a short how-to.
$step = function ($n, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'd-flex gap-3 mb-4', lang('Step'), array(
        $span((string)$n, 'badge rounded-pill text-bg-primary fs-6 align-self-start', lang('Step Number')),
        $el('div', '', lang('Text'), array(
            $heading('h3', $title, 'h6 fw-bold mb-1'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ));
};

// A check list: a tick and the words on each line.
$check_list = function ($items, $class = 'list-unstyled mb-0') use ($el, $icon, $span) {
    $children = array();
    $last = count($items) - 1;
    foreach (array_values($items) as $n => $text) {
        $children[] = $el('li', 'd-flex gap-2 ' . ($n === $last ? 'mb-0' : 'mb-2'), lang('List Item'), array(
            $icon('bi-check2', 'text-primary'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', $class, lang('Check List'), $children);
};

// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answer, $topics = '', $parent = 'tv-faq') use ($el, $para, $attr, $searchable) {
    $id = $parent . '-' . $n;
    return $el('div', 'accordion-item', lang('Question'), array(
        $el('h3', 'accordion-header', lang('Question Header'), array(
            $el('button', 'accordion-button collapsed', lang('Question'), array(), array(
                'text'   => $question,
                '_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#' . $id),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-controls', $id),
                ),
            )),
        )),
        $el('div', 'accordion-collapse collapse', lang('Answer'), array(
            $el('div', 'accordion-body text-body-secondary', lang('Body'), array($para($answer, 'mb-0'))),
        ), array('id' => $id, '_attrs' => array($attr('data-bs-parent', '#' . $parent)))),
    ), $searchable($id . '-item', $topics));
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the first deal and the way to read it.
// A shared component.
$notice = function () use ($el, $icon, $span, $link, $deals) {
    $o = reset($deals);
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon($o['icon'], 'text-primary'),
        $span($o['title'] . ' — ' . $o['badge'], '', lang('Text')),
        $link(lang('See the deals'), '{{page:deals}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher,
// the login region and the cart (with the shop) on the right. Every page
// places it as one shared component, so a change to it is made once for
// the whole site.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body-tertiary', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex flex-wrap align-items-center justify-content-end column-gap-1 column-gap-sm-2 row-gap-1 ms-auto', lang('Account Links'), array(
                $lang_switcher('text-nowrap'), $widget('login_region'), $widget('cart_link'),
            )),
        )),
    ));
};

// The header: the agency's name, the menu and the way to book a tour. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page. The deposits and gift cards
// are the shop's and are linked from the shop pages only, so the header
// stands on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('destinations', lang('Destinations')),
        array('tours', lang('Tours')),
        array('deals', lang('Deals')),
        array('enquiry', lang('Tailor-made Trips')),
        array('travel_info', lang('Travel Information')),
        array('about', lang('About Us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-airplane', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'ptv-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#tv-nav'),
                    $attr('aria-controls', 'tv-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ptv-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-suitcase'),
                            $span(lang('Book a Tour'), '', lang('Link Text')),
                        ), array('href' => '{{page:book}}')),
                    )),
                ), array('id' => 'tv-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the agency in a sentence, how to reach it and
// its profiles, the destinations by region, the tours by style, the
// traveller pages and the agency's pages; at the bottom the year, the
// site's name and the licence.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $regions, $styles, $agency, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $region_links = array($link(lang('All Destinations'), '{{page:destinations}}', $plain));
    foreach ($regions as $r) $region_links[] = $link($r['title'], '{{page:destinations}}?' . $query(array('region' => $r['title'])), $plain);
    $style_links = array($link(lang('All Tours'), '{{page:tours}}', $plain));
    foreach ($styles as $s) $style_links[] = $link($s['title'], '{{page:tours}}?' . $query(array('style' => $s['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Agency'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-airplane', 'text-primary'),
                        $span('{{site_name}}', 'ptv-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($agency['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($agency['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($agency['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $agency['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($agency['email'], 'mailto:' . $agency['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($agency['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-whatsapp', 'WhatsApp'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Destination Links'), array(
                    $heading('h2', lang('Destinations'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($region_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Tour Links'), array(
                    $heading('h2', lang('Tours'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($style_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Travellers'), array(
                    $heading('h2', lang('For Travellers'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book a Tour'), '{{page:book}}', $plain),
                        $link(lang('My Bookings'), '{{page:my_bookings}}', $plain),
                        $link(lang('Tailor-made Trips'), '{{page:enquiry}}', $plain),
                        $link(lang('My Enquiries'), '{{page:my_enquiries}}', $plain),
                        $link(lang('Travel Information'), '{{page:travel_info}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Agency'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Deals'), '{{page:deals}}', $plain),
                        $link(lang('Travel Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($agency['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The trip band: a full-width band in the palette's colour with the way to
// book a tour and the way to ask for a journey of one's own. Shared, so
// every page that ends with it carries the same call.
$trip_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Where would you like to go next?'), 'ptv-display h2 mb-2'),
                    $para(lang('Choose a tour and its departure, or tell us about the journey you have in mind; we answer within a day.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Tour'), '{{page:book}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('Plan a Tailor-made Trip'), '{{page:enquiry}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// Motion from the interactions on the nodes (pg_fx; nothing moves under
// reduced motion): $fx gives a node one effect, $rise brings the children
// of a row or a list in one after another as they scroll into view, and
// $fade_in is the way a section's heading comes in.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
$rise = function ($node, $stagger = 90) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger));
};
$fade_in = array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700);

// The facts of a tour in one line: length, largest group, destination and
// style, each bound to the Tours form's field of the same name.
$tour_facts = function ($sample_key = 'cappadocia', $class = 'd-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $tours, $destinations, $styles, $length_text) {
    $t = $tours[$sample_key];
    $fact = function ($icon_name, $children, $name) use ($el, $icon) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name, 'text-primary')), $children));
    };
    return $el('ul', 'list-unstyled ' . $class, lang('Tour Facts'), array(
        $fact('bi-calendar3', array($span($length_text($sample_key), '', lang('Length'), $bind('length'))), lang('Length')),
        $fact('bi-people', array(
            $span(lang('Up to'), '', lang('Label')),
            $span((string)$t['group'], '', lang('Value'), $bind('group_size')),
            $span(lang('travellers'), '', lang('Unit')),
        ), lang('Group Size')),
        $fact('bi-geo-alt', array($span($destinations[$t['destination']]['name'], '', lang('Tour Destination'), $bind('destination'))), lang('Tour Destination')),
        $fact($styles[$t['style']]['icon'], array($span($styles[$t['style']]['title'], '', lang('Travel Style'), $bind('style'))), lang('Travel Style')),
    ));
};

// A tour's card of the lists (the home, the tour list and a destination's
// page): the photo, the style, the name linking the tour page, the
// summary, the facts, the departures and the price. Every value is bound
// to the Tours form's field of the same name; the sample fills the canvas.
$tour_card = function ($sample_key = 'cappadocia') use ($el, $para, $span, $link, $image, $col, $bind, $tours, $styles, $price_text, $tour_facts, $photo, $icon, $attr) {
    $t = $tours[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden ptv-lift', lang('Tour Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('tour-' . $sample_key, 800, 600), $t['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($styles[$t['style']]['title'], 'ptv-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Travel Style'), $bind('style')),
                $el('h3', 'ptv-display h4 position-static mb-2', lang('Tour Name'), array(
                    $link($t['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($t['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $tour_facts($sample_key, 'd-flex flex-wrap gap-3 small text-body-secondary mb-3'),
                $el('p', 'd-flex align-items-center gap-1 small mb-2', lang('Departures'), array(
                    $icon('bi-calendar-event', 'text-primary'),
                    $span($t['departures'], '', lang('Departures'), $bind('departures')),
                )),
                $para($price_text($sample_key), 'ptv-price fw-semibold mt-auto mb-0', $bind('price')),
            )),
        )),
    ), '6', '4');
};

// A destination's card (the home and the destination list): the cover,
// the region, the name linking the destination page, the country and the
// summary, each bound to the Destinations form's field of the same name.
$destination_card = function ($sample_key = 'cappadocia') use ($el, $para, $span, $link, $image, $col, $bind, $destinations, $regions, $photo, $attr) {
    $d = $destinations[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden ptv-lift', lang('Destination Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('place-' . $sample_key, 800, 450), $d['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'cover', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($regions[$d['region']]['title'], 'ptv-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Region'), $bind('region')),
                $el('h3', 'ptv-display h4 position-static mb-1', lang('Destination Name'), array(
                    $link($d['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($d['country'], 'small fw-semibold mb-2', $bind('country')),
                $para($d['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// A deal's card (the deals page and the home): the badge, the name, a
// line, the terms and a link that opens the booking request with the deal
// chosen.
$deal_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon, $deals, $query) {
    $o = $deals[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Deal Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-3', lang('Deal Heading'), array(
                    $icon($o['icon'], 'fs-3 text-primary'),
                    $span($o['badge'], 'badge rounded-pill text-bg-primary', lang('Badge')),
                )),
                $heading('h3', $o['title'], 'ptv-display h4 mb-2'),
                $para($o['text'], 'mb-2'),
                $para($o['terms'], 'small text-body-secondary mb-4'),
                $link(lang('Book with this deal'), '{{page:book}}?' . $query(array('deal' => $o['title'])), 'btn btn-primary rounded-pill mt-auto'),
            )),
        )),
    ), '', '4');
};

// What a traveller says: the quote, the stars, the traveller and the tour.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Traveller Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'ptv-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Traveller'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('width' => '48', 'height' => '48', '_attrs' => array($attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3', $hint = '') use ($el, $attr, $para) {
    $children = array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    );
    if ($hint !== '') $children[] = $para($hint, 'form-text', array('id' => $id . '-help'));
    return $el('div', $class, $label, $children);
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    $props = array('id' => $id, '_attrs' => array_merge($attrs, $more));
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true, $cf = array()) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('textarea', 'form-control', lang('Field'), array(), $props);
};
// A pick list: the first, empty option is the prompt, not an answer.
// $selected is the answer chosen to begin with (no prompt then).
$select = function ($id, $name, $prompt, $options, $required = true, $selected = '', $cf = array()) use ($el, $attr) {
    $children = array();
    if ($selected === '') {
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $prompt, '_attrs' => array($attr('value', ''))));
    }
    foreach ($options as $text) {
        $oattrs = array($attr('value', $text));
        if ($text === $selected) $oattrs[] = $attr('selected', '');
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $text, '_attrs' => $oattrs));
    }
    $attrs = array($attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('select', 'form-select', lang('Field'), $children, $props);
};
$submit = function ($text, $class = 'btn btn-primary rounded-pill px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// A box the visitor must tick: what was agreed to is stored with the form.
// $privacy adds a link to the privacy page after the label.
$agree = function ($id, $name, $text, $privacy = false) use ($el, $attr, $link) {
    $children = array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
    );
    if ($privacy) $children[] = $link(lang('Privacy Policy'), '{{page:privacy}}', 'ms-1');
    return $el('div', 'form-check small mb-3', lang('Consent'), $children);
};
// A group of radio buttons under its legend: the legend names the field.
// Each option is array(value, label).
$radios = function ($id, $name, $legend, $options, $checked) use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $rattrs = array($attr('type', 'radio'), $attr('name', $name), $attr('value', $o[0]));
        if ($o[0] === $checked) $rattrs[] = $attr('checked', '');
        $children[] = $el('div', 'form-check form-check-inline', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => $rattrs)),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o[1], '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', 'mb-3', $legend, $children);
};
// The numbered heading of a part of a longer form.
$form_step = function ($n, $title) use ($el, $span, $heading) {
    return $el('div', 'd-flex align-items-center gap-2 mt-2 mb-3', lang('Step'), array(
        $span((string)$n, 'badge rounded-circle text-bg-primary', lang('Step Number')),
        $heading('h2', $title, 'h6 fw-bold mb-0'),
    ));
};

// The record's Edit button, shown to whoever may edit the submissions of
// its form; it opens the record in the control panel and comes back.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};

// The comments of a record page (a request's messages, a conversation's
// replies, a post's comments), in the reading column: the page's Comments
// widget, which reads the record the page's view above it shows.
$comments_section = function ($name, $widget_key) use ($el, $container, $row, $col, $widget) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array($widget($widget_key)), '', '8'),
            ), '', 'center'),
        )),
    ));
};

// A Messages node: the notices and errors of the form it sits in.
$messages = function ($class = '') {
    return array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => $class), 'children' => array());
};

// A thread of the Comments widget: the comments in the widget's order, each
// a card with its writer, badges, date, the comment it answers where the
// thread stops going deeper, its words, its file and its links; the page
// links; the form (its notices first, the comment being answered, the
// stars while the page asks for them, the words, the name for whoever types
// one, the file, the staff's publish options, the box to follow the thread,
// the CAPTCHA); the box to follow or stop following; the staff's Stop and
// Allow buttons. A part under a flag shows only while it holds for the
// visitor. $w: the words (title, empty, heading, label, sign_in, submit,
// watch_box, follow, unfollow, close, reopen), reply (each comment can be
// answered and shared), watch (the boxes to follow the thread).
$comment_thread = function ($w) use ($el, $heading, $para, $link, $span, $attr, $bind, $show_when, $loop, $widget_root, $messages) {
    $actions = array();
    if ($w['reply']) {
        $actions[] = $link(lang('Reply'), '#', 'link-primary', array(), array('_bindings' => array('action' => 'comment_reply')));
        $actions[] = $link(lang('Share'), '#', 'link-secondary', array(), array('_bindings' => array('action' => 'comment_share')));
    }
    // Bound to the comment's edit screen, so the list adds no pencil of its own.
    $actions[] = $link(lang('Edit'), '#', 'link-secondary', array(), array('_bindings' => array('href' => '__comment_edit_url', 'eo_visible_if' => 'comment_can_edit')));

    $badges = array(
        $span(lang('Jane Cooper'), 'fw-semibold', lang('Name'), $bind('__comment_name')),
        $span('jane', 'small text-body-secondary', lang('User Name'), array('_bindings' => array('text' => '__comment_staff_name', 'eo_visible_if' => 'is_staff'))),
        $span(lang('Badge'), 'badge rounded-pill text-bg-secondary', lang('Badge'), array('_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
    );
    if ($w['reply']) {
        $badges[] = $span(lang('Featured'), 'badge rounded-pill text-bg-primary', lang('Featured'), $show_when('comment_is_featured'));
    }
    $card = $el('div', 'card border-0 bg-body-tertiary rounded-4 flex-grow-1', lang('Comment Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $el('p', 'small fw-semibold text-danger-emphasis mb-2 pg-hide-if-empty', lang('Comment Status'), array(),
                array('text' => lang('Awaiting approval'), '_bindings' => array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending'))),
            $el('div', 'text-warning mb-1', lang('Stars'), pg_sw_part_comment_stars(), $show_when('comment_has_rating')),
            $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Name Line'), $badges),
            $span(lang('2 days ago'), 'd-block small text-body-secondary pg-hide-if-empty', lang('Time'), $bind('__comment_date')),
            $el('blockquote', 'border-start border-3 ps-3 my-2 small text-body-secondary', lang('Quote'), array(
                $el('p', 'mb-1', lang('In reply to'), array(
                    $span(lang('In reply to'), 'me-1', lang('Label')),
                    $link(lang('Jane Cooper'), '#', 'fw-semibold link-body-emphasis', array(), array('_bindings' => array('href' => '__comment_parent_url', 'text' => '__comment_parent_name'))),
                )),
                $el('p', 'mb-0 pg-hide-if-empty', lang('Quoted text'), array(), array('text' => lang('Quoted text'), '_bindings' => array('text' => '__comment_quote'))),
            ), $show_when('comment_has_quote')),
            $el('div', 'mt-2', lang('Message'), array(), array('text' => lang('Comment text'), '_bindings' => array('text' => '__comment_message_html'))),
            $el('p', 'small mt-3 mb-0', lang('Attachment'), array(
                $link('file.pdf', '#', 'me-2', array(), array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__comment_file_url', 'text' => '__comment_file_name'))),
                $span('12 KB', 'text-body-secondary', lang('Value'), $bind('__comment_file_size')),
            ), $show_when('comment_has_file')),
            $el('div', 'd-flex flex-wrap gap-3 mt-3 small', lang('Comment Actions'), $actions),
        )),
    ));

    $label = function ($for, $text, $class = 'form-label') use ($el, $attr) {
        return $el('label', $class, lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $for))));
    };
    $control = function ($tag, $class, $id, $value, $attrs = array()) use ($el) {
        return $el($tag, $class, lang('Field'), array(), array('id' => $id, '_attrs' => $attrs, '_bindings' => array('value' => $value)));
    };
    $fields = array($messages());
    if ($w['reply']) {
        // The comment being answered. Reply shows the box by its hidden
        // attribute, so it takes no display utility (d-flex would keep it
        // on screen, empty).
        $fields[] = $el('div', 'alert alert-secondary py-2 small', lang('Replying To'), array(
            $span(lang('Replying to'), 'me-1', lang('Text')),
            $el('strong', '', lang('Name'), array(), array('text' => lang('Jane Cooper'), '_bindings' => array('text' => '__reply_to_name'))),
            $link(lang('Cancel'), '#', 'ms-3 alert-link', array(), array('_bindings' => array('action' => 'comment_reply_cancel'))),
        ), $show_when('is_replying'));
    }
    $fields[] = $el('div', 'mb-3', lang('Rating'), array(
        $el('div', 'form-label', lang('Label'), array(), array('text' => lang('Rating'))),
        $el('div', '', lang('Stars'), array(), array('_bindings' => array('section' => 'comment_rating_input'))),
    ), $show_when('rating_on'));
    $fields[] = $el('div', 'mb-3', lang('Message'), array(
        $label('comment_message', $w['label']),
        $control('textarea', 'form-control', 'comment_message', 'comment_message', array($attr('rows', '5'))),
    ));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name', lang('Your Name')),
        $control('input', 'form-control', 'comment_name', 'comment_name', array($attr('type', 'text'))),
    ), $show_when('name_is_typed'));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name_type', lang('Your Name')),
        $control('select', 'form-select', 'comment_name_type', 'comment_name_type'),
    ), $show_when('name_is_chosen'));
    $fields[] = $el('p', 'mb-3 small', lang('Posting as'), array(
        $span(lang('Posting as'), 'me-1', lang('Label')),
        $el('strong', '', lang('Name'), array(), array('text' => lang('Jane Cooper'), '_bindings' => array('text' => '__comment_author_name'))),
    ), $show_when('name_is_fixed'));
    $fields[] = $el('div', 'mb-3', lang('Attach a File'), array(
        $label('comment_file', lang('Attach a File')),
        $control('input', 'form-control', 'comment_file', 'comment_file', array($attr('type', 'file'))),
    ), $show_when('files_on'));
    $fields[] = $el('div', 'mb-3 small', lang('Publish'), array(), array('_bindings' => array('section' => 'comment_publish')));
    if ($w['watch']) {
        $fields[] = $el('div', 'form-check mb-3', lang('Watch'), array(
            $control('input', 'form-check-input', 'comment_watcher', 'comment_watcher', array($attr('type', 'checkbox'))),
            $label('comment_watcher', $w['watch_box'], 'form-check-label'),
        ), $show_when('watching_on'));
    }
    $fields[] = $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'comment_captcha')));
    $fields[] = $el('div', '', lang('Send Button'), array(
        $el('button', 'btn btn-primary rounded-pill px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-outline-secondary rounded-pill', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
    };

    $children = array(
        $heading('h2', $w['title'], 'h4 fw-bold mb-3', array('_bindings' => array('text' => '__comments_title', 'eo_visible_if' => 'has_comments'))),
        $el('div', 'pg-hide-if-empty', lang('Comment List'), array(
            $loop(array($el('div', 'd-flex mb-3', lang('Comment'), array(
                $el('div', 'flex-shrink-0 border-start border-2 ms-2 me-3', lang('Reply Indent'), array(), $show_when('comment_is_reply')),
                $card,
            )))),
            $el('div', 'mt-3', lang('Pagination'), array(), array('_bindings' => array('section' => 'comment_pagination'))),
        )),
        $para($w['empty'], 'text-body-secondary', $show_when('no_comments')),
        $el('div', 'mt-4', lang('Add a comment'), array(
            $heading('h3', $w['heading'], 'h5 fw-bold mb-2', $show_when('comments_open')),
            $el('p', 'small text-body-secondary mb-3 pg-hide-if-empty', lang('Text'), array(), array('_bindings' => array('text' => '__comments_intro', 'eo_visible_if' => 'comments_open'))),
            $el('div', 'alert alert-secondary', lang('Closed'), array(), array('_bindings' => array('text' => '__comments_closed_message', 'eo_visible_if' => 'comments_closed'))),
            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2', lang('Sign in'), array(
                $span($w['sign_in'], '', lang('Text')),
                $link(lang('Sign in'), '#', 'alert-link', array(), array('rel' => 'nofollow', '_bindings' => array('href' => '__login_url'))),
            ), $show_when('must_sign_in')),
            $el('form', 'card card-body border-0 bg-body-tertiary rounded-4 p-4', lang('Comment Form'), $fields, array('_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment'))),
        )),
    );
    if ($w['watch']) {
        // Following the thread: how many follow it, the visitor's own
        // button, and the staff's list of who follows.
        $children[] = $el('div', 'small border-top pt-3 mt-4 d-flex flex-column gap-2 pg-hide-if-empty', lang('Watch'), array(
            $el('p', 'mb-0 pg-hide-if-empty', lang('Text'), array(), $bind('__watcher_count_text')),
            $el('form', 'd-flex flex-wrap align-items-center gap-2', lang('Watch Form'), array(
                $messages('w-100'),
                $small_button($w['follow'], 'comment_watch'),
                $small_button($w['unfollow'], 'comment_unwatch'),
            ), array('_bindings' => array('action' => 'comment_watch_form'))),
            $el('div', '', lang('Watchers'), array(), array('_bindings' => array('section' => 'comment_watchers'))),
        ), $show_when('watching_on'));
    }
    $children[] = $el('div', 'd-flex justify-content-end mt-3', lang('Staff Controls'), array(
        $el('form', 'd-flex gap-2', lang('Open or Close'), array(
            $small_button($w['close'], 'comment_close'),
            $small_button($w['reopen'], 'comment_reopen'),
        ), array('_bindings' => array('action' => 'comment_allow_new_form'))),
    ), $show_when('is_staff'));

    return $widget_root($children);
};
// ── Staff, newsletter and blog parts ────────────────────────────────────

// The staff pages' own menu, under the header: one shared component.
$staff_bar = function () use ($el, $link, $span) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center gap-4 py-2', lang('Staff Bar Container'), array(
            $span(lang('Staff'), 'badge rounded-pill text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('tour_new', lang('New Tour')),
                $item('destination_new', lang('New Destination')),
                $item('staff_bookings', lang('Booking Requests')),
                $item('staff_enquiries', lang('Tailor-made Enquiries')),
                $item('blog_new', lang('New Article')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no band.
$staff_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('account_bar'),
        $shared('header'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
    ));
};

// The newsletter's e-mail field and button, in one line. The address is the
// contact's, so the sign-up becomes a contact of the newsletter's group.
$newsletter_line = function ($id) use ($el, $attr, $submit) {
    return $el('div', 'input-group mb-3', lang('E-mail and Button'), array(
        $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Email'), '_attrs' => array($attr('for', $id)))),
        $el('input', 'form-control', lang('Field'), array(), array('id' => $id, '_cf' => array('contact_field' => 'email_address'), '_attrs' => array(
            $attr('type', 'email'), $attr('name', 'email'), $attr('required', ''), $attr('autocomplete', 'email'),
            $attr('placeholder', lang('Your e-mail address')),
        ))),
        $submit(lang('Subscribe')),
    ));
};
$newsletter_consent = function ($id) use ($agree) {
    return $agree($id, 'consent', lang('I would like to receive the agency\'s deals and Travel Journal posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// booking request takes documents (a passport copy, a visa) and pictures,
// so the default accepts PDFs, office files, text and pictures; one that
// takes only pictures is a collection's image kind (a tour's photos).
$file = function ($id, $name, $folder, $accept = '.pdf,.doc,.docx,.odt,.txt,.jpg,.jpeg,.png') use ($el, $attr) {
    return $el('input', 'form-control', '', array(), array('id' => $id, '_attrs' => array(
        $attr('type', 'file'), $attr('name', $name), $attr('accept', $accept),
    ), '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')
            + ($accept === 'image/*' ? array('kind' => 'image') : array())));
};

// A picture that leaves with its row when the record has none (the
// pg-hide-if-empty row of the form widgets).
$optional_image = function ($token, $alt_token, $class, $frame, $sample, $alt) use ($el, $image) {
    return $el('div', 'pg-hide-if-empty', lang('Picture'), array(
        $el('span', 'pg-field-value d-block ' . $frame, lang('Picture Frame'), array(
            $image($sample, $alt, $class, array('_bindings' => array('src' => $token, 'alt' => $alt_token))),
        )),
    ));
};

// A blog post card of the lists (the blog page and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('Ten things to pack for a walking tour'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'card-text small text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold text-primary', lang('Read More')),
            )),
        )),
    ), '6', '4');
};

// Summarize the post with AI: with the visitor's own assistant (each link
// opens it with a question about the post's address) or with the browser's
// built-in model, whose button the browser shows only where it can
// summarize and whose points come into the box below. Shown for a post
// anybody may open.
$ai_summary = function () use ($el, $link, $icon, $span, $attr, $bind, $show_when) {
    $assistant = function ($text, $token) use ($link, $attr) {
        return $link($text, '#', 'btn btn-sm btn-outline-primary rounded-pill', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    return $el('aside', 'p-3 rounded-4 bg-body-tertiary mb-4', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars', 'text-primary'),
            $span(lang('Summarize this post with AI'), 'small fw-semibold me-1', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-primary rounded-pill', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};

