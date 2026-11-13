<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bike Shop", part 1 of its files: the node builders, the
 * shop's own data (the shop and its opening hours, the workshop's jobs with
 * their prices and turnarounds, the kinds of bike, the frame sizes, the
 * bike fit packages, the catalog's groups and sample products with their
 * prices, the group rides counted from the day the template opens, the
 * paces and drop rules, the mechanics and fitters, the brands stocked and
 * the answers of the forms' pick lists) and the small helpers the pages
 * share (page shell, page header, section header, icon tile, pill, facts
 * list, steps, check lists, questions). cards.php and the page files of the
 * same folder are included after it in the same scope and build their
 * trees with these variables; includes/design_templates/pinegrap-bikeshop.php
 * puts them together.
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
// An entrance the operator can change or remove in the Interactions
// section: the node's children rise one after another as it scrolls into
// view. The platform keeps them still under reduced motion.
$rise = function ($node, $stagger = 80) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => (int)$stagger);
    return $node;
};
// A picture that fades in as it scrolls into view (the fit photo); nothing
// moves by itself.
$fade_in = function ($node) {
    $node['props']['_fx'] = array('effect' => 'fade', 'trigger' => 'view');
    return $node;
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
// every opening until the shop puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-bikeshop-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's steel and graphite. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e2e8f0/1f2937?text=' . rawurlencode($initials !== '' ? $initials : 'B');
};

// ── The shop: its hours and its workshop ────────────────────────────────

// The shop itself. The about page tells its story; the footer, the
// workshop page and the contact page use the address, phone and hours.
$shop = array(
    'tagline'  => lang('Bikes, parts and a workshop that gets you back on the road'),
    'story'    => lang('The shop began as a repair bench in a garage, fixing the neighbours\' punctures at the weekend. Today the workshop has four stands, the shop floor carries road, gravel, mountain and city bikes, and the Saturday ride still leaves from the door.'),
    'address'  => lang('Unit 4, Old Tannery Yard, Canal Street'),
    'phone'    => '+90 555 000 00 00',
    'email'    => 'info@example.com',
    'hours'    => lang('Open Tuesday to Saturday, 09:00 to 18:30'),
    'season'   => lang('The workshop is busiest when the first warm weekends arrive; book a service before then'),
    'workshop' => lang('Four workshop stands and a fitting studio behind the shop floor'),
    'collect'  => lang('Bikes are built, checked and collected in the shop; parts and accessories can be posted'),
);

// The opening hours, line by line (the workshop page, the contact page and
// the footer say them in a line).
$opening_hours = array(
    array('days' => lang('Shop, Tuesday to Friday'), 'hours' => '09:00 - 18:30'),
    array('days' => lang('Shop, Saturday'), 'hours' => '09:00 - 17:00'),
    array('days' => lang('Workshop drop-off'), 'hours' => '09:00 - 11:00'),
    array('days' => lang('Fitting studio, by appointment'), 'hours' => '10:00 - 17:00'),
);

