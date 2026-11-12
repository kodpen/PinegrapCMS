<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Bakery", part 1 of its files: the node builders, the
 * bakery's own data (its opening hours and pickup times, the kinds of bake
 * and the daily bake with their prices, the celebration cakes with their
 * sizes, sponges and fillings, the baking classes and their levels, the
 * wholesale ranges, what customers say and the answers of the forms' pick
 * lists) and the parts every page repeats (account bar, header, footer,
 * order band, bake list, cake, class, range and customer cards, page
 * shells, section headers, form fields, the comment thread, the staff bar
 * and the blog parts).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-bakery.php puts them together.
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
// every opening until the bakery puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-bakery-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ffe4e6/881337?text=' . rawurlencode($initials !== '' ? $initials : 'B');
};


// ── The bakery: its bakes, its cakes, its classes and its trade ────────

// The bakery itself. The about page tells its story; the footer, the
// order band and the contact lines use the address, phone and hours.
$bakery = array(
    'tagline' => lang('A neighbourhood bakery and patisserie: bread from the oven every morning, cakes made to order and classes for anyone who wants to bake'),
    'story'   => lang('Two bakers opened the shop in an old corner dairy with one oven and a sourdough starter they had kept for years. Today the bakery lights the ovens before dawn, bakes for the street, for local cafés and for the celebrations of the neighbourhood, and teaches what it knows at the long table in the back.'),
    'address' => lang('Mill Lane 14, Old Town'),
    'region'  => lang('On the corner of the market square, with parking for bikes and prams at the door'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'hello@example.com',
);

// The shop's opening hours in three lines, the way the footer and the
// visit page show them.
$opening_hours = array(
    array('days' => lang('Tuesday to Friday'), 'hours' => '07:00–19:00'),
    array('days' => lang('Saturday and Sunday'), 'hours' => '08:00–16:00'),
    array('days' => lang('Monday'), 'hours' => lang('Closed, the ovens rest'), 'closed' => true),
);

// The oven's morning (the home's hero): when the ovens are lit, when each
// of the day's batches comes out and the last order for the next day (the
// cut-off of $pickup['bakes']). Times of the day only, never a date.
$oven = array(
    'lit'        => '04:00',
    'batches'    => array(
        array('time' => '07:00', 'name' => lang('Sourdough and rye')),
        array('time' => '08:30', 'name' => lang('Croissants and cardamom buns')),
        array('time' => '11:00', 'name' => lang('Focaccia and the cakes of the day')),
    ),
    'last_order' => '14:00',
);

// When an order is collected: the pickup windows the bakery keeps and how
// far ahead each kind of order is placed (the how-to-order page, the
// notice and the FAQ). Counted from the order, never a fixed date.
$pickup = array(
    'window'    => lang('Orders are collected at the counter between 10:00 and 18:00 on the day you choose.'),
    'bakes'     => lang('Bread, pastries and gift boxes: order by 14:00 for the next day.'),
    'cakes'     => lang('Celebration cakes from the shop: order two days ahead.'),
    'custom'    => lang('Custom cakes: ask at least a week ahead; tiered wedding cakes a month ahead.'),
);

// The kinds of bake the daily bake lists, each in a section of its own.
$categories = array(
    'bread'  => array('icon' => 'bi-basket', 'title' => lang('Breads'),
        'text' => lang('Slow sourdough and yeasted loaves, shaped by hand and baked dark in the stone oven.')),
    'pastry' => array('icon' => 'bi-egg-fried', 'title' => lang('Pastries'),
        'text' => lang('Croissants, buns and morning pastries, laminated with butter over three days.')),
    'cake'   => array('icon' => 'bi-cake', 'title' => lang('Cakes by the Slice'),
        'text' => lang('The cakes of the day, sold by the slice at the counter or whole to take home.')),
    'tart'   => array('icon' => 'bi-pie-chart', 'title' => lang('Tarts and Biscuits'),
        'text' => lang('Fruit tarts of the season, biscuits and small sweet things for the afternoon.')),
);

// What a bake is free from or holds: the bakes' badges.
$diet_tags = array(
    'vegan'  => lang('Vegan'),
    'nuts'   => lang('Contains nuts'),
    'rye'    => lang('Wheat-free'),
    'dairy'  => lang('Contains dairy'),
);

// The daily bake: what the counter sells every day, with the price of one
// per currency. The daily bake page lists them by category.
$bakes = array(
    'sourdough' => array('category' => 'bread', 'name' => lang('Country Sourdough'), 'tags' => array('vegan'),
        'price'   => array('USD' => 8, 'EUR' => 7, 'GBP' => 6, 'TRY' => 180),
        'summary' => lang('Our everyday loaf: a long, slow rise, a dark crust and an open, chewy crumb.')),
    'rye'       => array('category' => 'bread', 'name' => lang('Seeded Rye'), 'tags' => array('vegan', 'rye'),
        'price'   => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 200),
        'summary' => lang('Dense and dark, full of sunflower and pumpkin seeds; it keeps for a week.')),
    'focaccia'  => array('category' => 'bread', 'name' => lang('Rosemary Focaccia'), 'tags' => array('vegan'),
        'price'   => array('USD' => 7, 'EUR' => 6, 'GBP' => 5, 'TRY' => 150),
        'summary' => lang('Soft and golden, with olive oil, sea salt and rosemary from the garden.')),
    'croissant' => array('category' => 'pastry', 'name' => lang('Butter Croissant'), 'tags' => array('dairy'),
        'price'   => array('USD' => 4, 'EUR' => 3, 'GBP' => 3, 'TRY' => 85),
        'summary' => lang('Three days in the making, crisp on the outside and soft in a hundred layers inside.')),
    'bun'       => array('category' => 'pastry', 'name' => lang('Cardamom Bun'), 'tags' => array('dairy'),
        'price'   => array('USD' => 4, 'EUR' => 4, 'GBP' => 3, 'TRY' => 90),
        'summary' => lang('Knotted by hand, rolled in cardamom sugar and gone by eleven most mornings.')),
    'carrot'    => array('category' => 'cake', 'name' => lang('Carrot Cake'), 'tags' => array('nuts', 'dairy'),
        'price'   => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 120),
        'summary' => lang('Spiced and moist, with walnuts and a thick layer of cream cheese icing.')),
    'chocolate' => array('category' => 'cake', 'name' => lang('Dark Chocolate Cake'), 'tags' => array('dairy'),
        'price'   => array('USD' => 6, 'EUR' => 5, 'GBP' => 4, 'TRY' => 130),
        'summary' => lang('Three layers of dark sponge and a glossy ganache: the cake the regulars ask for.')),
    'fruit'     => array('category' => 'tart', 'name' => lang('Seasonal Fruit Tart'), 'tags' => array('dairy'),
        'price'   => array('USD' => 6, 'EUR' => 5, 'GBP' => 5, 'TRY' => 140),
        'summary' => lang('Crisp pastry, vanilla cream and the best fruit of the market that week.')),
    'cookie'    => array('category' => 'tart', 'name' => lang('Brown Butter Cookie'), 'tags' => array('dairy'),
        'price'   => array('USD' => 3, 'EUR' => 3, 'GBP' => 2, 'TRY' => 70),
        'summary' => lang('Chewy in the middle, with dark chocolate and a pinch of sea salt.')),
);

