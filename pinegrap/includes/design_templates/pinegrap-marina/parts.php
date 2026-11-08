<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marina", part 1 of its files: the node builders, the
 * harbour's own data (the kinds of berth, the seasons and their rates, the
 * sample charter boats, the harbour services, the facilities ashore, the
 * approach by sea and the way by land, the harbour rules, the figures,
 * what crews say and the answers of the forms' pick lists) and the parts
 * every page repeats (account bar, header, footer, berth band, berth-kind,
 * boat, service and crew cards, the rate table, page shells, section
 * headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-marina.php puts them together.
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
// every opening until the marina puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-marina-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/dbeafe/1e3a8a?text=' . rawurlencode($initials !== '' ? $initials : 'M');
};


// ── The harbour: its berths, its seasons and what it offers ─────────────

// The harbour itself. The about page tells its story; the footer, the
// berth band, the arriving page and the contact lines use the address, the
// coordinates, the radio channel, the phone and the office hours.
$harbour = array(
    'tagline'     => lang('A sheltered marina at the head of the bay, with room for visiting yachts, boats on their home berth and crews ashore'),
    'story'       => lang('The harbour began as a fishing quay where a few yachts were allowed to tie up for the night. The breakwater came later, then the pontoons, the boatyard and the café on the old net store; today the marina is home to sailing and motor boats of every size, but the fishing boats still land their catch at the inner quay.'),
    'address'     => lang('Harbour Road 1, Pine Bay'),
    'coordinates' => '40.6512, 29.2741',
    'vhf'         => lang('VHF channel 72'),
    'channel'     => '72',
    'phone'       => '+90 555 000 00 00',
    'email'       => 'harbour@example.com',
    'hours'       => lang('Harbour office: every day, 8:00 to 20:00 in summer and 8:30 to 17:30 in winter'),
    'opening'     => lang('Open all year round, with a harbour master on watch day and night'),
    'arrival'     => lang('Call the harbour office on the radio before you enter and we will meet you at the reception pontoon'),
);

// The kinds of berth. Each has its own page (berth_<key>); the berth request
// opens with the kind chosen (?kind=) in the kind's own words.
$kinds = array(
    'visitor' => array('icon' => 'bi-compass', 'title' => lang('Visitor Berths'),
        'size'    => lang('For boats up to 25 metres, by the night'),
        'summary' => lang('Pontoon berths for yachts and motor boats passing through, for a night or a week.'),
        'text'    => lang('The visitor pontoon lies just inside the breakwater, a short walk from the harbour office, the showers and the town. Every berth has water and shore power, and our marina staff take your lines when you arrive.'),
        'points'  => array(lang('Water and shore power on every berth'), lang('Showers, laundry and Wi-Fi included'), lang('Help with your lines on arrival'), lang('Five minutes\' walk to the town'))),
    'seasonal' => array('icon' => 'bi-sun', 'title' => lang('Seasonal Berths'),
        'size'    => lang('For boats up to 20 metres, for the summer'),
        'summary' => lang('Your own berth from spring to autumn, with the boat ready whenever you come down.'),
        'text'    => lang('A seasonal berth is yours for the whole summer: come and go as you like, and the berth waits for you. Many seasonal owners lift out with us for the winter, so the season ends with the boat already in the yard.'),
        'points'  => array(lang('The same berth all summer'), lang('Water and shore power on every berth'), lang('A parking permit for one car'), lang('A better rate for a winter lift-out'))),
    'annual' => array('icon' => 'bi-anchor', 'title' => lang('Annual Berths'),
        'size'    => lang('For boats up to 30 metres, all year round'),
        'summary' => lang('A home berth for the whole year, with a key to the pontoon gate and a locker ashore.'),
        'text'    => lang('An annual berth makes the marina your boat\'s home port. Berth holders have a key to their pontoon gate, a locker ashore, a car parking permit and a lower rate in the boatyard and at the fuel dock.'),
        'points'  => array(lang('A home berth all year round'), lang('Pontoon gate key and a locker ashore'), lang('Lower rates in the boatyard'), lang('Mail and parcels kept for you'))),
    'mooring' => array('icon' => 'bi-life-preserver', 'title' => lang('Swinging Moorings'),
        'size'    => lang('For boats up to 12 metres, in the outer bay'),
        'summary' => lang('A buoy in the sheltered outer bay, with the harbour launch to bring you ashore.'),
        'text'    => lang('Our swinging moorings lie in the outer bay, sheltered from the prevailing wind. They are the quiet way to keep a smaller boat: the harbour launch runs crews to and from the dinghy pontoon in the season.'),
        'points'  => array(lang('Moorings checked every winter'), lang('Harbour launch in the season'), lang('Space for a dinghy at the pontoon'), lang('Showers and Wi-Fi ashore included'))),
    'drystack' => array('icon' => 'bi-box-seam', 'title' => lang('Dry Stack'),
        'size'    => lang('For motor boats up to 9 metres, kept ashore'),
        'summary' => lang('Your motor boat kept in a rack ashore and launched when you ask for it.'),
        'text'    => lang('The dry stack keeps motor boats out of the water, under cover and away from weed. Tell us when you are coming and the boat is waiting at the pontoon, washed and ready; when you are back, we lift it out again.'),
        'points'  => array(lang('Kept dry and under cover'), lang('Launched when you ask'), lang('A fresh-water wash after every trip'), lang('No antifouling needed'))),
);

