<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ferry", part 1 of its files: the node builders, the
 * line's own data (the ports, the seasons, the routes with their sailings,
 * the kinds of fare and the fares, what is on board, the travel tips, the
 * figures, what travellers say, the sample travel notices and the answers
 * of the forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, booking band, route, port, notice, feature and traveller
 * cards, the timetable and fare tables, page shells, section headers, form
 * fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-ferry.php puts them together.
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
// every opening until the line puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-ferry-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ccfbf1/134e4a?text=' . rawurlencode($initials !== '' ? $initials : 'F');
};



// ── The line: its ports, its routes, its sailings and its fares ─────────

// The ferry line itself. The about page tells its story; the footer, the
// booking band, the travel page and the contact lines use the address, the
// phone, the booking office's hours and the check-in rule.
$line = array(
    'tagline'  => lang('A ferry line between the mainland and the islands, carrying foot passengers, cars and campervans across the bay every day of the year'),
    'story'    => lang('The line began with one wooden boat that took the islanders\' post, their shopping and the odd visitor across the bay. A car ferry followed when the island roads were paved, and today three ships sail four routes, carrying commuters, school children, holidaymakers and the lorries that keep the island shops full.'),
    'address'  => lang('Ferry Terminal, Quay Road 1, Pine Bay'),
    'phone'    => '+90 555 000 00 00',
    'email'    => 'bookings@example.com',
    'hours'    => lang('Booking office: every day, 7:00 to 21:00 in summer and 7:30 to 19:00 in winter'),
    'opening'  => lang('Sailing every day of the year, weather permitting'),
    'checkin'  => lang('Check in at least 45 minutes before departure with a vehicle and 20 minutes before on foot'),
);

// The ports. The ports page shows each with its check-in, parking and the
// ways to get there; the routes name their two ports by key.
$ports = array(
    'pinebay' => array('icon' => 'bi-buildings', 'title' => lang('Pine Bay'),
        'summary'     => lang('The mainland terminal in the town centre, with a waiting hall, a café and the booking office.'),
        'address'     => lang('Ferry Terminal, Quay Road 1, Pine Bay'),
        'coordinates' => '40.6512, 29.2741',
        'parking'     => lang('A long-stay car park beside the terminal, paid by the day.'),
        'transport'   => lang('The town buses stop at the terminal gate, and the railway station is a ten-minute walk.'),
        'points'      => array(lang('Booking office and waiting hall'), lang('Café and newsagent'), lang('Long-stay car park'), lang('Step-free access to the ramp'))),
    'north' => array('icon' => 'bi-signpost-split', 'title' => lang('North Harbour'),
        'summary'     => lang('The mainland port on the coast road, the quickest way to Cedar Island for drivers from the north.'),
        'address'     => lang('North Harbour Quay, Coast Road'),
        'coordinates' => '40.7120, 29.3305',
        'parking'     => lang('Free short-stay parking for those dropping off and collecting.'),
        'transport'   => lang('The coast road bus stops at the harbour every hour, timed to meet the ferry.'),
        'points'      => array(lang('Ticket kiosk on the quay'), lang('Covered waiting area'), lang('Free short-stay parking'), lang('Bus timed to meet the ferry'))),
    'gull' => array('icon' => 'bi-tree', 'title' => lang('Gull Island'),
        'summary'     => lang('The island\'s harbour village, with the beaches, the lighthouse and the walking paths a short way from the ramp.'),
        'address'     => lang('Harbour Village, Gull Island'),
        'coordinates' => '40.5873, 29.1864',
        'parking'     => lang('A small car park at the harbour; the village itself is best on foot.'),
        'transport'   => lang('The island bus meets every ferry and runs to the beaches and the lighthouse.'),
        'points'      => array(lang('Ticket kiosk at the ramp'), lang('Bicycle hire by the harbour'), lang('Island bus meets every ferry'), lang('Cafés on the quay'))),
    'cedar' => array('icon' => 'bi-house-heart', 'title' => lang('Cedar Island'),
        'summary'     => lang('The larger island\'s main port, with a terminal building, the island\'s shops and the road to every village.'),
        'address'     => lang('Ferry Quay, Cedar Town, Cedar Island'),
        'coordinates' => '40.6235, 29.4018',
        'parking'     => lang('A car park for islanders and visitors by the terminal building.'),
        'transport'   => lang('Taxis wait at the terminal and the island buses leave from the square.'),
        'points'      => array(lang('Terminal building with a booking desk'), lang('Taxis at every arrival'), lang('Island buses from the square'), lang('Shops a short walk away'))),
);

// The seasons. Timetables change with them; the dates are words, since the
// line sets the exact dates each year and writes them on the timetables page.
$seasons = array(
    'low'  => array('title' => lang('Winter Timetable'), 'when' => lang('Autumn, winter and early spring')),
    'high' => array('title' => lang('Summer Timetable'), 'when' => lang('Late spring and summer')),
);