// The occasions a celebration cake is made for: the cake order's first
// question and the cakes' badges.
$occasions = array(
    'birthday'    => lang('Birthday'),
    'wedding'     => lang('Wedding'),
    'celebration' => lang('Celebration'),
    'corporate'   => lang('Company event'),
);

// The sizes of a celebration cake, how many it serves and the price the
// size starts at, the plainest cake's (the size guide of the cakes page).
$cake_sizes = array(
    'small'  => array('title' => lang('15 cm round'), 'servings' => lang('8 to 10 slices'),
        'price' => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1600)),
    'medium' => array('title' => lang('20 cm round'), 'servings' => lang('14 to 18 slices'),
        'price' => array('USD' => 65, 'EUR' => 60, 'GBP' => 52, 'TRY' => 2300)),
    'large'  => array('title' => lang('25 cm round'), 'servings' => lang('24 to 30 slices'),
        'price' => array('USD' => 80, 'EUR' => 74, 'GBP' => 64, 'TRY' => 2850)),
    'tiered' => array('title' => lang('Two tiers'), 'servings' => lang('40 to 60 slices'),
        'price' => array('USD' => 240, 'EUR' => 220, 'GBP' => 190, 'TRY' => 8500)),
);

// The sponges and the fillings a cake order chooses from.
$flavours = array(lang('Vanilla'), lang('Dark chocolate'), lang('Lemon and poppy seed'), lang('Carrot and walnut'), lang('Red velvet'));
$fillings = array(lang('Vanilla buttercream'), lang('Chocolate ganache'), lang('Salted caramel'), lang('Raspberry jam and cream'), lang('Lemon curd'));

