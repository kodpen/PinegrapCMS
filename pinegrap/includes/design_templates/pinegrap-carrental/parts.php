<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Rental", part 1 of its files: the node builders,
 * the rental company's own data (the car classes, fuels and gearboxes the
 * fleet is filtered by, the sample cars, the pick-up desks, the extras,
 * the deals, the rental terms, the business plans, the team, what renters
 * say and the answers of the request forms' pick lists) and the parts
 * every page repeats (account bar, header, footer, booking band, car card
 * and facts, class tile, location, extra, deal, business plan and
 * testimonial cards, page shells, section headers, form fields, the
 * comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-carrental.php puts them together.
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
// every opening until the company puts its own photos in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-carrental-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/fef3c7/92400e?text=' . rawurlencode($initials !== '' ? $initials : 'A');
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
// ── The company: its fleet, its desks, its extras and its deals ─────────

// The rental company itself. The about and locations pages tell its
// story; the footer, the bands and the contact lines use the phone and the
// head office's address.
$company = array(
    'tagline' => lang('Clean, checked cars at the airport, in town and by the sea'),
    'bio'     => lang('A local car rental company with desks at the airport, in the city centre and on the coast: clean cars, clear prices and people who answer the phone.'),
    'address' => lang('12 Harbour Street, Izmir'),
    'email'   => 'rentals@example.com',
    'phone'   => '+90 555 000 00 00',
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed, with the base
// currency's symbol. A rate therefore shows one amount wherever it appears.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A price list times $n, currency by currency: what $n days of a rate
// come to, so a sum shows the same figures in every currency.
$times = function (array $prices, $n) {
    $out = array();
    foreach ($prices as $code => $amount) $out[$code] = $amount * $n;
    return $out;
};

// The car classes, in the order the fleet list and the home's tiles show
// them. A booking promises a class, not a model: each class's lowest rate
// a day, per currency, is the "from" price of its tile. 'deposit' is the
// amount held on the card at pick-up.
$classes = array(
    'economy'  => array('icon' => 'bi-car-front', 'title' => lang('Economy'),
        'text' => lang('Small and thrifty, for the city and short trips.'),
        'rate' => array('USD' => 29, 'EUR' => 27, 'GBP' => 23, 'TRY' => 1100), 'deposit' => array('USD' => 250, 'EUR' => 230, 'GBP' => 200, 'TRY' => 9500)),
    'compact'  => array('icon' => 'bi-car-front-fill', 'title' => lang('Mid-size'),
        'text' => lang('Room for four and their bags, easy to park.'),
        'rate' => array('USD' => 36, 'EUR' => 33, 'GBP' => 29, 'TRY' => 1350), 'deposit' => array('USD' => 300, 'EUR' => 280, 'GBP' => 240, 'TRY' => 11500)),
    'suv'      => array('icon' => 'bi-truck-front', 'title' => lang('SUV'),
        'text' => lang('High seats and a big boot for families and mountain roads.'),
        'rate' => array('USD' => 55, 'EUR' => 51, 'GBP' => 44, 'TRY' => 2100), 'deposit' => array('USD' => 450, 'EUR' => 420, 'GBP' => 360, 'TRY' => 17000)),
    'premium'  => array('icon' => 'bi-gem', 'title' => lang('Premium'),
        'text' => lang('Leather, quiet and every comfort, for business or a special trip.'),
        'rate' => array('USD' => 89, 'EUR' => 82, 'GBP' => 71, 'TRY' => 3400), 'deposit' => array('USD' => 800, 'EUR' => 740, 'GBP' => 640, 'TRY' => 30000)),
    'van'      => array('icon' => 'bi-bus-front', 'title' => lang('People Carrier'),
        'text' => lang('Seven to nine seats for groups, teams and big families.'),
        'rate' => array('USD' => 69, 'EUR' => 64, 'GBP' => 55, 'TRY' => 2600), 'deposit' => array('USD' => 500, 'EUR' => 460, 'GBP' => 400, 'TRY' => 19000)),
    'electric' => array('icon' => 'bi-ev-front', 'title' => lang('Electric'),
        'text' => lang('Quiet, clean and charged when you collect it.'),
        'rate' => array('USD' => 49, 'EUR' => 45, 'GBP' => 39, 'TRY' => 1850), 'deposit' => array('USD' => 400, 'EUR' => 370, 'GBP' => 320, 'TRY' => 15000)),
);

$fuels = array(
    'petrol'   => array('icon' => 'bi-fuel-pump', 'title' => lang('Gasoline')),
    'diesel'   => array('icon' => 'bi-fuel-pump-diesel', 'title' => lang('Diesel')),
    'hybrid'   => array('icon' => 'bi-ev-front', 'title' => lang('Hybrid')),
    'electric' => array('icon' => 'bi-ev-station', 'title' => lang('Electric')),
);
$transmissions = array('manual' => lang('Manual gearbox'), 'automatic' => lang('Automatic gearbox'));

// The sample cars: the records the Fleet form starts with. Each is the
// model a class is usually given with ("or similar"); the lists, the car
// page and the booking request read them, the home shows the featured
// ones. 'rate' is the car's lowest rate a day per currency. Makes and
// model names are invented brand names and stay as they are in every
// language.
$cars = array(
    'lumo-one'     => array('class' => 'economy', 'make' => 'Lumo', 'model' => 'One', 'seats' => 4, 'doors' => 3, 'bags' => 1,
        'gearbox' => 'manual', 'fuel' => 'petrol', 'min_age' => 21, 'featured' => false,
        'rate' => array('USD' => 29, 'EUR' => 27, 'GBP' => 23, 'TRY' => 1100),
        'summary'     => lang('A light three-door for the city, cheap to run and easy to park.'),
        'description' => lang('The One is the smallest car in the fleet: four seats, a boot for two cabin bags and a fuel tank that lasts all week in town. Ideal for one or two people who mostly drive in the city.'),
        'features'    => array(lang('Air conditioning'), lang('Bluetooth audio'), lang('Unlimited mileage'), lang('Roadside assistance'))),
    'lumo-five'    => array('class' => 'compact', 'make' => 'Lumo', 'model' => 'Five', 'seats' => 5, 'doors' => 5, 'bags' => 2,
        'gearbox' => 'automatic', 'fuel' => 'petrol', 'min_age' => 21, 'featured' => true,
        'rate' => array('USD' => 36, 'EUR' => 33, 'GBP' => 29, 'TRY' => 1350),
        'summary'     => lang('A five-door automatic with room for four adults and two suitcases.'),
        'description' => lang('Our most booked car: an automatic five-door that is easy in town and calm on the motorway. Two suitcases fit in the boot, and the touch screen mirrors your phone for maps and music.'),
        'features'    => array(lang('Automatic gearbox'), lang('Phone mirroring on the touch screen'), lang('Parking sensors'), lang('Unlimited mileage'))),
    'ferra-trek'   => array('class' => 'suv', 'make' => 'Ferra', 'model' => 'Trek', 'seats' => 5, 'doors' => 5, 'bags' => 4,
        'gearbox' => 'automatic', 'fuel' => 'diesel', 'min_age' => 23, 'featured' => true,
        'rate' => array('USD' => 55, 'EUR' => 51, 'GBP' => 44, 'TRY' => 2100),
        'summary'     => lang('A family SUV with four suitcases in the boot and a high driving seat.'),
        'description' => lang('The Trek takes a family of five and a week of luggage to the coast or into the mountains. It sits high, has a large boot and keeps the children busy with charging points in the back.'),
        'features'    => array(lang('Large boot for four suitcases'), lang('Reversing camera'), lang('Cruise control'), lang('USB charging in the back'))),
    'odin-grand'   => array('class' => 'premium', 'make' => 'Odin', 'model' => 'Grand', 'seats' => 5, 'doors' => 4, 'bags' => 3,
        'gearbox' => 'automatic', 'fuel' => 'hybrid', 'min_age' => 25, 'featured' => false,
        'rate' => array('USD' => 89, 'EUR' => 82, 'GBP' => 71, 'TRY' => 3400),
        'summary'     => lang('A quiet hybrid saloon with leather seats, for business trips and special days.'),
        'description' => lang('The Grand is the car to meet a client in or to drive to a wedding: leather seats, a quiet hybrid engine and every assistant on the road. It is cleaned inside and out before each rental.'),
        'features'    => array(lang('Leather seats'), lang('Adaptive cruise control'), lang('Heated seats'), lang('Hybrid economy'))),
    'ferra-nine'   => array('class' => 'van', 'make' => 'Ferra', 'model' => 'Nine', 'seats' => 9, 'doors' => 4, 'bags' => 6,
        'gearbox' => 'manual', 'fuel' => 'diesel', 'min_age' => 25, 'featured' => false,
        'rate' => array('USD' => 69, 'EUR' => 64, 'GBP' => 55, 'TRY' => 2600),
        'summary'     => lang('Nine seats and room for six bags, for groups and teams.'),
        'description' => lang('The Nine carries a team, a choir or two families with their bags. It drives like a large car, with a sliding door on each side and air conditioning for every row.'),
        'features'    => array(lang('Nine seats'), lang('Air conditioning for every row'), lang('Sliding doors'), lang('Unlimited mileage'))),
    'vela-spark'   => array('class' => 'electric', 'make' => 'Vela', 'model' => 'Spark', 'seats' => 5, 'doors' => 5, 'bags' => 2,
        'gearbox' => 'automatic', 'fuel' => 'electric', 'min_age' => 21, 'featured' => true,
        'rate' => array('USD' => 49, 'EUR' => 45, 'GBP' => 39, 'TRY' => 1850),
        'summary'     => lang('An electric hatchback with a range of 420 km, charged when you collect it.'),
        'description' => lang('The Spark is charged to 100% when you collect it and goes 420 km on a charge. A charging card for the public network is in the glovebox; return it with at least half a charge.'),
        'features'    => array(lang('Range of 420 km'), lang('Charging card included'), lang('Automatic gearbox'), lang('Heat pump climate control'))),
);
// A car's title as the lists and the booking request name it.
$car_title = function ($key) use ($cars) {
    return $cars[$key]['make'] . ' ' . $cars[$key]['model'];
};
// A car's "From … a day" words, the value of its Rate field.
$car_rate = function ($key) use ($cars, $money) {
    return sprintf(lang('From %s a day'), $money($cars[$key]['rate']));
};

// The desks a car is collected from and returned to, in the order the
// locations page lists them. 'hours' are the desk's opening hours; a car
// may be returned to any desk.
$locations = array(
    'airport' => array('icon' => 'bi-airplane', 'title' => lang('Airport Desk'), 'kind' => lang('Airport'),
        'address' => lang('Arrivals hall, Izmir Airport'),
        'text'    => lang('Our desk is in the arrivals hall: the car waits in the short-stay car park, a two-minute walk away.'),
        'hours'   => array(array(lang('Every day'), lang('Open around the clock'))),
        'points'  => array(lang('We follow your flight if it is late'), lang('Meet and greet at the exit'), lang('Key box for late returns'))),
    'city'    => array('icon' => 'bi-buildings', 'title' => lang('City Centre Office'), 'kind' => lang('City'),
        'address' => lang('12 Harbour Street, Izmir'),
        'text'    => lang('Our head office by the ferry pier, with parking for your own car while you travel.'),
        'hours'   => array(array(lang('Monday–Saturday'), '08:00–20:00'), array(lang('Sunday'), '09:00–17:00')),
        'points'  => array(lang('Your own car parked free while you travel'), lang('Delivery to your hotel in the city'), lang('Business and long-term rentals'))),
    'coast'   => array('icon' => 'bi-umbrella', 'title' => lang('Coast Office'), 'kind' => lang('Resort'),
        'address' => lang('4 Marina Road, Çeşme'),
        'text'    => lang('A summer desk at the marina, for a car by the day or for the rest of your holiday.'),
        'hours'   => array(array(lang('Every day'), '08:30–21:00')),
        'points'  => array(lang('Delivery to your holiday home'), lang('Rentals by the day'), lang('Return at the airport at no charge'))),
);

// The extras a booking may add. 'price' per currency and 'unit' (a day or
// once a rental); the booking request lists them as boxes to tick.
$extras = array(
    'cover'   => array('icon' => 'bi-shield-check', 'title' => lang('Full Cover'), 'unit' => 'day',
        'price' => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 550),
        'text'  => lang('No excess to pay for damage, tyres or glass, and a smaller deposit at pick-up.')),
    'driver'  => array('icon' => 'bi-person-plus', 'title' => lang('Additional Driver'), 'unit' => 'day',
        'price' => array('USD' => 6, 'EUR' => 6, 'GBP' => 5, 'TRY' => 220),
        'text'  => lang('A second driver on the contract; bring their licence to the desk.')),
    'child'   => array('icon' => 'bi-person-arms-up', 'title' => lang('Child Seat'), 'unit' => 'day',
        'price' => array('USD' => 7, 'EUR' => 6, 'GBP' => 5, 'TRY' => 250),
        'text'  => lang('A seat for babies and young children, fitted before you arrive.')),
    'gps'     => array('icon' => 'bi-geo', 'title' => lang('Satellite Navigation'), 'unit' => 'day',
        'price' => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 180),
        'text'  => lang('Maps that work without a phone signal, in the mountains too.')),
    'wifi'    => array('icon' => 'bi-wifi', 'title' => lang('Mobile Wi-Fi'), 'unit' => 'day',
        'price' => array('USD' => 6, 'EUR' => 6, 'GBP' => 5, 'TRY' => 220),
        'text'  => lang('A pocket hotspot with unlimited data for every phone in the car.')),
    'delivery' => array('icon' => 'bi-house-door', 'title' => lang('Delivery and Collection'), 'unit' => 'rental',
        'price' => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 950),
        'text'  => lang('We bring the car to your hotel or home and collect it at the end.')),
);
// The words of an extra's price.
$extra_price = function ($key) use ($extras, $money) {
    $e = $extras[$key];
    return sprintf($e['unit'] === 'day' ? lang('%s a day') : lang('%s a rental'), $money($e['price']));
};