// The routes. Each has its own page (route_<key>); the booking request opens
// with the route chosen (?route=) in the route's own words. 'band' picks the
// fares of a short or a long crossing; 'sailings' are the departure times
// from each port per season, as the timetable writes them.
$routes = array(
    'bay_gull' => array('from' => 'pinebay', 'to' => 'gull', 'band' => 'short', 'icon' => 'bi-arrow-left-right',
        'title'    => lang('Pine Bay – Gull Island'),
        'duration' => lang('50 minutes'),
        'distance' => lang('9 nautical miles'),
        'ship'     => lang('Seabreeze'),
        'summary'  => lang('The short hop to Gull Island, for a day on the beaches or the walk to the lighthouse.'),
        'text'     => lang('Our busiest route leaves from the town centre and crosses the sheltered side of the bay. In summer the ferry sails every ninety minutes, so a day on the island needs no planning; in winter it still runs from the first school crossing to the evening.'),
        'points'   => array(lang('Cars, vans and bicycles on board'), lang('Café and an open deck'), lang('Sailings timed for the school day'), lang('Island bus meets every ferry')),
        'sailings' => array(
            'low'  => array('out' => array('07:00', '09:30', '12:30', '15:30', '18:00'), 'back' => array('08:00', '10:30', '13:30', '16:30', '19:00')),
            'high' => array('out' => array('07:00', '08:30', '10:00', '11:30', '13:00', '14:30', '16:00', '17:30', '19:00', '20:30'), 'back' => array('07:50', '09:20', '10:50', '12:20', '13:50', '15:20', '16:50', '18:20', '19:50', '21:20')))),
    'bay_cedar' => array('from' => 'pinebay', 'to' => 'cedar', 'band' => 'long', 'icon' => 'bi-arrow-left-right',
        'title'    => lang('Pine Bay – Cedar Island'),
        'duration' => lang('2 hours 10 minutes'),
        'distance' => lang('24 nautical miles'),
        'ship'     => lang('Island Star'),
        'summary'  => lang('The long crossing to Cedar Island on our largest ship, with a restaurant, lounges and cabins.'),
        'text'     => lang('The crossing to Cedar Island rounds the headland and runs along the open coast. Island Star carries the island\'s lorries as well as cars and campervans, and her restaurant, reclining-seat lounge and cabins make the two hours pass quickly.'),
        'points'   => array(lang('Restaurant and café'), lang('Reclining-seat lounge'), lang('Cabins for a rest'), lang('Room for lorries and coaches')),
        'sailings' => array(
            'low'  => array('out' => array('08:00', '14:00'), 'back' => array('11:00', '17:30')),
            'high' => array('out' => array('07:30', '11:00', '15:00', '19:00'), 'back' => array('10:00', '13:30', '17:30', '21:30')))),
    'north_cedar' => array('from' => 'north', 'to' => 'cedar', 'band' => 'short', 'icon' => 'bi-arrow-left-right',
        'title'    => lang('North Harbour – Cedar Island'),
        'duration' => lang('40 minutes'),
        'distance' => lang('7 nautical miles'),
        'ship'     => lang('Kestrel'),
        'summary'  => lang('The quick crossing from the north coast, the islanders\' everyday route to the mainland.'),
        'text'     => lang('From North Harbour the island is only seven miles away. Kestrel is a small, quick ferry for cars and foot passengers, sailing through the day so that islanders can work on the mainland and be home for dinner.'),
        'points'   => array(lang('Sailings through the day'), lang('Cars and foot passengers'), lang('Coffee bar on board'), lang('Free short-stay parking at the port')),
        'sailings' => array(
            'low'  => array('out' => array('06:30', '08:30', '11:00', '14:00', '17:00', '19:30'), 'back' => array('07:20', '09:30', '12:00', '15:00', '18:00', '20:20')),
            'high' => array('out' => array('06:30', '08:00', '09:30', '11:00', '12:30', '14:00', '15:30', '17:00', '18:30', '20:00'), 'back' => array('07:15', '08:45', '10:15', '11:45', '13:15', '14:45', '16:15', '17:45', '19:15', '20:45')))),
    'gull_cedar' => array('from' => 'gull', 'to' => 'cedar', 'band' => 'short', 'icon' => 'bi-shuffle',
        'title'    => lang('Gull Island – Cedar Island'),
        'duration' => lang('1 hour'),
        'distance' => lang('12 nautical miles'),
        'ship'     => lang('Seabreeze'),
        'summary'  => lang('The island hopper between the two islands, for a holiday that takes in both.'),
        'text'     => lang('The island hopper links Gull Island and Cedar Island without a return to the mainland. It sails twice a day in summer and on weekends in winter, a favourite of walkers and cyclists touring both islands.'),
        'points'   => array(lang('Ideal for island-hopping holidays'), lang('Bicycles carried free'), lang('Café on board'), lang('Twice a day in summer')),
        'sailings' => array(
            'low'  => array('out' => array('10:00'), 'back' => array('15:00')),
            'high' => array('out' => array('09:30', '16:00'), 'back' => array('12:00', '18:30')))),
);

