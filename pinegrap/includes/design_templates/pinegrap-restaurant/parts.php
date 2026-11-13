<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Restaurant", part 1 of its files: the node builders,
 * the restaurant's own data (the opening days and sittings, the courses of
 * the menu and the dishes it opens with, the diet tags, the tasting menus,
 * the wine list, the team, the private dining rooms, the events, the gift
 * vouchers, what guests say and the answers of the forms' pick lists) and
 * the parts every page repeats (account bar, header, footer, reserve band,
 * dish, tasting menu, wine, room, team and guest cards, page shells,
 * section headers, form fields, the comment thread, the staff bar and the
 * blog parts).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-restaurant.php puts them together.
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
// every opening until the restaurant puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-restaurant-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/f5e6ea/6b1d2f?text=' . rawurlencode($initials !== '' ? $initials : 'R');
};



// ── The restaurant: its days, its menu, its wines and its rooms ────────

// The restaurant itself. The about page tells its story; the footer, the
// reserve band and the contact lines use the address, phone and hours.
$restaurant = array(
    'tagline' => lang('A neighbourhood restaurant cooking with the seasons: what the farms bring in the morning is on the table at night'),
    'story'   => lang('Our chef opened the restaurant in an old bakery with twelve tables and a wood oven. The menu still changes with what the farmers, the fishermen and our own garden bring, the wine list leans on small growers, and the same welcome waits for a quick lunch as for a long dinner.'),
    'address' => lang('Market Lane 14, Old Town'),
    'region'  => lang('Two streets from the market square, with parking under the town hall'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'table@example.com',
);

// The days of the week with the restaurant's sittings on each: lunch and
// dinner, the first and the last table a reservation may take. A day with
// no sitting is a closing day (a test holds every bookable time to these).
$days = array(
    'mon' => array('title' => lang('Monday'), 'lunch' => false, 'dinner' => false),
    'tue' => array('title' => lang('Tuesday'), 'lunch' => false, 'dinner' => true),
    'wed' => array('title' => lang('Wednesday'), 'lunch' => false, 'dinner' => true),
    'thu' => array('title' => lang('Thursday'), 'lunch' => false, 'dinner' => true),
    'fri' => array('title' => lang('Friday'), 'lunch' => true, 'dinner' => true),
    'sat' => array('title' => lang('Saturday'), 'lunch' => true, 'dinner' => true),
    'sun' => array('title' => lang('Sunday'), 'lunch' => true, 'dinner' => false),
);
// The two sittings: the first and the last table, and the kitchen's close.
$sittings = array(
    'lunch'  => array('title' => lang('Lunch'), 'first' => '12:00', 'last' => '14:00', 'close' => '15:30'),
    'dinner' => array('title' => lang('Dinner'), 'first' => '18:30', 'last' => '21:30', 'close' => '23:30'),
);
// The opening hours in lines, the way the footer and the contact page
// show them.
$opening_hours = array(
    array('days' => lang('Tuesday to Thursday'), 'hours' => '18:30–23:30'),
    array('days' => lang('Friday and Saturday'), 'hours' => '12:00–15:30, 18:30–23:30'),
    array('days' => lang('Sunday'), 'hours' => '12:00–15:30'),
    array('days' => lang('Monday'), 'hours' => lang('Closed')),
);

// The times a table may be booked at: every half hour of each sitting,
// from its first table to its last (the reservation form's Time).
$time_choices = array();
foreach ($sittings as $s) {
    list($h, $m) = explode(':', $s['first']);
    $t = (int)$h * 60 + (int)$m;
    list($h, $m) = explode(':', $s['last']);
    for ($last = (int)$h * 60 + (int)$m; $t <= $last; $t += 30) $time_choices[] = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
}

// The courses of the menu, in the order of the menu page's tabs: each tab
// lists the dishes of its course (a list filtered by its words, the Menu
// form's Course answer).
$courses = array(
    'snacks'   => array('title' => lang('To Begin'), 'icon' => 'bi-basket',
        'note' => lang('Bread from the wood oven, a few bites to share and something to open the appetite.')),
    'starters' => array('title' => lang('Appetisers'), 'icon' => 'bi-flower1',
        'note' => lang('Light plates built around the vegetable, the fish or the cheese of the week.')),
    'mains'    => array('title' => lang('Main Courses'), 'icon' => 'bi-fire',
        'note' => lang('Cooked over the wood fire or slowly in the oven, with the garden on the side.')),
    'desserts' => array('title' => lang('Desserts'), 'icon' => 'bi-cake2',
        'note' => lang('The pastry kitchen\'s plates, the cheese board and a sweet wine to go with them.')),
);

// The diet tags a dish may carry (a check box group, so a dish can carry
// several) with the mark the menu's key gives each, and the badges.
$diets = array(
    array(lang('Vegetarian'), 'bi-leaf', 'success'),
    array(lang('Vegan'), 'bi-flower1', 'success'),
    array(lang('Gluten free'), 'bi-slash-circle', 'info'),
    array(lang('Contains nuts'), 'bi-exclamation-triangle', 'warning'),
    array(lang('Spicy'), 'bi-fire', 'danger'),
);
$badges = array(lang('New'), lang('Seasonal'), lang('Chef\'s pick'), lang('Sold out today'));

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A dish, a tasting menu or a voucher therefore shows one amount wherever
// it appears.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// The dishes the menu opens with: course, name, description, price per
// currency, diet tags, badge, shown on the home page, photo. Each is a
// record of the Menu form (its price written as words in the base
// currency), edited or deleted like any other.
$V = lang('Vegetarian'); $VG = lang('Vegan'); $GF = lang('Gluten free'); $NUT = lang('Contains nuts'); $HOT = lang('Spicy');
$dishes = array(
    'bread'    => array('course' => 'snacks', 'name' => lang('Wood Oven Bread'), 'diet' => array($VG), 'badge' => '', 'featured' => false,
        'text'  => lang('Sourdough from this morning\'s bake, cultured butter and olive oil from the coast.'),
        'price' => array('USD' => 6, 'EUR' => 5, 'GBP' => 5, 'TRY' => 180)),
    'oysters'  => array('course' => 'snacks', 'name' => lang('Oysters with Green Apple'), 'diet' => array($GF), 'badge' => '', 'featured' => false,
        'text'  => lang('Three oysters, green apple granita and a drop of shallot vinegar.'),
        'price' => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 480)),
    'croquette' => array('course' => 'snacks', 'name' => lang('Smoked Cheese Croquettes'), 'diet' => array($V), 'badge' => lang('Chef\'s pick'), 'featured' => true,
        'text'  => lang('Crisp outside, molten inside, with a sweet pepper jam.'),
        'price' => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 290)),
    'beetroot' => array('course' => 'starters', 'name' => lang('Roasted Beetroot and Goat\'s Cheese'), 'diet' => array($V, $GF, $NUT), 'badge' => '', 'featured' => false,
        'text'  => lang('Beetroot from the garden baked in salt, whipped goat\'s cheese and toasted hazelnuts.'),
        'price' => array('USD' => 14, 'EUR' => 13, 'GBP' => 11, 'TRY' => 450)),
    'crudo'    => array('course' => 'starters', 'name' => lang('Sea Bass Crudo'), 'diet' => array($GF, $HOT), 'badge' => lang('New'), 'featured' => true,
        'text'  => lang('Line-caught sea bass, citrus, green chilli and the first olive oil of the season.'),
        'price' => array('USD' => 17, 'EUR' => 16, 'GBP' => 14, 'TRY' => 560)),
    'soup'     => array('course' => 'starters', 'name' => lang('Wild Mushroom Broth'), 'diet' => array($VG, $GF), 'badge' => lang('Seasonal'), 'featured' => false,
        'text'  => lang('A clear broth of forest mushrooms with herbs and a few drops of truffle oil.'),
        'price' => array('USD' => 12, 'EUR' => 11, 'GBP' => 10, 'TRY' => 390)),
    'lamb'     => array('course' => 'mains', 'name' => lang('Slow-Roasted Lamb Shoulder'), 'diet' => array($GF), 'badge' => lang('Chef\'s pick'), 'featured' => true,
        'text'  => lang('Seven hours in the wood oven, smoked aubergine, yoghurt and mint.'),
        'price' => array('USD' => 34, 'EUR' => 31, 'GBP' => 27, 'TRY' => 1150)),
    'fish'     => array('course' => 'mains', 'name' => lang('Catch of the Day'), 'diet' => array($GF), 'badge' => '', 'featured' => false,
        'text'  => lang('Whatever the boats brought in, grilled whole over the fire with fennel and lemon.'),
        'price' => array('USD' => 32, 'EUR' => 29, 'GBP' => 25, 'TRY' => 1080)),
    'risotto'  => array('course' => 'mains', 'name' => lang('Pumpkin and Sage Risotto'), 'diet' => array($V, $GF), 'badge' => lang('Seasonal'), 'featured' => false,
        'text'  => lang('Roasted pumpkin, brown butter, sage and an aged mountain cheese.'),
        'price' => array('USD' => 24, 'EUR' => 22, 'GBP' => 19, 'TRY' => 790)),
    'chocolate' => array('course' => 'desserts', 'name' => lang('Dark Chocolate Tart'), 'diet' => array($V), 'badge' => '', 'featured' => true,
        'text'  => lang('A thin, dark chocolate tart with sea salt and crème fraîche.'),
        'price' => array('USD' => 11, 'EUR' => 10, 'GBP' => 9, 'TRY' => 360)),
    'pear'     => array('course' => 'desserts', 'name' => lang('Poached Pear and Almond'), 'diet' => array($V, $GF, $NUT), 'badge' => '', 'featured' => false,
        'text'  => lang('A pear poached in red wine with almond cream and a crisp tuile.'),
        'price' => array('USD' => 10, 'EUR' => 9, 'GBP' => 8, 'TRY' => 330)),
    'cheese'   => array('course' => 'desserts', 'name' => lang('Cheese from the Valley'), 'diet' => array($V), 'badge' => '', 'featured' => false,
        'text'  => lang('Three cheeses from the valley\'s small dairies, quince paste and oat crackers.'),
        'price' => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 490)),
);
// The records of the Menu form the template starts with.
$dish_records = array();
foreach ($dishes as $key => $d) {
    $dish_records[] = array(
        'course'      => $courses[$d['course']]['title'],
        'name'        => $d['name'],
        'description' => $d['text'],
        'price'       => $money($d['price']),
        'diet'        => $d['diet'],
        'badge'       => $d['badge'],
        'featured'    => $d['featured'] ? lang('Yes') : lang('No'),
        'photo'       => $photo('dish-' . $key, 800, 600),
    );
}