// The celebration cakes: the cakes page shows them, each opening the cake
// order with the cake chosen; with the shop the smaller ones are sold
// whole for pickup. 'price' is the smallest size's, 'sizes' the sizes it
// is made in.
$cakes = array(
    'naked'    => array('name' => lang('Naked Berry Cake'), 'occasion' => 'birthday', 'sizes' => array('small', 'medium', 'large'),
        'price'   => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1600),
        'summary' => lang('Vanilla sponge, cream and fresh berries, with the sides left bare: simple and generous.')),
    'drip'     => array('name' => lang('Chocolate Drip Cake'), 'occasion' => 'birthday', 'sizes' => array('small', 'medium', 'large'),
        'price'   => array('USD' => 55, 'EUR' => 50, 'GBP' => 44, 'TRY' => 1950),
        'summary' => lang('Dark chocolate layers, salted caramel and a ganache drip, topped the way you like.')),
    'floral'   => array('name' => lang('Pressed Flower Cake'), 'occasion' => 'celebration', 'sizes' => array('small', 'medium', 'large'),
        'price'   => array('USD' => 65, 'EUR' => 60, 'GBP' => 52, 'TRY' => 2300),
        'summary' => lang('Lemon sponge and pale buttercream with edible flowers pressed into the sides.')),
    'wedding'  => array('name' => lang('Two-Tier Wedding Cake'), 'occasion' => 'wedding', 'sizes' => array('tiered'),
        'price'   => array('USD' => 240, 'EUR' => 220, 'GBP' => 190, 'TRY' => 8500),
        'summary' => lang('Two tiers in the flavours you choose at a tasting, finished to match the day.')),
);

// The levels a baking class is open to: the class list's filter (?level=).
// Each answer's value is its own words, so a filter link reads as it shows.
$levels = array(
    'first'    => lang('First time at the bench'),
    'home'     => lang('Home bakers'),
    'keen'     => lang('Keen bakers'),
);