// A route's two ports, as words.
$route_ports = function ($key) use ($routes, $ports) {
    return sprintf(lang('From %1$s to %2$s'), $ports[$routes[$key]['from']]['title'], $ports[$routes[$key]['to']]['title']);
};
// A route's departure times from one end in a season, as one line.
$sailing_times = function ($key, $season, $way) use ($routes) {
    return implode(' · ', $routes[$key]['sailings'][$season][$way]);
};
// A route's sailings a day in a season, as words.
$sailings_a_day = function ($key, $season) use ($routes) {
    return sprintf(lang('%d sailings a day each way'), count($routes[$key]['sailings'][$season]['out']));
};

// The kinds of fare. A foot fare is per person, a vehicle fare is for the
// vehicle with its driver, an extra is added to a fare. The fares page and
// the booking request (?fare=) use the titles.
$fare_kinds = array(
    'adult'      => array('group' => 'foot', 'icon' => 'bi-person', 'title' => lang('Adult'), 'note' => lang('Ages 12 and over')),
    'child'      => array('group' => 'foot', 'icon' => 'bi-person-arms-up', 'title' => lang('Child'), 'note' => lang('Ages 4 to 11; under 4 travel free')),
    'senior'     => array('group' => 'foot', 'icon' => 'bi-person-heart', 'title' => lang('Senior'), 'note' => lang('Ages 65 and over')),
    'bicycle'    => array('group' => 'extra', 'icon' => 'bi-bicycle', 'title' => lang('Bicycle'), 'note' => lang('Added to a foot passenger\'s fare')),
    'motorcycle' => array('group' => 'vehicle', 'icon' => 'bi-scooter', 'title' => lang('Motorcycle'), 'note' => lang('With its rider')),
    'car'        => array('group' => 'vehicle', 'icon' => 'bi-car-front', 'title' => lang('Car'), 'note' => lang('Up to 5 metres long and 2 metres high, with its driver')),
    'van'        => array('group' => 'vehicle', 'icon' => 'bi-truck', 'title' => lang('Van or Campervan'), 'note' => lang('Up to 7 metres long and 3 metres high, with its driver')),
    'cabin'      => array('group' => 'extra', 'icon' => 'bi-door-closed', 'title' => lang('Cabin'), 'note' => lang('For up to four people, on the long crossing only')),
);

// A single crossing's fare per kind and band, per currency. The fares page
// and the route pages show them as words; the booking request asks for a
// place, it never charges. The cabin is offered on the long crossing only.
$fares = array(
    'adult'      => array('short' => array('USD' => 6, 'EUR' => 6, 'GBP' => 5, 'TRY' => 220), 'long' => array('USD' => 14, 'EUR' => 13, 'GBP' => 11, 'TRY' => 520)),
    'child'      => array('short' => array('USD' => 3, 'EUR' => 3, 'GBP' => 2, 'TRY' => 110), 'long' => array('USD' => 7, 'EUR' => 6, 'GBP' => 5, 'TRY' => 260)),
    'senior'     => array('short' => array('USD' => 4, 'EUR' => 4, 'GBP' => 3, 'TRY' => 150), 'long' => array('USD' => 10, 'EUR' => 9, 'GBP' => 8, 'TRY' => 370)),
    'bicycle'    => array('short' => array('USD' => 2, 'EUR' => 2, 'GBP' => 2, 'TRY' => 75), 'long' => array('USD' => 4, 'EUR' => 4, 'GBP' => 3, 'TRY' => 150)),
    'motorcycle' => array('short' => array('USD' => 12, 'EUR' => 11, 'GBP' => 10, 'TRY' => 440), 'long' => array('USD' => 26, 'EUR' => 24, 'GBP' => 21, 'TRY' => 960)),
    'car'        => array('short' => array('USD' => 28, 'EUR' => 26, 'GBP' => 22, 'TRY' => 1030), 'long' => array('USD' => 62, 'EUR' => 57, 'GBP' => 49, 'TRY' => 2290)),
    'van'        => array('short' => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1660), 'long' => array('USD' => 98, 'EUR' => 91, 'GBP' => 78, 'TRY' => 3620)),
    'cabin'      => array('long' => array('USD' => 30, 'EUR' => 28, 'GBP' => 24, 'TRY' => 1110)),
);
// What every fare includes (the fares page).
$fare_includes = array(
    lang('A seat in the lounges and on the open decks'),
    lang('Luggage you can carry, and a pushchair'),
    lang('Wi-Fi on board'),
    lang('Changes to another sailing on the same day, where there is room'),
);
// The terms the booking office confirms with every booking.
$fare_note = lang('Fares are for a single crossing and are a guide for planning; a return costs twice the single fare. The booking office confirms the fare when it confirms your booking.');

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A kind's fare on a route, as words; '' where the route does not offer it.
$fare = function ($kind, $route) use ($fares, $routes, $money) {
    $band = $routes[$route]['band'];
    return isset($fares[$kind][$band]) ? $money($fares[$kind][$band]) : '';
};
// A route's adult foot fare, as words.
$from_fare = function ($route) use ($fare) {
    return sprintf(lang('Foot passengers from %s'), $fare('adult', $route));
};

