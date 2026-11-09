<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort", part 1 of its files: the node builders,
 * the resort's own data (the resort and its lift hours, the difficulties
 * and areas of the mountain, the sample slopes and lifts, today's
 * conditions, lift passes, season passes and rental with their prices, the
 * ski school's lessons, levels and ages, lodging, the sample events counted
 * from the day the template opens, the people, the figures, what guests say
 * and the answers of the forms' pick lists) and the small helpers the pages
 * share (page shell, page header, section header, steps, check lists,
 * questions).
 * cards.php and the page files of the same folder are included after it
 * in the same scope and build their trees with these variables;
 * includes/design_templates/pinegrap-skiresort.php puts them together.
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
// every opening until the resort puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-skiresort-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e0f2fe/075985?text=' . rawurlencode($initials !== '' ? $initials : 'S');
};

// ── The resort: its hours, its mountain and its slopes and lifts ────────

// The resort itself. The about page tells its story; the footer, the snow
// band and the conditions page use the address, phone and hours.
$resort = array(
    'tagline'  => lang('A family ski resort above the pine forest, with slopes for every level'),
    'story'    => lang('The resort began with one drag lift that the village built to keep its young people in the valley. Today eleven lifts carry skiers from the village to the summit bowl, the ski school teaches thousands of children every winter, and the families who started it still run the mountain huts.'),
    'address'  => lang('Valley Station, Pine Village'),
    'phone'    => '+90 555 000 00 00',
    'email'    => 'info@example.com',
    'hours'    => lang('Lifts run every day of the season, 08:30 to 16:30'),
    'season'   => lang('Open from the first good snow of winter until the snow melts in spring'),
    'altitude' => lang('From 1,450 m in the village to 2,600 m at the summit'),
    'licence'  => lang('A member of the national ski areas association'),
);

// The hours of the lifts and the offices, line by line (the conditions
// page, the home and the footer say them in a line).
$lift_hours = array(
    array('days' => lang('Lifts, every day'), 'hours' => '08:30 - 16:30'),
    array('days' => lang('Night skiing, Friday and Saturday'), 'hours' => '18:00 - 21:00'),
    array('days' => lang('Ticket office and rental shop'), 'hours' => '08:00 - 17:00'),
    array('days' => lang('Ski school desk'), 'hours' => '08:00 - 17:30'),
);

// The difficulties of a slope, as the piste markers colour them. The
// slope list filters on them (?difficulty=) in their own words.
$difficulties = array(
    'green' => array('icon' => 'bi-circle-fill', 'colour' => 'text-success', 'title' => lang('Green: Beginner'),
        'summary' => lang('Wide and gentle, for a first day on skis and for children.')),
    'blue'  => array('icon' => 'bi-square-fill', 'colour' => 'text-primary', 'title' => lang('Blue: Easy'),
        'summary' => lang('Groomed and not too steep, for skiers who can turn and stop.')),
    'red'   => array('icon' => 'bi-diamond-fill', 'colour' => 'text-danger', 'title' => lang('Red: Intermediate'),
        'summary' => lang('Steeper and narrower, for confident skiers on any snow.')),
    'black' => array('icon' => 'bi-diamond-half', 'colour' => 'text-body-emphasis', 'title' => lang('Black: Expert'),
        'summary' => lang('Steep, sometimes ungroomed, for experts only.')),
);

// The areas of the mountain. The slope and lift lists filter on them
// (?area=).
$areas = array(
    'village' => array('icon' => 'bi-house', 'title' => lang('Village Slopes'),
        'summary' => lang('The nursery slopes and the ski school meeting point, beside the village.')),
    'forest'  => array('icon' => 'bi-tree', 'title' => lang('Pine Forest'),
        'summary' => lang('Long runs through the trees, sheltered when the wind blows.')),
    'summit'  => array('icon' => 'bi-triangle', 'title' => lang('Summit Bowl'),
        'summary' => lang('Open slopes above the tree line, with the best snow of the mountain.')),
);