// The tasting menus: the courses in order, the price of the menu and of
// the wine pairing per guest. The tasting page and the home show them;
// each opens the reservation request with the menu chosen (?tasting=).
$tastings = array(
    'seasons' => array('title' => lang('Seasons Menu'), 'icon' => 'bi-tree', 'featured' => true,
        'text'    => lang('Seven courses that follow the week\'s market, served to the whole table.'),
        'courses' => array(lang('Oysters with Green Apple'), lang('Sea Bass Crudo'), lang('Wild Mushroom Broth'), lang('Catch of the Day'), lang('Slow-Roasted Lamb Shoulder'), lang('Cheese from the Valley'), lang('Dark Chocolate Tart')),
        'price'   => array('USD' => 95, 'EUR' => 88, 'GBP' => 76, 'TRY' => 3200),
        'pairing' => array('USD' => 60, 'EUR' => 55, 'GBP' => 48, 'TRY' => 2000)),
    'garden'  => array('title' => lang('Garden Menu'), 'icon' => 'bi-flower2', 'featured' => false,
        'text'    => lang('Six vegetarian courses from our own garden and the farms around the town.'),
        'courses' => array(lang('Wood Oven Bread'), lang('Roasted Beetroot and Goat\'s Cheese'), lang('Wild Mushroom Broth'), lang('Pumpkin and Sage Risotto'), lang('Cheese from the Valley'), lang('Poached Pear and Almond')),
        'price'   => array('USD' => 80, 'EUR' => 74, 'GBP' => 64, 'TRY' => 2700),
        'pairing' => array('USD' => 50, 'EUR' => 46, 'GBP' => 40, 'TRY' => 1700)),
    'lunch'   => array('title' => lang('Long Lunch'), 'icon' => 'bi-sun', 'featured' => false,
        'text'    => lang('Four courses at a slower pace, on Friday, Saturday and Sunday at lunch.'),
        'courses' => array(lang('Smoked Cheese Croquettes'), lang('Sea Bass Crudo'), lang('Catch of the Day'), lang('Dark Chocolate Tart')),
        'price'   => array('USD' => 55, 'EUR' => 50, 'GBP' => 44, 'TRY' => 1850),
        'pairing' => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1200)),
);
// A price per guest: "… per guest".
$per_guest = function (array $prices) use ($money) {
    return sprintf(lang('%s per guest'), $money($prices));
};