// The workshop's jobs: the Service Requests form's job (?job=) and the
// workshop page's price list. 'turnaround' is said as words beside a clock.
$job_kinds = array(
    'puncture' => array('icon' => 'bi-record-circle', 'title' => lang('Puncture Repair'), 'turnaround' => lang('Same day'),
        'summary'  => lang('A new tube or a tubeless plug, the tyre checked for what caused it.'),
        'includes' => array(lang('Tube or tubeless repair'), lang('Tyre checked inside and out'), lang('Pressure set for your weight'))),
    'safety'   => array('icon' => 'bi-shield-check', 'title' => lang('Safety Check'), 'turnaround' => lang('Same day'),
        'summary'  => lang('Brakes, gears, wheels and bolts checked and adjusted, with a list of anything worn.'),
        'includes' => array(lang('Brakes and gears adjusted'), lang('Bolts checked to their torque'), lang('A written list of worn parts'))),
    'service'  => array('icon' => 'bi-tools', 'title' => lang('Full Service'), 'turnaround' => lang('2 days'),
        'summary'  => lang('The drivetrain cleaned, the hubs and headset checked, the wheels trued and every cable set.'),
        'includes' => array(lang('Drivetrain cleaned and lubricated'), lang('Wheels trued and spokes tensioned'), lang('Hubs, headset and bottom bracket checked'), lang('Brakes bled or cables replaced when needed'))),
    'overhaul' => array('icon' => 'bi-gear-wide-connected', 'title' => lang('Overhaul'), 'turnaround' => lang('4 days'),
        'summary'  => lang('The bike stripped to the frame, every part cleaned or replaced and built back up.'),
        'includes' => array(lang('Stripped to the frame and cleaned'), lang('Bearings serviced or replaced'), lang('New cables and housing'), lang('Built back up and test ridden'))),
    'suspension' => array('icon' => 'bi-arrows-collapse-vertical', 'title' => lang('Suspension Service'), 'turnaround' => lang('3 days'),
        'summary'  => lang('Fork and shock seals and oil renewed, the sag set for the way you ride.'),
        'includes' => array(lang('Seals and oil renewed'), lang('Sag and rebound set with you'), lang('Stanchions checked for wear'))),
    'ebike'    => array('icon' => 'bi-lightning-charge', 'title' => lang('E-Bike Check'), 'turnaround' => lang('1 day'),
        'summary'  => lang('The motor and battery read out, the firmware updated and the brakes checked for the extra weight.'),
        'includes' => array(lang('Motor and battery diagnostics'), lang('Firmware updated'), lang('Brakes and tyres checked for the extra weight'))),
);
// The workshop's prices: labour, parts on top and quoted first.
$job_prices = array(
    'puncture'   => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 450),
    'safety'     => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1100),
    'service'    => array('USD' => 95, 'EUR' => 88, 'GBP' => 75, 'TRY' => 3000),
    'overhaul'   => array('USD' => 190, 'EUR' => 175, 'GBP' => 150, 'TRY' => 6000),
    'suspension' => array('USD' => 120, 'EUR' => 110, 'GBP' => 95, 'TRY' => 3800),
    'ebike'      => array('USD' => 60, 'EUR' => 55, 'GBP' => 48, 'TRY' => 1900),
);

// The kinds of bike: the requests' bike kind (?bike=) and the catalog's
// bikes.
$bike_kinds = array(
    'road'     => array('icon' => 'bi-bicycle', 'title' => lang('Road Bike')),
    'gravel'   => array('icon' => 'bi-signpost-split', 'title' => lang('Gravel Bike')),
    'mountain' => array('icon' => 'bi-triangle', 'title' => lang('Mountain Bike')),
    'city'     => array('icon' => 'bi-buildings', 'title' => lang('City Bike')),
    'ebike'    => array('icon' => 'bi-lightning-charge', 'title' => lang('Electric Bike')),
    'kids'     => array('icon' => 'bi-balloon', 'title' => lang('Children\'s Bike')),
);

// The frame sizes the bikes come in: the shop's size option and the sizes
// page. The size codes are international and stay as they are; the rider
// heights are words.
$frame_sizes = array(
    'S'  => lang('Riders 155 to 167 cm'),
    'M'  => lang('Riders 167 to 177 cm'),
    'L'  => lang('Riders 177 to 186 cm'),
    'XL' => lang('Riders 186 to 196 cm'),
);

// The bike fit packages (?fit=): the Fit Requests form's fit and the fit
// page's packages ('pricing-tiers').
$fit_kinds = array(
    'basic' => array('title' => lang('Basic Fit'), 'length' => lang('An hour'),
        'summary'  => lang('Saddle height, setback and bar reach set on your own bike.'),
        'includes' => array(lang('Saddle height and setback'), lang('Bar reach and drop'), lang('A fit sheet to keep'))),
    'full'  => array('title' => lang('Full Fit'), 'length' => lang('Two and a half hours'),
        'summary'  => lang('A talk about how you ride, a flexibility check, then every contact point set and filmed.'),
        'includes' => array(lang('Interview and flexibility check'), lang('Saddle, bars, cleats and stem set'), lang('Video before and after'), lang('A follow-up visit within six weeks'))),
    'cleat' => array('title' => lang('Cleat Fit'), 'length' => lang('Half an hour'),
        'summary'  => lang('Cleats placed for your feet and knees, for riders whose position is already right.'),
        'includes' => array(lang('Cleat position and angle'), lang('Float chosen with you'), lang('Wedges or shims when needed'))),
);
$fit_prices = array(
    'basic' => array('USD' => 80, 'EUR' => 74, 'GBP' => 64, 'TRY' => 2500),
    'full'  => array('USD' => 220, 'EUR' => 200, 'GBP' => 175, 'TRY' => 7000),
    'cleat' => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1400),
);
// How a rider rides: the Fit Requests form's riding style.
$riding_styles = array(lang('Commuting'), lang('Long rides at an easy pace'), lang('Fast group rides'), lang('Racing'), lang('Off-road and trails'));