// The baking classes: the records the Baking Classes form starts with. The
// class list, the class page and the home read them; 'minutes' is how long
// a class lasts, 'seats' how many bake at the long table, 'price' a place.
$baking_classes = array(
    'sourdough' => array('name' => lang('Sourdough at Home'), 'level' => 'first', 'minutes' => 180, 'seats' => 8, 'icon' => 'bi-basket',
        'price'   => array('USD' => 85, 'EUR' => 78, 'GBP' => 68, 'TRY' => 2900),
        'teacher' => lang('Elif Şahin'),
        'summary' => lang('Feed a starter, shape a loaf and bake it: you go home with your bread, your starter and the method.')),
    'croissant' => array('name' => lang('Croissants and Laminated Dough'), 'level' => 'keen', 'minutes' => 240, 'seats' => 6, 'icon' => 'bi-layers',
        'price'   => array('USD' => 120, 'EUR' => 110, 'GBP' => 96, 'TRY' => 4100),
        'teacher' => lang('Jonas Berg'),
        'summary' => lang('Fold butter into dough the way the bakery does, then shape croissants and pains au chocolat.')),
    'cakes'     => array('name' => lang('Cake Decorating'), 'level' => 'home', 'minutes' => 180, 'seats' => 8, 'icon' => 'bi-brush',
        'price'   => array('USD' => 95, 'EUR' => 88, 'GBP' => 76, 'TRY' => 3300),
        'teacher' => lang('Elif Şahin'),
        'summary' => lang('Level, fill and ice a layer cake, pipe a border and finish it with flowers to take home.')),
    'kids'      => array('name' => lang('Little Bakers'), 'level' => 'first', 'minutes' => 90, 'seats' => 10, 'icon' => 'bi-balloon',
        'price'   => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1200),
        'teacher' => lang('Jonas Berg'),
        'summary' => lang('Children from seven bake and decorate biscuits and buns, with a grown-up close by.')),
);

// What the bakery bakes for the trade: the wholesale page's ranges and the
// enquiry's Range answers (?range=).
$wholesale_ranges = array(
    'bread'   => array('icon' => 'bi-basket', 'title' => lang('Bread for restaurants'),
        'text' => lang('Sourdough, rye and focaccia delivered before service, sliced if you ask.')),
    'pastry'  => array('icon' => 'bi-cup-hot', 'title' => lang('Pastries for cafés'),
        'text' => lang('Croissants, buns and cookies baked that morning, delivered by eight.')),
    'cakes'   => array('icon' => 'bi-cake', 'title' => lang('Cakes for the counter'),
        'text' => lang('Whole cakes and traybakes, cut into portions and ready for your display.')),
    'events'  => array('icon' => 'bi-building', 'title' => lang('Office and event platters'),
        'text' => lang('Breakfast pastries and sweet platters for meetings, launches and staff days.')),
);

// The bakery in four figures (the home and about pages).
$figures = array(
    array('value' => $oven['lit'], 'label' => lang('When the ovens are lit')),
    array('value' => '48', 'label' => lang('Hours a sourdough takes')),
    array('value' => '20+', 'label' => lang('Cafés and restaurants we bake for')),
    array('value' => '3', 'label' => lang('Bakers on the team')),
);