// The states of a slope and of a lift, set by the staff every morning:
// the Status field's answers.
$run_statuses = array(
    'open'    => lang('Open'),
    'closed'  => lang('Closed'),
    'on_hold' => lang('On Hold'),
);

// The kinds of lift.
$lift_kinds = array(
    'gondola'  => lang('Gondola'),
    'chair'    => lang('Chairlift'),
    'drag'     => lang('Drag Lift'),
    'carpet'   => lang('Magic Carpet'),
);

// The sample lifts: the records the Lifts form starts with.
$lifts = array(
    'valley_gondola' => array('title' => lang('Valley Gondola'), 'kind' => 'gondola', 'area' => 'village', 'status' => 'open',
        'ride' => lang('9 minutes'), 'capacity' => lang('2,400 skiers an hour')),
    'forest_chair'   => array('title' => lang('Forest Chair'), 'kind' => 'chair', 'area' => 'forest', 'status' => 'open',
        'ride' => lang('7 minutes'), 'capacity' => lang('1,800 skiers an hour')),
    'summit_chair'   => array('title' => lang('Summit Express'), 'kind' => 'chair', 'area' => 'summit', 'status' => 'on_hold',
        'ride' => lang('6 minutes'), 'capacity' => lang('2,000 skiers an hour')),
    'ridge_drag'     => array('title' => lang('Ridge Drag'), 'kind' => 'drag', 'area' => 'summit', 'status' => 'closed',
        'ride' => lang('4 minutes'), 'capacity' => lang('900 skiers an hour')),
    'nursery_carpet' => array('title' => lang('Nursery Carpet'), 'kind' => 'carpet', 'area' => 'village', 'status' => 'open',
        'ride' => lang('2 minutes'), 'capacity' => lang('600 skiers an hour')),
);

// The sample slopes: the records the Slopes form starts with. 'lift' is a
// key of $lifts (the lift that serves it); length and drop are words.
$slopes = array(
    'first_steps' => array('title' => lang('First Steps'), 'difficulty' => 'green', 'area' => 'village', 'lift' => 'nursery_carpet', 'status' => 'open',
        'length' => lang('300 m'), 'drop' => lang('40 m'),
        'summary' => lang('The nursery slope where the ski school starts every morning.'),
        'details' => array(lang('Fenced from the other slopes'), lang('Served by the magic carpet'), lang('Floodlit for night skiing'), lang('Instructors on the slope all day')),
        'featured' => true),
    'meadow' => array('title' => lang('Meadow Run'), 'difficulty' => 'blue', 'area' => 'village', 'lift' => 'valley_gondola', 'status' => 'open',
        'length' => lang('2.4 km'), 'drop' => lang('380 m'),
        'summary' => lang('A wide, gentle run from the gondola\'s middle station back to the village.'),
        'details' => array(lang('Groomed every night'), lang('Snowmaking along its whole length'), lang('A hut with a terrace halfway down'), lang('The way home at the end of the day')),
        'featured' => true),
    'pine_trail' => array('title' => lang('Pine Trail'), 'difficulty' => 'blue', 'area' => 'forest', 'lift' => 'forest_chair', 'status' => 'open',
        'length' => lang('3.1 km'), 'drop' => lang('520 m'),
        'summary' => lang('A winding run through the forest, sheltered on windy days.'),
        'details' => array(lang('Groomed every night'), lang('Good snow when the summit is closed'), lang('Views over the valley at every bend'), lang('A favourite of families')),
        'featured' => false),
    'fox' => array('title' => lang('Fox Run'), 'difficulty' => 'red', 'area' => 'forest', 'lift' => 'forest_chair', 'status' => 'open',
        'length' => lang('1.8 km'), 'drop' => lang('450 m'),
        'summary' => lang('A steeper line through the trees with a long, fast finish.'),
        'details' => array(lang('Groomed twice a week'), lang('Narrow in the middle section'), lang('Used for the club races'), lang('Timed slalom course on race days')),
        'featured' => true),
    'bowl' => array('title' => lang('Summit Bowl'), 'difficulty' => 'red', 'area' => 'summit', 'lift' => 'summit_chair', 'status' => 'on_hold',
        'length' => lang('2.2 km'), 'drop' => lang('600 m'),
        'summary' => lang('Open, sunny slopes above the tree line with the best snow on the mountain.'),
        'details' => array(lang('Groomed every night'), lang('Wide enough for big turns'), lang('Closed in strong wind'), lang('A view of three valleys from the top')),
        'featured' => false),
    'eagle' => array('title' => lang('Eagle Couloir'), 'difficulty' => 'black', 'area' => 'summit', 'lift' => 'ridge_drag', 'status' => 'closed',
        'length' => lang('900 m'), 'drop' => lang('380 m'),
        'summary' => lang('A steep, narrow couloir from the ridge, opened only after the patrol checks it.'),
        'details' => array(lang('Never groomed'), lang('Opened by the ski patrol'), lang('Avalanche gear recommended'), lang('Experts only')),
        'featured' => false),
);