// The seasons. Rates change with them; the dates are words, since the
// marina sets the exact dates each year and writes them on the rates page.
$seasons = array(
    'low'  => array('title' => lang('Low Season'), 'when' => lang('Autumn, winter and early spring')),
    'high' => array('title' => lang('High Season'), 'when' => lang('Late spring and summer')),
);

// A berth's rate per kind and season, per currency, with the words it is
// counted in. The rates page and the berth-kind pages show them as words;
// the berth request asks for a berth, it never charges.
$rates = array(
    'visitor'  => array('unit' => lang('%s a metre a night'),
                        'low' => array('USD' => 3, 'EUR' => 3, 'GBP' => 2, 'TRY' => 110),
                        'high' => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 185)),
    'seasonal' => array('unit' => lang('%s a metre for the season'),
                        'low' => array('USD' => 180, 'EUR' => 166, 'GBP' => 143, 'TRY' => 6650),
                        'high' => array('USD' => 240, 'EUR' => 222, 'GBP' => 190, 'TRY' => 8850)),
    'annual'   => array('unit' => lang('%s a metre a year'),
                        'low' => array('USD' => 320, 'EUR' => 296, 'GBP' => 254, 'TRY' => 11800),
                        'high' => array('USD' => 320, 'EUR' => 296, 'GBP' => 254, 'TRY' => 11800)),
    'mooring'  => array('unit' => lang('%s a night'),
                        'low' => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 550),
                        'high' => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 920)),
    'drystack' => array('unit' => lang('%s a month'),
                        'low' => array('USD' => 280, 'EUR' => 259, 'GBP' => 222, 'TRY' => 10300),
                        'high' => array('USD' => 380, 'EUR' => 351, 'GBP' => 302, 'TRY' => 14000)),
);

// What a stay or a berth may add to the rate (the rates page), per currency.
$extras = array(
    'power' => array('title' => lang('Shore power on a visitor berth'), 'unit' => lang('a day'), 'price' => array('USD' => 6, 'EUR' => 6, 'GBP' => 5, 'TRY' => 220)),
    'lift'  => array('title' => lang('Crane lift and pressure wash'), 'unit' => lang('a metre'), 'price' => array('USD' => 22, 'EUR' => 20, 'GBP' => 17, 'TRY' => 810)),
    'store' => array('title' => lang('Winter storage ashore'), 'unit' => lang('a metre a month'), 'price' => array('USD' => 14, 'EUR' => 13, 'GBP' => 11, 'TRY' => 520)),
    'pump'  => array('title' => lang('Holding tank pump-out'), 'unit' => lang('each time'), 'price' => array('USD' => 10, 'EUR' => 9, 'GBP' => 8, 'TRY' => 370)),
    'car'   => array('title' => lang('Extra car parking permit'), 'unit' => lang('a week'), 'price' => array('USD' => 12, 'EUR' => 11, 'GBP' => 10, 'TRY' => 440)),
);
// What every rate includes (the rates page).
$rate_includes = array(
    lang('Water on the pontoon'),
    lang('Hot showers and toilets ashore'),
    lang('Wi-Fi across the marina'),
    lang('Security and a harbour master day and night'),
);
// The terms the harbour office confirms with every berth.
$rate_note = lang('Rates are a guide for planning, counted on the boat\'s length overall. The harbour office confirms the rate when it allocates your berth.');

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A kind's rate in a season, as words.
$season_rate = function ($kind, $season) use ($rates, $money) {
    return sprintf($rates[$kind]['unit'], $money($rates[$kind][$season]));
};
// A kind's lowest rate, as words.
$from_rate = function ($kind) use ($rates, $money) {
    return sprintf(lang('From %s'), sprintf($rates[$kind]['unit'], $money($rates[$kind]['low'])));
};