// The wine list by kind: name, where it grows, the grapes, the price of a
// glass (0: by the bottle only) and of a bottle. Small growers, made-up
// names, so no real house is named.
$wine_groups = array(
    'sparkling' => lang('Sparkling'),
    'white'     => lang('White'),
    'rose'      => lang('Rosé'),
    'red'       => lang('Red'),
    'sweet'     => lang('Sweet'),
);
$wines = array(
    array('group' => 'sparkling', 'name' => lang('Valley Brut'), 'region' => lang('Thrace'), 'grape' => lang('Chardonnay and Pinot Noir'),
        'glass' => array('USD' => 11, 'EUR' => 10, 'GBP' => 9, 'TRY' => 380), 'bottle' => array('USD' => 58, 'EUR' => 54, 'GBP' => 46, 'TRY' => 1950)),
    array('group' => 'white', 'name' => lang('Hillside White'), 'region' => lang('Aegean coast'), 'grape' => lang('Narince'),
        'glass' => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 300), 'bottle' => array('USD' => 42, 'EUR' => 39, 'GBP' => 33, 'TRY' => 1400)),
    array('group' => 'white', 'name' => lang('Stone Terrace'), 'region' => lang('Central Anatolia'), 'grape' => lang('Emir'),
        'glass' => array('USD' => 10, 'EUR' => 9, 'GBP' => 8, 'TRY' => 340), 'bottle' => array('USD' => 48, 'EUR' => 44, 'GBP' => 38, 'TRY' => 1600)),
    array('group' => 'rose', 'name' => lang('Summer Garden'), 'region' => lang('Aegean coast'), 'grape' => lang('Çalkarası'),
        'glass' => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 300), 'bottle' => array('USD' => 40, 'EUR' => 37, 'GBP' => 32, 'TRY' => 1350)),
    array('group' => 'red', 'name' => lang('Old Vines Red'), 'region' => lang('Eastern Anatolia'), 'grape' => lang('Öküzgözü'),
        'glass' => array('USD' => 11, 'EUR' => 10, 'GBP' => 9, 'TRY' => 380), 'bottle' => array('USD' => 52, 'EUR' => 48, 'GBP' => 41, 'TRY' => 1750)),
    array('group' => 'red', 'name' => lang('River Bend'), 'region' => lang('Thrace'), 'grape' => lang('Cabernet Franc'),
        'glass' => array('USD' => 12, 'EUR' => 11, 'GBP' => 10, 'TRY' => 400), 'bottle' => array('USD' => 60, 'EUR' => 55, 'GBP' => 48, 'TRY' => 2000)),
    array('group' => 'red', 'name' => lang('Cellar Reserve'), 'region' => lang('Central Anatolia'), 'grape' => lang('Kalecik Karası'),
        'glass' => array('USD' => 0, 'EUR' => 0, 'GBP' => 0, 'TRY' => 0), 'bottle' => array('USD' => 95, 'EUR' => 88, 'GBP' => 76, 'TRY' => 3200)),
    array('group' => 'sweet', 'name' => lang('Late Harvest'), 'region' => lang('Aegean coast'), 'grape' => lang('Muscat'),
        'glass' => array('USD' => 8, 'EUR' => 7, 'GBP' => 6, 'TRY' => 270), 'bottle' => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1500)),
);

