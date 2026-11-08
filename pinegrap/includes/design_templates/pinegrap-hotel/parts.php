<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Hotel", part 1 of its files: the node builders, the
 * hotel's own data (the kinds of room, the views, the sample rooms and
 * their rates, the board plans, the offers, the facilities, dining, spa,
 * the event spaces, the gift vouchers, what guests say and the answers of
 * the forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, booking band, room, plan, offer and guest cards, page
 * shells, section headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-hotel.php puts them together.
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
// every opening until the hotel puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-hotel-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/f5ebe0/5c3d2e?text=' . rawurlencode($initials !== '' ? $initials : 'H');
};

// ── The hotel: its rooms, its rates and what it offers ─────────────────

// The hotel itself. The about page tells its story; the footer, the
// booking band and the contact lines use the address, phone and times.
$hotel = array(
    'tagline'   => lang('A small hotel by the sea, with twenty-four rooms and a garden of olive trees'),
    'story'     => lang('An old stone house above the bay, opened as a hotel by one family and run by the same family today. Every room looks at the sea or the garden, breakfast is made from what the region grows, and the beach is three minutes down the path.'),
    'address'   => lang('Cove Road 12, Old Harbour'),
    'region'    => lang('On the coast, 40 minutes from the airport'),
    'phone'     => '+90 555 000 00 00',
    'email'     => 'stay@example.com',
    'check_in'  => '15:00',
    'check_out' => '11:00',
    'reception' => lang('Reception is open day and night'),
);

// The kinds of room, in the order the room list filters them. Each
// answer's value is its own words, so a filter link (?type=) reads as it
// shows.
$room_types = array(
    'room'   => array('icon' => 'bi-door-open', 'title' => lang('Guest Rooms'),
        'text' => lang('Light rooms for two with a bathroom of local stone and a window on the bay or the garden.')),
    'suite'  => array('icon' => 'bi-stars', 'title' => lang('Suites'),
        'text' => lang('A bedroom and a sitting room, a terrace for breakfast outside and a bath with a view.')),
    'family' => array('icon' => 'bi-people', 'title' => lang('Family Rooms'),
        'text' => lang('Room for two adults and two children, with a second sleeping corner and a garden door.')),
);

// The views, the room list's second filter.
$views = array(
    'sea'    => array('icon' => 'bi-water', 'title' => lang('Sea view')),
    'garden' => array('icon' => 'bi-tree', 'title' => lang('Garden view')),
);

// The sample rooms: the records the Rooms form starts with. The room list,
// the room page and the home read them; the home shows the featured ones.
// 'guests' and 'size' (m²) are the numbers the lists sort on.
$rooms = array(
    'classic'  => array('name' => lang('Classic Double'), 'type' => 'room', 'view' => 'garden', 'guests' => 2, 'size' => 22,
        'beds'        => lang('One double bed'),
        'summary'     => lang('A quiet room over the olive garden with a double bed and a reading chair by the window.'),
        'description' => lang('The Classic Double looks over the olive garden at the back of the house, where the mornings are cool and the evenings smell of jasmine. It has a double bed with linen sheets, a reading chair, a desk and a shower of local stone.'),
        'amenities'   => array(lang('Air conditioning'), lang('Rain shower'), lang('Fast Wi-Fi'), lang('Tea and coffee tray')),
        'featured'    => false),
    'superior' => array('name' => lang('Superior Sea View'), 'type' => 'room', 'view' => 'sea', 'guests' => 2, 'size' => 26,
        'beds'        => lang('One king-size bed or two single beds'),
        'summary'     => lang('A bright room for two with a balcony over the bay and the sound of the waves at night.'),
        'description' => lang('From its small balcony the Superior Sea View looks straight over the bay to the islands. The bed is made as one king-size bed or two single beds, as you ask, and the bathroom has a walk-in shower and a window to the sea.'),
        'amenities'   => array(lang('Balcony over the bay'), lang('Air conditioning'), lang('Walk-in shower'), lang('Fast Wi-Fi'), lang('Minibar')),
        'featured'    => true),
    'junior'   => array('name' => lang('Junior Suite'), 'type' => 'suite', 'view' => 'sea', 'guests' => 3, 'size' => 38,
        'beds'        => lang('One king-size bed and a sofa bed'),
        'summary'     => lang('A large room with a sitting corner, a terrace for breakfast outside and a bath with a view.'),
        'description' => lang('The Junior Suite has a sitting corner with a sofa bed for a third guest and a terrace where breakfast can be brought in the morning. A free-standing bath faces the window, so the sea is in view while you rest.'),
        'amenities'   => array(lang('Private terrace'), lang('Free-standing bath'), lang('Sofa bed for a third guest'), lang('Espresso machine'), lang('Bathrobes and slippers')),
        'featured'    => true),
    'terrace'  => array('name' => lang('Terrace Suite'), 'type' => 'suite', 'view' => 'sea', 'guests' => 2, 'size' => 52,
        'beds'        => lang('One king-size bed'),
        'summary'     => lang('The top of the house: a bedroom, a sitting room and a wide terrace with loungers over the sea.'),
        'description' => lang('The Terrace Suite takes the whole top floor. A sitting room opens onto a wide terrace with two loungers and a table for dinner under the stars; the bedroom has its own door to the terrace and a bathroom with a bath and a separate shower.'),
        'amenities'   => array(lang('Wide terrace with loungers'), lang('Separate sitting room'), lang('Bath and separate shower'), lang('Espresso machine'), lang('Evening turndown')),
        'featured'    => true),
    'family'   => array('name' => lang('Family Garden Room'), 'type' => 'family', 'view' => 'garden', 'guests' => 4, 'size' => 40,
        'beds'        => lang('One double bed and two single beds'),
        'summary'     => lang('Two sleeping areas for a family of four and a door straight onto the garden lawn.'),
        'description' => lang('The Family Garden Room has a double bed for the parents and a separate corner with two single beds for the children, divided by a sliding door. It opens onto the lawn, a few steps from the pool, and a cot can be added for a baby.'),
        'amenities'   => array(lang('Door onto the garden'), lang('Separate children\'s corner'), lang('Cot on request'), lang('Air conditioning'), lang('Fast Wi-Fi')),
        'featured'    => false),
    'loft'     => array('name' => lang('Family Sea Loft'), 'type' => 'family', 'view' => 'sea', 'guests' => 4, 'size' => 46,
        'beds'        => lang('One double bed and two single beds on the mezzanine'),
        'summary'     => lang('A high room with a mezzanine for the children and a balcony over the bay.'),
        'description' => lang('Under the old roof beams, the Family Sea Loft has a double bed below and two single beds on a mezzanine that children love. The balcony faces the sea, and the bathroom has both a bath and a shower.'),
        'amenities'   => array(lang('Balcony over the bay'), lang('Mezzanine with two beds'), lang('Bath and shower'), lang('Air conditioning'), lang('Minibar')),
        'featured'    => false),
);