// The types of charter boat, the boat list's filter (?type=).
$boat_types = array(
    'sail'      => array('icon' => 'bi-wind', 'title' => lang('Sailing Yacht')),
    'motor'     => array('icon' => 'bi-speedometer2', 'title' => lang('Motor Yacht')),
    'catamaran' => array('icon' => 'bi-water', 'title' => lang('Catamaran')),
    'day'       => array('icon' => 'bi-sun', 'title' => lang('Day Boat')),
    'rib'       => array('icon' => 'bi-lightning', 'title' => lang('RIB')),
);
$skipper_choices = array(lang('Skippered'), lang('Bareboat'), lang('Skippered or bareboat'));

// The sample charter boats: the records the Charter Boats form starts
// with. The boat list, the boat page and the home read them; the home shows
// the featured ones. 'length' (metres), 'guests' and 'cabins' are the
// numbers the lists sort on; 'price' is a day's charter, per currency.
$boats = array(
    'aurora' => array('name' => lang('Aurora'), 'type' => 'sail', 'skipper' => 2, 'length' => 12, 'guests' => 6, 'cabins' => 3,
        'price'       => array('USD' => 450, 'EUR' => 416, 'GBP' => 357, 'TRY' => 16600),
        'summary'     => lang('A well-kept 12-metre cruising yacht for a week in the islands or a day\'s sail in the bay.'),
        'description' => lang('Aurora is a comfortable cruising yacht with three double cabins, two heads and a cockpit with room for everyone at dinner. She sails well in a breeze and is easy to handle for a crew of two, with a skipper aboard if you prefer.'),
        'features'    => array(lang('Three double cabins'), lang('Two heads with showers'), lang('Bimini and cockpit table'), lang('Dinghy with outboard')),
        'featured'    => true),
    'meltem' => array('name' => lang('Meltem'), 'type' => 'sail', 'skipper' => 1, 'length' => 10, 'guests' => 4, 'cabins' => 2,
        'price'       => array('USD' => 320, 'EUR' => 296, 'GBP' => 254, 'TRY' => 11800),
        'summary'     => lang('A lively 10-metre yacht for a crew that knows how to sail and wants to go on its own.'),
        'description' => lang('Meltem is our smaller yacht for bareboat charter: two cabins, a bright saloon and a modern rig that rewards a crew with some experience. We ask for a sailing licence and brief the skipper before you leave.'),
        'features'    => array(lang('Two double cabins'), lang('Furling mainsail'), lang('Chart plotter and autopilot'), lang('Solar panel')),
        'featured'    => false),
    'lagoon' => array('name' => lang('Blue Lagoon'), 'type' => 'catamaran', 'skipper' => 2, 'length' => 14, 'guests' => 8, 'cabins' => 4,
        'price'       => array('USD' => 780, 'EUR' => 721, 'GBP' => 619, 'TRY' => 28800),
        'summary'     => lang('A roomy sailing catamaran for families and groups, stable at anchor and generous on deck.'),
        'description' => lang('Blue Lagoon has four cabins, each with its own head, a saloon level with the cockpit and a wide trampoline to lie on at anchor. She stays level under sail, which makes her the favourite of families with young children.'),
        'features'    => array(lang('Four cabins with their own heads'), lang('Trampoline and swim platform'), lang('Watermaker'), lang('Paddle boards on board')),
        'featured'    => true),
    'poseidon' => array('name' => lang('Poseidon'), 'type' => 'motor', 'skipper' => 0, 'length' => 18, 'guests' => 8, 'cabins' => 3,
        'price'       => array('USD' => 1850, 'EUR' => 1710, 'GBP' => 1467, 'TRY' => 68250),
        'summary'     => lang('A motor yacht with a crew of two for a day along the coast or a few nights in the bays.'),
        'description' => lang('Poseidon comes with her skipper and a deckhand who cooks. She reaches the far bays in an hour, has a flybridge for the view and a swim platform for long afternoons at anchor.'),
        'features'    => array(lang('Skipper and deckhand aboard'), lang('Flybridge with sun pads'), lang('Swim platform'), lang('Air conditioning')),
        'featured'    => true),
    'gull' => array('name' => lang('Gull'), 'type' => 'day', 'skipper' => 1, 'length' => 7, 'guests' => 6, 'cabins' => 0,
        'price'       => array('USD' => 220, 'EUR' => 203, 'GBP' => 174, 'TRY' => 8100),
        'summary'     => lang('An open day boat with a sunshade, for a swim and a picnic in the nearest bays.'),
        'description' => lang('Gull needs no licence for the inner bay: we show you the controls, hand you a map of the beaches and wave you off. She has a sunshade, a cool box and a ladder for swimming.'),
        'features'    => array(lang('No licence needed in the inner bay'), lang('Sunshade and cool box'), lang('Swim ladder'), lang('Map of the beaches')),
        'featured'    => false),
    'dart' => array('name' => lang('Dart'), 'type' => 'rib', 'skipper' => 0, 'length' => 8, 'guests' => 8, 'cabins' => 0,
        'price'       => array('USD' => 380, 'EUR' => 351, 'GBP' => 302, 'TRY' => 14000),
        'summary'     => lang('A fast rigid inflatable with a skipper, for a trip to the sea caves and the island beaches.'),
        'description' => lang('Dart is the quickest way to the sea caves and the beaches on the far side of the island. Her skipper knows every cove, and the seats are padded for the ride home.'),
        'features'    => array(lang('Skipper aboard'), lang('Sea caves and island beaches'), lang('Padded seats'), lang('Snorkels and masks')),
        'featured'    => false),
);
// A boat's features as the value of its Features field: one line per item.
$features_text = function ($key) use ($boats) {
    return implode("\n", $boats[$key]['features']);
};
// A boat's day rate as words, the value of its Price field.
$boat_price = function ($key) use ($boats, $money) {
    return sprintf(lang('From %s a day'), $money($boats[$key]['price']));
};
// The words of a boat's type and skipper choice, the values of its Type
// and Skipper fields.
$boat_type = function ($key) use ($boats, $boat_types) {
    return $boat_types[$boats[$key]['type']]['title'];
};
$boat_skipper = function ($key) use ($boats, $skipper_choices) {
    return $skipper_choices[$boats[$key]['skipper']];
};