// The team: the about page and the home read them.
$team = array(
    'chef'      => array('name' => lang('Elif Şahin'), 'role' => lang('Chef and Owner'),
        'text' => lang('Cooked in kitchens across the Mediterranean before coming home to open her own, with the market as her menu.')),
    'sous'      => array('name' => lang('Can Öztürk'), 'role' => lang('Head Chef'),
        'text' => lang('Runs the kitchen every night and keeps the wood fire at the right heat for every plate.')),
    'pastry'    => array('name' => lang('Maya Collins'), 'role' => lang('Pastry Chef'),
        'text' => lang('Bakes the bread at dawn and the tarts in the afternoon, and never repeats a dessert menu twice.')),
    'sommelier' => array('name' => lang('Kerem Aksoy'), 'role' => lang('Sommelier'),
        'text' => lang('Visits the growers on the list himself and finds the glass that suits your plate and your mood.')),
);

// The rooms for private dining: how many sit at the table, how many stand
// at a reception, and a line. The private dining page shows them; each
// opens the enquiry with the room chosen (?room=).
$rooms = array(
    'cellar'  => array('icon' => 'bi-door-closed', 'title' => lang('The Wine Cellar'), 'seated' => 12, 'standing' => 0,
        'text' => lang('One long oak table among the bottles, for a birthday, a family dinner or a tasting with our sommelier.')),
    'garden'  => array('icon' => 'bi-tree', 'title' => lang('The Garden Room'), 'seated' => 28, 'standing' => 40,
        'text' => lang('Glass doors onto the herb garden, for a wedding dinner, a company evening or a long summer lunch.')),
    'whole'   => array('icon' => 'bi-building', 'title' => lang('The Whole Restaurant'), 'seated' => 60, 'standing' => 90,
        'text' => lang('Every table and the terrace, the kitchen cooking only for you and your guests.')),
);

