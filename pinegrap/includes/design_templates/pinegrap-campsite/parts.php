<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Campsite", part 1 of its files: the node builders, the
 * park's own data (the kinds of stay, the sample pitches and cabins, the
 * facilities, the activities, the area around, the seasons and their
 * prices, the way to get here, the park rules, the figures, what guests
 * say and the answers of the forms' pick lists) and the parts every page
 * repeats (account bar, header, footer, stay band, stay-kind, unit,
 * facility and guest cards, the season price table, page shells, section
 * headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-campsite.php puts them together.
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

// A node with an interaction (props._fx, played by pg_fx; reduced motion
// is honoured there).
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// A list or a row whose items rise in one after another on view.
$rise = function ($node, $stagger = 80) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger);
    return $node;
};

// A sample picture with a fixed seed, so the same picture comes back on
// every opening until the park puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-campsite-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/dcfce7/166534?text=' . rawurlencode($initials !== '' ? $initials : 'C');
};

// ── The park: its places to stay, its seasons and what there is to do ───

// The park itself. The about page tells its story; the footer, the stay
// band, the location page and the contact lines use the address, the
// coordinates, the phone and the reception hours.
$park = array(
    'tagline'     => lang('A family-run campsite between the lake and the forest, with room to pitch, park or simply sleep under wood'),
    'story'       => lang('The campsite began as a farmer\'s meadow where friends were allowed to pitch a tent by the lake. The family still runs it: the meadow has become pitches with hook-ups, the old barn holds the shop and the café, and a row of wooden cabins stands at the forest edge, but the evenings still end by a campfire.'),
    'address'     => lang('Lakeside Road 4, Pine Valley'),
    'coordinates' => '40.7128, 29.9214',
    'phone'       => '+90 555 000 00 00',
    'email'       => 'stay@example.com',
    'hours'       => lang('Reception: every day in the season, 8:00 to 20:00'),
    'opening'     => lang('Open from spring to late autumn; cabins all year round'),
    'arrival'     => lang('Arrival from 14:00, departure by 11:00'),
);

// The kinds of stay. Each has its own page (stay_<key>); the stay request
// opens with the kind chosen (?kind=) in the kind's own words, and the
// unit list filters on it.
$kinds = array(
    'tent' => array('icon' => 'bi-triangle', 'title' => lang('Tent Pitches'),
        'sleeps'  => lang('Room for a tent, a car and up to six people'),
        'summary' => lang('Grass pitches in the meadow and by the lake, with or without electricity.'),
        'text'    => lang('Our tent pitches are wide and flat, marked by hedges and young trees so every family has its own corner. Choose a quiet spot at the forest edge, a sunny one in the meadow or one a few steps from the water.'),
        'points'  => array(lang('Pitches of 80 to 120 m²'), lang('Electric hook-up on most pitches'), lang('Car parked beside the tent'), lang('Washing block within two minutes\' walk')),
        'bring'   => array(lang('Your tent and sleeping bags'), lang('A camping stove')),
        'ready'   => array(lang('Electric hook-up on most pitches'), lang('Washing block within two minutes\' walk'))),
    'touring' => array('icon' => 'bi-truck', 'title' => lang('Touring Pitches'),
        'sleeps'  => lang('For a caravan or a motorhome and up to six people'),
        'summary' => lang('Level hardstanding and grass pitches for caravans, motorhomes and campervans.'),
        'text'    => lang('Touring pitches have a level base, an electric hook-up and a water tap close by. Motorhomes empty and fill at the service point by the gate, and long outfits find pitches that need no reversing.'),
        'points'  => array(lang('Hardstanding or grass, all level'), lang('Electric hook-up on every pitch'), lang('Fresh water and waste point'), lang('Pull-through pitches for long outfits')),
        'bring'   => array(lang('Your caravan or motorhome')),
        'ready'   => array(lang('Electric hook-up on every pitch'), lang('Fresh water and waste point'))),
    'glamping' => array('icon' => 'bi-stars', 'title' => lang('Glamping Tents'),
        'sleeps'  => lang('Sleeps two to five, beds made'),
        'summary' => lang('Bell tents and safari tents with real beds, a wood stove and a deck under the trees.'),
        'text'    => lang('For camping without the tent pegs: our glamping tents stand on wooden decks, with proper beds, bed linen, a wood stove for cool nights, lanterns and a picnic table. Some have their own kitchen and shower.'),
        'points'  => array(lang('Real beds with linen'), lang('Wood stove and lanterns'), lang('A deck with a table and chairs'), lang('Fire pit beside the tent')),
        'bring'   => array(lang('Towels and a torch')),
        'ready'   => array(lang('Real beds with linen'), lang('Wood stove and lanterns'))),
    'cabin' => array('icon' => 'bi-house', 'title' => lang('Cabins and Lodges'),
        'sleeps'  => lang('Sleeps two to six, open all year'),
        'summary' => lang('Wooden cabins with a kitchen, a shower and a terrace looking over the lake or into the forest.'),
        'text'    => lang('Our cabins are built of local pine. Each has a kitchen, a shower room, heating for the cold months and a terrace with a barbecue. They are the way to stay with us out of season, when the lake is quiet and the forest turns gold.'),
        'points'  => array(lang('Kitchen, shower and heating'), lang('Terrace with a barbecue'), lang('Bed linen and towels included'), lang('Open all year round')),
        'bring'   => array(lang('Only yourselves')),
        'ready'   => array(lang('Kitchen, shower and heating'), lang('Bed linen and towels included'))),
);