// The cover levels the extras page compares: the price a day against the
// excess, the most a renter pays if the car comes back damaged. Basic
// comes with every rental; Full is the Full Cover extra; Plus is asked for
// in the booking request's notes. 'meter' is the excess drawn against
// Basic's (a Bootstrap width class, '' for none); 'covers' says which of
// the cover lines each level takes. Full Cover halves the class's deposit
// ('deposit' is the share held), as the questions page promises a smaller
// one.
$covers = array(
    'basic' => array('title' => lang('Basic Cover'), 'text' => lang('The cover every rental comes with.'),
        'price' => null, 'excess' => array('USD' => 900, 'EUR' => 830, 'GBP' => 720, 'TRY' => 34000),
        'meter' => 'w-100', 'covers' => array('glass' => false, 'deposit' => false), 'deposit' => 1, 'chosen' => false),
    'plus'  => array('title' => lang('Plus Cover'), 'text' => lang('A lower excess, with glass and tyres covered.'),
        'price' => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 340), 'excess' => array('USD' => 450, 'EUR' => 415, 'GBP' => 360, 'TRY' => 17000),
        'meter' => 'w-50', 'covers' => array('glass' => true, 'deposit' => false), 'deposit' => 1, 'chosen' => false),
    'full'  => array('title' => $extras['cover']['title'], 'text' => $extras['cover']['text'],
        'price' => $extras['cover']['price'], 'excess' => array('USD' => 0, 'EUR' => 0, 'GBP' => 0, 'TRY' => 0),
        'meter' => '', 'covers' => array('glass' => true, 'deposit' => true), 'deposit' => 0.5, 'chosen' => true),
);