// The events, each a record of the Events form the template starts with:
// the weekday it falls on (1 = Monday), how many weeks after the first
// such day, the time, the kind, the price per guest and a line. The date
// is counted from the day the template opens, so every event is still to
// come ($event_on).
$events = array(
    array('title' => lang('Winemaker\'s Dinner'), 'weekday' => 4, 'weeks' => 1, 'time' => '19:30', 'kind' => lang('Wine dinner'),
        'price' => array('USD' => 110, 'EUR' => 100, 'GBP' => 88, 'TRY' => 3700),
        'text'  => lang('Five courses with five wines, and the grower at the table to tell their story.')),
    array('title' => lang('Saturday Cooking Class'), 'weekday' => 6, 'weeks' => 0, 'time' => '10:00', 'kind' => lang('Cooking class'),
        'price' => array('USD' => 85, 'EUR' => 78, 'GBP' => 68, 'TRY' => 2850),
        'text'  => lang('Shop at the market with the chef, cook three dishes in our kitchen and eat them together.')),
    array('title' => lang('Harvest Supper'), 'weekday' => 5, 'weeks' => 2, 'time' => '19:00', 'kind' => lang('Supper club'),
        'price' => array('USD' => 70, 'EUR' => 65, 'GBP' => 56, 'TRY' => 2350),
        'text'  => lang('One long table, sharing plates of the season and a guest cook from a farm we buy from.')),
    array('title' => lang('Sunday Jazz Lunch'), 'weekday' => 7, 'weeks' => 1, 'time' => '12:30', 'kind' => lang('Live music'),
        'price' => array('USD' => 55, 'EUR' => 50, 'GBP' => 44, 'TRY' => 1850),
        'text'  => lang('The Long Lunch menu with a trio playing in the garden room.')),
);
// The date of an event as Y-m-d: the first day strictly after today with
// that ISO weekday, plus $weeks weeks.
$event_on = function ($weekday, $weeks = 0) {
    $ts = strtotime('tomorrow');
    while ((int)date('N', $ts) !== (int)$weekday) $ts = strtotime('+1 day', $ts);
    return date('Y-m-d', strtotime('+' . (int)$weeks . ' week', $ts));
};

// The gift vouchers the shop sells (with the shop), with their prices.
// The SKUs name the template, so the products are its own.
$vouchers = array(
    'gift50'   => array('sku' => 'RESTAURANT-VOUCHER-50', 'icon' => 'bi-gift', 'title' => lang('Gift Voucher'),
        'text'  => lang('A voucher for lunch, dinner or a bottle from the list, valid for a year.'),
        'price' => array('USD' => 50, 'EUR' => 50, 'GBP' => 50, 'TRY' => 2000)),
    'gift100'  => array('sku' => 'RESTAURANT-VOUCHER-100', 'icon' => 'bi-gift', 'title' => lang('Gift Voucher for Dinner'),
        'text'  => lang('A larger voucher for a dinner for two, valid for a year.'),
        'price' => array('USD' => 100, 'EUR' => 100, 'GBP' => 100, 'TRY' => 4000)),
    'tasting'  => array('sku' => 'RESTAURANT-TASTING-TWO', 'icon' => 'bi-stars', 'title' => lang('Seasons Menu for Two'),
        'text'  => lang('The seven-course Seasons Menu with the wine pairing for two guests.'),
        'price' => array('USD' => 310, 'EUR' => 286, 'GBP' => 248, 'TRY' => 10400)),
    'class'    => array('sku' => 'RESTAURANT-COOKING-CLASS', 'icon' => 'bi-egg-fried', 'title' => lang('A Place at the Cooking Class'),
        'text'  => lang('A Saturday morning at the market and in our kitchen with the chef, lunch included.'),
        'price' => array('USD' => 85, 'EUR' => 78, 'GBP' => 68, 'TRY' => 2850)),
);

// The restaurant in four figures (the home and about pages).
$figures = array(
    array('value' => '12', 'label' => lang('Farms and growers we buy from')),
    array('value' => '120+', 'label' => lang('Wines on the list')),
    array('value' => '7', 'label' => lang('Courses on the Seasons Menu')),
    array('value' => '1', 'label' => lang('Wood oven, lit every day')),
);