// The settings of a pitch or a cabin, the unit list's second filter.
$settings = array(
    'lake'   => array('icon' => 'bi-water', 'title' => lang('By the lake')),
    'forest' => array('icon' => 'bi-tree', 'title' => lang('At the forest edge')),
    'meadow' => array('icon' => 'bi-flower2', 'title' => lang('In the meadow')),
);
$hookup_choices = array(lang('Electric hook-up'), lang('No hook-up'));

// The sample pitches and cabins: the records the Pitches and Cabins form
// starts with. The unit list, the unit page and the home read them; the
// home shows the featured ones. 'sleeps' and 'size' (m²) are the numbers
// the lists sort on.
$units = array(
    'meadow' => array('name' => lang('Meadow Pitch'), 'kind' => 'tent', 'setting' => 'meadow', 'hookup' => true, 'sleeps' => 6, 'size' => 100,
        'summary'     => lang('A sunny grass pitch in the open meadow, close to the playground and the washing block.'),
        'description' => lang('The meadow pitches are the family favourites: open grass, sun from morning to evening, the playground in sight and the washing block a short walk away. There is room for a large tent, a gazebo and the car.'),
        'features'    => array(lang('Electric hook-up'), lang('Sun all day'), lang('Playground in sight'), lang('Car beside the tent')),
        'featured'    => false),
    'lakeside' => array('name' => lang('Lakeside Pitch'), 'kind' => 'tent', 'setting' => 'lake', 'hookup' => false, 'sleeps' => 4, 'size' => 80,
        'summary'     => lang('A quiet grass pitch a few steps from the water, for tents only and no electricity.'),
        'description' => lang('The lakeside pitches sit on a strip of grass along the shore, kept for tents only so the evenings stay quiet. There is no electricity here, only the sound of the water and a view of the sunset over the lake.'),
        'features'    => array(lang('A few steps from the lake'), lang('Tents only'), lang('Sunset view'), lang('Car park nearby')),
        'featured'    => true),
    'hardstanding' => array('name' => lang('Hardstanding Touring Pitch'), 'kind' => 'touring', 'setting' => 'meadow', 'hookup' => true, 'sleeps' => 6, 'size' => 110,
        'summary'     => lang('A level gravel pitch for a caravan or a motorhome, with a hook-up and water close by.'),
        'description' => lang('The hardstanding pitches have a level gravel base that stays firm after rain, a grass strip for the awning, an electric hook-up and a water tap shared with the next pitch. The motorhome service point is on the way out.'),
        'features'    => array(lang('Level gravel base'), lang('Electric hook-up'), lang('Water tap close by'), lang('Grass strip for the awning')),
        'featured'    => true),
    'pullthrough' => array('name' => lang('Pull-Through Touring Pitch'), 'kind' => 'touring', 'setting' => 'forest', 'hookup' => true, 'sleeps' => 6, 'size' => 140,
        'summary'     => lang('A long pitch under the pines that a large motorhome or a car and caravan drive straight onto.'),
        'description' => lang('The pull-through pitches at the forest edge are long enough for a large motorhome or a car with a caravan, so there is no reversing. The pines give shade in the afternoon and shelter on windy days.'),
        'features'    => array(lang('No reversing needed'), lang('Shade in the afternoon'), lang('Electric hook-up'), lang('For outfits up to 12 metres')),
        'featured'    => false),
    'bell' => array('name' => lang('Bell Tent Fox'), 'kind' => 'glamping', 'setting' => 'forest', 'hookup' => true, 'sleeps' => 4, 'size' => 20,
        'summary'     => lang('A cotton bell tent on a deck at the forest edge, with a double bed, two singles and a wood stove.'),
        'description' => lang('Fox stands on its own deck where the meadow meets the forest. Inside there is a double bed, two single beds, rugs, lanterns and a small wood stove; outside a picnic table, a fire pit and a cool box for the evening.'),
        'features'    => array(lang('Double bed and two singles'), lang('Wood stove'), lang('Private deck and fire pit'), lang('Bed linen included')),
        'featured'    => true),
    'safari' => array('name' => lang('Safari Tent Heron'), 'kind' => 'glamping', 'setting' => 'lake', 'hookup' => true, 'sleeps' => 5, 'size' => 35,
        'summary'     => lang('A large safari tent by the lake with two bedrooms, its own kitchen and a shower.'),
        'description' => lang('Heron is our largest tent: two bedrooms, a kitchen with a fridge and a hob, a shower room and a covered veranda that looks over the lake. It is camping with all the comfort of a cabin.'),
        'features'    => array(lang('Two bedrooms'), lang('Kitchen and shower'), lang('Covered veranda over the lake'), lang('Bed linen and towels included')),
        'featured'    => false),
    'pine' => array('name' => lang('Pine Cabin'), 'kind' => 'cabin', 'setting' => 'forest', 'hookup' => true, 'sleeps' => 4, 'size' => 32,
        'summary'     => lang('A wooden cabin for four in the forest, with a kitchen, a shower and a terrace with a barbecue.'),
        'description' => lang('The Pine Cabin has a bedroom with a double bed, a second room with bunk beds, a kitchen with a living corner and a shower room. Its terrace faces the forest, with a barbecue and a table for long dinners outside.'),
        'features'    => array(lang('Double room and bunk room'), lang('Kitchen and shower room'), lang('Terrace with a barbecue'), lang('Heating for the cold months')),
        'featured'    => true),
    'lodge' => array('name' => lang('Lake Lodge'), 'kind' => 'cabin', 'setting' => 'lake', 'hookup' => true, 'sleeps' => 6, 'size' => 48,
        'summary'     => lang('Our largest cabin: three bedrooms, a wood burner and a wide terrace over the lake.'),
        'description' => lang('The Lake Lodge sleeps six in three bedrooms, with a living room around a wood burner, a full kitchen and two shower rooms. The wide terrace looks straight over the lake, and a rowing boat waits at its own jetty.'),
        'features'    => array(lang('Three bedrooms'), lang('Wood burner'), lang('Terrace over the lake'), lang('Rowing boat at the jetty')),
        'featured'    => false),
);
// A unit's features as the value of its Features field: one line per item.
$features_text = function ($key) use ($units) {
    return implode("\n", $units[$key]['features']);
};