// Today's conditions: what the staff write on the conditions page each
// morning. Words only, so the sample never reads as an old report.
$conditions = array(
    array('icon' => 'bi-rulers', 'label' => lang('Snow at the village'), 'value' => lang('65 cm')),
    array('icon' => 'bi-snow2', 'label' => lang('Snow at the summit'), 'value' => lang('140 cm')),
    array('icon' => 'bi-cloud-snow', 'label' => lang('Last snowfall'), 'value' => lang('20 cm overnight')),
    array('icon' => 'bi-thermometer-snow', 'label' => lang('Temperature'), 'value' => lang('-6 °C at the summit')),
    array('icon' => 'bi-wind', 'label' => lang('Wind'), 'value' => lang('Strong at the summit, calm in the forest')),
    array('icon' => 'bi-exclamation-triangle', 'label' => lang('Avalanche danger'), 'value' => lang('Moderate (2 of 5)')),
);

// ── Passes, rental and their prices ─────────────────────────────────────

// The lift passes. With the shop each is a product of the Lift Passes
// group; without it the passes page lists them with their prices. One
// table of prices feeds both, so the page and the cart show one figure.
$lift_passes = array(
    'half_day'  => array('title' => lang('Half-Day Pass'), 'who' => lang('Every lift from 12:30'),
        'includes' => lang('Every lift of the resort for the afternoon')),
    'day'       => array('title' => lang('One-Day Pass'), 'who' => lang('Every lift, all day'),
        'includes' => lang('Every lift of the resort, night skiing included')),
    'three_day' => array('title' => lang('Three-Day Pass'), 'who' => lang('Three days in a row'),
        'includes' => lang('Every lift of the resort for three days')),
    'six_day'   => array('title' => lang('Six-Day Pass'), 'who' => lang('Six days in a row'),
        'includes' => lang('Every lift of the resort for six days, and one free swim in the village pool')),
);
$lift_pass_prices = array(
    'half_day'  => array('USD' => 38, 'EUR' => 35, 'GBP' => 30, 'TRY' => 1300),
    'day'       => array('USD' => 55, 'EUR' => 50, 'GBP' => 44, 'TRY' => 1900),
    'three_day' => array('USD' => 150, 'EUR' => 138, 'GBP' => 120, 'TRY' => 5200),
    'six_day'   => array('USD' => 280, 'EUR' => 258, 'GBP' => 224, 'TRY' => 9700),
);