// What customers say, each about a bake, a cake or a class.
$testimonials = array(
    array('about' => lang('Country Sourdough'), 'name' => lang('Defne Koç'), 'role' => lang('Buys the Country Sourdough every Saturday'),
        'quote' => lang('I have tried every bakery in town. This is the loaf my family asks for, and it is still good on Wednesday.')),
    array('about' => lang('Chocolate Drip Cake'), 'name' => lang('Oliver Hayes'), 'role' => lang('Ordered a Chocolate Drip Cake'),
        'quote' => lang('They answered within the hour, made exactly the cake my daughter drew, and it tasted even better than it looked.')),
    array('about' => lang('Sourdough at Home'), 'name' => lang('Merve Aksoy'), 'role' => lang('Took Sourdough at Home'),
        'quote' => lang('Three hours, a lot of flour and a loaf I was proud of. My starter is still alive two months later.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the customer chose.
$occasion_choices = array_values($occasions);
$cake_names = array();
foreach ($cakes as $c) $cake_names[] = $c['name'];
// A cake order may ask for a design of its own beside the listed cakes.
$cake_choices = array_merge($cake_names, array(lang('My own design')));
$size_choices = array();
foreach ($cake_sizes as $s) $size_choices[] = sprintf(lang('%1$s, %2$s'), $s['title'], $s['servings']);
$flavour_choices = $flavours;
$filling_choices = $fillings;
$class_names = array();
foreach ($baking_classes as $c) $class_names[] = $c['name'];
$level_titles = array_values($levels);
$range_titles = array();
foreach ($wholesale_ranges as $r) $range_titles[] = $r['title'];
$business_choices = array(lang('Café'), lang('Restaurant'), lang('Hotel'), lang('Shop or deli'), lang('Office'), lang('Something else'));
$delivery_day_choices = array(lang('Tuesday'), lang('Wednesday'), lang('Thursday'), lang('Friday'), lang('Saturday'), lang('Sunday'));
$fulfilment_choices = array(
    array(lang('I will collect it'), lang('I will collect it')),
    array(lang('Please deliver it'), lang('Please deliver it')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a cake order, set by the bakery in the panel.
$cake_statuses = array(lang('Request received'), lang('Quote sent'), lang('Confirmed'), lang('Ready to collect'), lang('Picked up'), lang('Cancelled'));
// The states of a class place request, set by the bakery in the panel.
$booking_statuses = array(lang('Request received'), lang('Place confirmed'), lang('This class is full'), lang('Attended'), lang('Cancelled'));
// The states of a wholesale enquiry, set by the bakery in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Samples arranged'), lang('Account opened'), lang('Declined'));

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A bake therefore shows one amount wherever it appears.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A cake's "From …" words: the price of its smallest size.
$from_price = function (array $prices) use ($money) {
    return sprintf(lang('From %s'), $money($prices));
};

// The query string of a link that opens a list with a filter set
// (?level=) or a form with a choice made (?cake=, ?occasion=, ?class=, ?range=):
// the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the order band above the footer unless the page is
// a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('order_band');
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
            $heading('h1', $title, 'pbk-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pbk-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pbk-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the wholesale ranges are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what customers say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'bky-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: how far ahead a cake is ordered, and the
// way to ask for one. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-cake2', 'text-primary'),
        $span(lang('Celebration cakes are made to order: ask a week ahead for a cake of your own design.'), '', lang('Text')),
        $link(lang('Order a cake'), '{{page:cake_order}}', 'link-primary fw-semibold'),
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

// The header: the bakery's name, the menu and the way to order a cake.
// The links to the current page are marked active on the server (smart
// active state), so one header serves every page. The shop is linked from
// the shop pages and the how-to-order page only, so the header stands on a
// site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('bakes', lang('The Daily Bake')),
        array('cakes', lang('Celebration Cakes')),
        array('how_to_order', lang('Order and Collect')),
        array('classes', lang('Baking Classes')),
        array('wholesale', lang('Wholesale')),
        array('visit', lang('Visit the Bakery')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-cake2-fill', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pbk-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#bky-nav'),
                    $attr('aria-controls', 'bky-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-pencil-square'),
                            $span(lang('Order a Cake'), '', lang('Link Text')),
                        ), array('href' => '{{page:cake_order}}')),
                    )),
                ), array('id' => 'bky-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the bakery in a sentence and its profiles,
// the bakes by kind, the orders and classes, the bakery's pages and how to
// reach it with the opening hours; at the bottom the year and the name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $categories, $levels, $bakery, $opening_hours, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $bake_links = array();
    foreach ($categories as $key => $c) $bake_links[] = $link($c['title'], '{{page:bakes}}#bky-cat-' . $key, $plain);
    $bake_links[] = $link(lang('Celebration Cakes'), '{{page:cakes}}', $plain);
    $class_links = array($link(lang('Baking Classes'), '{{page:classes}}', $plain));
    foreach ($levels as $title) $class_links[] = $link($title, '{{page:classes}}?' . $query(array('level' => $title)), $plain);
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
            $span($h['hours'], 'pbk-price', lang('Hours')),
        ));
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Bakery'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-cake2-fill', 'text-primary'),
                        $span('{{site_name}}', 'pbk-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($bakery['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-pinterest', 'Pinterest'),
                        $social('bi-tiktok', 'TikTok'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Bake Links'), array(
                    $heading('h2', lang('Our Bakes'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($bake_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Orders and Classes'), array(
                    $heading('h2', lang('Orders and Classes'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array_merge(array(
                        $link(lang('Order a Cake'), '{{page:cake_order}}', $plain),
                        $link(lang('My Cake Orders'), '{{page:my_cake_orders}}', $plain),
                        $link(lang('Order and Collect'), '{{page:how_to_order}}', $plain),
                        $link(lang('My Class Places'), '{{page:my_bookings}}', $plain),
                    ), $class_links)),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Bakery'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Wholesale'), '{{page:wholesale}}', $plain),
                        $link(lang('From the Bakery'), '{{page:blog}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Visit Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($bakery['address'], '{{page:visit}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($bakery['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $bakery['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($bakery['email'], 'mailto:' . $bakery['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-clock', $el('div', '', lang('Opening Hours'), $hours), lang('Opening Hours')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Baked by hand, every morning, a few streets from you.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The order band: a full-width band in the palette's colour with the cake
// order and the way ordering works. Shared, so every page that ends with
// it carries the same offer.
$order_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('A cake made for your day'), 'pbk-display h2 mb-2'),
                    $para(lang('Tell us the occasion, how many it should serve and what you would like on it; we answer with a design and a price within a day.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Order a Cake'), '{{page:cake_order}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('How Ordering Works'), '{{page:how_to_order}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A bake of the daily bake: the name, its badges, a line and the price of
// one, in a list under its category.
$bake_item = function ($key) use ($el, $para, $span, $bakes, $diet_tags, $money) {
    $b = $bakes[$key];
    $badges = array();
    foreach ($b['tags'] as $t) $badges[] = $span($diet_tags[$t], 'badge rounded-pill text-bg-light border', lang('Badge'));
    return $el('li', 'list-group-item d-flex align-items-start gap-3 py-3 px-0 bg-transparent', lang('Bake'), array(
        $el('div', 'flex-grow-1', lang('Bake Details'), array(
            $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-1', lang('Name Line'), array_merge(array(
                $span($b['name'], 'fw-semibold', lang('Bake Name')),
            ), $badges)),
            $para($b['summary'], 'small text-body-secondary mb-0'),
        )),
        $span($money($b['price']), 'pbk-price fw-bold text-nowrap', lang('Price')),
    ));
};

// A category of the daily bake: its picture, title and line, and its
// bakes. A search section of its own, so a search opens the page there.
$bake_section = function ($cat) use ($el, $heading, $para, $image, $photo, $attr, $categories, $bakes, $bake_item, $searchable) {
    $c = $categories[$cat];
    $items = array();
    foreach ($bakes as $key => $b) {
        if ($b['category'] === $cat) $items[] = $bake_item($key);
    }
    return $el('section', 'card h-100 border-0 shadow-sm overflow-hidden', $c['title'], array(
        $el('span', 'd-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
            $image($photo('category-' . $cat, 800, 450), $c['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
        )),
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $heading('h2', $c['title'], 'pbk-display h4 mb-1'),
            $para($c['text'], 'small text-body-secondary mb-2'),
            $el('ul', 'list-group list-group-flush', lang('Bakes'), $items),
        )),
    ), $searchable('bky-cat-' . $cat));
};

// A celebration cake's card (the cakes page and the home): the photo, the
// occasion, the name, a line, the price from and the sizes, and a link
// that opens the cake order with the cake and the occasion chosen.
$cake_card = function ($key) use ($el, $heading, $para, $span, $link, $image, $col, $icon, $attr, $photo, $cakes, $occasions, $cake_sizes, $from_price, $query) {
    $c = $cakes[$key];
    $sizes = array();
    foreach ($c['sizes'] as $s) $sizes[] = $cake_sizes[$s]['title'];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pbk-lift', lang('Cake Card'), array(
            $el('span', 'd-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('cake-' . $key, 800, 600), $c['name'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($occasions[$c['occasion']], 'pbk-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Occasion')),
                $heading('h3', $c['name'], 'pbk-display h4 mb-2'),
                $para($c['summary'], 'small text-body-secondary mb-3'),
                $el('p', 'd-flex flex-wrap align-items-center gap-2 small mb-4', lang('Price and Sizes'), array(
                    $span($from_price($c['price']), 'pbk-price fw-bold', lang('Price')),
                    $icon('bi-dot', 'text-body-secondary'),
                    $span(implode(', ', $sizes), 'text-body-secondary', lang('Sizes')),
                )),
                $link(lang('Order this cake'), '{{page:cake_order}}?' . $query(array('cake' => $c['name'], 'occasion' => $occasions[$c['occasion']])), 'btn btn-outline-primary rounded-pill mt-auto'),
            )),
        )),
    ), '6', '3');
};

// A baking class's card of the lists (the home and the class list): the
// photo, the level, the name linking the class page, the summary, how long
// it lasts, the seats and the price of a place. Every value is bound to
// the Baking Classes form's field of the same name; the sample fills the
// canvas.
$class_card = function ($sample_key = 'sourdough') use ($el, $para, $span, $link, $image, $col, $icon, $bind, $attr, $photo, $baking_classes, $levels, $money) {
    $c = $baking_classes[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array($icon($icon_name, 'text-primary'), $span($text, '', lang('Value'), $bind($token))));
    };
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pbk-lift', lang('Baking Class Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('class-' . $sample_key, 800, 600), $c['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($levels[$c['level']], 'pbk-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Level'), $bind('level')),
                $el('h3', 'pbk-display h4 position-static mb-2', lang('Baking Class Name'), array(
                    $link($c['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($c['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mt-auto mb-0', lang('Baking Class Facts'), array(
                    $fact('bi-stopwatch', sprintf(lang('%d min'), $c['minutes']), 'length', lang('Length')),
                    $fact('bi-people', sprintf(lang('%d places'), $c['seats']), 'seats', lang('Places at the Table')),
                    $fact('bi-tag', $money($c['price']), 'price', lang('Price')),
                )),
            )),
        )),
    ), '6', '3');
};