// The seasons. Prices change with them; the dates are words, since the
// park sets the exact dates each year and writes them on the prices page.
$seasons = array(
    'low'  => array('title' => lang('Low Season'), 'when' => lang('Spring and late autumn, outside the school holidays')),
    'mid'  => array('title' => lang('Mid Season'), 'when' => lang('Early summer and early autumn')),
    'high' => array('title' => lang('High Season'), 'when' => lang('The summer school holidays and public holidays')),
);

// A night's price per kind of stay and season, per currency: a pitch with
// two people and a car, a glamping tent or a cabin as a whole. The prices
// page, the stay-kind pages and the unit pages show them as words; the
// stay request asks for an offer, it never charges.
$season_prices = array(
    'tent'     => array('low' => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 900),
                        'mid' => array('USD' => 32, 'EUR' => 30, 'GBP' => 26, 'TRY' => 1200),
                        'high' => array('USD' => 42, 'EUR' => 39, 'GBP' => 34, 'TRY' => 1550)),
    'touring'  => array('low' => array('USD' => 30, 'EUR' => 28, 'GBP' => 24, 'TRY' => 1100),
                        'mid' => array('USD' => 38, 'EUR' => 35, 'GBP' => 30, 'TRY' => 1400),
                        'high' => array('USD' => 50, 'EUR' => 46, 'GBP' => 40, 'TRY' => 1850)),
    'glamping' => array('low' => array('USD' => 85, 'EUR' => 79, 'GBP' => 68, 'TRY' => 3150),
                        'mid' => array('USD' => 110, 'EUR' => 102, 'GBP' => 88, 'TRY' => 4050),
                        'high' => array('USD' => 145, 'EUR' => 134, 'GBP' => 116, 'TRY' => 5350)),
    'cabin'    => array('low' => array('USD' => 110, 'EUR' => 102, 'GBP' => 88, 'TRY' => 4050),
                        'mid' => array('USD' => 140, 'EUR' => 130, 'GBP' => 112, 'TRY' => 5200),
                        'high' => array('USD' => 185, 'EUR' => 171, 'GBP' => 148, 'TRY' => 6850)),
);

// What a stay may add to the night's price (the prices page), per currency.
$extras = array(
    'adult' => array('title' => lang('Extra adult on a pitch'), 'unit' => lang('a night'), 'price' => array('USD' => 8, 'EUR' => 7, 'GBP' => 6, 'TRY' => 300)),
    'child' => array('title' => lang('Extra child aged 3 to 12'), 'unit' => lang('a night'), 'price' => array('USD' => 4, 'EUR' => 4, 'GBP' => 3, 'TRY' => 150)),
    'dog'   => array('title' => lang('Dog'), 'unit' => lang('a night'), 'price' => array('USD' => 3, 'EUR' => 3, 'GBP' => 3, 'TRY' => 100)),
    'car'   => array('title' => lang('Second car'), 'unit' => lang('a night'), 'price' => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 180)),
    'wood'  => array('title' => lang('Bag of firewood'), 'unit' => lang('a bag'), 'price' => array('USD' => 7, 'EUR' => 6, 'GBP' => 5, 'TRY' => 250)),
);
// What every price includes (the prices page).
$price_includes = array(
    lang('Hot showers and the washing blocks'),
    lang('Wi-Fi around the reception and the café'),
    lang('The swimming lake and the playgrounds'),
    lang('Parking for one car'),
);
// The terms the reception confirms with every offer.
$price_note = lang('Prices are a guide for planning. We confirm the price of your stay with our offer, after we have found you a place.');

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A night's price of a kind in a season, as words.
$season_price = function ($kind, $season) use ($season_prices, $money) {
    return sprintf(lang('%s a night'), $money($season_prices[$kind][$season]));
};
// A kind's lowest price, the value of a unit's Price field.
$from_price = function ($kind) use ($season_prices, $money) {
    return sprintf(lang('From %s a night'), $money($season_prices[$kind]['low']));
};

// The facilities of the park (the facilities page and the home).
$facilities = array(
    array('icon' => 'bi-droplet', 'title' => lang('Washing blocks'),
        'text' => lang('Two heated blocks with free hot showers, family rooms and a baby room.')),
    array('icon' => 'bi-basket', 'title' => lang('Camp shop'),
        'text' => lang('Fresh bread every morning, local cheese, camping gas and the things you forgot.')),
    array('icon' => 'bi-cup-hot', 'title' => lang('Café in the old barn'),
        'text' => lang('Breakfast, pizza from the wood oven and a terrace over the meadow.')),
    array('icon' => 'bi-water', 'title' => lang('Swimming lake'),
        'text' => lang('A sandy beach, a shallow corner for children and a jetty to jump from.')),
    array('icon' => 'bi-emoji-smile', 'title' => lang('Playgrounds'),
        'text' => lang('A wooden adventure playground and a corner for the smallest children.')),
    array('icon' => 'bi-wifi', 'title' => lang('Wi-Fi'),
        'text' => lang('Free Wi-Fi around the reception, the café and the barn.')),
    array('icon' => 'bi-arrow-repeat', 'title' => lang('Laundry'),
        'text' => lang('Washing machines, dryers and a sheltered line for wet clothes.')),
    array('icon' => 'bi-ev-station', 'title' => lang('Motorhome services'),
        'text' => lang('Fresh water, waste disposal and a charging point for electric cars.')),
);