// The season passes: the whole winter. With the shop each is a product of
// the Season Passes group; the passes page lists them.
$season_passes = array(
    'adult'  => array('title' => lang('Adult Season Pass'), 'who' => lang('Ages 18 to 64'),
        'benefits' => array(lang('Every lift, every day of the season'), lang('Night skiing included'), lang('10% off lessons and rental'), lang('A free day pass for a friend'))),
    'young'  => array('title' => lang('Young Season Pass'), 'who' => lang('Ages 6 to 17'),
        'benefits' => array(lang('Every lift, every day of the season'), lang('Night skiing included'), lang('10% off lessons and rental'), lang('Free entry to the race days'))),
    'family' => array('title' => lang('Family Season Pass'), 'who' => lang('Two adults and their children under 18'),
        'benefits' => array(lang('Every lift for the whole family'), lang('Night skiing included'), lang('15% off lessons and rental'), lang('Two free day passes for friends'))),
    'senior' => array('title' => lang('Senior Season Pass'), 'who' => lang('Ages 65 and over'),
        'benefits' => array(lang('Every lift, every day of the season'), lang('Priority boarding at the gondola'), lang('10% off lessons and rental'), lang('Coffee at the summit hut on us'))),
);
$season_pass_prices = array(
    'adult'  => array('USD' => 690, 'EUR' => 640, 'GBP' => 550, 'TRY' => 24000),
    'young'  => array('USD' => 390, 'EUR' => 360, 'GBP' => 310, 'TRY' => 13500),
    'family' => array('USD' => 1650, 'EUR' => 1520, 'GBP' => 1320, 'TRY' => 57000),
    'senior' => array('USD' => 450, 'EUR' => 415, 'GBP' => 360, 'TRY' => 15500),
);

// The rental equipment, per day. With the shop each is a product of the
// Rental group; the rental page lists them.
$rental = array(
    'ski_set'       => array('icon' => 'bi-person-walking', 'title' => lang('Ski Set'),
        'includes' => lang('Skis, boots and poles, fitted by our technicians')),
    'snowboard_set' => array('icon' => 'bi-snow', 'title' => lang('Snowboard Set'),
        'includes' => lang('A board and boots, set up for your stance')),
    'kids_set'      => array('icon' => 'bi-balloon', 'title' => lang('Children\'s Set'),
        'includes' => lang('Skis, boots, poles and a helmet for children up to 12')),
    'helmet'        => array('icon' => 'bi-shield-check', 'title' => lang('Helmet'),
        'includes' => lang('A helmet in your size, cleaned after every use')),
);
$rental_prices = array(
    'ski_set'       => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1200),
    'snowboard_set' => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1200),
    'kids_set'      => array('USD' => 22, 'EUR' => 20, 'GBP' => 17, 'TRY' => 750),
    'helmet'        => array('USD' => 8, 'EUR' => 7, 'GBP' => 6, 'TRY' => 280),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A season pass's "… a season" and a rental's "… a day" words.
$season_price_text = function ($key) use ($season_pass_prices, $money) {
    return sprintf(lang('%s a season'), $money($season_pass_prices[$key]));
};
$rental_price_text = function ($key) use ($rental_prices, $money) {
    return sprintf(lang('%s a day'), $money($rental_prices[$key]));
};

// ── The ski school, lodging and events ──────────────────────────────────

// The levels and age groups of a lesson: the lesson request's lists
// (?level=, ?age=).
$levels = array(
    'first'        => lang('First time on snow'),
    'beginner'     => lang('Beginner'),
    'intermediate' => lang('Intermediate'),
    'advanced'     => lang('Advanced level'),
);
$age_groups = array(
    'little' => lang('Ages 4 to 6'),
    'kids'   => lang('Ages 7 to 12'),
    'teens'  => lang('Ages 13 to 17'),
    'adults' => lang('Adults'),
);