// What a customer says: the quote, the stars, the customer and what they
// bought or learnt.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Customer Quote'), array(
            $stars(),
            $el('blockquote', 'pbk-quote mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Customer'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// A wholesale range (the wholesale page and the home): the icon, the
// title, a line and a link that opens the enquiry with the range chosen.
$range_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $wholesale_ranges, $query) {
    $r = $wholesale_ranges[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pbk-lift', lang('Range Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($r['icon'], 'align-self-start mb-3'),
                $heading('h3', $r['title'], 'pbk-display h5 mb-2'),
                $para($r['text'], 'small text-body-secondary mb-4'),
                $link(lang('Ask about this range'), '{{page:wholesale_enquiry}}?' . $query(array('range' => $r['title'])), 'btn btn-outline-primary rounded-pill mt-auto'),
            )),
        )),
    ), '6', '3');
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
                $item('staff_cake_orders', lang('Cake Orders')),
                $item('staff_bookings', lang('Class Place Requests')),
                $item('staff_enquiries', lang('Wholesale Enquiries')),
                $item('class_new', lang('New Baking Class')),
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
    return $agree($id, 'consent', lang('I would like to receive the bakery\'s news, the bakes of the season and new class dates by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A cake
// order may carry a sketch or a picture of the cake wished for, a wholesale
// enquiry a menu, so the default accepts PDFs, office files, text and
// pictures; one that takes only pictures is a collection's image kind (a
// class's photo).
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
                    $link(lang('How to keep a sourdough starter alive on holiday'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