// The deals. Each opens the booking request with the deal chosen
// (?deal=); the staff confirm the price by hand. No fixed date: a deal
// names what it gives, never a season's dates.
$deals = array(
    'week'    => array('icon' => 'bi-calendar-week', 'title' => lang('Seven Days for Six'), 'badge' => lang('One day free'),
        'text'   => lang('Rent any car for seven days or more and the seventh day is on us.'),
        'points' => array(lang('Any car class'), lang('Seven days or more'), lang('Free cancellation up to 48 hours before'))),
    'early'   => array('icon' => 'bi-alarm', 'title' => lang('Book Early'), 'badge' => lang('15% off'),
        'text'   => lang('Book at least thirty days before pick-up and pay 15% less on the rate.'),
        'points' => array(lang('Thirty days ahead or more'), lang('Any car class'), lang('Free cancellation up to 48 hours before'))),
    'weekend' => array('icon' => 'bi-sun', 'title' => lang('Weekend Escape'), 'badge' => lang('Free extra driver'),
        'text'   => lang('Collect on Friday, return on Monday morning, and share the driving at no charge.'),
        'points' => array(lang('Friday to Monday'), lang('Additional driver included'), lang('Unlimited mileage'))),
);

// The rental terms, in the order the terms page and the FAQ give them.
$terms = array(
    'age'     => array('icon' => 'bi-person-vcard', 'title' => lang('Driver age and licence'),
        'text' => lang('Drivers must be 21 or over and have held a full licence for at least two years; some classes ask for 23 or 25.')),
    'deposit' => array('icon' => 'bi-credit-card', 'title' => lang('Security deposit'),
        'text' => lang('A deposit is held on a credit card in the main driver\'s name at pick-up and released when the car comes back.')),
    'fuel'    => array('icon' => 'bi-fuel-pump', 'title' => lang('Fuel and charge'),
        'text' => lang('Full to full: you collect the car full and return it full. Electric cars come back with at least half a charge.')),
    'mileage' => array('icon' => 'bi-speedometer2', 'title' => lang('Mileage'),
        'text' => lang('Every rental comes with unlimited mileage inside the country.')),
    'cancel'  => array('icon' => 'bi-x-circle', 'title' => lang('Cancellation policy'),
        'text' => lang('Cancel free of charge up to 48 hours before pick-up; later, one day\'s rate is charged.')),
    'border'  => array('icon' => 'bi-signpost-split', 'title' => lang('Ferries and borders'),
        'text' => lang('Ask us before you take the car on a ferry or across a border: we prepare the papers and the cover.')),
);