// The ski school's lessons. 'level' and 'age' are keys of $levels and
// $age_groups; a lesson card opens the request with all three chosen.
$lessons = array(
    'snow_cubs'   => array('title' => lang('Snow Cubs'), 'level' => 'first', 'age' => 'little', 'length' => lang('Five mornings, two hours each'),
        'summary' => lang('Games on the nursery slope that turn little ones into skiers by the end of the week.')),
    'junior'      => array('title' => lang('Junior Ski Group'), 'level' => 'beginner', 'age' => 'kids', 'length' => lang('Five days, 10:00 to 15:00 with lunch'),
        'summary' => lang('Small groups by level, lunch with the instructor and a race on the last day.')),
    'teen_board'  => array('title' => lang('Teen Snowboard Camp'), 'level' => 'intermediate', 'age' => 'teens', 'length' => lang('Five afternoons, three hours each'),
        'summary' => lang('Carving, the snow park and safe riding with instructors who ride every day.')),
    'adult_start' => array('title' => lang('Adult First Turns'), 'level' => 'first', 'age' => 'adults', 'length' => lang('Three mornings, two and a half hours each'),
        'summary' => lang('From putting on skis to riding the chairlift and the first blue run.')),
    'adult_grow'  => array('title' => lang('Adult Improvers'), 'level' => 'intermediate', 'age' => 'adults', 'length' => lang('Five mornings, two and a half hours each'),
        'summary' => lang('Parallel turns, the red runs and confidence on every kind of snow.')),
    'off_piste'   => array('title' => lang('Off-Piste with a Guide'), 'level' => 'advanced', 'age' => 'adults', 'length' => lang('One day with a mountain guide'),
        'summary' => lang('Fresh snow away from the slopes, with avalanche gear and a guide who knows the mountain.')),
);
$lesson_prices = array(
    'snow_cubs'   => array('USD' => 190, 'EUR' => 175, 'GBP' => 150, 'TRY' => 6500),
    'junior'      => array('USD' => 320, 'EUR' => 295, 'GBP' => 255, 'TRY' => 11000),
    'teen_board'  => array('USD' => 260, 'EUR' => 240, 'GBP' => 205, 'TRY' => 9000),
    'adult_start' => array('USD' => 150, 'EUR' => 138, 'GBP' => 120, 'TRY' => 5200),
    'adult_grow'  => array('USD' => 230, 'EUR' => 212, 'GBP' => 182, 'TRY' => 7900),
    'off_piste'   => array('USD' => 280, 'EUR' => 258, 'GBP' => 224, 'TRY' => 9700),
);

// The places to stay. A card opens the stay enquiry with its stay chosen
// (?stay=); 'from' is the lowest price a night, said as words.
$lodging = array(
    'hotel'     => array('icon' => 'bi-building', 'title' => lang('Summit Lodge Hotel'), 'sleeps' => lang('Rooms for two to four'),
        'where'   => lang('At the foot of the gondola'),
        'summary' => lang('Ski in, ski out, with a spa, a ski room with boot warmers and breakfast with a view of the slopes.')),
    'chalets'   => array('icon' => 'bi-house-heart', 'title' => lang('Pine Chalets'), 'sleeps' => lang('Chalets for six to ten'),
        'where'   => lang('In the forest, five minutes from the village'),
        'summary' => lang('Wooden chalets with a fireplace and a sauna, for families and groups of friends.')),
    'apartments' => array('icon' => 'bi-door-open', 'title' => lang('Village Apartments'), 'sleeps' => lang('Apartments for two to six'),
        'where'   => lang('In the village square'),
        'summary' => lang('Self-catering apartments beside the shops, the ski school and the bus to the lifts.')),
    'hut'       => array('icon' => 'bi-moon-stars', 'title' => lang('Mountain Hut'), 'sleeps' => lang('Dormitories for up to 24'),
        'where'   => lang('At the summit, by the top of the chairlift'),
        'summary' => lang('A night above the clouds and the first tracks in the morning, with dinner and breakfast.')),
);
$lodging_prices = array(
    'hotel'      => array('USD' => 180, 'EUR' => 165, 'GBP' => 142, 'TRY' => 6200),
    'chalets'    => array('USD' => 320, 'EUR' => 295, 'GBP' => 255, 'TRY' => 11000),
    'apartments' => array('USD' => 110, 'EUR' => 100, 'GBP' => 88, 'TRY' => 3800),
    'hut'        => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1550),
);
// A lesson's "From … " and a stay's "From … a night" words.
$lesson_price_text = function ($key) use ($lesson_prices, $money) {
    return sprintf(lang('From %s'), $money($lesson_prices[$key]));
};
$lodging_price_text = function ($key) use ($lodging_prices, $money) {
    return sprintf(lang('From %s a night'), $money($lodging_prices[$key]));
};

// The kinds of event. The event cards say it.
$event_kinds = array(
    'race'     => lang('Race'),
    'night'    => lang('Night Skiing'),
    'festival' => lang('Festival'),
    'family'   => lang('Family Day'),
    'torch'    => lang('Torchlight Descent'),
    'music'    => lang('Live Music'),
);