// A room's lowest rate for a night, per currency, room only. The room
// list, the room page and the Rooms form's Rate field show it.
$room_rates = array(
    'classic'  => array('USD' => 140, 'EUR' => 130, 'GBP' => 115, 'TRY' => 5200),
    'superior' => array('USD' => 190, 'EUR' => 175, 'GBP' => 155, 'TRY' => 7000),
    'junior'   => array('USD' => 260, 'EUR' => 240, 'GBP' => 210, 'TRY' => 9600),
    'terrace'  => array('USD' => 380, 'EUR' => 350, 'GBP' => 305, 'TRY' => 14000),
    'family'   => array('USD' => 230, 'EUR' => 215, 'GBP' => 185, 'TRY' => 8500),
    'loft'     => array('USD' => 280, 'EUR' => 260, 'GBP' => 225, 'TRY' => 10400),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A rate and a voucher therefore show one amount wherever they appear.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A room's "From … a night" words, the value of its Rate field.
$rate_text = function ($key) use ($room_rates, $money) {
    return sprintf(lang('From %s a night'), $money($room_rates[$key]));
};

// The board plans a booking chooses from. 'extra' is the price per guest
// and night on top of the room rate (none for room only).
$rate_plans = array(
    'room'      => array('icon' => 'bi-key', 'title' => lang('Room Only'), 'extra' => null,
        'text'   => lang('The room and nothing more, for guests who like to find breakfast in the harbour cafés.'),
        'points' => array(lang('Free cancellation up to three days before arrival'), lang('Pool, garden and beach towels'), lang('Fast Wi-Fi'))),
    'breakfast' => array('icon' => 'bi-cup-hot', 'title' => lang('Bed and Breakfast'), 'extra' => array('USD' => 18, 'EUR' => 16, 'GBP' => 14, 'TRY' => 650),
        'text'   => lang('Breakfast on the terrace every morning: bread from the oven, the garden\'s fruit and eggs as you like them.'),
        'points' => array(lang('Breakfast from 7:30 to 10:30'), lang('Free cancellation up to three days before arrival'), lang('Pool, garden and beach towels'))),
    'half'      => array('icon' => 'bi-moon-stars', 'title' => lang('Half Board'), 'extra' => array('USD' => 48, 'EUR' => 44, 'GBP' => 38, 'TRY' => 1750),
        'text'   => lang('Breakfast and a three-course dinner in the restaurant, with the fish of the day and the region\'s wines.'),
        'points' => array(lang('Breakfast and dinner every day'), lang('A table by the window kept for you'), lang('Free cancellation up to three days before arrival'))),
);
// The words of a plan's price line.
$plan_price = function ($key) use ($rate_plans, $money) {
    $p = $rate_plans[$key];
    return $p['extra'] === null ? lang('Included in the room rate') : sprintf(lang('+ %s per guest a night'), $money($p['extra']));
};

// The offers. Each opens the booking request with the offer chosen
// (?offer=); the terms are what the reception confirms by hand.
$offers = array(
    'early'     => array('icon' => 'bi-calendar-check', 'title' => lang('Early Booking'), 'badge' => lang('Save 15%'),
        'text'  => lang('Plan ahead and ask for your room at least sixty days before you arrive.'),
        'terms' => lang('For stays of three nights or more; the saving is taken off the room rate when we confirm.')),
    'long'      => array('icon' => 'bi-hourglass-split', 'title' => lang('Stay Four, Pay Three'), 'badge' => lang('One night free'),
        'text'  => lang('Stay four nights or more in spring or autumn and the fourth night is on the house.'),
        'terms' => lang('Outside the summer months; one free night for every four nights of the stay.')),
    'honeymoon' => array('icon' => 'bi-heart', 'title' => lang('Honeymoon by the Sea'), 'badge' => lang('Suites only'),
        'text'  => lang('A suite, a bottle of sparkling wine on arrival, a dinner on the terrace and a late check-out.'),
        'terms' => lang('For three nights or more in a suite; tell us the occasion in your request.')),
);

// What the hotel has beside the rooms (the home and the about page).
$facilities = array(
    'pool'      => array('icon' => 'bi-water', 'title' => lang('Garden Pool'),
        'text' => lang('A salt-water pool among the olive trees, with loungers and shade from morning to sunset.')),
    'beach'     => array('icon' => 'bi-umbrella', 'title' => lang('Private Beach'),
        'text' => lang('Three minutes down the path: a pebble cove with sunbeds, umbrellas and towels from the hotel.')),
    'spa'       => array('icon' => 'bi-flower1', 'title' => lang('Spa and Hammam'),
        'text' => lang('A stone hammam, a sauna and two treatment rooms with massages based on olive oil.')),
    'restaurant' => array('icon' => 'bi-egg-fried', 'title' => lang('Restaurant'),
        'text' => lang('Breakfast on the terrace and dinners from the region, with the fish of the day.')),
    'parking'   => array('icon' => 'bi-p-circle', 'title' => lang('Free Parking'),
        'text' => lang('Parking under the pines for every guest, with two chargers for electric cars.')),
    'transfer'  => array('icon' => 'bi-car-front', 'title' => lang('Airport Transfer'),
        'text' => lang('A driver at the airport on the day you arrive, if you ask for it with your booking.')),
);

// Where to eat and drink (the dining page and the home).
$dining = array(
    'restaurant' => array('icon' => 'bi-egg-fried', 'title' => lang('The Restaurant'), 'hours' => lang('Dinner from 19:00 to 22:30'),
        'text' => lang('A short menu that changes with the market: fish from the harbour, vegetables from the garden and wines from the hills behind the bay.')),
    'terrace'    => array('icon' => 'bi-cup-hot', 'title' => lang('Breakfast Terrace'), 'hours' => lang('Breakfast from 7:30 to 10:30'),
        'text' => lang('Bread from our own oven, honey from the village, the garden\'s fruit and eggs cooked as you like them.')),
    'bar'        => array('icon' => 'bi-cup-straw', 'title' => lang('Pool Bar'), 'hours' => lang('Open from 11:00 to midnight'),
        'text' => lang('Fresh lemonade at noon, light plates in the afternoon and a glass of wine as the sun goes down.')),
);

// The spa's treatments, with how long they take in minutes.
$treatments = array(
    array('title' => lang('Hammam Ritual'), 'minutes' => 45,
        'text' => lang('Warm marble, a foam wash and a scrub with olive oil soap, the way it has been done for centuries.')),
    array('title' => lang('Olive Oil Massage'), 'minutes' => 60,
        'text' => lang('A slow full-body massage with warm oil from the hotel\'s own trees.')),
    array('title' => lang('Sea Salt Scrub'), 'minutes' => 30,
        'text' => lang('Salt from the coast mixed with herbs, for skin that feels new after a day in the sun.')),
    array('title' => lang('Massage for Two'), 'minutes' => 75,
        'text' => lang('Side by side in the larger treatment room, followed by tea in the garden.')),
);

// The rooms for meetings, weddings and private dinners. 'capacity' is the
// number of guests seated; the event enquiry names the space.
$event_spaces = array(
    'garden'  => array('icon' => 'bi-tree', 'title' => lang('Olive Garden'), 'capacity' => 150, 'size' => 600,
        'text' => lang('The lawn under the old olive trees, for a wedding at sunset or a long dinner by lantern light.')),
    'terrace' => array('icon' => 'bi-sunset', 'title' => lang('Sea Terrace'), 'capacity' => 80, 'size' => 240,
        'text' => lang('The terrace over the bay, for a drinks reception or a summer party with the sea behind it.')),
    'hall'    => array('icon' => 'bi-easel', 'title' => lang('Stone Hall'), 'capacity' => 60, 'size' => 120,
        'text' => lang('The old house\'s vaulted hall with daylight, a screen and good sound, for a meeting or a workshop.')),
    'library' => array('icon' => 'bi-book', 'title' => lang('Library'), 'capacity' => 14, 'size' => 45,
        'text' => lang('A quiet room with one long table, for a board meeting or a private dinner.')),
);

// The gift vouchers and stays the shop sells (with the shop), with their
// prices. The SKUs name the template, so the products are its own.
$vouchers = array(
    'gift100'  => array('sku' => 'HOTEL-VOUCHER-100', 'icon' => 'bi-gift', 'title' => lang('Gift Voucher'),
        'text'  => lang('A voucher for a stay, a dinner or the spa, valid for a year.'),
        'price' => array('USD' => 100, 'EUR' => 100, 'GBP' => 100, 'TRY' => 4000)),
    'gift250'  => array('sku' => 'HOTEL-VOUCHER-250', 'icon' => 'bi-gift', 'title' => lang('Gift Voucher for a Stay'),
        'text'  => lang('A larger voucher for a night or two by the sea, valid for a year.'),
        'price' => array('USD' => 250, 'EUR' => 250, 'GBP' => 250, 'TRY' => 10000)),
    'spa'      => array('sku' => 'HOTEL-SPA-DAY', 'icon' => 'bi-flower1', 'title' => lang('Spa Day for Two'),
        'text'  => lang('A hammam ritual and an olive oil massage for two, with lunch by the pool.'),
        'price' => array('USD' => 220, 'EUR' => 200, 'GBP' => 175, 'TRY' => 8000)),
    'dinner'   => array('sku' => 'HOTEL-DINNER-TWO', 'icon' => 'bi-egg-fried', 'title' => lang('Dinner for Two'),
        'text'  => lang('A five-course dinner on the sea terrace with a glass of wine for each course.'),
        'price' => array('USD' => 160, 'EUR' => 150, 'GBP' => 130, 'TRY' => 6000)),
    'escape'   => array('sku' => 'HOTEL-ESCAPE-TWO', 'icon' => 'bi-suitcase', 'title' => lang('Two Nights by the Sea'),
        'text'  => lang('Two nights in a Superior Sea View with breakfast and one dinner for two.'),
        'price' => array('USD' => 520, 'EUR' => 480, 'GBP' => 420, 'TRY' => 19000)),
);

// What is near the hotel (the location page), with how far it is.
$nearby = array(
    array('icon' => 'bi-airplane', 'title' => lang('Airport'), 'distance' => lang('40 minutes by car')),
    array('icon' => 'bi-shop', 'title' => lang('Old Harbour and market'), 'distance' => lang('10 minutes on foot')),
    array('icon' => 'bi-bank', 'title' => lang('Ancient theatre'), 'distance' => lang('20 minutes by car')),
    array('icon' => 'bi-signpost-split', 'title' => lang('Coastal walking path'), 'distance' => lang('From the garden gate')),
);

// The hotel in four figures (the home and about pages).
$figures = array(
    array('value' => '24', 'label' => lang('Rooms and suites')),
    array('value' => '3', 'label' => lang('Minutes to the beach')),
    array('value' => '4.9', 'label' => lang('Average guest rating')),
    array('value' => '3', 'label' => lang('Generations of one family')),
);

// What guests say, each about one of the sample rooms.
$testimonials = array(
    array('room' => 'superior', 'name' => lang('Elif Yıldız'), 'role' => lang('Stayed in a Superior Sea View'),
        'quote' => lang('We woke up to the sea every morning and fell asleep to the waves. Breakfast on the terrace was the best part of the day.')),
    array('room' => 'family', 'name' => lang('Mark Turner'), 'role' => lang('Stayed in a Family Garden Room'),
        'quote' => lang('The children ran from our door to the pool in ten steps. The staff knew their names by the second day.')),
    array('room' => 'terrace', 'name' => lang('Ayşe Kara'), 'role' => lang('Stayed in the Terrace Suite'),
        'quote' => lang('Dinner on our own terrace under the stars for our anniversary. We have already asked for the same suite next year.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the guest chose.
$room_type_titles = array();
foreach ($room_types as $t) $room_type_titles[] = $t['title'];
$view_titles = array();
foreach ($views as $v) $view_titles[] = $v['title'];
$room_names = array();
foreach ($rooms as $r) $room_names[] = $r['name'];
$booking_room_choices = array_merge($room_names, array(lang('Any room that is free')));
$plan_choices = array();
foreach ($rate_plans as $p) $plan_choices[] = $p['title'];
$offer_choices = array(lang('No offer'));
foreach ($offers as $o) $offer_choices[] = $o['title'];
$arrival_choices = array(lang('Before 15:00'), lang('15:00 to 18:00'), lang('18:00 to 21:00'), lang('After 21:00'), lang('Not sure yet'));
$bed_choices = array(
    array(lang('One large bed'), lang('One large bed')),
    array(lang('Two single beds'), lang('Two single beds')),
    array(lang('No preference'), lang('No preference')),
);
// The states of a booking request, set by the reception in the panel.
$booking_statuses = array(lang('Request received'), lang('Confirmed'), lang('Waiting for you'), lang('Declined'), lang('Cancelled'));
$event_kinds = array(lang('Wedding'), lang('Meeting or workshop'), lang('Private dinner'), lang('Birthday or anniversary'), lang('Something else'));
$event_space_choices = array();
foreach ($event_spaces as $s) $event_space_choices[] = $s['title'];
$event_space_choices[] = lang('Not sure yet');
$event_size_choices = array(lang('Up to 15 guests'), lang('15 to 60 guests'), lang('60 to 150 guests'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of an event enquiry, set by the events team in the panel.
$event_statuses = array(lang('Enquiry received'), lang('Proposal sent'), lang('Date held for you'), lang('Confirmed'), lang('Declined'));

// The query string of a link that opens a list with a filter set (?type=,
// ?room_view=; ?view= is reserved by pg_sw_url_param_reserved()) or a form
// with a choice made (?room=, ?plan=, ?offer=, ?space=): the form's fields
// start with these values (_cf.url_param).
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
            $heading('h1', $title, 'pht-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pht-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pht-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the facilities are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what guests say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'ht-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the first offer and the way to read it.
// A shared component.
$notice = function () use ($el, $icon, $span, $link, $offers) {
    $o = reset($offers);
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon($o['icon'], 'text-primary'),
        $span($o['title'] . ' — ' . $o['badge'], '', lang('Text')),
        $link(lang('See the offers'), '{{page:offers}}', 'link-primary fw-semibold'),
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
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'), $widget('cart_link'),
            )),
        )),
    ));
};

// The header: the hotel's name, the menu and the way to ask for a room.
// The links to the current page are marked active on the server (smart
// active state), so one header serves every page. The gift vouchers are
// the shop's and are linked from the shop pages only, so the header stands
// on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('rooms', lang('Rooms and Suites')),
        array('offers', lang('Special Offers')),
        array('dining', lang('Dining')),
        array('spa', lang('Spa')),
        array('meetings', lang('Weddings and Meetings')),
        array('location', lang('Getting Here')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-buildings', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pht-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#ht-nav'),
                    $attr('aria-controls', 'ht-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-check'),
                            $span(lang('Ask for a Room'), '', lang('Link Text')),
                        ), array('href' => '{{page:book}}')),
                    )),
                ), array('id' => 'ht-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the hotel in a sentence and its profiles,
// the rooms by kind and view, the guest pages, the hotel's pages and how
// to reach it; at the bottom the year and the site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $room_types, $views, $hotel, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $room_links = array($link(lang('All Rooms'), '{{page:rooms}}', $plain));
    foreach ($room_types as $t) $room_links[] = $link($t['title'], '{{page:rooms}}?' . $query(array('type' => $t['title'])), $plain);
    foreach ($views as $v) $room_links[] = $link($v['title'], '{{page:rooms}}?' . $query(array('room_view' => $v['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Hotel'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-buildings', 'text-primary'),
                        $span('{{site_name}}', 'pht-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($hotel['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-whatsapp', 'WhatsApp'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Room Links'), array(
                    $heading('h2', lang('Rooms and Suites'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($room_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Guests'), array(
                    $heading('h2', lang('For Guests'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Ask for a Room'), '{{page:book}}', $plain),
                        $link(lang('My Bookings'), '{{page:my_bookings}}', $plain),
                        $link(lang('Rates and Board'), '{{page:rates}}', $plain),
                        $link(lang('Special Offers'), '{{page:offers}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Hotel'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Dining'), '{{page:dining}}', $plain),
                        $link(lang('Spa'), '{{page:spa}}', $plain),
                        $link(lang('Weddings and Meetings'), '{{page:meetings}}', $plain),
                        $link(lang('Hotel Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Getting Here'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($hotel['address'], '{{page:location}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($hotel['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $hotel['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($hotel['email'], 'mailto:' . $hotel['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-clock', $span($hotel['reception'], '', lang('Text')), lang('Opening Hours')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(sprintf(lang('Check-in from %1$s, check-out until %2$s'), $hotel['check_in'], $hotel['check_out']), 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The booking band: a full-width band in the palette's colour with the way
// to ask for a room and the phone for those who would rather call. Shared,
// so every page that ends with it carries the same offer.
$booking_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $hotel) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Come and stay by the sea'), 'pht-display h2 mb-2'),
                    $para(lang('Tell us your dates and the room you like; the reception answers within a day with the price and the room held for you.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Ask for a Room'), '{{page:book}}', 'btn btn-light btn-lg rounded-pill px-4'),
                        $link(lang('Call Us'), 'tel:' . preg_replace('/[^0-9+]/', '', $hotel['phone']), 'btn btn-outline-light btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The facts of a room in one line: guests, beds, size and view, each
// bound to the Rooms form's field of the same name.
$room_facts = function ($sample_key = 'superior', $class = 'd-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $rooms, $views) {
    $r = $rooms[$sample_key];
    $fact = function ($icon_name, $children, $name) use ($el, $icon) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name, 'text-primary')), $children));
    };
    return $el('ul', 'list-unstyled ' . $class, lang('Room Facts'), array(
        $fact('bi-people', array(
            $span((string)$r['guests'], '', lang('Value'), $bind('guests')),
            $span(lang('guests'), '', lang('Unit')),
        ), lang('Guests')),
        $fact('bi-arrows-angle-expand', array(
            $span((string)$r['size'], '', lang('Value'), $bind('size')),
            $span('m²', '', lang('Unit')),
        ), lang('Size')),
        $fact('bi-moon', array($span($r['beds'], '', lang('Beds'), $bind('beds'))), lang('Beds')),
        $fact($views[$r['view']]['icon'], array($span($views[$r['view']]['title'], '', lang('Room View'), $bind('view'))), lang('Room View')),
    ));
};

// A room's card of the lists (the home and the room list): the photo, the
// kind, the name linking the room page, the summary, the facts and the
// rate. Every value is bound to the Rooms form's field of the same name;
// the sample fills the canvas.
$room_card = function ($sample_key = 'superior') use ($el, $para, $span, $link, $image, $col, $bind, $rooms, $room_types, $rate_text, $room_facts, $photo, $attr) {
    $r = $rooms[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pht-lift', lang('Room Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('room-' . $sample_key, 800, 600), $r['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($room_types[$r['type']]['title'], 'pht-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Room Type'), $bind('type')),
                $el('h3', 'pht-display h4 position-static mb-2', lang('Room Name'), array(
                    $link($r['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($r['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $room_facts($sample_key, 'd-flex flex-wrap gap-3 small text-body-secondary mb-3'),
                $para($rate_text($sample_key), 'pht-price fw-semibold mt-auto mb-0', $bind('rate')),
            )),
        )),
    ), '6', '4');
};

// A board plan's card (the rates page and the room page): the name, what
// it adds to the rate, a line, what it holds and a link that opens the
// booking request with the plan chosen.
$plan_card = function ($key) use ($el, $heading, $para, $link, $col, $check_list, $icon_tile, $rate_plans, $plan_price, $query) {
    $p = $rate_plans[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Plan Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($p['icon'], 'align-self-start mb-3'),
                $heading('h3', $p['title'], 'pht-display h4 mb-1'),
                $para($plan_price($key), 'pht-price fw-semibold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $check_list($p['points'], 'list-unstyled small mb-4'),
                $link(lang('Ask with this plan'), '{{page:book}}?' . $query(array('plan' => $p['title'])), 'btn btn-outline-primary rounded-pill mt-auto'),
            )),
        )),
    ), '6', '4');
};

// An offer's card (the offers page and the home): the badge, the name, a
// line, the terms and a link that opens the booking request with the
// offer chosen.
$offer_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon, $offers, $query) {
    $o = $offers[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Offer Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-3', lang('Offer Heading'), array(
                    $icon($o['icon'], 'fs-3 text-primary'),
                    $span($o['badge'], 'badge rounded-pill text-bg-primary', lang('Badge')),
                )),
                $heading('h3', $o['title'], 'pht-display h4 mb-2'),
                $para($o['text'], 'mb-2'),
                $para($o['terms'], 'small text-body-secondary mb-4'),
                $link(lang('Ask with this offer'), '{{page:book}}?' . $query(array('offer' => $o['title'])), 'btn btn-primary rounded-pill mt-auto'),
            )),
        )),
    ), '', '4');
};