// The harbour services (the services page and the home).
$services = array(
    array('icon' => 'bi-fuel-pump', 'title' => lang('Fuel dock'),
        'text' => lang('Diesel and petrol at the fuel pontoon by the entrance, with room for a large yacht alongside.'), 'when' => lang('Every day, 8:00 to 19:00')),
    array('icon' => 'bi-recycle', 'title' => lang('Pump-out and waste'),
        'text' => lang('A holding tank pump-out at the fuel dock and a sorted waste point for oil, batteries and recycling.'), 'when' => lang('Whenever the fuel dock is open')),
    array('icon' => 'bi-tools', 'title' => lang('Boatyard and crane'),
        'text' => lang('A 40-tonne travel lift, a crane for masts and engines, and a yard with engineers, riggers and painters.'), 'when' => lang('Weekdays, by appointment')),
    array('icon' => 'bi-shop', 'title' => lang('Chandlery'),
        'text' => lang('Ropes, fenders, paint, charts and spares, with parts ordered in by the next day.'), 'when' => lang('Every day in the season')),
    array('icon' => 'bi-wind', 'title' => lang('Sail loft'),
        'text' => lang('Sail repairs, new canvas work, sprayhoods and covers made to measure.'), 'when' => lang('Weekdays')),
    array('icon' => 'bi-truck', 'title' => lang('Provisioning'),
        'text' => lang('Order your shopping before you arrive and it waits on board when you step aboard.'), 'when' => lang('Order a day ahead')),
);

// The facilities ashore (the facilities page and the home).
$facilities = array(
    array('icon' => 'bi-droplet', 'title' => lang('Showers and toilets'),
        'text' => lang('Heated shower blocks at the head of each pontoon, open day and night with your berth key.')),
    array('icon' => 'bi-arrow-repeat', 'title' => lang('Laundry'),
        'text' => lang('Washing machines and dryers beside the harbour office.')),
    array('icon' => 'bi-wifi', 'title' => lang('Wi-Fi'),
        'text' => lang('Free Wi-Fi on every pontoon and in the café.')),
    array('icon' => 'bi-cup-hot', 'title' => lang('Café on the quay'),
        'text' => lang('Breakfast, fresh fish at lunch and a terrace over the boats.')),
    array('icon' => 'bi-basket', 'title' => lang('Provisions shop'),
        'text' => lang('Fresh bread, ice, drinks and the things you forgot on board.')),
    array('icon' => 'bi-p-square', 'title' => lang('Car park'),
        'text' => lang('Parking for berth holders and visiting crews, a short walk from the pontoons.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Security'),
        'text' => lang('Gated pontoons, cameras and a harbour master on watch day and night.')),
    array('icon' => 'bi-ev-station', 'title' => lang('Charging point'),
        'text' => lang('Charging for electric cars and for electric outboards at the dinghy pontoon.')),
);