// What is on board (the onboard page and the home). 'where' says which
// ships or crossings have it.
$onboard = array(
    array('icon' => 'bi-cup-hot', 'title' => lang('Café'),
        'text' => lang('Coffee, tea, fresh pastries and sandwiches, and ice cream on a summer crossing.'), 'where' => lang('On every ship')),
    array('icon' => 'bi-egg-fried', 'title' => lang('Restaurant'),
        'text' => lang('Hot meals with a view of the coast: breakfast on the morning sailings, lunch and dinner after.'), 'where' => lang('On the Cedar Island crossing')),
    array('icon' => 'bi-easel', 'title' => lang('Reclining-seat lounge'),
        'text' => lang('A quiet lounge with reclining seats and sockets beside every row.'), 'where' => lang('On the Cedar Island crossing')),
    array('icon' => 'bi-door-closed', 'title' => lang('Cabins'),
        'text' => lang('Cabins for up to four people with a washbasin, for a rest or a nap with the children.'), 'where' => lang('On the Cedar Island crossing')),
    array('icon' => 'bi-sun', 'title' => lang('Open decks'),
        'text' => lang('Benches on the top deck for the sea air, the islands coming closer and the dolphins in the bay.'), 'where' => lang('On every ship')),
    array('icon' => 'bi-balloon', 'title' => lang('Children\'s corner'),
        'text' => lang('Soft play and drawing for the youngest passengers, close to the café.'), 'where' => lang('On Island Star and Seabreeze')),
    array('icon' => 'bi-heart', 'title' => lang('Pets on board'),
        'text' => lang('Dogs on a lead are welcome on the open decks and in the pet area; they stay out of the restaurant and cabins.'), 'where' => lang('On every ship')),
    array('icon' => 'bi-universal-access', 'title' => lang('Accessible travel'),
        'text' => lang('Lifts from the car deck, accessible toilets and crew ready to help you on and off; tell us when you book.'), 'where' => lang('On every ship')),
);

// Travelling with us (the travel page): what to know before the sailing.
$travel_tips = array(
    array('icon' => 'bi-clock', 'title' => lang('Check-in'),
        'text' => lang('With a vehicle, check in at least 45 minutes before departure; on foot, 20 minutes. Late arrivals may lose their place.')),
    array('icon' => 'bi-person-vcard', 'title' => lang('Tickets and ID'),
        'text' => lang('Bring your booking reference and an ID card or passport for every adult; island residents show their resident card.')),
    array('icon' => 'bi-suitcase', 'title' => lang('Luggage'),
        'text' => lang('Foot passengers take their luggage on board; leave large cases in the luggage racks by the stairs.')),
    array('icon' => 'bi-car-front', 'title' => lang('Vehicles'),
        'text' => lang('Tell us the length and height of your vehicle and anything it tows; vehicles over 3 metres high take the lorry deck.')),
    array('icon' => 'bi-heart', 'title' => lang('Pets'),
        'text' => lang('Pets travel free on a lead or in a carrier; on the long crossing they may also stay in the car.')),
    array('icon' => 'bi-universal-access', 'title' => lang('Help on board'),
        'text' => lang('Tell us when you book if you need a wheelchair, help with steps or a parking place near the lift.')),
);

// Why travellers choose the line (the home and the about page).
$why_us = array(
    array('icon' => 'bi-calendar-check', 'title' => lang('Every day of the year'),
        'text' => lang('Sailings in every season, timed for school, work and the first and last bus.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Safety first'),
        'text' => lang('Experienced crews, ships inspected every year and sailings held back when the weather says so.')),
    array('icon' => 'bi-car-front', 'title' => lang('Room for your car'),
        'text' => lang('Cars, vans, campervans, motorcycles and bicycles on every route.')),
    array('icon' => 'bi-megaphone', 'title' => lang('Told in good time'),
        'text' => lang('Every change and disruption on our travel notices as soon as we know it.')),
);