// Business and long-term rentals, as the business page offers them; the
// enquiry's Rental Plan list starts from ?plan=.
$business_plans = array(
    'monthly'   => array('title' => lang('Monthly rental'), 'featured' => false,
        'text'   => lang('A car for one to eleven months, with servicing and tyres included.'),
        'points' => array(lang('One to eleven months'), lang('Servicing and tyres included'), lang('Change the car at any time'))),
    'lease'     => array('title' => lang('Long-term lease'), 'featured' => true,
        'text'   => lang('A new car for one to three years at a fixed monthly price, with insurance and servicing.'),
        'points' => array(lang('One to three years'), lang('Insurance and servicing included'), lang('A replacement car when needed'))),
    'corporate' => array('title' => lang('Company account'), 'featured' => false,
        'text'   => lang('Short rentals for your team on one monthly invoice, at an agreed rate.'),
        'points' => array(lang('One invoice a month'), lang('An agreed rate for every class'), lang('Priority at the airport desk'))),
);

// The people a renter meets: the desks, the fleet and business rentals.
$team = array(
    array('name' => lang('Deniz Kaya'), 'role' => lang('Operations manager'),
        'bio' => lang('Runs the three desks and answers the phone at night when a flight is late.')),
    array('name' => lang('Ayşe Polat'), 'role' => lang('Fleet manager'),
        'bio' => lang('Checks every car between rentals and decides when one leaves the fleet.')),
    array('name' => lang('Emre Koç'), 'role' => lang('Business rentals'),
        'bio' => lang('Looks after company accounts and long-term rentals, from one car to forty.')),
);