// Arriving by sea (the arriving page). 'when' is words.
$approach = array(
    array('icon' => 'bi-broadcast', 'title' => lang('Call us on the radio'),
        'text' => lang('Call the harbour office on our radio channel when you are a mile out; we give you a berth and meet you at the reception pontoon.')),
    array('icon' => 'bi-signpost', 'title' => lang('The approach'),
        'text' => lang('Enter between the green light on the breakwater and the red buoy, keep to the marked channel and slow to three knots inside.')),
    array('icon' => 'bi-rulers', 'title' => lang('Depths'),
        'text' => lang('Three and a half metres in the entrance and on the outer pontoons, two and a half on the inner ones.')),
    array('icon' => 'bi-moon-stars', 'title' => lang('Arriving at night'),
        'text' => lang('The entrance is lit and the night watch answers the radio; tie up on the reception pontoon and we see you in the morning.')),
);

// How to get here by land (the arriving page).
$directions = array(
    array('icon' => 'bi-car-front', 'title' => lang('By car'),
        'text' => lang('Follow the signs for the harbour from the coast road; the marina car park is at the end of Harbour Road.')),
    array('icon' => 'bi-airplane', 'title' => lang('By air'),
        'text' => lang('The nearest airport is an hour away; the harbour office can book a transfer for your crew.')),
    array('icon' => 'bi-bus-front', 'title' => lang('By bus'),
        'text' => lang('The town bus stops at the harbour gate every half hour from the morning to the evening.')),
);

// The harbour rules (the rules page), each with its icon.
$rules = array(
    array('icon' => 'bi-speedometer', 'title' => lang('Speed in the harbour'),
        'text' => lang('Three knots inside the breakwater, and no wash near the pontoons.')),
    array('icon' => 'bi-broadcast', 'title' => lang('Arrival and departure'),
        'text' => lang('Call the harbour office before you enter and before you leave a visitor berth.')),
    array('icon' => 'bi-moon', 'title' => lang('Quiet hours'),
        'text' => lang('From 23:00 to 7:00 the pontoons are quiet: no music on deck and no generators running.')),
    array('icon' => 'bi-droplet-half', 'title' => lang('Clean water'),
        'text' => lang('Holding tanks only in the harbour; pump out at the fuel dock, never into the water.')),
    array('icon' => 'bi-fire', 'title' => lang('Fire safety'),
        'text' => lang('No barbecues on the pontoons; refuel with the engine off and nobody smoking.')),
    array('icon' => 'bi-person-check', 'title' => lang('Children and pets'),
        'text' => lang('Children wear a life jacket on the pontoons; dogs stay on a lead ashore.')),
);

// Why crews come back (the home and the about page).
$why_us = array(
    array('icon' => 'bi-shield-check', 'title' => lang('A safe harbour'),
        'text' => lang('A breakwater that holds in any wind and a harbour master on watch day and night.')),
    array('icon' => 'bi-tools', 'title' => lang('Everything on site'),
        'text' => lang('Fuel, a boatyard, a chandlery and a sail loft, all within a short walk.')),
    array('icon' => 'bi-people', 'title' => lang('Help on the pontoon'),
        'text' => lang('Our marina staff meet every boat and take your lines.')),
    array('icon' => 'bi-geo-alt', 'title' => lang('The town at the gate'),
        'text' => lang('Restaurants, markets and the old town five minutes\' walk away.')),
);

// The harbour in four figures (the home and the about page).
$figures = array(
    array('value' => '420', 'label' => lang('Berths on the pontoons')),
    array('value' => '60', 'label' => lang('Berths kept for visitors')),
    array('value' => '30 m', 'label' => lang('Largest boat we take')),
    array('value' => '40 t', 'label' => lang('Travel lift in the boatyard')),
);