// The sample events: the records the Events form starts with. Each falls
// on the first such ISO weekday after today, plus 'weeks' weeks
// ($event_on), so every event is still to come when the template opens.
$events = array(
    'torchlight' => array('title' => lang('Torchlight Descent'), 'kind' => 'torch', 'weekday' => 6, 'weeks' => 0, 'time' => '18:30',
        'place' => lang('Meadow Run, down to the village square'), 'price' => lang('Free to watch'),
        'summary' => lang('The instructors ski down the mountain at dusk with torches, followed by fireworks and hot drinks in the square.'),
        'featured' => true),
    'club_race'  => array('title' => lang('Club Slalom Race'), 'kind' => 'race', 'weekday' => 7, 'weeks' => 1, 'time' => '10:00',
        'place' => lang('Fox Run'), 'price' => lang('Entry with a day pass'),
        'summary' => lang('A friendly slalom for every age, with medals for the fastest in each group.'),
        'featured' => true),
    'night_jam'  => array('title' => lang('Night Park Jam'), 'kind' => 'night', 'weekday' => 5, 'weeks' => 1, 'time' => '19:00',
        'place' => lang('First Steps and the snow park'), 'price' => lang('With a day pass'),
        'summary' => lang('Floodlit slopes, a DJ on the terrace and prizes for the best tricks.'),
        'featured' => true),
    'family_day' => array('title' => lang('Family Snow Day'), 'kind' => 'family', 'weekday' => 6, 'weeks' => 2, 'time' => '10:00',
        'place' => lang('Village Slopes'), 'price' => lang('Free for children under six'),
        'summary' => lang('Snowman building, sledging, a treasure hunt and taster lessons for the whole family.'),
        'featured' => false),
    'snow_fest'  => array('title' => lang('Winter Festival'), 'kind' => 'festival', 'weekday' => 6, 'weeks' => 3, 'time' => '12:00',
        'place' => lang('The village square'), 'price' => lang('Free entry'),
        'summary' => lang('Food stalls, local music, ice sculptures and a parade of the ski school.'),
        'featured' => false),
    'hut_music'  => array('title' => lang('Music at the Summit Hut'), 'kind' => 'music', 'weekday' => 4, 'weeks' => 2, 'time' => '16:00',
        'place' => lang('Summit Hut terrace'), 'price' => lang('Free with a lift pass'),
        'summary' => lang('A band on the terrace for the last hour of the lifts, then the gondola down at sunset.'),
        'featured' => false),
);
// The date of an event as Y-m-d: the first day strictly after today with
// that ISO weekday, plus $weeks weeks.
$event_on = function ($weekday, $weeks = 0) {
    $ts = strtotime('tomorrow');
    while ((int)date('N', $ts) !== (int)$weekday) $ts = strtotime('+1 day', $ts);
    return date('Y-m-d', strtotime('+' . (int)$weeks . ' week', $ts));
};
// A slope's details as the value of its Details field: one line per item.
$details_text = function ($list) {
    return implode("\n", $list);
};

// The resort's people (the about page). Fixed data, not a collection.
$people = array(
    array('name' => lang('Elif Kaya'), 'role' => lang('Resort Director'),
        'bio' => lang('Grew up in the village and runs the mountain with the families who built it.')),
    array('name' => lang('Marco Bianchi'), 'role' => lang('Head of the Ski School'),
        'bio' => lang('Trains the instructors and still teaches the Snow Cubs every morning.')),
    array('name' => lang('Can Öztürk'), 'role' => lang('Mountain Operations Manager'),
        'bio' => lang('Decides each morning which slopes and lifts open, with the ski patrol.')),
    array('name' => lang('Anna Berg'), 'role' => lang('Guest Services'),
        'bio' => lang('Answers every lodging and group enquiry and plans the resort\'s events.')),
);