// What there is to do (the activities page and the home). 'when' is words.
$activities = array(
    array('icon' => 'bi-life-preserver', 'title' => lang('Canoes and paddle boards'),
        'text' => lang('Hire a canoe or a board at the jetty and explore the lake\'s quiet bays.'), 'when' => lang('Every day in the season')),
    array('icon' => 'bi-signpost-split', 'title' => lang('Forest walks'),
        'text' => lang('Marked trails start at the gate, from a short loop to a day on the ridge.'), 'when' => lang('All year round')),
    array('icon' => 'bi-bicycle', 'title' => lang('Bike hire'),
        'text' => lang('Bikes for all ages and a trailer for the youngest, with maps of the lakeside paths.'), 'when' => lang('Every day in the season')),
    array('icon' => 'bi-fire', 'title' => lang('Campfire evenings'),
        'text' => lang('Stories, songs and marshmallows around the big fire by the barn.'), 'when' => lang('Every Friday and Saturday evening')),
    array('icon' => 'bi-binoculars', 'title' => lang('Nature club for children'),
        'text' => lang('Bug hunts, den building and bird watching with our ranger.'), 'when' => lang('Weekday mornings in the school holidays')),
    array('icon' => 'bi-moon-stars', 'title' => lang('Stargazing'),
        'text' => lang('Far from city lights, the sky over the lake is full of stars; we lend telescopes.'), 'when' => lang('Clear evenings on request')),
);

// The area around the park (the activities page and the location page).
$area = array(
    array('icon' => 'bi-shop', 'title' => lang('The village'), 'distance' => '2 km',
        'text' => lang('A bakery, a weekly market and a few good restaurants.')),
    array('icon' => 'bi-bank', 'title' => lang('The old castle'), 'distance' => '12 km',
        'text' => lang('Ruins on a hill with a view over the whole valley.')),
    array('icon' => 'bi-tree', 'title' => lang('The national park'), 'distance' => '20 km',
        'text' => lang('Waterfalls, gorges and long trails through old forest.')),
    array('icon' => 'bi-buildings', 'title' => lang('The town'), 'distance' => '35 km',
        'text' => lang('Museums, shops, a railway station and a hospital.')),
);

// How to get here (the location page).
$directions = array(
    array('icon' => 'bi-car-front', 'title' => lang('By car'),
        'text' => lang('Leave the motorway at the Pine Valley exit and follow the brown campsite signs for 8 km along the lake.')),
    array('icon' => 'bi-train-front', 'title' => lang('By train'),
        'text' => lang('The nearest station is in the town; the reception can book a taxi to meet your train.')),
    array('icon' => 'bi-bus-front', 'title' => lang('By bus'),
        'text' => lang('The lake bus stops at the campsite gate several times a day in the season.')),
);

// The park rules (the rules page), each with its icon.
$rules = array(
    array('icon' => 'bi-moon', 'title' => lang('Quiet hours'),
        'text' => lang('From 22:00 to 7:00 the park is quiet: no music, no cars driving in or out.')),
    array('icon' => 'bi-clock', 'title' => lang('Arrival and departure'),
        'text' => lang('Arrival from 14:00, departure by 11:00. Ask the reception for a late departure.')),
    array('icon' => 'bi-heart', 'title' => lang('Dogs welcome'),
        'text' => lang('Dogs stay on a lead in the park and have their own meadow to run free.')),
    array('icon' => 'bi-fire', 'title' => lang('Fires and barbecues'),
        'text' => lang('Fires only in the fire pits and the bowls we lend; barbecues stand off the grass.')),
    array('icon' => 'bi-car-front', 'title' => lang('Cars'),
        'text' => lang('Walking pace on the park roads; a second car parks at the gate.')),
    array('icon' => 'bi-recycle', 'title' => lang('Waste'),
        'text' => lang('Sort your waste at the recycling point by the shop.')),
);

// Why guests come back (the home and the about page).
$why_us = array(
    array('icon' => 'bi-arrows-angle-expand', 'title' => lang('Room to breathe'),
        'text' => lang('Large pitches, hedges between them and no more than a hundred and twenty in eight hectares.')),
    array('icon' => 'bi-water', 'title' => lang('A lake to swim in'),
        'text' => lang('Clean water, a sandy beach and canoes at the jetty, all inside the park.')),
    array('icon' => 'bi-people', 'title' => lang('Run by a family'),
        'text' => lang('We live on the site and know our guests by name.')),
    array('icon' => 'bi-tree', 'title' => lang('Nature at the gate'),
        'text' => lang('Forest trails, birds on the lake and a sky full of stars.')),
);

// The park in four figures (the home and the about page).
$figures = array(
    array('value' => '120', 'label' => lang('Pitches for tents, caravans and motorhomes')),
    array('value' => '14', 'label' => lang('Glamping tents and cabins')),
    array('value' => '8 ha', 'label' => lang('Of meadow and forest')),
    array('value' => '1 km', 'label' => lang('Of lake shore')),
);