// The line in four figures (the home and the about page).
$figures = array(
    array('value' => '3', 'label' => lang('Ships in our fleet')),
    array('value' => '4', 'label' => lang('Routes across the bay')),
    array('value' => '40+', 'label' => lang('Sailings on a summer day')),
    array('value' => '600', 'label' => lang('Passengers on our largest ship')),
);

// What travellers say, each about a route.
$testimonials = array(
    array('route' => 'bay_gull', 'name' => lang('Selin Kaya'), 'role' => lang('Travels Pine Bay – Gull Island'),
        'quote' => lang('We take the morning ferry with the children and the bicycles, spend the day on the beaches and are home for dinner. The crew always remembers the children\'s names.')),
    array('route' => 'bay_cedar', 'name' => lang('Daniel Weber'), 'role' => lang('Travels Pine Bay – Cedar Island'),
        'quote' => lang('A cabin on the long crossing made the trip with our campervan easy: a rest, lunch in the restaurant and the island in sight before we knew it.')),
    array('route' => 'north_cedar', 'name' => lang('Ayşe Toprak'), 'role' => lang('Travels North Harbour – Cedar Island'),
        'quote' => lang('I cross to work on the mainland every weekday. The ferry is on time, and when the wind is up I know from the travel notices before I leave home.')),
);

// The sample travel notices: the records the Travel Notices form starts
// with. The notice list, the notice page and the home read them; the home
// shows the featured ones. 'kind' is an index of $notice_kinds, 'route' a
// route key or '' for every route.
$notice_kinds = array(lang('Disruption'), lang('Timetable Change'), lang('Weather Warning'), lang('Port Works'), lang('General Notice'));
$sample_notices = array(
    'wind' => array('kind' => 2, 'route' => 'bay_cedar', 'featured' => true,
        'title'   => lang('Strong winds may delay the afternoon sailings'),
        'when'    => lang('This afternoon and evening'),
        'summary' => lang('A strong westerly is forecast on the open coast; the afternoon crossings to Cedar Island may leave late or be held in port.'),
        'details' => lang('The captain decides before each sailing whether it is safe to leave. If a sailing is held, your booking moves to the next one with room, and we call or e-mail you. The short routes across the bay are not affected so far.')),
    'ramp' => array('kind' => 3, 'route' => 'bay_gull', 'featured' => true,
        'title'   => lang('Ramp works at Gull Island harbour'),
        'when'    => lang('Weekdays for the next three weeks'),
        'summary' => lang('The vehicle ramp is being renewed; vehicles board from the north quay, foot passengers as usual.'),
        'details' => lang('Follow the signs to the north quay when you drive off the ferry at Gull Island. Allow a few extra minutes at check-in; the sailings keep their times.')),
    'winter' => array('kind' => 1, 'route' => '', 'featured' => true,
        'title'   => lang('The winter timetable starts soon'),
        'when'    => lang('From the end of the summer season'),
        'summary' => lang('Fewer sailings on every route as the season ends; the island hopper sails on weekends only.'),
        'details' => lang('The winter timetable is on the timetables page. Bookings already made for a sailing that no longer runs are moved to the nearest one, and we let you know.')),
    'kestrel' => array('kind' => 0, 'route' => 'north_cedar', 'featured' => false,
        'title'   => lang('Kestrel back in service after her yearly inspection'),
        'when'    => lang('From tomorrow morning'),
        'summary' => lang('The North Harbour crossing returns to its full timetable with Kestrel back on the route.'),
        'details' => lang('While Kestrel was in dock, Seabreeze covered the route with fewer sailings. Thank you for your patience; the first sailing tomorrow leaves North Harbour at its usual time.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the traveller chose.
$route_titles = array();
foreach ($routes as $r) $route_titles[] = $r['title'];
$port_titles = array();
foreach ($ports as $p) $port_titles[] = $p['title'];
$season_titles = array();
foreach ($seasons as $s) $season_titles[] = $s['title'];
$fare_titles = array();
foreach ($fare_kinds as $f) $fare_titles[] = $f['title'];
$notice_route_choices = array_merge(array(lang('All Routes')), $route_titles);
$journey_choices = array(
    array(lang('Single crossing'), lang('Single crossing')),
    array(lang('Return crossing'), lang('Return crossing')),
);
$vehicle_choices = array(lang('No vehicle, on foot'), lang('A car'), lang('A car with a trailer or caravan'), lang('A van or campervan'), lang('A motorcycle'), lang('A bicycle'));
$cabin_choices = array(lang('No cabin'), lang('A two-berth cabin'), lang('A four-berth cabin'));
$group_kind_choices = array(lang('A school group'), lang('A coach tour'), lang('A club or society'), lang('A company outing'), lang('Another group'));
// The states of a booking request, set by the booking office in the panel.
$booking_statuses = array(lang('Request received'), lang('Booking confirmed'), lang('Waiting list'), lang('Sailing full'), lang('Booking cancelled'));
// The states of a group enquiry, set by the group desk in the panel.
$group_statuses = array(lang('Enquiry received'), lang('Offer sent'), lang('Group confirmed'), lang('Sailing full'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the booking band above the footer unless the page is
// a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('booking_band');
    $children[] = $shared('footer');
    return $root($children);
};

$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-body-tertiary', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'pfy-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pfy-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pfy-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the onboard services and the
// travel tips are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'fy-faq') use ($el, $para, $attr, $searchable) {
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

// The line's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pfy-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the way to today's travel notices and to a
// booking. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-megaphone', 'text-primary'),
        $span(lang('Before you travel, check the travel notices for your route'), '', lang('Text')),
        $link(lang('Travel Notices'), '{{page:notices}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The line
// sells no tickets online, so the bar carries no cart.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body-tertiary', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'),
            )),
        )),
    ));
};