// The pontoons behind the breakwater (the Pontoon Plan block on the home,
// the berths and the arriving page): the letter, what it is for, how many
// berths, their length, the largest boat, the depth alongside and what
// each berth has. The berths add up to the first figure above.
$pontoons = array(
    'A' => array('name' => lang('Pontoon A'), 'use' => lang('Visiting boats'), 'berths' => '60',
        'from' => '10', 'to' => '25', 'largest' => '25', 'depth' => '3.5', 'amenities' => array('power', 'water', 'wifi')),
    'B' => array('name' => lang('Pontoon B'), 'use' => lang('Seasonal and annual'), 'berths' => '200',
        'from' => '8', 'to' => '15', 'largest' => '15', 'depth' => '3', 'amenities' => array('power', 'water', 'wifi')),
    'C' => array('name' => lang('Pontoon C'), 'use' => lang('Large yachts'), 'berths' => '110',
        'from' => '15', 'to' => '30', 'largest' => '30', 'depth' => '3.5', 'amenities' => array('power', 'water', 'wifi')),
    'D' => array('name' => lang('Pontoon D'), 'use' => lang('Day boats and tenders'), 'berths' => '50',
        'from' => '5', 'to' => '9', 'largest' => '9', 'depth' => '2.5', 'amenities' => array('water')),
);
$pontoon_amenities = array(
    'power' => array('icon' => 'bi-plug', 'title' => lang('Shore power')),
    'water' => array('icon' => 'bi-droplet', 'title' => lang('Water')),
    'wifi'  => array('icon' => 'bi-wifi', 'title' => lang('Wi-Fi')),
);

// What crews say, each about a kind of berth.
$testimonials = array(
    array('kind' => 'visitor', 'name' => lang('Anna Lindqvist'), 'role' => lang('Stayed on the Visitor Berths'),
        'quote' => lang('Somebody was waiting on the pontoon to take our lines after a long day at sea. Clean showers, a good café and the town at the gate.')),
    array('kind' => 'annual', 'name' => lang('Murat Yıldız'), 'role' => lang('Keeps a boat on the Annual Berths'),
        'quote' => lang('Our boat has lived here for six years. The yard looks after her in winter and she is ready to sail when spring comes.')),
    array('kind' => 'drystack', 'name' => lang('Elif Demir'), 'role' => lang('Keeps a boat in the Dry Stack'),
        'quote' => lang('I call in the morning and the boat is in the water by the time I arrive. No antifouling, no cleaning, just the sea.')),
);