// The company in four figures (the home and about pages).
$figures = array(
    array('value' => '180', 'label' => lang('Cars in the fleet')),
    array('value' => '3', 'label' => lang('Desks for pick-up and return')),
    array('value' => '24/7', 'label' => lang('Open at the airport')),
    array('value' => '4.8', 'label' => lang('Average customer rating')),
);

// What renters say, each about one of the sample cars.
$testimonials = array(
    array('car' => 'ferra-trek', 'name' => lang('Selin Aydın'), 'role' => lang('Rented a Ferra Trek for a family holiday'),
        'quote' => lang('Our flight landed two hours late and the desk was still waiting for us. The car was spotless and the child seats were already fitted.')),
    array('car' => 'vela-spark', 'name' => lang('Burak Şahin'), 'role' => lang('Drove a Vela Spark along the coast'),
        'quote' => lang('My first electric car. They showed me how to charge it, and the charging card in the glovebox worked everywhere.')),
    array('car' => 'lumo-five', 'name' => lang('Zeynep Demir'), 'role' => lang('Rents a Lumo Five every summer'),
        'quote' => lang('The price on the confirmation was the price I paid. No surprises at the desk, which is rare.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the renter chose.
$class_titles = array();
foreach ($classes as $c) $class_titles[] = $c['title'];
$fuel_titles = array();
foreach ($fuels as $f) $fuel_titles[] = $f['title'];
$transmission_titles = array_values($transmissions);
$car_titles = array();
foreach (array_keys($cars) as $key) $car_titles[] = $car_title($key);
$location_titles = array();
foreach ($locations as $l) $location_titles[] = $l['title'];
$extra_titles = array();
foreach ($extras as $e) $extra_titles[] = $e['title'];
$deal_choices = array(lang('No deal'));
foreach ($deals as $d) $deal_choices[] = $d['title'];
$plan_choices = array(lang('Not sure yet'));
foreach ($business_plans as $p) $plan_choices[] = $p['title'];
// The times of pick-up and return, on the hour; the airport desk takes
// any of them, the offices only inside their hours.
$time_choices = array();
for ($h = 7; $h <= 23; $h++) $time_choices[] = sprintf('%02d:00', $h);
// The main driver's age: the classes ask for a minimum age.
$age_choices = array(lang('21 to 24'), lang('25 to 69'), lang('70 or over'));
$contact_choices = array(
    array(lang('Phone'), lang('Phone')),
    array(lang('E-mail'), lang('E-mail')),
    array(lang('WhatsApp'), lang('WhatsApp')),
);
// A business enquiry asks for bands, never a tax number or an ID.
$fleet_size_choices = array(lang('One car'), lang('2 to 5 cars'), lang('6 to 20 cars'), lang('More than 20 cars'));
$rental_term_choices = array(lang('One to three months'), lang('Three to twelve months'), lang('One to two years'), lang('Three years'));
// The states of each request, set by the staff in the panel.
$booking_statuses = array(lang('Request received'), lang('Confirmed'), lang('Car collected'), lang('Car returned'), lang('Cancelled'));
$enquiry_statuses = array(lang('Request received'), lang('Quote sent'), lang('Agreed'), lang('Closed'));

// The query string of a link that opens a list with a filter set
// (?class=, ?gearbox=, ?fuel=) or a request form with a choice made
// (?car=, ?class=, ?location=, ?deal=, ?plan=): the form's fields start
// with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};


// A page: the account bar, the header, the page's own content (the lists,
// the forms, the account pages) and the shared footer.
$page = function ($main, $newsletter = true) use ($root, $shared, $el, $widget) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($newsletter) $children[] = $widget('newsletter_band');
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
            $heading('h1', $title, 'display-6 fw-semibold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pcr-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'h3 fw-semibold mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the categories are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what clients say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'cr-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the free cancellation and the way to book.
// A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-shield-check', 'text-primary'),
        $span(lang('Free cancellation up to 48 hours before pick-up.'), '', lang('Text')),
        $link(lang('Book a Car'), '{{page:book}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site.
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

// The header: the company's name, the menu, the renter's bookings and the
// way to book a car. The links to the current page are marked active on
// the server (smart active state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('fleet', lang('Our Fleet')),
        array('locations', lang('Locations')),
        array('deals', lang('Deals')),
        array('extras', lang('Extras')),
        array('business', lang('Business Rentals')),
        array('about', lang('About')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $el('span', 'd-inline-flex p-2 rounded-2 text-bg-primary lh-1', lang('Icon Box'), array($icon('bi-key-fill', 'fs-5'))),
                    $span('{{site_name}}', 'pcr-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#cr-nav'),
                    $attr('aria-controls', 'cr-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav pcr-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $link(lang('My Bookings'), '{{page:my_bookings}}', 'btn btn-outline-primary text-nowrap'),
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-key'),
                            $span(lang('Book a Car'), '', lang('Link Text')),
                        ), array('href' => '{{page:book}}')),
                    )),
                ), array('id' => 'cr-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// A desk's opening hours, as a definition list: the days, then the hours.
$hours = function ($location_key, $class = 'row small mb-0') use ($el, $locations) {
    $children = array();
    foreach ($locations[$location_key]['hours'] as $d) {
        $children[] = $el('dt', 'col-6 fw-normal text-body-secondary', lang('Day'), array(), array('text' => $d[0]));
        $children[] = $el('dd', 'col-6 mb-2', lang('Time'), array(), array('text' => $d[1]));
    }
    return $el('dl', $class, lang('List'), $children);
};

// The footer, on a dark band: the company in a sentence and the profiles,
// the fleet by class, the desks, the renter's pages, the company's pages
// and how to reach the head office; at the bottom the year, the site's
// name and the line that rates are confirmed with the booking.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $classes, $locations, $company, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $fleet_links = array($link(lang('All Cars'), '{{page:fleet}}', $plain));
    foreach ($classes as $c) $fleet_links[] = $link($c['title'], '{{page:fleet}}?' . $query(array('class' => $c['title'])), $plain);
    $desk_links = array();
    foreach ($locations as $key => $l) $desk_links[] = $link($l['title'], '{{page:locations}}#cr-loc-' . $key, $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Company'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-bold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-key-fill', 'text-primary'),
                        $span('{{site_name}}', 'pcr-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($company['bio'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-whatsapp', 'WhatsApp'),
                    )),
                    $contact_line('bi-geo-alt', $span($company['address'], '', lang('Text')), lang('Address')),
                    $contact_line('bi-telephone', $link($company['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $company['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($company['email'], 'mailto:' . $company['email'], $plain . ' text-break'), lang('Email')),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Fleet Links'), array(
                    $heading('h2', lang('Our Fleet'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($fleet_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Locations'), array(
                    $heading('h2', lang('Locations'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($desk_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Renters'), array(
                    $heading('h2', lang('For Renters'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book a Car'), '{{page:book}}', $plain),
                        $link(lang('My Bookings'), '{{page:my_bookings}}', $plain),
                        $link(lang('Business Rentals'), '{{page:business}}', $plain),
                        $link(lang('My Enquiries'), '{{page:my_enquiries}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Company'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Deals'), '{{page:deals}}', $plain),
                        $link(lang('Extras'), '{{page:extras}}', $plain),
                        $link(lang('Rental Terms'), '{{page:terms}}', $plain),
                        $link(lang('About'), '{{page:about}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Road Trip Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Rates are the lowest price a day; the price of your rental is confirmed with your booking.'), 'small text-body-secondary mb-0 text-md-end'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The booking band: a full-width band in the palette's colour with the
// way to book and the deals. Shared, so every page that ends with it
// carries the same offer.
$booking_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Ready for the road?'), 'pcr-display h2 fw-bold mb-2'),
                    $para(lang('Tell us where and when; we confirm your car and its price within the hour.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Car'), '{{page:book}}', 'btn btn-secondary btn-lg px-4'),
                        $link(lang('See the Deals'), '{{page:deals}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A car's facts in one line: seats, bags, gearbox and fuel. $bound reads
// them from the record (the cards of the lists and the car page); else the
// sample's own values are written.
$car_facts = function ($sample_key, $bound = true) use ($el, $icon, $span, $cars, $fuels, $transmissions) {
    $s = $cars[$sample_key];
    $fact = function ($icon_name, $value, $unit, $name, $token) use ($el, $icon, $span, $bound) {
        $children = array(
            $icon($icon_name, 'text-primary'),
            $span($value, 'fw-semibold', lang('Value'), $bound ? array('_bindings' => array('text' => $token)) : array()),
        );
        if ($unit !== '') $children[] = $span($unit, 'text-body-secondary', lang('Unit'));
        return $el('li', 'd-flex align-items-center gap-1 pg-hide-if-empty', $name, $children);
    };
    return $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-0', lang('Facts'), array(
        $fact('bi-people', (string)$s['seats'], lang('seats'), lang('Seats'), 'seats'),
        $fact('bi-suitcase', (string)$s['bags'], lang('bags'), lang('Bags'), 'bags'),
        $fact('bi-gear', $transmissions[$s['gearbox']], '', lang('Gearbox'), 'transmission'),
        $fact($fuels[$s['fuel']]['icon'], $fuels[$s['fuel']]['title'], '', lang('Fuel'), 'fuel'),
    ));
};

// A car's card of the lists (the home and the fleet): the first photo, the
// class, the title linking the car's page with "or similar" under it, the
// rate and the facts. Every value is bound to the Fleet form's field of
// the same name; the sample fills the canvas.
$car_card = function ($sample_key = 'lumo-five') use ($el, $para, $span, $link, $image, $col, $bind, $car_facts, $cars, $car_title, $car_rate, $classes, $photo) {
    $s = $cars[$sample_key];
    $title = $car_title($sample_key);
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pcr-lift', lang('Car Card'), array(
            $el('div', 'position-relative', lang('Picture'), array(
                $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo($sample_key, 800, 450), $title, 'card-img-top object-fit-cover',
                        array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'))),
                )),
                $el('div', 'position-absolute top-0 start-0 p-3', lang('Badges'), array(
                    $span($classes[$s['class']]['title'], 'badge text-bg-primary', lang('Car Class'), $bind('class')),
                )),
            )),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $el('h3', 'h5 card-title fw-semibold position-static mb-0', lang('Car Title'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('or similar'), 'small text-body-secondary mb-3'),
                $el('div', 'mb-3', lang('Facts'), array($car_facts($sample_key))),
                $para($car_rate($sample_key), 'pcr-price fw-bold text-primary mt-auto pt-3 border-top mb-0', $bind('rate_label')),
            )),
        )),
    ), '6', '4');
};

// A line with an icon in front (the hero's facts, a class card's seats and
// bags, the cover lines).
$tick = function ($icon_name, $text, $class = '') use ($el, $icon, $span) {
    return $el('li', trim('d-flex align-items-center gap-2 ' . $class), lang('Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Text')),
    ));
};
// The model a class is given with: its name and "or similar".
$model_line = function ($car_key, $class = '') use ($el, $span, $car_title) {
    return $el('div', trim('d-flex flex-wrap gap-1 ' . $class), lang('Model'), array(
        $span($car_title($car_key), 'fw-semibold text-body-emphasis', lang('Name')),
        $span(lang('or similar'), 'text-body-secondary', lang('Text')),
    ));
};
// A day rate as a figure and "/day" after it.
$day_rate = function ($prices, $size = 'fs-3') use ($el, $span, $money) {
    return $el('div', 'd-flex align-items-baseline gap-1', lang('Daily rate'), array(
        $span($money($prices), 'pg-carrental-figure fw-bold text-body-emphasis ' . $size, lang('Price')),
        $span(lang('/day'), 'small text-body-secondary', lang('Unit')),
    ));
};
// The first sample car of a class: the model the class is shown with.
$class_car = function ($key) use ($cars) {
    foreach ($cars as $car_key => $c) {
        if ($c['class'] === $key) return $car_key;
    }
    return 'lumo-five';
};

// A car class's card on the home's class line-up (the Car Rental Class
// Line-up block's card): the picture of its model, the name linking the
// fleet list filtered on the class, the model "or similar", its seats,
// bags and gearbox, the day rate and the deposit. Mid-size is marked as
// the most chosen.
$class_tile = function ($key) use ($el, $link, $span, $image, $attr, $tick, $model_line, $day_rate, $class_car, $classes, $cars, $transmissions, $money, $photo, $car_title, $query) {
    $c = $classes[$key];
    $car_key = $class_car($key);
    $car = $cars[$car_key];
    $head = array(
        $el('h3', 'h4 fw-bold mb-0', lang('Name'), array(
            $link($c['title'], '{{page:fleet}}?' . $query(array('class' => $c['title'])), 'stretched-link link-body-emphasis text-decoration-none'),
        )),
    );
    if ($key === 'compact') $head[] = $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary text-wrap', lang('Badge'));
    return $el('li', 'pg-carrental-class', $c['title'], array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Car Class'), array(
            $image($photo($car_key, 800, 450), $car_title($car_key), 'pg-ratio-16x9 w-100 object-fit-cover',
                array('_attrs' => array($attr('loading', 'lazy')))),
            $el('div', 'card-body p-4 d-flex flex-column', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-1', lang('Title'), $head),
                $model_line($car_key, 'small mb-3'),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-4', lang('Facts'), array(
                    $tick('bi-people', str_replace('{var}', (string)$car['seats'], lang('{var} seats'))),
                    $tick('bi-briefcase', str_replace('{var}', (string)$car['bags'], lang('{var} bags'))),
                    $tick('bi-gear', $transmissions[$car['gearbox']]),
                )),
                $el('dl', 'd-flex align-items-end justify-content-between gap-3 mt-auto mb-0 pt-3 border-top', lang('Prices'), array(
                    $el('div', '', lang('Daily rate'), array(
                        $el('dt', 'small fw-normal text-body-secondary mb-1', lang('Label'), array($span(lang('Daily rate'), '', lang('Text')))),
                        $el('dd', 'mb-0', lang('Price'), array($day_rate($c['rate']))),
                    )),
                    $el('div', 'text-end', lang('Security deposit'), array(
                        $el('dt', 'small fw-normal text-body-secondary mb-1', lang('Label'), array($span(lang('Security deposit'), '', lang('Text')))),
                        $el('dd', 'pg-carrental-figure fw-semibold mb-0', lang('Price'), array($span($money($c['deposit']), '', lang('Price')))),
                    )),
                )),
            )),
        )),
    ));
};