// ── The catalog: bikes, parts, accessories and vouchers ─────────────────

// The groups of the catalog. With the shop each is a product group; 'sku'
// starts the codes of its products. Bikes are collected in the shop and a
// voucher comes by e-mail; parts and accessories can be posted.
$product_groups = array(
    'bikes'       => array('icon' => 'bi-bicycle', 'title' => lang('Bikes'), 'sku' => 'BKS-BIKE-', 'shippable' => false),
    'parts'       => array('icon' => 'bi-gear', 'title' => lang('Parts'), 'sku' => 'BKS-PART-', 'shippable' => true),
    'accessories' => array('icon' => 'bi-bag', 'title' => lang('Accessories'), 'sku' => 'BKS-ACC-', 'shippable' => true),
    'vouchers'    => array('icon' => 'bi-gift', 'title' => lang('Workshop Vouchers'), 'sku' => 'BKS-GIFT-', 'shippable' => false),
);

// The sample products. A bike names its kind and comes in the frame sizes;
// 'featured' bikes are the newest on the home.
$products = array(
    'tarmac'     => array('group' => 'bikes', 'kind' => 'road', 'title' => lang('Ridgeline Road Disc'),
        'summary' => lang('An aluminium road bike with a carbon fork, hydraulic disc brakes and room for 32 mm tyres.'),
        'details' => array(lang('Aluminium frame, carbon fork'), lang('22 speeds'), lang('Hydraulic disc brakes'), lang('Weighs about 9 kg')),
        'featured' => true),
    'gravel'     => array('group' => 'bikes', 'kind' => 'gravel', 'title' => lang('Dust Trail Gravel'),
        'summary' => lang('A steel gravel bike with mounts for bags and racks, at home on farm tracks and long tours.'),
        'details' => array(lang('Steel frame and fork'), lang('One chainring, 11 speeds'), lang('Room for 45 mm tyres'), lang('Mounts for racks and bottles')),
        'featured' => true),
    'trail'      => array('group' => 'bikes', 'kind' => 'mountain', 'title' => lang('Rockface Trail 130'),
        'summary' => lang('A full-suspension trail bike with 130 mm of travel and a dropper post.'),
        'details' => array(lang('130 mm front and rear'), lang('Dropper seat post'), lang('29-inch wheels'), lang('Tubeless ready')),
        'featured' => true),
    'commuter'   => array('group' => 'bikes', 'kind' => 'ebike', 'title' => lang('Canal Line E-Commuter'),
        'summary' => lang('An electric city bike with lights, mudguards and a rack, for a hilly ride to work.'),
        'details' => array(lang('Range of about 90 km'), lang('Hub gears and belt drive'), lang('Lights, mudguards and rack'), lang('Charges in four hours')),
        'featured' => false),
    'tube'       => array('group' => 'parts', 'title' => lang('Inner Tube, 700 x 28-32'),
        'summary' => lang('A butyl tube with a 48 mm presta valve.'),
        'details' => array(lang('Presta valve, 48 mm'), lang('Fits 28 to 32 mm tyres')),
        'featured' => false),
    'chain'      => array('group' => 'parts', 'title' => lang('11-Speed Chain'),
        'summary' => lang('A nickel-plated chain with a quick link, for road and gravel drivetrains.'),
        'details' => array(lang('Quick link included'), lang('116 links')),
        'featured' => false),
    'pads'       => array('group' => 'parts', 'title' => lang('Disc Brake Pads, Organic'),
        'summary' => lang('A quiet organic compound for road and city bikes, one pair per box.'),
        'details' => array(lang('Organic compound'), lang('One wheel per box')),
        'featured' => false),
    'tyre'       => array('group' => 'parts', 'title' => lang('All-Season Tyre, 700 x 32'),
        'summary' => lang('A puncture-resistant tyre with reflective sidewalls.'),
        'details' => array(lang('Puncture protection belt'), lang('Reflective sidewalls')),
        'featured' => false),
    'helmet'     => array('group' => 'accessories', 'title' => lang('Road Helmet'),
        'summary' => lang('A light, well-ventilated helmet with a rear light clip.'),
        'details' => array(lang('Sizes S, M and L'), lang('Rear light clip')),
        'featured' => false),
    'lights'     => array('group' => 'accessories', 'title' => lang('Front and Rear Light Set'),
        'summary' => lang('Rechargeable lights for the dark evenings, charged by USB.'),
        'details' => array(lang('800 lumen front, 60 lumen rear'), lang('Charged by USB-C')),
        'featured' => false),
    'lock'       => array('group' => 'accessories', 'title' => lang('D-Lock with Cable'),
        'summary' => lang('A hardened steel D-lock with a cable for the front wheel.'),
        'details' => array(lang('Hardened steel shackle'), lang('1.2 m cable')),
        'featured' => false),
    'pump'       => array('group' => 'accessories', 'title' => lang('Track Pump with Gauge'),
        'summary' => lang('A floor pump with a large gauge and a head for both valves.'),
        'details' => array(lang('Up to 11 bar'), lang('Presta and Schrader head')),
        'featured' => false),
    'gift_service' => array('group' => 'vouchers', 'title' => lang('Full Service Voucher'),
        'summary' => lang('A full service in the workshop, to give to a rider who has everything else.'),
        'details' => array(lang('Valid for a year'), lang('Sent by e-mail')),
        'featured' => false),
    'gift_fit'   => array('group' => 'vouchers', 'title' => lang('Bike Fit Voucher'),
        'summary' => lang('A full bike fit in the studio, booked when it suits them.'),
        'details' => array(lang('Valid for a year'), lang('Sent by e-mail')),
        'featured' => false),
);
$product_prices = array(
    'tarmac'       => array('USD' => 1450, 'EUR' => 1340, 'GBP' => 1150, 'TRY' => 46000),
    'gravel'       => array('USD' => 1290, 'EUR' => 1190, 'GBP' => 1020, 'TRY' => 41000),
    'trail'        => array('USD' => 2690, 'EUR' => 2480, 'GBP' => 2130, 'TRY' => 85000),
    'commuter'     => array('USD' => 2390, 'EUR' => 2200, 'GBP' => 1890, 'TRY' => 76000),
    'tube'         => array('USD' => 8, 'EUR' => 7, 'GBP' => 6, 'TRY' => 250),
    'chain'        => array('USD' => 38, 'EUR' => 35, 'GBP' => 30, 'TRY' => 1200),
    'pads'         => array('USD' => 22, 'EUR' => 20, 'GBP' => 17, 'TRY' => 700),
    'tyre'         => array('USD' => 42, 'EUR' => 39, 'GBP' => 33, 'TRY' => 1350),
    'helmet'       => array('USD' => 110, 'EUR' => 100, 'GBP' => 88, 'TRY' => 3500),
    'lights'       => array('USD' => 65, 'EUR' => 60, 'GBP' => 52, 'TRY' => 2100),
    'lock'         => array('USD' => 55, 'EUR' => 50, 'GBP' => 44, 'TRY' => 1750),
    'pump'         => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1450),
    'gift_service' => array('USD' => 95, 'EUR' => 88, 'GBP' => 75, 'TRY' => 3000),
    'gift_fit'     => array('USD' => 220, 'EUR' => 200, 'GBP' => 175, 'TRY' => 7000),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A job's "From … " words (parts on top) and a fit's price.