// What a guest says: the quote, the stars, the guest and the room.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Guest Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pht-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Guest'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
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
                $item('room_new', lang('New Room')),
                $item('staff_bookings', lang('Booking Requests')),
                $item('staff_enquiries', lang('Event Enquiries')),
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
    return $agree($id, 'consent', lang('I would like to receive the hotel\'s offers and Hotel Journal posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// event enquiry takes documents (an agenda, a seating plan) and pictures
// (a mood board), so the default accepts PDFs, office files, text and
// pictures; one that takes only pictures is a collection's image kind (a
// room's).
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
                    $link(lang('Five quiet coves within a short walk of the hotel'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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


// ── The Hotel blocks ────────────────────────────────────────────────────
// The home, the rooms page and the rates page place the Hotel blocks (room
// showcase, stay clock); their pg-hotel- rules ship in the template's User
// Styles.

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

// The Hotel: Room Showcase block: the sample room $key as a guest weighs
// it. A figure.pg-hotel-gallery of its three pictures (the first,
// pg-hotel-main, spans two rows from md up) with its view as a badge;
// beside it the kind of room, the name ($tag: an h1 when the block opens
// the page), the summary, the rate a night in pg-hotel-figure, a
// dl.pg-hotel-facts (guests, floor area, beds, view), four amenities and
// $buttons (null: Ask for This Room with the room filled in, and All
// Rooms). The pictures fade and the facts rise on view, or on load when
// the block opens the page. A search section under $id.
$room_showcase = function ($key, $id, $tag = 'h2', $class = 'py-5', $buttons = null)
    use ($el, $heading, $para, $link, $image, $icon, $span, $container, $row, $col, $fx, $attr, $searchable, $query, $photo, $rooms, $room_types, $views, $room_rates, $money) {
    $r = $rooms[$key];
    $view = $views[$r['view']];
    $trigger = $tag === 'h1' ? 'load' : 'view';
    $lazy = $tag === 'h1' ? array() : array('_attrs' => array($attr('loading', 'lazy')));
    $shot = function ($class, $seed, $alt) use ($el, $image, $photo, $lazy) {
        return $el('div', trim('pg-hotel-shot ' . $class), lang('Picture'), array(
            $image($photo($seed, 1200, 900), $alt, '', $lazy),
        ));
    };
    $fact = function ($icon_name, $label, $value) use ($el, $icon, $span) {
        return $el('div', '', $label, array(
            $el('dt', 'd-flex align-items-center gap-2', lang('Label'), array(
                $icon($icon_name, 'text-primary'),
                $span($label, '', lang('Label')),
            )),
            $el('dd', '', $label, array($span($value, '', lang('Value')))),
        ));
    };
    $amenities = array();
    foreach (array_slice($r['amenities'], 0, 4) as $a) {
        $amenities[] = $el('li', 'd-flex gap-2 mb-2', $a, array(
            $icon('bi-check2', 'text-primary flex-shrink-0'),
            $span($a, '', lang('Text')),
        ));
    }
    if ($buttons === null) {
        $buttons = array(
            $link(lang('Ask for This Room'), '{{page:book}}?' . $query(array('room' => $r['name'])), 'btn btn-primary rounded-pill px-4'),
            $link(lang('All Rooms'), '{{page:rooms}}', 'btn btn-outline-secondary rounded-pill px-4'),
        );
    }
    $gallery = $el('figure', 'pg-hotel-gallery position-relative', lang('Pictures'), array(
        $shot('pg-hotel-main', 'room-' . $key, $r['summary']),
        $shot('', 'room-' . $key . '-2', $r['amenities'][0]),
        $shot('', 'room-' . $key . '-3', $r['beds']),
        $el('div', 'badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1 position-absolute top-0 start-0 m-3', lang('Room view'), array(
            $icon($view['icon']),
            $span($view['title'], '', lang('Text')),
        )),
    ));
    return $el('section', $class, lang('Hotel (Room Showcase)'), array(
        $container(array(
            $row(array(
                $col(array(
                    $fx($gallery, array('effect' => 'fade', 'trigger' => $trigger, 'duration' => 900)),
                ), '', '7'),
                $col(array(
                    $para($room_types[$r['type']]['title'], 'pht-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading($tag, $r['name'], 'pht-display ' . ($tag === 'h1' ? 'display-5' : 'h2') . ' mb-3'),
                    $para($r['summary'], 'lead text-body-secondary mb-3'),
                    $el('div', 'd-flex align-items-baseline gap-2 mb-4', lang('Price'), array(
                        $span(lang('Starting from'), 'small text-body-secondary', lang('Text')),
                        $span($money($room_rates[$key]), 'pg-hotel-figure pht-price fs-2 fw-bold', lang('Price')),
                        $span(lang('/ night'), 'text-body-secondary', lang('Text')),
                    )),
                    $fx($el('dl', 'pg-hotel-facts border-top mb-4', lang('Facts'), array(
                        $fact('bi-people', lang('Guests'), sprintf(lang('Up to %s'), $r['guests'])),
                        $fact('bi-arrows-angle-expand', lang('Floor Area'), $r['size'] . ' m²'),
                        $fact('bi-moon', lang('Beds'), $r['beds']),
                        $fact($view['icon'], lang('Room view'), $view['title']),
                    )), array('effect' => 'slide-up', 'trigger' => $trigger, 'stagger' => 80)),
                    $heading($tag === 'h1' ? 'h2' : 'h3', lang('In the room'), 'pht-display h5 mb-3'),
                    $el('ul', 'list-unstyled row row-cols-1 row-cols-sm-2 g-0 mb-4', lang('Amenities'), $amenities),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
                ), '', '5'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, lang('room, suite, beds, size, view, amenities, rate')));
};

// The Hotel: Stay Clock block: the arrival and the departure as <time>s of
// $hotel (check-in, check-out) in three pg-hotel-end cards on a
// pg-hotel-clock row, the stay between them, and the reception's phone
// under it. The header fades and the cards rise on view. A search section
// under $id.
$stay_clock = function ($id, $class = 'py-5 bg-body-tertiary')
    use ($el, $heading, $para, $link, $icon, $span, $container, $row, $col, $fx, $rise, $attr, $searchable, $hotel) {
    $line = function ($icon_name, $text) use ($el, $icon, $span) {
        return $el('li', 'd-flex gap-2 mb-2', $text, array(
            $icon($icon_name, 'text-primary flex-shrink-0'),
            $span($text, '', lang('Text')),
        ));
    };
    $card = function ($icon_name, $title, $lead, $time, $notes, $md) use ($el, $heading, $para, $icon, $col, $attr) {
        $inner = array(
            $icon($icon_name, 'fs-3 text-primary d-block mb-2'),
            $heading('h3', $title, 'pht-display h5 mb-1'),
            $para($lead, 'small text-body-secondary ' . ($time === '' ? 'mb-3' : 'mb-2')),
        );
        if ($time !== '') {
            $inner[] = $el('time', 'pg-hotel-figure pht-price d-block display-5 fw-bold mb-3', lang('Time'), array(), array(
                'text' => $time, '_attrs' => array($attr('datetime', $time))));
        }
        $inner[] = $el('ul', 'list-unstyled small text-start mb-0', lang('Notes'), $notes);
        return $col(array($el('div', 'pg-hotel-end h-100 p-4 text-center', $title, $inner)), $md, '4');
    };
    return $el('section', $class, lang('Hotel (Stay Clock)'), array(
        $container(array(
            $fx($el('div', 'text-center col-lg-8 mx-auto mb-5', lang('Section Header'), array(
                $para(lang('Your stay'), 'pht-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                $heading('h2', lang('From arrival to departure'), 'pht-display h2 mb-2'),
                $para(lang('Tell us when you arrive with your request; we keep the room ready and your luggage safe.'), 'lead text-body-secondary mb-0'),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700)),
            $rise($row(array(
                $card('bi-door-open', lang('Arrival'), lang('Your room is ready from'), $hotel['check_in'], array(
                    $line('bi-suitcase', lang('Arriving early? We keep your luggage and the pool is yours.')),
                    $line('bi-car-front', lang('A driver at the airport on the day you arrive, if you ask for it with your booking.')),
                ), '6'),
                $card('bi-moon-stars', lang('During your stay'), $hotel['reception'], '', array(
                    $line('bi-cup-hot', lang('Breakfast from 7:30 to 10:30')),
                    $line('bi-water', lang('Pool, garden and beach towels')),
                    $line('bi-wifi', lang('Fast Wi-Fi')),
                    $line('bi-p-circle', lang('Free Parking')),
                ), '12'),
                $card('bi-box-arrow-right', lang('Departure'), lang('Please leave the room by'), $hotel['check_out'], array(
                    $line('bi-clock-history', lang('Late check-out until 14:00 when the room is free; ask at the reception.')),
                    $line('bi-bag', lang('Leaving later? Your bags wait at the reception.')),
                ), '6'),
            ), '4', 'center', 'pg-hotel-clock'), 120),
            $el('div', 'd-flex flex-wrap align-items-center justify-content-center gap-2 gap-sm-3 mt-4', lang('Reception'), array(
                $icon('bi-telephone', 'fs-5 text-primary flex-shrink-0'),
                $span(lang('Reception'), 'fw-semibold', lang('Text')),
                $link($hotel['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $hotel['phone']), 'fw-semibold link-primary'),
            )),
        )),
    ), $searchable($id, lang('check-in, check-out, arrival, departure, luggage, late check-out')));
};