// The kinds of event (the event list's filter, ?kind=).
$event_kinds = array(lang('Regatta'), lang('Open Day'), lang('Sailing Course'), lang('Social Evening'), lang('Boat Show'));

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the skipper chose.
$kind_titles = array();
foreach ($kinds as $k) $kind_titles[] = $k['title'];
$season_titles = array();
foreach ($seasons as $s) $season_titles[] = $s['title'];
$type_titles = array();
foreach ($boat_types as $t) $type_titles[] = $t['title'];
$boat_titles = array();
foreach ($boats as $b) $boat_titles[] = $b['name'];
$request_kind_choices = array_merge($kind_titles, array(lang('Not sure yet')));
$vessel_choices = array(lang('A sailing yacht'), lang('A motor yacht'), lang('A catamaran'), lang('A motor boat'), lang('Another boat'));
$power_choices = array(lang('16 A shore power'), lang('32 A shore power'), lang('63 A shore power'), lang('No shore power'));
$experience_choices = array(lang('We want a skipper aboard'), lang('We hold a sailing or boating licence'), lang('Some experience, no licence yet'));
$charter_boat_choices = array_merge(array(lang('Any boat that suits us')), $boat_titles);
// The states of a berth request, set by the harbour office in the panel.
$request_statuses = array(lang('Request received'), lang('Berth allocated'), lang('Berth confirmed'), lang('Waiting list'), lang('Harbour full'));
// The states of a charter enquiry, set by the charter desk in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Offer sent'), lang('Charter confirmed'), lang('Boat not free'));
// The states of an event entry, set by the harbour office in the panel.
$entry_statuses = array(lang('Entry received'), lang('Entry confirmed'), lang('Waiting list'), lang('Entries closed'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the berth band above the footer unless the
// page is a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('berth_band');
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
            $heading('h1', $title, 'pmn-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pmn-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pmn-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the approach and why-us points are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'mn-faq') use ($el, $para, $attr, $searchable) {
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


// The harbour's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pmn-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the radio channel and the way to ask for a
// berth. A shared component.
$notice = function () use ($el, $icon, $span, $link, $harbour) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-broadcast', 'text-primary'),
        $span(sprintf(lang('Arriving by sea? Call us on %s'), $harbour['vhf']), '', lang('Text')),
        $link(lang('Request a Berth'), '{{page:berth_request}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The
// harbour sells nothing online, so the bar carries no cart.
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

// The header: the marina's name, the menu and the way to ask for a berth.
// The links to the current page are marked active on the server (smart
// active state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('berths', lang('Berths')),
        array('rates', lang('Rates')),
        array('services', lang('Services')),
        array('charters', lang('Charters')),
        array('events', lang('Events')),
        array('arriving', lang('Arriving')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-life-preserver', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pmn-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#mn-nav'),
                    $attr('aria-controls', 'mn-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-plus'),
                            $span(lang('Request a Berth'), '', lang('Link Text')),
                        ), array('href' => '{{page:berth_request}}')),
                    )),
                ), array('id' => 'mn-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the marina in a sentence, how to reach it
// and its profiles, the kinds of berth, the charter boats by type, the
// crews' pages and the harbour's pages; at the bottom the year, the site's
// name and the opening.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $kinds, $boat_types, $harbour, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $kind_links = array();
    foreach ($kinds as $key => $k) $kind_links[] = $link($k['title'], '{{page:berth_' . $key . '}}', $plain);
    $boat_links = array($link(lang('All Charter Boats'), '{{page:charters}}', $plain));
    foreach ($boat_types as $t) $boat_links[] = $link($t['title'], '{{page:charters}}?' . $query(array('type' => $t['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Marina'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-life-preserver', 'text-primary'),
                        $span('{{site_name}}', 'pmn-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($harbour['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($harbour['address'], '{{page:arriving}}', $plain), lang('Address')),
                        $contact_line('bi-broadcast', $span($harbour['vhf'], '', lang('Text')), lang('Radio Channel')),
                        $contact_line('bi-telephone', $link($harbour['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $harbour['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($harbour['email'], 'mailto:' . $harbour['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($harbour['hours'], '', lang('Text')), lang('Office Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Berth Links'), array(
                    $heading('h2', lang('Berths'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($kind_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Charter Links'), array(
                    $heading('h2', lang('Charters'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($boat_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Crews'), array(
                    $heading('h2', lang('For Crews'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Request a Berth'), '{{page:berth_request}}', $plain),
                        $link(lang('Charter Enquiry'), '{{page:charter_enquiry}}', $plain),
                        $link(lang('My Berth Requests'), '{{page:my_requests}}', $plain),
                        $link(lang('Rates'), '{{page:rates}}', $plain),
                        $link(lang('Harbour Rules'), '{{page:rules}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Marina'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Services'), '{{page:services}}', $plain),
                        $link(lang('Facilities'), '{{page:facilities}}', $plain),
                        $link(lang('Events'), '{{page:events}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($harbour['opening'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The berth band: a full-width band in the palette's colour with the ways
// to ask for a berth and to see the rates. Shared, so every page that ends
// with it carries the same call.
$berth_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Planning to come alongside?'), 'pmn-display h2 mb-2'),
                    $para(lang('Tell us your boat and your dates, and the harbour office will find you a berth and confirm it within a day.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Request a Berth'), '{{page:berth_request}}', 'btn btn-light btn-lg rounded-pill px-4'),
                        $link(lang('See the Rates'), '{{page:rates}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The facts of a kind of berth in one list: the boats it takes and its
// lowest rate.
$kind_facts = function ($key, $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $kinds, $from_rate) {
    $fact = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name),
        ));
    };
    return $el('ul', $class, lang('Berth Facts'), array(
        $fact('bi-rulers', $kinds[$key]['size'], lang('Boat Size')),
        $fact('bi-tag', $from_rate($key), lang('Rate')),
    ));
};

// A kind of berth's card (the home and the berths page): the picture, the
// rate, the title linking the kind's page and the summary.
$kind_card = function ($key) use ($el, $para, $span, $link, $image, $col, $photo, $attr, $kinds, $from_rate) {
    $k = $kinds[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pmn-lift', lang('Berth Card'), array(
            $el('span', 'd-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('berth-' . $key, 800, 600), $k['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($from_rate($key), 'pmn-price small fw-semibold text-primary mb-1', lang('Rate')),
                $el('h3', 'pmn-display h5 position-static mb-2', lang('Berth Name'), array(
                    $link($k['title'], '{{page:berth_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($k['summary'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// The facts of a charter boat in one list: type, length, guests and
// skipper, each bound to the Charter Boats form's field of the same name.
$boat_facts = function ($sample_key = 'aurora', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $boats, $boat_type, $boat_skipper) {
    $b = $boats[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    $number = function ($icon_name, $label, $value, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($label, '', lang('Label')),
            $span((string)$value, '', lang('Value'), $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Boat Facts'), array(
        $fact('bi-tag', $boat_type($sample_key), 'type', lang('Type')),
        $number('bi-rulers', lang('Length in metres'), $b['length'], 'length', lang('Length')),
        $number('bi-people', lang('Guests'), $b['guests'], 'guests', lang('Guests')),
        $fact('bi-person-badge', $boat_skipper($sample_key), 'skipper', lang('Skipper')),
    ));
};

// A charter boat's card of the lists (the home and the boat list): the
// photo, the type, the name linking the boat page, the guests, the price
// and the summary. Every value is bound to the Charter Boats form's field
// of the same name; the sample fills the canvas.
$boat_card = function ($sample_key = 'aurora') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $boats, $boat_type, $boat_price, $photo, $attr) {
    $b = $boats[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pmn-lift', lang('Boat Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('boat-' . $sample_key, 800, 600), $b['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($boat_type($sample_key), 'pmn-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Type'), $bind('type')),
                $el('h3', 'pmn-display h5 position-static mb-2', lang('Boat Name'), array(
                    $link($b['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-center gap-1 small mb-1', lang('Guests'), array(
                    $icon('bi-people', 'text-primary'),
                    $span(lang('Guests'), '', lang('Label')),
                    $span((string)$b['guests'], '', lang('Value'), $bind('guests')),
                )),
                $para($b['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $span($boat_price($sample_key), 'pmn-price mt-auto fw-semibold', lang('Price'), $bind('price')),
            )),
        )),
    ), '6', '4');
};

// A service, a facility, a rule or a reason: an icon tile, the title, the
// words and, for a service, when it is open.
$feature_card = function ($item, $md = '6', $lg = '3') use ($el, $heading, $para, $span, $col, $icon_tile) {
    $children = array(
        $icon_tile($item['icon'], 'mb-3'),
        $heading('h3', $item['title'], 'pmn-display h5 mb-2'),
        $para($item['text'], 'small text-body-secondary mb-0'),
    );
    if (!empty($item['when'])) $children[] = $span($item['when'], 'd-block small fw-semibold text-primary mt-2', lang('When'));
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', lang('Feature'), $children),
    ), $md, $lg);
};

// What a crew says: the quote, the person and the kind of berth.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $icon, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Crew Quote'), array(
            $icon('bi-quote', 'fs-2 text-primary lh-1 mb-2'),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pmn-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Crew Details'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// The rates as a table: a row per kind of berth (or the one kind given), a
// column per season. Each season's head opens the berth request with the
// season chosen (?season=), each kind's name opens its page.
$rate_table = function ($only = '') use ($el, $link, $span, $attr, $kinds, $seasons, $season_rate, $query) {
    $head = array($el('th', '', lang('Table Header'), array(), array('text' => lang('Berth'), '_attrs' => array($attr('scope', 'col')))));
    foreach ($seasons as $s) {
        $head[] = $el('th', 'text-end', lang('Table Header'), array(
            $link($s['title'], '{{page:berth_request}}?' . $query(array('season' => $s['title'])), 'link-body-emphasis'),
            $span($s['when'], 'd-block small fw-normal text-body-secondary', lang('When')),
        ), array('_attrs' => array($attr('scope', 'col'))));
    }
    $rows = array();
    foreach ($kinds as $key => $k) {
        if ($only !== '' && $only !== $key) continue;
        $cells = array($el('th', 'fw-semibold', lang('Table Header'), array(
            $link($k['title'], '{{page:berth_' . $key . '}}', 'link-body-emphasis text-decoration-none'),
        ), array('_attrs' => array($attr('scope', 'row')))));
        foreach (array_keys($seasons) as $season) {
            $cells[] = $el('td', 'pmn-price text-end text-nowrap', lang('Table Cell'), array(), array('text' => $season_rate($key, $season)));
        }
        $rows[] = $el('tr', '', lang('Table Row'), $cells);
    }
    return $el('div', 'table-responsive', lang('Rate Table'), array(
        $el('table', 'table align-middle mb-0', lang('Table'), array(
            $el('thead', '', lang('Table Head'), array($el('tr', '', lang('Table Row'), $head))),
            $el('tbody', '', lang('Table Body'), $rows),
        )),
    ));
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
                $item('staff_requests', lang('Berth Requests')),
                $item('staff_enquiries', lang('Charter Enquiries')),
                $item('staff_entries', lang('Event Entries')),
                $item('boat_new', lang('New Charter Boat')),
                $item('event_new', lang('New Event')),
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
    return $agree($id, 'consent', lang('I would like to receive the marina\'s news and event dates by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A berth
// request may bring a boat's papers or an insurance certificate, so the default accepts
// PDFs, office files, text and pictures; one that takes only pictures is a
// collection's image kind (a charter boat's photo).
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
                    $link(lang('Our favourite anchorages a day\'s sail from the harbour'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