$job_price_text = function ($key) use ($job_prices, $money) {
    return sprintf(lang('From %s'), $money($job_prices[$key]));
};
$fit_price_text = function ($key) use ($fit_prices, $money) {
    return $money($fit_prices[$key]);
};
// A product's code: its group's prefix and its key.
$product_sku = function ($key) use ($products, $product_groups) {
    return $product_groups[$products[$key]['group']]['sku'] . strtoupper(str_replace('_', '-', $key));
};

// ── Group rides ─────────────────────────────────────────────────────────

// The paces of a ride (?pace=): the ride cards' pill and the list's filter.
$paces = array(
    'social' => array('icon' => 'bi-cup-hot', 'title' => lang('Social'), 'speed' => lang('About 22 km/h, with a café stop')),
    'steady' => array('icon' => 'bi-speedometer', 'title' => lang('Steady'), 'speed' => lang('About 27 km/h, regrouping on the climbs')),
    'fast'   => array('icon' => 'bi-lightning', 'title' => lang('Fast'), 'speed' => lang('Over 30 km/h, for riders who train')),
);
// Whether the ride waits for everyone.
$drop_rules = array(
    'nodrop' => array('icon' => 'bi-people', 'title' => lang('No-drop')),
    'drop'   => array('icon' => 'bi-wind', 'title' => lang('Drop ride')),
);