// The resort in four figures (the home and the about page).
$figures = array(
    array('value' => '45 km', 'label' => lang('Of marked slopes')),
    array('value' => '11', 'label' => lang('Lifts from the village to the summit')),
    array('value' => '1,150 m', 'label' => lang('Of vertical drop')),
    array('value' => '80', 'label' => lang('Instructors in the ski school')),
);

// What guests say (the home and the ski school page).
$testimonials = array(
    array('name' => lang('Zeynep Arslan'), 'role' => lang('Mother of two Snow Cubs'),
        'quote' => lang('By Friday both children were skiing down Meadow Run with us. The instructors made every morning a game.')),
    array('name' => lang('Tom Fletcher'), 'role' => lang('Season pass holder'),
        'quote' => lang('The forest runs are the best in the region on a windy day, and the lifts are never crowded.')),
    array('name' => lang('Selin Aydın'), 'role' => lang('Organised a club trip'),
        'quote' => lang('Forty skiers, chalets, passes and lessons arranged with one enquiry. We are coming back next winter.')),
);

// ── The answers of the forms' pick lists ────────────────────────────────
// Each answer's value is its own words, so the e-mails and the lists read
// as the guest chose.
$difficulty_titles = array();
foreach ($difficulties as $d) $difficulty_titles[] = $d['title'];
$area_titles = array();
foreach ($areas as $a) $area_titles[] = $a['title'];
$status_titles = array_values($run_statuses);
$lift_kind_titles = array_values($lift_kinds);
$level_titles = array_values($levels);
$age_titles = array_values($age_groups);
$lesson_titles = array();
foreach ($lessons as $l) $lesson_titles[] = $l['title'];
// The lesson request's lesson: each lesson by title, or a private lesson.
$lesson_choices = array_merge($lesson_titles, array(lang('A private lesson')));
$lesson_day_choices = array(lang('One day'), lang('Three days'), lang('Five days'), lang('Not sure yet'));
$sport_choices = array(
    array(lang('Ski'), lang('Ski')),
    array(lang('Snowboard'), lang('Snowboard')),
);
$skier_counts = array('1', '2', '3', '4', '5', '6');
// The stay enquiry's stay: each place by title, or not decided yet.
$stay_titles = array();
foreach ($lodging as $s) $stay_titles[] = $s['title'];
$stay_choices = array_merge($stay_titles, array(lang('Not decided yet')));
$group_kinds = array(lang('Family or friends'), lang('School trip'), lang('Ski club or team'), lang('Company event'), lang('Something else'));
$night_choices = array(lang('2 nights'), lang('3 nights'), lang('4 nights'), lang('5 nights'), lang('6 nights'), lang('7 nights or more'));
$event_kind_titles = array_values($event_kinds);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a lesson request and of a stay or group enquiry, set by
// the resort in the panel.
$lesson_statuses = array(lang('Request received'), lang('Instructor booked'), lang('Waiting list'), lang('Cancelled'));
$enquiry_statuses = array(lang('Enquiry received'), lang('Offer sent'), lang('Confirmed'), lang('Closed'));

// The words of a sample record's pick list answers.
$slope_difficulty = function ($key) use ($slopes, $difficulties) {
    return $difficulties[$slopes[$key]['difficulty']]['title'];
};
$slope_area = function ($key) use ($slopes, $areas) {
    return $areas[$slopes[$key]['area']]['title'];
};
$slope_status = function ($key) use ($slopes, $run_statuses) {
    return $run_statuses[$slopes[$key]['status']];
};
$lift_status = function ($key) use ($lifts, $run_statuses) {
    return $run_statuses[$lifts[$key]['status']];
};

// The query string of a link that opens a list with a filter set
// (?difficulty=, ?area=) or a form with a choice made (?lesson=, ?level=,
// ?age=, ?stay=, ?group=): the form's fields start with these values
// (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// ── Page helpers ────────────────────────────────────────────────────────

// A page: the account bar, the header, the page's own content and the
// shared footer, with the snow band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('snow_band');
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
            $heading('h1', $title, 'psk-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'psk-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'psk-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the conditions and the ways to
// the resort are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'skr-faq') use ($el, $para, $attr, $searchable) {
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