// The header: the line's name, the menu and the way to ask for a booking.
// The links to the current page are marked active on the server (smart
// active state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('routes', lang('Routes')),
        array('timetables', lang('Timetables')),
        array('fares', lang('Fares')),
        array('ports', lang('Ports')),
        array('onboard', lang('On Board')),
        array('notices', lang('Travel Notices')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-water', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pfy-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#fy-nav'),
                    $attr('aria-controls', 'fy-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-ticket-perforated'),
                            $span(lang('Request a Booking'), '', lang('Link Text')),
                        ), array('href' => '{{page:booking_request}}')),
                    )),
                ), array('id' => 'fy-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the line in a sentence, how to reach it and
// its profiles, the routes, the travel notices by route, the travellers'
// pages and the line's pages; at the bottom the year, the site's name and
// the sailing days.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $routes, $line, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $route_links = array();
    foreach ($routes as $key => $r) $route_links[] = $link($r['title'], '{{page:route_' . $key . '}}', $plain);
    $notice_links = array($link(lang('All Travel Notices'), '{{page:notices}}', $plain));
    foreach ($routes as $r) $notice_links[] = $link($r['title'], '{{page:notices}}?' . $query(array('route' => $r['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Line'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-water', 'text-primary'),
                        $span('{{site_name}}', 'pfy-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($line['tagline'], 'small text-body-secondary mb-3'),
                    $contact_line('bi-geo-alt', $span($line['address'], '', lang('Address')), lang('Address')),
                    $contact_line('bi-telephone', $link($line['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $line['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($line['email'], 'mailto:' . $line['email'], $plain), lang('Email')),
                    $contact_line('bi-clock', $span($line['hours'], '', lang('Opening Hours')), lang('Opening Hours')),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Route Links'), array(
                    $heading('h2', lang('Routes'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($route_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Notice Links'), array(
                    $heading('h2', lang('Travel Notices'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($notice_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Travellers'), array(
                    $heading('h2', lang('For Travellers'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Request a Booking'), '{{page:booking_request}}', $plain),
                        $link(lang('Group Enquiry'), '{{page:group_enquiry}}', $plain),
                        $link(lang('My Bookings'), '{{page:my_bookings}}', $plain),
                        $link(lang('Timetables'), '{{page:timetables}}', $plain),
                        $link(lang('Fares'), '{{page:fares}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Line'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Ports'), '{{page:ports}}', $plain),
                        $link(lang('On Board'), '{{page:onboard}}', $plain),
                        $link(lang('Travelling with Us'), '{{page:travel}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($line['opening'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The booking band: a full-width band in the palette's colour with the
// ways to ask for a booking and to see the timetables. Shared, so every
// page that ends with it carries the same call.
$booking_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Ready to cross?'), 'pfy-display h2 mb-2'),
                    $para(lang('Tell us your route, your day and who is travelling, and the booking office will confirm your place on board within a day.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Request a Booking'), '{{page:booking_request}}', 'btn btn-light btn-lg rounded-pill px-4'),
                        $link(lang('See the Timetables'), '{{page:timetables}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The facts of a route in one list: its ports, the crossing time, the
// distance and the ship.
$route_facts = function ($key, $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $routes, $route_ports) {
    $r = $routes[$key];
    $fact = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name),
        ));
    };
    return $el('ul', $class, lang('Route Facts'), array(
        $fact('bi-geo-alt', $route_ports($key), lang('Ports')),
        $fact('bi-clock', $r['duration'], lang('Crossing Time')),
        $fact('bi-rulers', $r['distance'], lang('Distance')),
        $fact('bi-water', $r['ship'], lang('Ship on the Route')),
    ));
};

// A route's card (the home and the routes page): the picture, the lowest
// foot fare, the title linking the route's page, the crossing time and the
// summary.
$route_card = function ($key) use ($el, $para, $span, $link, $image, $col, $icon, $photo, $attr, $routes, $from_fare) {
    $r = $routes[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pfy-lift', lang('Route Card'), array(
            $el('span', 'd-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('route-' . $key, 800, 600), $r['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'd-flex align-items-center gap-1 small fw-semibold text-primary mb-1', lang('Crossing Time'), array(
                    $icon('bi-clock'),
                    $span($r['duration'], '', lang('Value')),
                )),
                $el('h3', 'pfy-display h5 position-static mb-2', lang('Route Name'), array(
                    $link($r['title'], '{{page:route_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($r['summary'], 'small text-body-secondary mb-3'),
                $span($from_fare($key), 'pfy-price mt-auto small fw-semibold', lang('Fare')),
            )),
        )),
    ), '6', '3');
};

// A port's card (the ports page and the route pages): the name, the
// summary, the address, parking and the way to get there, and a link that
// opens the port on the map in a new window.
$port_card = function ($key, $md = '6', $lg = '6') use ($el, $heading, $para, $span, $link, $icon, $col, $icon_tile, $check_list, $ports, $attr) {
    $p = $ports[$key];
    list($lat, $lon) = array_map('trim', explode(',', $p['coordinates']));
    $line = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $span($text, '', lang('Text'))));
    };
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 p-4', lang('Port Card'), array(
            $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Port Name'), array(
                $icon_tile($p['icon']),
                $heading('h3', $p['title'], 'pfy-display h4 mb-0', array('id' => 'fy-port-' . $key)),
            )),
            $para($p['summary'], 'text-body-secondary mb-3'),
            $line('bi-geo-alt', $p['address'], lang('Address')),
            $line('bi-p-square', $p['parking'], lang('Parking')),
            $line('bi-bus-front', $p['transport'], lang('Getting There')),
            $check_list($p['points'], 'list-unstyled small my-3'),
            $link(lang('Open in maps'), 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '#map=15/' . $lat . '/' . $lon,
                'btn btn-sm btn-outline-primary rounded-pill align-self-start mt-auto', array($attr('rel', 'noopener')), array('target' => '_blank')),
        )),
    ), $md, $lg);
};

// An onboard service, a travel tip or a reason: an icon tile, the title,
// the words and, for a service, where it is found.
$feature_card = function ($item, $md = '6', $lg = '3') use ($el, $heading, $para, $span, $col, $icon_tile) {
    $children = array(
        $icon_tile($item['icon'], 'mb-3'),
        $heading('h3', $item['title'], 'pfy-display h5 mb-2'),
        $para($item['text'], 'small text-body-secondary mb-0'),
    );
    if (!empty($item['where'])) $children[] = $span($item['where'], 'd-block small fw-semibold text-primary mt-2', lang('Where'));
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', lang('Feature'), $children),
    ), $md, $lg);
};

// What a traveller says: the quote, the person and the route.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $icon, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Traveller Quote'), array(
            $icon('bi-quote', 'fs-2 text-primary lh-1 mb-2'),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pfy-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Traveller Details'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// A route's timetable as a table: a row per season, a column per port of
// departure with its times. The route's name opens the booking request
// with the route chosen (?route=).
$timetable = function ($key) use ($el, $link, $span, $heading, $attr, $routes, $ports, $seasons, $sailing_times, $query) {
    $r = $routes[$key];
    $head = array($el('th', '', lang('Table Header'), array(), array('text' => lang('Timetable'), '_attrs' => array($attr('scope', 'col')))));
    foreach (array('from', 'to') as $end) {
        $head[] = $el('th', '', lang('Table Header'), array(), array('text' => sprintf(lang('Departures from %s'), $ports[$r[$end]]['title']), '_attrs' => array($attr('scope', 'col'))));
    }
    $rows = array();
    foreach ($seasons as $season => $s) {
        $rows[] = $el('tr', '', lang('Table Row'), array(
            $el('th', 'fw-semibold', lang('Table Header'), array(
                $span($s['title'], 'd-block', lang('Season')),
                $span($s['when'], 'd-block small fw-normal text-body-secondary', lang('When')),
            ), array('_attrs' => array($attr('scope', 'row')))),
            $el('td', 'pfy-time', lang('Table Cell'), array(), array('text' => $sailing_times($key, $season, 'out'))),
            $el('td', 'pfy-time', lang('Table Cell'), array(), array('text' => $sailing_times($key, $season, 'back'))),
        ));
    }
    return $el('div', 'mb-4', lang('Timetable'), array(
        $el('div', 'd-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-2', lang('Timetable Title'), array(
            $heading('h3', $r['title'], 'pfy-display h5 mb-0'),
            $link(lang('Book this route'), '{{page:booking_request}}?' . $query(array('route' => $r['title'])), 'small fw-semibold link-primary'),
        )),
        $el('div', 'table-responsive', lang('Timetable Table'), array(
            $el('table', 'table align-middle mb-0', lang('Table'), array(
                $el('thead', '', lang('Table Head'), array($el('tr', '', lang('Table Row'), $head))),
                $el('tbody', '', lang('Table Body'), $rows),
            )),
        )),
    ));
};

// The fares as a table: a row per kind of fare, a column per route (or the
// one route given). Each kind's name opens the booking request with the
// fare chosen (?fare=), each route's name opens its page; a kind a route
// does not offer shows a dash.
$fare_table = function ($only = '') use ($el, $link, $span, $attr, $routes, $fare_kinds, $fare, $query) {
    $head = array($el('th', '', lang('Table Header'), array(), array('text' => lang('Fare'), '_attrs' => array($attr('scope', 'col')))));
    foreach ($routes as $key => $r) {
        if ($only !== '' && $only !== $key) continue;
        $head[] = $el('th', 'text-end', lang('Table Header'), array(
            $link($r['title'], '{{page:route_' . $key . '}}', 'link-body-emphasis'),
        ), array('_attrs' => array($attr('scope', 'col'))));
    }
    $rows = array();
    foreach ($fare_kinds as $kind => $f) {
        $cells = array($el('th', 'fw-semibold', lang('Table Header'), array(
            $link($f['title'], '{{page:booking_request}}?' . $query(array('fare' => $f['title'])), 'link-body-emphasis text-decoration-none'),
            $span($f['note'], 'd-block small fw-normal text-body-secondary', lang('Note')),
        ), array('_attrs' => array($attr('scope', 'row')))));
        foreach (array_keys($routes) as $route) {
            if ($only !== '' && $only !== $route) continue;
            $text = $fare($kind, $route);
            $cells[] = $el('td', 'pfy-price text-end text-nowrap', lang('Table Cell'), array(), array('text' => $text !== '' ? $text : '–'));
        }
        $rows[] = $el('tr', '', lang('Table Row'), $cells);
    }
    return $el('div', 'table-responsive', lang('Fare Table'), array(
        $el('table', 'table align-middle mb-0', lang('Table'), array(
            $el('thead', '', lang('Table Head'), array($el('tr', '', lang('Table Row'), $head))),
            $el('tbody', '', lang('Table Body'), $rows),
        )),
    ));
};

// The words of a notice's kind and route, the values of its Kind and Route
// fields.
$notice_kind = function ($key) use ($sample_notices, $notice_kinds) {
    return $notice_kinds[$sample_notices[$key]['kind']];
};
$notice_route = function ($key) use ($sample_notices, $routes) {
    $route = $sample_notices[$key]['route'];
    return $route === '' ? lang('All Routes') : $routes[$route]['title'];
};

// A travel notice's card of the lists (the home and the notice list): the
// kind, the route, the title linking the notice page, when and the summary.
// Every value is bound to the Travel Notices form's field of the same name;
// the sample fills the canvas.
$notice_card = function ($sample_key = 'wind', $md = '6', $lg = '4') use ($el, $para, $span, $link, $col, $bind, $icon, $sample_notices, $notice_kind, $notice_route) {
    $n = $sample_notices[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pfy-lift', lang('Notice Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Notice Labels'), array(
                    $span($notice_kind($sample_key), 'badge rounded-pill text-bg-warning', lang('Kind'), $bind('kind')),
                    $span($notice_route($sample_key), 'pfy-eyebrow small text-uppercase fw-semibold text-primary', lang('Route'), $bind('route')),
                )),
                $el('h3', 'pfy-display h5 position-static mb-2', lang('Notice Title'), array(
                    $link($n['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-center gap-1 small fw-semibold mb-2', lang('When'), array(
                    $icon('bi-clock', 'text-primary'),
                    $span($n['when'], '', lang('Value'), $bind('when')),
                )),
                $para($n['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), $md, $lg);
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
$submit = function ($text, $class = 'btn btn-primary px-4') use ($el, $attr) {
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
                $item('staff_bookings', lang('Booking Requests')),
                $item('staff_groups', lang('Group Enquiries')),
                $item('notice_new', lang('New Travel Notice')),
                $item('blog_new', lang('New Article')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

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
    return $agree($id, 'consent', lang('I would like to receive the ferry line\'s news, timetable changes and offers by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A booking
// request may bring a vehicle's papers or a medical note, so the default accepts
// PDFs, office files, text and pictures; one that takes only pictures is a
// collection's image kind (a travel notice's photo).
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
                    $link(lang('Five island walks to make the most of a day crossing'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