// The sample rides: the records the Group Rides form starts with. Each
// falls on the first such ISO weekday after today, plus 'weeks' weeks
// ($ride_on), so every ride is still to come when the template opens.
$rides = array(
    'saturday_social' => array('title' => lang('Saturday Café Ride'), 'pace' => 'social', 'drop' => 'nodrop', 'weekday' => 6, 'weeks' => 0, 'time' => '08:30',
        'distance' => '45 km', 'climbing' => '350 m', 'place' => lang('The shop door'),
        'summary' => lang('An easy loop along the river to the lake café and back, with a stop for everyone to catch up.'),
        'featured' => true),
    'wednesday_steady' => array('title' => lang('Wednesday Evening Loop'), 'pace' => 'steady', 'drop' => 'nodrop', 'weekday' => 3, 'weeks' => 0, 'time' => '18:30',
        'distance' => '35 km', 'climbing' => '420 m', 'place' => lang('The shop door'),
        'summary' => lang('A steady loop over the two hills west of town, lights on once the evenings draw in.'),
        'featured' => false),
    'sunday_fast' => array('title' => lang('Sunday Hill Reps'), 'pace' => 'fast', 'drop' => 'drop', 'weekday' => 7, 'weeks' => 0, 'time' => '07:30',
        'distance' => '80 km', 'climbing' => '1,200 m', 'place' => lang('Bridge car park'),
        'summary' => lang('A hard morning on the long climb, three times up, for riders who train.'),
        'featured' => false),
    'gravel_explore' => array('title' => lang('Gravel Explorer'), 'pace' => 'steady', 'drop' => 'nodrop', 'weekday' => 7, 'weeks' => 1, 'time' => '09:00',
        'distance' => '60 km', 'climbing' => '700 m', 'place' => lang('Forest gate'),
        'summary' => lang('Farm tracks and forest roads for gravel and cross bikes, with a picnic at the halfway point.'),
        'featured' => true),
    'beginners' => array('title' => lang('First Group Ride'), 'pace' => 'social', 'drop' => 'nodrop', 'weekday' => 6, 'weeks' => 2, 'time' => '10:00',
        'distance' => '25 km', 'climbing' => '150 m', 'place' => lang('The shop door'),
        'summary' => lang('A short ride on quiet roads for riders new to riding in a group, with a talk on hand signals first.'),
        'featured' => true),
);
// The date of a ride as Y-m-d: the first day strictly after today with
// that ISO weekday, plus $weeks weeks.
$ride_on = function ($weekday, $weeks = 0) {
    $ts = strtotime('tomorrow');
    while ((int)date('N', $ts) !== (int)$weekday) $ts = strtotime('+1 day', $ts);
    return date('Y-m-d', strtotime('+' . (int)$weeks . ' week', $ts));
};
// A product's details as the value of its Details field: one line per item.
$details_text = function ($list) {
    return implode("\n", $list);
};

// ── The people and the brands ───────────────────────────────────────────

// The shop's people (the about page, 'team-grid'). Fixed data, not a
// collection.
$people = array(
    array('name' => lang('Emre Çelik'), 'role' => lang('Owner and Head Mechanic'),
        'bio' => lang('Opened the shop in the garage and still builds every wheel that leaves the workshop.')),
    array('name' => lang('Hannah Price'), 'role' => lang('Workshop Mechanic'),
        'bio' => lang('Looks after the suspension and e-bike jobs and answers the service requests.')),
    array('name' => lang('Deniz Akın'), 'role' => lang('Bike Fitter'),
        'bio' => lang('A former physiotherapist who runs the fitting studio and every fit request.')),
    array('name' => lang('Luca Moretti'), 'role' => lang('Ride Leader'),
        'bio' => lang('Plans the routes of the group rides and leads the Saturday café ride.')),
);

// The brands the shop stocks ('logos-grid'): sample names, replaced by the
// shop's own.
$brands = array('Ridgeline', 'Dust Trail', 'Rockface', 'Canal Line', 'Spoke & Co', 'Northgear');