// What guests say, each about one of the dishes or menus.
$testimonials = array(
    array('about' => lang('Seasons Menu'), 'name' => lang('Ece Yılmaz'), 'role' => lang('Guest, came for the Seasons Menu'),
        'quote' => lang('Seven courses and not one we would leave out. The sommelier\'s pairings turned a birthday into an evening we still talk about.')),
    array('about' => lang('Slow-Roasted Lamb Shoulder'), 'name' => lang('Tom Fletcher'), 'role' => lang('Guest, ordered the Slow-Roasted Lamb Shoulder'),
        'quote' => lang('The lamb falls off the bone and the smoked aubergine is worth the trip on its own. We book again before we leave.')),
    array('about' => lang('Saturday Cooking Class'), 'name' => lang('Zeynep Arslan'), 'role' => lang('Guest, joined the Saturday Cooking Class'),
        'quote' => lang('A morning at the market with the chef and lunch we cooked ourselves. The best present my husband ever gave me.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the guest chose.
$course_titles = array();
foreach ($courses as $c) $course_titles[] = $c['title'];
$diet_titles = array();
foreach ($diets as $d) $diet_titles[] = $d[0];
// The reservation's menu: à la carte or one of the tasting menus.
$tasting_choices = array(lang('À la carte'));
foreach ($tastings as $t) $tasting_choices[] = $t['title'];
$event_titles = array();
foreach ($events as $e) $event_titles[] = $e['title'];
$room_choices = array();
foreach ($rooms as $r) $room_choices[] = $r['title'];
$guest_choices = array('1', '2', '3', '4', '5', '6', '7', '8');
$occasion_choices = array(lang('No special occasion'), lang('Birthday'), lang('Anniversary'), lang('Business dinner'), lang('Celebration'), lang('Something else'));
$seating_choices = array(lang('No preference'), lang('Dining room'), lang('Garden terrace'), lang('Kitchen counter'));
$enquiry_kinds = array(lang('Birthday or family dinner'), lang('Wedding dinner'), lang('Company dinner'), lang('Drinks reception'), lang('Something else'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a reservation request, set by the front of house in the panel.
$reservation_statuses = array(lang('Request received'), lang('Table confirmed'), lang('Fully booked'), lang('Seated'), lang('Cancelled'));
// The states of a private dining enquiry, set by the events team in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Proposal sent'), lang('Confirmed'), lang('Declined'));

// The query string of a link that opens a form with a choice made
// (?tasting=, ?event=, ?room=, ?occasion=): the form's fields start with
// these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the reserve band above the footer unless the page
// is a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('reserve_band');
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
            $heading('h1', $title, 'prt-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'prt-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'prt-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the menus and the rooms are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'rt-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the chef's table and the way to book it.
// A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-fire', 'text-primary'),
        $span(lang('Six seats at the kitchen counter, one sitting a night: the chef\'s table.'), '', lang('Text')),
        $link(lang('Book the chef\'s table'), '{{page:book_online}}', 'link-primary fw-semibold'),
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

// The header: the restaurant's name, the menu and the way to reserve a
// table. The links to the current page are marked active on the server
// (smart active state), so one header serves every page. The gift
// vouchers the shop sells are linked from the shop pages only, so the
// header stands on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('menu', lang('The Menu')),
        array('tasting', lang('Tasting Menus')),
        array('wine', lang('Wine List')),
        array('events', lang('Events')),
        array('private_dining', lang('Private Dining')),
        array('about', lang('Our Story')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-egg-fried', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'prt-brand fs-3', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#rt-nav'),
                    $attr('aria-controls', 'rt-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav prt-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-check'),
                            $span(lang('Reserve a Table'), '', lang('Link Text')),
                        ), array('href' => '{{page:reserve}}')),
                    )),
                ), array('id' => 'rt-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the restaurant in a sentence and its
// profiles, the food and wine pages, the guests' pages, the restaurant's pages
// and how to reach it with the opening hours; at the bottom the year and
// the site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $restaurant, $opening_hours, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    // The menu's courses are tabs of one page, so the footer links the
    // pages, not a course.
    $eat_links = array(
        $link(lang('The Menu'), '{{page:menu}}', $plain),
        $link(lang('Tasting Menus'), '{{page:tasting}}', $plain),
        $link(lang('Wine List'), '{{page:wine}}', $plain),
        $link(lang('The Chef\'s Table'), '{{page:book_online}}', $plain),
    );
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };
    $hours = array();
    foreach ($opening_hours as $h) {
        $hours[] = $el('span', 'd-block', lang('Opening Hours'), array(
            $span($h['days'] . ': ', '', lang('Days')),
            $span($h['hours'], 'prt-price text-wrap', lang('Hours')),
        ));
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Restaurant'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-3 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-egg-fried', 'text-primary'),
                        $span('{{site_name}}', 'prt-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($restaurant['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-tiktok', 'TikTok'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Menu Links'), array(
                    $heading('h2', lang('Eat and Drink'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($eat_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Guests'), array(
                    $heading('h2', lang('For Guests'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Reserve a Table'), '{{page:reserve}}', $plain),
                        $link(lang('My Reservations'), '{{page:my_reservations}}', $plain),
                        $link(lang('The Chef\'s Table'), '{{page:book_online}}', $plain),
                        $link(lang('Private Dining'), '{{page:private_dining}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Restaurant'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Our Story'), '{{page:about}}', $plain),
                        $link(lang('Events'), '{{page:events}}', $plain),
                        $link(lang('Kitchen Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Getting Here'), '{{page:location}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Visit Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($restaurant['address'], '{{page:location}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($restaurant['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $restaurant['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($restaurant['email'], 'mailto:' . $restaurant['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-clock', $el('div', '', lang('Opening Hours'), $hours), lang('Opening Hours')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Cooked with the seasons, served with care.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The reserve band: a full-width band in the palette's colour with the
// way to book a table and to plan a private dinner. Shared, so every page
// that ends with it carries the same offer.
$reserve_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Your table is waiting'), 'prt-display h2 mb-2'),
                    $para(lang('Book lunch or dinner in a minute and we confirm by e-mail. For twelve guests or more, ask about the private rooms.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Reserve a Table'), '{{page:reserve}}', 'btn btn-secondary btn-lg px-4'),
                        $link(lang('Private Dining'), '{{page:private_dining}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A dish's card of the lists (the home's favourites): the photo with its
// badge, the course, the name linking the dish page, the price, the
// description and the diet tags. Every value is bound to the Menu form's
// field of the same name; the sample fills the canvas.
$dish_card = function ($sample_key = 'lamb') use ($el, $span, $para, $link, $col, $image, $icon, $bind, $dishes, $courses, $money, $photo, $attr) {
    $d = $dishes[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden prt-lift', lang('Menu Dish Card'), array(
            $el('div', 'position-relative', lang('Picture Area'), array(
                $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo('dish-' . $sample_key, 800, 600), $d['name'], 'w-100 h-100 object-fit-cover',
                        array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $span($d['badge'] !== '' ? $d['badge'] : lang('New'), 'pg-hide-if-empty badge bg-body text-body-emphasis position-absolute top-0 start-0 m-3', lang('Badge'), $bind('badge')),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($courses[$d['course']]['title'], 'prt-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Menu Course'), $bind('course')),
                $el('div', 'd-flex justify-content-between align-items-baseline gap-2 mb-2', lang('Dish Heading'), array(
                    $el('h3', 'prt-display h5 position-static mb-0', lang('Dish Title'), array(
                        $link($d['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                            array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                    )),
                    $span($money($d['price']), 'prt-price fw-semibold text-primary text-nowrap', lang('Price'), $bind('price')),
                )),
                $para($d['text'], 'small text-body-secondary mb-3', $bind('description')),
                $el('span', 'pg-hide-if-empty badge rounded-pill bg-success-subtle text-success-emphasis align-self-start mt-auto', lang('Diet'), array(
                    $icon('bi-leaf', 'me-1'),
                    $span($d['diet'] ? implode(', ', $d['diet']) : lang('Vegetarian'), 'pg-field-value', lang('Value'), $bind('diet')),
                )),
            )),
        )),
    ), '6', '3');
};

// A dish of a course's tab on the menu: the name with a rule that leads
// the eye to the price, the description and its tags; the name opens the
// dish page. A tag the dish has not got leaves with its badge.
$dish_line = function ($sample_key = 'lamb') use ($el, $span, $para, $link, $icon, $col, $bind, $dishes, $money) {
    $d = $dishes[$sample_key];
    return $col(array(
        $el('article', 'h-100 py-3 border-bottom', lang('Menu Dish'), array(
            $el('div', 'd-flex align-items-baseline gap-2', lang('Dish Heading'), array(
                $el('h3', 'prt-display h5 mb-0', lang('Dish Title'), array(
                    $link($d['name'], '#', 'link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $el('div', 'flex-grow-1 border-bottom', lang('Leader')),
                $span($money($d['price']), 'prt-price fw-semibold text-primary text-nowrap', lang('Price'), $bind('price')),
            )),
            $para($d['text'], 'small text-body-secondary mt-1 mb-2', $bind('description')),
            $el('div', 'd-flex flex-wrap gap-1', lang('Tags'), array(
                $span($d['badge'] !== '' ? $d['badge'] : lang('New'), 'pg-hide-if-empty badge text-bg-warning', lang('Badge'), $bind('badge')),
                $el('span', 'pg-hide-if-empty badge rounded-pill bg-success-subtle text-success-emphasis', lang('Diet'), array(
                    $icon('bi-leaf', 'me-1'),
                    $span($d['diet'] ? implode(', ', $d['diet']) : lang('Vegetarian'), 'pg-field-value', lang('Value'), $bind('diet')),
                )),
            )),
        )),
    ), '', '6');
};

// A tasting menu's card (the tasting page and the home): the name, the
// price per guest and of the wine pairing, a line, the courses in order
// and a link that opens the reservation request with the menu chosen. The
// menu most guests choose is marked.
$tasting_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon_tile, $tastings, $per_guest, $query) {
    $t = $tastings[$key];
    $top = array($icon_tile($t['icon']));
    if ($t['featured']) $top[] = $span(lang('Most chosen'), 'badge text-bg-primary', lang('Badge'));
    $items = array();
    foreach ($t['courses'] as $c) $items[] = $el('li', 'py-1 border-bottom', lang('Menu Course'), array(), array('text' => $c));
    return $col(array(
        $el('article', 'card h-100 shadow-sm ' . ($t['featured'] ? 'border-primary border-2' : 'border-0'), lang('Tasting Menu Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-start justify-content-between gap-2 mb-3', lang('Menu Heading'), $top),
                $heading('h3', $t['title'], 'prt-display h4 mb-1'),
                $para($per_guest($t['price']), 'prt-price h5 fw-bold mb-1'),
                $para(sprintf(lang('Wine pairing: %s'), $per_guest($t['pairing'])), 'small text-body-secondary mb-3'),
                $para($t['text'], 'small mb-3'),
                $el('ol', 'list-unstyled small text-body-secondary mb-4', lang('Course List'), $items),
                $link(lang('Reserve for this menu'), '{{page:reserve}}?' . $query(array('tasting' => $t['title'])),
                    ($t['featured'] ? 'btn btn-primary' : 'btn btn-outline-primary') . ' mt-auto'),
            )),
        )),
    ), '', '4');
};

// A kind of wine on the wine list: its name over a table of the wines,
// each with where it grows, the grapes, the glass and the bottle price.
$wine_group = function ($group) use ($el, $heading, $span, $wines, $wine_groups, $money, $attr) {
    $rows = array();
    foreach ($wines as $w) {
        if ($w['group'] !== $group) continue;
        $rows[] = $el('tr', '', lang('Wine'), array(
            $el('th', 'fw-semibold', lang('Wine Name'), array(
                $span($w['name'], 'd-block', lang('Name')),
                $span($w['region'] . ' · ' . $w['grape'], 'd-block small fw-normal text-body-secondary', lang('Region and Grape')),
            ), array('_attrs' => array($attr('scope', 'row')))),
            $el('td', 'prt-price text-end text-nowrap', lang('By the Glass'), array(), array('text' => $w['glass']['USD'] > 0 ? $money($w['glass']) : '–')),
            $el('td', 'prt-price text-end text-nowrap', lang('Bottle'), array(), array('text' => $money($w['bottle']))),
        ));
    }
    return $el('section', 'mb-5', $wine_groups[$group], array(
        $heading('h2', $wine_groups[$group], 'prt-display h3 mb-3'),
        $el('div', 'table-responsive', lang('Table Wrapper'), array(
            $el('table', 'table align-middle mb-0', lang('Wine Table'), array(
                $el('thead', '', lang('Table Head'), array(
                    $el('tr', '', lang('Row'), array(
                        $el('th', 'small text-body-secondary fw-semibold', lang('Wine'), array(), array('text' => lang('Wine'), '_attrs' => array($attr('scope', 'col')))),
                        $el('th', 'small text-body-secondary fw-semibold text-end', lang('By the Glass'), array(), array('text' => lang('By the Glass'), '_attrs' => array($attr('scope', 'col')))),
                        $el('th', 'small text-body-secondary fw-semibold text-end', lang('Bottle'), array(), array('text' => lang('Bottle'), '_attrs' => array($attr('scope', 'col')))),
                    )),
                )),
                $el('tbody', '', lang('Table Body'), $rows),
            )),
        )),
    ), array('id' => 'rt-wine-' . $group));
};

// A private dining room's card: the icon, the name, how many it seats and
// holds standing, a line and a link that opens the enquiry with the room
// chosen.
$room_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon, $icon_tile, $rooms, $query) {
    $r = $rooms[$key];
    $facts = array($el('li', 'd-inline-flex align-items-center gap-1', lang('Seated'), array(
        $icon('bi-people', 'text-primary'), $span(sprintf(lang('%d seated'), $r['seated']), '', lang('Value')),
    )));
    if ($r['standing'] > 0) {
        $facts[] = $el('li', 'd-inline-flex align-items-center gap-1', lang('Standing'), array(
            $icon('bi-cup-straw', 'text-primary'), $span(sprintf(lang('%d standing'), $r['standing']), '', lang('Value')),
        ));
    }
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm prt-lift', lang('Private Room Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($r['icon'], 'align-self-start mb-3'),
                $heading('h3', $r['title'], 'prt-display h4 mb-2'),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-3', lang('Room Details'), $facts),
                $para($r['text'], 'small text-body-secondary mb-4'),
                $link(lang('Ask about this room'), '{{page:private_enquiry}}?' . $query(array('room' => $r['title'])), 'btn btn-outline-primary mt-auto'),
            )),
        )),
    ), '', '4');
};

// A member of the team: the portrait, the name, the role and a line.
$team_card = function ($key) use ($el, $heading, $para, $span, $col, $image, $team, $portrait, $attr) {
    $t = $team[$key];
    return $col(array(
        $el('article', 'text-center', lang('Team Member'), array(
            $image($portrait($t['name'], 320), $t['name'], 'rounded-circle mb-3', array('width' => '160', 'height' => '160', '_attrs' => array($attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'prt-display h5 mb-1'),
            $span($t['role'], 'd-block small fw-semibold text-primary mb-2', lang('Role')),
            $para($t['text'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// What a guest says: the quote, the stars, the guest and what they came for.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Guest Quote'), array(
            $stars(),
            $el('blockquote', 'prt-quote mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Guest'), array(
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
                $item('staff_reservations', lang('Reservation Requests')),
                $item('staff_enquiries', lang('Private Dining Enquiries')),
                $item('dish_new', lang('New Dish')),
                $item('event_new', lang('New Event')),
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
    return $agree($id, 'consent', lang('I would like to receive the restaurant\'s news, new menus, events and Kitchen Journal posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// enquiry may carry a seating plan or a draft menu, so the default accepts
// PDFs, office files, text and pictures; one that takes only pictures is a
// collection's image kind (a dish's photo).
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
                    $link(lang('What the market brought in this week'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