// A desk's card (the home and the locations page): the place, its kind,
// the address, the hours, what it offers and the way to book a car there.
// The locations page gives each desk its own section with the anchor the
// footer links (#cr-loc-<key>).
$location_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon_tile, $hours, $check_list, $locations, $query) {
    $l = $locations[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm', lang('Location Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Title'), array(
                    $icon_tile($l['icon']),
                    $el('div', '', lang('Name and Kind'), array(
                        $heading('h3', $l['title'], 'h5 fw-bold mb-0'),
                        $span($l['kind'], 'small text-body-secondary', lang('Kind')),
                    )),
                )),
                $para($l['address'], 'fw-semibold mb-2'),
                $para($l['text'], 'small text-body-secondary mb-3'),
                $hours($key, 'row small mb-3'),
                $check_list($l['points'], 'list-unstyled small mb-4'),
                $link(lang('Book from here'), '{{page:book}}?' . $query(array('location' => $l['title'])), 'btn btn-outline-primary mt-auto'),
            )),
        )),
    ), '6', '4', '', '12', '');
};

// An extra's card (the extras page and the home): the icon, the name, the
// price and what it gives.
$extra_card = function ($key) use ($el, $heading, $para, $col, $icon_tile, $extras, $extra_price) {
    $e = $extras[$key];
    return $col(array(
        $el('article', 'd-flex gap-3 h-100', lang('Extra'), array(
            $icon_tile($e['icon'], 'flex-shrink-0 align-self-start'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $e['title'], 'h6 fw-bold mb-1'),
                $para($extra_price($key), 'pcr-price small fw-semibold text-primary mb-1'),
                $para($e['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// A deal's card (the deals page and the home): what it gives in a badge,
// the name, a line, its terms and a link that opens the booking request
// with the deal chosen.
$deal_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon, $check_list, $deals, $query) {
    $d = $deals[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pcr-lift', lang('Deal Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-3', lang('Badges'), array(
                    $icon($d['icon'], 'fs-3 text-primary'),
                    $span($d['badge'], 'badge rounded-pill text-bg-primary text-wrap', lang('Badge')),
                )),
                $heading('h3', $d['title'], 'h5 fw-bold mb-2'),
                $para($d['text'], 'small text-body-secondary mb-3'),
                $check_list($d['points'], 'list-unstyled small mb-4'),
                $link(lang('Book with this deal'), '{{page:book}}?' . $query(array('deal' => $d['title'])), 'btn btn-primary mt-auto'),
            )),
        )),
    ), '6', '4');
};