// ── The answers of the forms' pick lists ────────────────────────────────
// Each answer's value is its own words, so the e-mails and the lists read
// as the rider chose.
$job_titles = array();
foreach ($job_kinds as $j) $job_titles[] = $j['title'];
// The service request's job: each job by title, or something else.
$job_choices = array_merge($job_titles, array(lang('Something else')));
$bike_titles = array();
foreach ($bike_kinds as $b) $bike_titles[] = $b['title'];
$fit_titles = array();
foreach ($fit_kinds as $f) $fit_titles[] = $f['title'];
$size_titles = array_keys($frame_sizes);
$group_titles = array();
foreach ($product_groups as $g) $group_titles[] = $g['title'];
$pace_titles = array();
foreach ($paces as $p) $pace_titles[] = $p['title'];
$drop_titles = array();
foreach ($drop_rules as $r) $drop_titles[] = $r['title'];
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a service request, set by the workshop in the panel.
$service_statuses = array(lang('Request received'), lang('Booked In'), lang('In the Workshop'), lang('Waiting for Parts'), lang('Ready to Collect'), lang('Bike collected'));
// The states of a fit request.
$fit_statuses = array(lang('Request received'), lang('Booked In'), lang('Fitted'), lang('Cancelled'));

// The words of a sample record's pick list answers.
$ride_pace = function ($key) use ($rides, $paces) {
    return $paces[$rides[$key]['pace']]['title'];
};
$ride_drop = function ($key) use ($rides, $drop_rules) {
    return $drop_rules[$rides[$key]['drop']]['title'];
};
$product_group = function ($key) use ($products, $product_groups) {
    return $product_groups[$products[$key]['group']]['title'];
};

// The query string of a link that opens a list with a filter set (?pace=)
// or a form with a choice made (?job=, ?bike=, ?fit=): the form's fields
// start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// ── Page helpers ────────────────────────────────────────────────────────

// A page: the account bar, the header, the page's own content and the
// shared footer. The staff pages add the staff bar (cards.php).
$page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
    ));
};

// The top of the inner pages: where the page sits, its title and one
// sentence on the steel surface, an orange rule under them like the edge
// of a workbench, and whatever the page adds.
$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'pbs-hero py-4 py-lg-5 bg-body-tertiary border-bottom border-4 border-primary', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'pbs-display display-5 text-uppercase mb-2'),
            $para($lead, 'lead mb-0'),
        ), $more)),
    ));
};

// The heading over a section: an orange bar beside the condensed title,
// one sentence under it, and a square link to the rest on the right.
$section_header = function ($title, $lead = '', $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $text = array($heading('h2', $title, 'pbs-display h2 text-uppercase mb-1'));
    if ($lead !== '') $text[] = $para($lead, 'text-body-secondary mb-0');
    $children = array($el('div', 'pbs-mark border-start border-4 border-primary ps-3', lang('Section Title'), $text));
    if ($more_text !== '') {
        $children[] = $el('a', 'btn btn-sm btn-outline-dark rounded-0 d-inline-flex align-items-center gap-1 text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), $children);
};

// An icon in a square steel tile, the way the jobs, the bike kinds and the
// catalog's groups are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center bg-dark text-white p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// A pill of an icon and its words (a turnaround, a pace, a drop rule):
// never a colour alone.
$pill = function ($icon_name, $text, $class = 'bg-body text-body-emphasis border') use ($el, $icon, $span) {
    return $el('span', 'badge rounded-pill d-inline-flex align-items-center gap-1 ' . $class, lang('Badge'), array(
        $icon($icon_name),
        $span($text, '', lang('Text')),
    ));
};

// A short list of facts (a ride's distance and climbing, a bike's
// details): the name above, the figure in tabular digits under it.
$facts = function ($pairs) use ($el) {
    $children = array();
    foreach ($pairs as $label => $value) {
        $children[] = $el('div', '', lang('Fact'), array(
            $el('dt', 'small text-body-secondary fw-normal', lang('Term'), array(), array('text' => $label)),
            $el('dd', 'pg-bike-num fw-semibold mb-0', lang('Value'), array(), array('text' => $value)),
        ));
    }
    return $el('dl', 'd-flex flex-wrap gap-4 mb-0', lang('Facts'), $children);
};

// A numbered step of a short how-to: the square key of the block family.
$step = function ($n, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'd-flex gap-3 mb-4', lang('Step'), array(
        $span((string)$n, 'pg-bike-key text-bg-primary fw-bold align-self-start', lang('Step Number')),
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
            $icon('bi-check2-square', 'text-primary'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', $class, lang('Check List'), $children);
};

// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answer, $topics = '', $parent = 'bikeshop-faq') use ($el, $para, $attr, $searchable) {
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