// What guests say, each about a kind of stay.
$testimonials = array(
    array('kind' => 'tent', 'name' => lang('Zeynep Arslan'), 'role' => lang('Stayed on the Tent Pitches'),
        'quote' => lang('Our children swam all day and fell asleep by the campfire. A big, quiet pitch and a family that made us feel at home.')),
    array('kind' => 'touring', 'name' => lang('Peter Hansen'), 'role' => lang('Stayed on the Touring Pitches'),
        'quote' => lang('Level pitches, a hook-up that works and a service point you can reach without a three-point turn. We will be back with the motorhome.')),
    array('kind' => 'cabin', 'name' => lang('Selin Koç'), 'role' => lang('Stayed in the Cabins and Lodges'),
        'quote' => lang('We came in late autumn: the lake was still, the forest gold and the cabin warm. The best weekend of the year.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the guest chose.
$kind_titles = array();
foreach ($kinds as $k) $kind_titles[] = $k['title'];
$setting_titles = array();
foreach ($settings as $s) $setting_titles[] = $s['title'];
$unit_titles = array();
foreach ($units as $u) $unit_titles[] = $u['name'];
$season_titles = array();
foreach ($seasons as $s) $season_titles[] = $s['title'];
$request_kind_choices = array_merge($kind_titles, array(lang('Not sure yet')));
$request_unit_choices = array_merge(array(lang('Any free place of the kind I chose')), $unit_titles);
$outfit_choices = array(lang('A small tent'), lang('A large family tent'), lang('A caravan'), lang('A motorhome'), lang('A campervan'), lang('No outfit: a glamping tent or a cabin'));
$pet_choices = array(lang('No pets'), lang('One dog'), lang('Two dogs'));
// The states of a stay request, set by the reception in the panel.
$request_statuses = array(lang('Request received'), lang('Offer sent'), lang('Stay confirmed'), lang('Waiting list'), lang('Fully booked'));
$group_kinds = array(lang('A school or youth group'), lang('A club or society'), lang('A caravan rally'), lang('A family celebration'), lang('Another group'));
$group_sizes = array(lang('10 to 20 people'), lang('20 to 50 people'), lang('More than 50 people'));
// The states of a group enquiry, set by the reception in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Offer sent'), lang('Group confirmed'), lang('Not possible this time'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the stay band above the footer unless the
// page is a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('stay_band');
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
            $heading('h1', $title, 'pcp-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pcp-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pcp-display h3 mb-0'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'cp-faq') use ($el, $para, $attr, $searchable) {
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


// The park's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pcp-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the season is open and the way to ask for
// a place. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-megaphone', 'text-primary'),
        $span(lang('Stay requests are open for the coming season'), '', lang('Text')),
        $link(lang('Request a Stay'), '{{page:book}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The park
// sells nothing online, so the bar carries no cart.
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

// The header: the park's name, the menu and the way to ask for a stay. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('stays', lang('Places to Stay')),
        array('units', lang('Pitches and Cabins')),
        array('facilities', lang('Facilities')),
        array('activities', lang('Activities')),
        array('prices', lang('Prices')),
        array('location', lang('Getting Here')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-tree', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pcp-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#cp-nav'),
                    $attr('aria-controls', 'cp-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav pcp-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-plus'),
                            $span(lang('Request a Stay'), '', lang('Link Text')),
                        ), array('href' => '{{page:book}}')),
                    )),
                ), array('id' => 'cp-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the park in a sentence, how to reach it and
// its profiles, the kinds of stay, the pitches and cabins by kind, the
// guests' pages and the park's pages; at the bottom the year, the site's
// name and the opening season.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $kinds, $park, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $kind_links = array();
    $unit_links = array($link(lang('All Pitches and Cabins'), '{{page:units}}', $plain));
    foreach ($kinds as $key => $k) {
        $kind_links[] = $link($k['title'], '{{page:stay_' . $key . '}}', $plain);
        $unit_links[] = $link($k['title'], '{{page:units}}?' . $query(array('kind' => $k['title'])), $plain);
    }
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Campsite'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-tree', 'text-primary'),
                        $span('{{site_name}}', 'pcp-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($park['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($park['address'], '{{page:location}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($park['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $park['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($park['email'], 'mailto:' . $park['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($park['hours'], '', lang('Text')), lang('Reception Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Stay Links'), array(
                    $heading('h2', lang('Places to Stay'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($kind_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Unit Links'), array(
                    $heading('h2', lang('Pitches and Cabins'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($unit_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Guests'), array(
                    $heading('h2', lang('For Guests'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Request a Stay'), '{{page:book}}', $plain),
                        $link(lang('Group Stays'), '{{page:groups}}', $plain),
                        $link(lang('My Stay Requests'), '{{page:my_requests}}', $plain),
                        $link(lang('Prices'), '{{page:prices}}', $plain),
                        $link(lang('Park Rules'), '{{page:rules}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Campsite'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Facilities'), '{{page:facilities}}', $plain),
                        $link(lang('Activities'), '{{page:activities}}', $plain),
                        $link(lang('Getting Here'), '{{page:location}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($park['opening'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The stay band: a full-width band in the palette's colour with the ways
// to ask for a stay and to see the prices. Shared, so every page that
// ends with it carries the same call.
$stay_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Ready for a few nights outdoors?'), 'pcp-display h2 mb-2'),
                    $para(lang('Tell us your dates and how you camp, and the reception will send you an offer within a day.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Request a Stay'), '{{page:book}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('See the Prices'), '{{page:prices}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// ── The Campsite blocks ─────────────────────────────────────────────────

// The Campsite: Hero block (pg-campsite-lift, pg-campsite-kinds): one wide
// picture, then a card lying over its lower edge from md up with the
// eyebrow, the page's only h1, a line, the stay request and the prices with
// the arrival times and the season beside them, and a way into each kind
// of stay (its page). The picture fades and the card rises on load; the
// picture is the first thing the page shows, so it is not lazy.
$camp_hero = function ($eyebrow, $title, $lead, $src, $alt) use ($el, $heading, $para, $link, $span, $icon, $image, $container, $attr, $fx, $kinds, $park) {
    $fact = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('li', 'd-flex align-items-center gap-2', $name, array(
            $icon($icon_name, 'text-primary flex-shrink-0'),
            $span($text, '', lang('Text')),
        ));
    };
    $tiles = array();
    foreach ($kinds as $key => $k) {
        $tiles[] = $el('li', 'col position-relative d-flex align-items-center gap-3 p-3', $k['title'], array(
            $icon($k['icon'], 'fs-4 text-primary flex-shrink-0'),
            $el('div', '', lang('Kind Text'), array(
                $link($k['title'], '{{page:stay_' . $key . '}}', 'stretched-link d-block fw-semibold text-body text-decoration-none'),
                $span($k['sleeps'], 'd-block small text-body-secondary', lang('Sleeps')),
            )),
        ));
    }
    $picture = $el('div', 'ratio ratio-21x9 rounded-4 overflow-hidden bg-body-tertiary', lang('Hero Picture'), array(
        $image($src, $alt, 'object-fit-cover'),
    ));
    $card = $el('div', 'pg-campsite-lift col-lg-10 mx-auto p-4 p-lg-5 rounded-4 bg-body shadow', lang('Hero Card'), array(
        $para($eyebrow, 'pcp-eyebrow small text-uppercase fw-semibold text-primary mb-3'),
        $heading('h1', $title, 'pcp-display display-5 mb-3'),
        $para($lead, 'lead text-body-secondary mb-4'),
        $el('div', 'd-flex flex-wrap align-items-center gap-3 mb-4', lang('Buttons'), array(
            $link(lang('Request a Stay'), '{{page:book}}', 'btn btn-primary btn-lg rounded-pill px-4'),
            $link(lang('Prices and Seasons'), '{{page:prices}}', 'btn btn-outline-secondary btn-lg rounded-pill px-4'),
            $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-0', lang('Stay Facts'), array(
                $fact('bi-clock', $park['arrival'], lang('Arrival')),
                $fact('bi-calendar-range', $park['opening'], lang('Opening')),
            )),
        )),
        $el('nav', 'pg-campsite-kinds', lang('Kinds of Stay'), array(
            $para(lang('Choose how you want to stay'), 'small fw-semibold mb-2'),
            $el('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-2 mb-0', lang('Kinds'), $tiles),
        ), array('_attrs' => array($attr('aria-label', lang('Kinds of Stay'))))),
    ));
    return $el('section', 'pcp-hero py-5 overflow-hidden', lang('Hero'), array(
        $container(array(
            $fx($picture, array('effect' => 'fade', 'trigger' => 'load', 'duration' => 1000)),
            $fx($card, array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700, 'delay' => 200)),
        )),
    ));
};

// The Campsite: Comfort Scale block (pg-campsite-scale, pg-campsite-stop):
// the kinds of stay from the wildest to the cosiest on a line that fills
// with the theme colour, each a card with who it sleeps, what a guest
// brings, what is ready and a link to its page; the cards rise in one
// after another on view. A search section under its anchor $id.
$comfort_scale = function ($id, $class = 'py-5 bg-body-tertiary') use ($el, $heading, $para, $link, $span, $icon, $container, $attr, $rise, $kinds, $searchable) {
    $hidden = array('_attrs' => array($attr('aria-hidden', 'true')));
    $list = function ($title, $icon_name, $items) use ($el, $heading, $icon, $span) {
        $lis = array();
        foreach ($items as $t) {
            $lis[] = $el('li', 'd-flex gap-2 mb-1', lang('List Item'), array(
                $icon($icon_name, 'text-primary flex-shrink-0'),
                $span($t, '', lang('Text')),
            ));
        }
        return $el('div', 'mb-3', $title, array(
            $heading('h4', $title, 'small text-uppercase fw-semibold text-body-secondary mb-2'),
            $el('ul', 'list-unstyled small mb-0', lang('Items'), $lis),
        ));
    };
    $stops = array();
    foreach ($kinds as $key => $k) {
        $stops[] = $el('li', 'col d-flex', $k['title'], array(
            $el('div', 'pg-campsite-stop d-flex flex-column w-100 p-4 rounded-4 border bg-body', lang('Stay Card'), array(
                $icon($k['icon'], 'd-block fs-3 text-primary mb-2'),
                $heading('h3', $k['title'], 'pcp-display h5 mb-1'),
                $para($k['sleeps'], 'small text-body-secondary mb-3'),
                $list(lang('You bring'), 'bi-backpack', $k['bring']),
                $list(lang('Ready for you'), 'bi-check-lg', $k['ready']),
                $link(str_replace('{var}', $k['title'], lang('See the {var}')), '{{page:stay_' . $key . '}}', 'link-primary fw-semibold small mt-auto'),
            )),
        ));
    }
    return $el('section', $class, lang('Ways to Stay'), array(
        $container(array(
            $el('div', 'text-center col-lg-8 mx-auto mb-5', lang('Section Header'), array(
                $para(lang('Ways to stay'), 'pcp-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                $heading('h2', lang('From a tent in the meadow to a cabin with a stove'), 'pcp-display h2 mb-3'),
                $para(lang('Every way to stay with us, from the wildest to the cosiest: what you bring, and what is ready when you arrive.'), 'lead text-body-secondary mb-0'),
            )),
            $el('div', 'd-none d-lg-flex justify-content-between small fw-semibold text-body-secondary mb-2', lang('Scale Ends'), array(
                $span(lang('Closest to nature'), '', lang('Text')),
                $span(lang('Most comfort'), '', lang('Text')),
            ), $hidden),
            $el('div', 'pg-campsite-scale d-none d-lg-block mb-5', lang('Scale'), array(), $hidden),
            $rise($el('ol', 'list-unstyled row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4 mb-0', lang('Kinds'), $stops), 120),
        )),
    ), $searchable($id, lang('tent, caravan, motorhome, campervan, glamping, cabin, lodge, bungalow')));
};

// The Campsite: Pitch Drawing block (pg-campsite-plot, pg-campsite-area,
// pg-campsite-marker): a tent pitch seen from above, its car place and
// tent area and four numbered points drawn with position utilities (hidden
// from screen readers, the legend says it all), and beside it the legend
// of the same numbers. The drawing scales in and the legend rises on view.
$pitch_drawing = function ($id, $more = array(), $class = 'py-5') use ($el, $heading, $para, $span, $container, $row, $col, $attr, $fx, $rise, $searchable) {
    $marker = function ($n, $place) use ($span) {
        return $span((string)$n, 'pg-campsite-marker position-absolute translate-middle shadow-sm ' . $place, lang('Number'));
    };
    $points = array(
        array(lang('Car beside the tent'), lang('One car parks on the pitch; a second car parks at the gate.')),
        array(lang('Electric hook-up'), lang('A post at the pitch\'s edge; bring a long cable with a campsite plug.')),
        array(lang('The park road'), lang('Walking pace on the park roads.')),
        array(lang('Hedges between the pitches'), lang('Hedges and young trees give every family its own corner.')),
    );
    $legend = array();
    foreach ($points as $i => $p) {
        $legend[] = $el('li', 'd-flex gap-3 mb-3', $p[0], array(
            $span((string)($i + 1), 'pg-campsite-marker', lang('Number')),
            $el('div', '', lang('Point Text'), array(
                $heading('h3', $p[0], 'h6 mb-1'),
                $para($p[1], 'small text-body-secondary mb-0'),
            )),
        ));
    }
    $drawing = $el('div', 'ratio ratio-4x3', lang('Drawing'), array(
        $el('div', 'pg-campsite-plot', lang('Pitch'), array(
            $el('div', 'pg-campsite-area position-absolute top-0 bottom-0 start-0 m-3 w-25', lang('Car Place'), array($span(lang('Car'), '', lang('Text')))),
            $el('div', 'pg-campsite-area position-absolute top-0 end-0 m-3 w-50 h-75', lang('Tent Area'), array($span(lang('Tent or caravan'), '', lang('Text')))),
            $marker(1, 'top-0 start-0 mt-3 ms-3'),
            $marker(2, 'top-0 start-50'),
            $marker(3, 'top-100 start-50'),
            $marker(4, 'top-50 start-100'),
        ), array('_attrs' => array($attr('aria-hidden', 'true')))),
    ));
    return $el('section', $class, lang('Pitch Drawing'), array(
        $container(array(
            $row(array(
                $fx($col(array(
                    $el('figure', 'mb-0 p-3', lang('Pitch Drawing'), array(
                        $drawing,
                        $el('figcaption', 'small text-body-secondary text-center mt-4', lang('Caption'), array(), array('text' => lang('A meadow pitch of about 100 m², seen from above'))),
                    )),
                ), '', '6'), array('effect' => 'scale', 'trigger' => 'view', 'duration' => 700)),
                $col(array_merge(array(
                    $para(lang('Your pitch'), 'pcp-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', lang('What a pitch holds'), 'pcp-display h2 mb-3'),
                    $para(lang('Our tent pitches are wide and flat, marked by hedges and young trees so every family has its own corner.'), 'text-body-secondary mb-4'),
                    $rise($el('ol', 'list-unstyled ' . ($more ? 'mb-4' : 'mb-0'), lang('Legend'), $legend), 100),
                ), $more), '', '6'),
            ), '4', '', 'align-items-center g-lg-5'),
        )),
    ), $searchable($id, lang('pitch, hook-up, electricity, car, hedge, size')));
};

// The facts of a kind of stay in one list: who it sleeps and its lowest
// price.
$kind_facts = function ($key, $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $kinds, $from_price) {
    $fact = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name),
        ));
    };
    return $el('ul', $class, lang('Stay Facts'), array(
        $fact('bi-people', $kinds[$key]['sleeps'], lang('Sleeps')),
        $fact('bi-tag', $from_price($key), lang('Price')),
    ));
};

// A kind of stay's card (the home and the places-to-stay page): the
// picture, the price, the title linking the kind's page and the summary.
$kind_card = function ($key) use ($el, $para, $span, $link, $image, $col, $photo, $attr, $kinds, $from_price) {
    $k = $kinds[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pcp-lift', lang('Stay Card'), array(
            $el('span', 'd-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('kind-' . $key, 800, 600), $k['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($from_price($key), 'pcp-price small fw-semibold text-primary mb-1', lang('Price')),
                $el('h3', 'pcp-display h5 position-static mb-2', lang('Stay Name'), array(
                    $link($k['title'], '{{page:stay_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($k['summary'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3');
};

// The words of a unit's kind and setting, the values of its Kind and
// Setting fields.
$unit_kind = function ($key) use ($units, $kinds) {
    return $kinds[$units[$key]['kind']]['title'];
};
$unit_setting = function ($key) use ($units, $settings) {
    return $settings[$units[$key]['setting']]['title'];
};
$unit_hookup = function ($key) use ($units, $hookup_choices) {
    return $units[$key]['hookup'] ? $hookup_choices[0] : $hookup_choices[1];
};

// The facts of a pitch or a cabin in one list: kind, setting, sleeps and
// hook-up, each bound to the Pitches and Cabins form's field of the same
// name.
$unit_facts = function ($sample_key = 'lakeside', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $units, $unit_kind, $unit_setting, $unit_hookup) {
    $u = $units[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Unit Facts'), array(
        $fact('bi-house', $unit_kind($sample_key), 'kind', lang('Kind')),
        $fact('bi-geo', $unit_setting($sample_key), 'setting', lang('Place in the Park')),
        $el('li', 'd-inline-flex align-items-center gap-1', lang('Sleeps'), array(
            $icon('bi-people', 'text-primary'),
            $span(lang('Sleeps'), '', lang('Label')),
            $span((string)$u['sleeps'], '', lang('Value'), $bind('sleeps')),
        )),
        $fact('bi-plug', $unit_hookup($sample_key), 'hookup', lang('Hook-up')),
    ));
};

// A pitch's or a cabin's card of the lists (the home and the unit list):
// the photo, the kind, the name linking the unit page, the setting, the
// price and the summary. Every value is bound to the Pitches and Cabins
// form's field of the same name; the sample fills the canvas.
$unit_card = function ($sample_key = 'lakeside') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $units, $unit_kind, $unit_setting, $from_price, $photo, $attr) {
    $u = $units[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pcp-lift', lang('Unit Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('unit-' . $sample_key, 800, 600), $u['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($unit_kind($sample_key), 'pcp-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Kind'), $bind('kind')),
                $el('h3', 'pcp-display h5 position-static mb-2', lang('Unit Name'), array(
                    $link($u['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-1', lang('Place in the Park'), array(
                    $icon('bi-geo', 'text-primary'),
                    $span($unit_setting($sample_key), '', lang('Place in the Park'), $bind('setting')),
                )),
                $para($u['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $span($from_price($u['kind']), 'pcp-price mt-auto fw-semibold', lang('Price'), $bind('price')),
            )),
        )),
    ), '6', '4');
};

// A facility, an activity, a rule or a reason: an icon tile, the title,
// the words and, for an activity, when it runs.
$feature_card = function ($item, $md = '6', $lg = '3') use ($el, $heading, $para, $span, $col, $icon_tile) {
    $children = array(
        $icon_tile($item['icon'], 'mb-3'),
        $heading('h3', $item['title'], 'pcp-display h5 mb-2'),
        $para($item['text'], 'small text-body-secondary mb-0'),
    );
    if (!empty($item['when'])) $children[] = $span($item['when'], 'd-block small fw-semibold text-primary mt-2', lang('When'));
    if (!empty($item['distance'])) $children[] = $span($item['distance'], 'd-block small fw-semibold text-primary mt-2', lang('Distance'));
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body-tertiary', lang('Feature'), $children),
    ), $md, $lg);
};

// What a guest says: the quote, the guest and the kind of stay.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $icon, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Guest Quote'), array(
            $icon('bi-quote', 'fs-2 text-primary lh-1 mb-2'),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pcp-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Guest Details'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// The season prices as a table: a row per kind of stay (or the one kind
// given), a column per season. Each season's head opens the stay request
// with the season chosen (?season=).
$season_table = function ($only = '') use ($el, $link, $span, $attr, $kinds, $seasons, $season_price, $query) {
    $head = array($el('th', '', lang('Table Header'), array(), array('text' => lang('Place to stay'), '_attrs' => array($attr('scope', 'col')))));
    foreach ($seasons as $s) {
        $head[] = $el('th', 'text-end', lang('Table Header'), array(
            $link($s['title'], '{{page:book}}?' . $query(array('season' => $s['title'])), 'link-body-emphasis'),
            $span($s['when'], 'd-block small fw-normal text-body-secondary', lang('When')),
        ), array('_attrs' => array($attr('scope', 'col'))));
    }
    $rows = array();
    foreach ($kinds as $key => $k) {
        if ($only !== '' && $only !== $key) continue;
        $cells = array($el('th', 'fw-semibold', lang('Table Header'), array(
            $link($k['title'], '{{page:stay_' . $key . '}}', 'link-body-emphasis text-decoration-none'),
        ), array('_attrs' => array($attr('scope', 'row')))));
        foreach (array_keys($seasons) as $season) {
            $cells[] = $el('td', 'pcp-price text-end text-nowrap', lang('Table Cell'), array(), array('text' => $season_price($key, $season)));
        }
        $rows[] = $el('tr', '', lang('Table Row'), $cells);
    }
    return $el('div', 'table-responsive', lang('Price Table'), array(
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
                $item('staff_requests', lang('Stay Requests')),
                $item('staff_enquiries', lang('Group Enquiries')),
                $item('unit_new', lang('New Pitch or Cabin')),
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
    return $agree($id, 'consent', lang('I would like to receive the campsite\'s news and season offers by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A group
// enquiry may bring a programme or a list of tents, so the default accepts
// PDFs, office files, text and pictures; one that takes only pictures is a
// collection's image kind (a pitch's or a cabin's photo).
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
                    $link(lang('Our favourite walks from the campsite gate'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