// A business plan's card (the business page): the name, a line, what it
// gives and a link that opens the enquiry with it chosen.
$business_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $check_list, $business_plans, $query) {
    $p = $business_plans[$key];
    $children = array();
    if ($p['featured']) $children[] = $span(lang('Most chosen'), 'badge text-bg-primary align-self-start mb-3', lang('Badge'));
    $children[] = $heading('h3', $p['title'], 'h5 fw-bold mb-2');
    $children[] = $para($p['text'], 'small text-body-secondary mb-3');
    $children[] = $check_list($p['points'], 'list-unstyled small mb-4');
    $children[] = $link(lang('Ask for a quote'), '{{page:business_enquiry}}?' . $query(array('plan' => $p['title'])),
        'btn ' . ($p['featured'] ? 'btn-primary' : 'btn-outline-primary') . ' mt-auto');
    return $col(array(
        $el('article', 'card h-100 ' . ($p['featured'] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Plan Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), $children),
        )),
    ), '6', '4');
};

// What a renter says: the quote, the stars, the person and the car.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Customer Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Customer'), array(
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
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary', array(),
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
        $el('button', 'btn btn-primary px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-outline-secondary', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
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
                $item('car_new', lang('New Car')),
                $item('staff_bookings', lang('Booking Requests')),
                $item('staff_enquiries', lang('Business Enquiries')),
                $item('blog_new', lang('New Article')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no newsletter.
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
    return $agree($id, 'consent', lang('I would like to receive deals, new cars and the Road Trip Journal by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// request may carry documents (a company letter, a flight ticket) and
// pictures, so the default accepts PDFs, office files and pictures; one
// that takes only pictures is a collection's image kind (a car's photos).
$file = function ($id, $name, $folder, $accept = '.pdf,.doc,.docx,.odt,.jpg,.jpeg,.png') use ($el, $attr) {
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
                    $link(lang('Five coast roads worth a day of driving'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
        return $link($text, '#', 'btn btn-sm btn-outline-primary', array($attr('rel', 'noopener nofollow')),
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
            $el('button', 'btn btn-sm btn-primary', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};

