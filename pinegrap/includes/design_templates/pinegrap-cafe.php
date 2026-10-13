<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Café": a coffee company with several cafés, its own
 * roastery, a kitchen that bakes every morning and a catering service for
 * companies. A home page with the favourites of the menu, this season's
 * coffee, the cafés, the coffee club, the catering offer, the coming
 * events, what guests say and the newest posts; a menu whose dishes are
 * records of a form (each with its category, price, diet tags, a badge and
 * a photo), listed category by category in tabs and kept by the staff; the
 * cafés with their hours; a corporate page with the catering packages and a
 * quote request the company follows from its account; the coming events,
 * kept like the menu; a table booking the guest follows from their account;
 * careers with the open positions and an application form that takes a
 * CV; the about page, a blog with its post page, a contact page whose
 * messages become conversations (the inbox and the conversation page), the
 * sign-in and account pages, a staff area (the coming reservations, the
 * catering requests and the forms of the menu, the events, the blog and the
 * staff directory), a help center that searches the whole site and a
 * newsletter that collects e-mail addresses. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). There is no catalog: the café sells at its
 * counters, not online.
 *
 * A table booking is a record of the Reservations form, a quote request one
 * of the Catering Requests form. A visitor without an account gets one
 * (auto-registration), so the record's page and the list in the account
 * show it at once. Its Status is an office-use-only field the staff change
 * in the control panel; the messages under it are the page's comments,
 * mailed to the guest and to the café.
 *
 * The menu and the events are records of their own forms, written in when
 * the forms are made (the template's sample records): the menu page lists
 * each category in a tab of its own (a list filtered by the category), the
 * home page the dishes marked for it and the next events.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components and 'requires'.
 *
 * Every structural node carries its role in '_label' (the designer's own
 * label, shown beside the element's tag in the layer tree).
 *
 * The version follows the template, not the software: raise it whenever the
 * pages change, so a design records which edition it started from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';
$shop      = defined('ECOMMERCE') && ECOMMERCE;

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
// A date field in the format given (date()'s letters), in place of the
// site's own date format.
$bind_date = function ($token, $format) {
    return array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => $format));
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
// leaves it out while the site has a single language, so it can stay in the
// header of every site.
$lang_switcher = function ($class = '', $variant = 'secondary') {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => true, 'size' => 'sm',
        'align' => 'lg-end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};

// A sample portrait: the person's initials on the palette's colour, until
// the café puts a photo in its place. The initials follow the name in the
// site's language.
$portrait = function ($name, $size = 480) {
    $words = preg_split('/\s+/u', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . $size . 'x' . $size . '/f1e6dc/6f4e37?text=' . rawurlencode($initials !== '' ? $initials : 'C');
};
// A sample photo, the same for a seed on every site.
$photo = function ($seed, $w, $h) {
    return 'https://picsum.photos/seed/pinegrap-cafe-' . $seed . '/' . $w . '/' . $h;
};

// ── The company: its cafés, its menu and its catering ──────────────────

// The cafés, in the order the pages list them. Each has its section on the
// cafés page (#cafe-<key>), the words a visitor may search for, its hours
// (Monday to Friday, Saturday, Sunday) and what it offers.
$cafes = array(
    'old_town' => array(
        'name'     => lang('Old Town'),
        'address'  => lang('12 Market Square'),
        'phone'    => '+90 555 000 00 11',
        'badge'    => lang('Our first café'),
        'text'     => lang('Where it began in 2009: the roaster behind the counter and a terrace on the market square.'),
        'hours'    => lang('Every day 07:30–22:00'),
        'week'     => array('07:30–22:00', '07:30–22:00', '07:30–22:00'),
        'features' => array(array('bi-fire', lang('Roastery on site')), array('bi-sun', lang('Terrace')), array('bi-wifi', lang('Free Wi-Fi'))),
        'seed'     => 'old-town',
        'topics'   => lang('old town, market square, terrace, roastery, first café'),
    ),
    'riverside' => array(
        'name'     => lang('Riverside'),
        'address'  => lang('8 Harbour Walk'),
        'phone'    => '+90 555 000 00 22',
        'badge'    => lang('Open until 23:00'),
        'text'     => lang('Big windows over the water, the longest hours of the three and live music on Friday evenings.'),
        'hours'    => lang('Every day 08:00–23:00'),
        'week'     => array('08:00–23:00', '08:00–23:00', '08:00–23:00'),
        'features' => array(array('bi-water', lang('Sea view')), array('bi-music-note-beamed', lang('Live music on Fridays')), array('bi-people', lang('Room for private events'))),
        'seed'     => 'riverside',
        'topics'   => lang('riverside, harbour, sea view, live music, private events, late opening'),
    ),
    'business' => array(
        'name'     => lang('Business District'),
        'address'  => lang('Vertex Tower, ground floor'),
        'phone'    => '+90 555 000 00 33',
        'badge'    => lang('For offices'),
        'text'     => lang('A quick coffee before work, tables for meetings and the catering kitchen for the offices around it.'),
        'hours'    => lang('Mon–Fri 07:00–19:00'),
        'week'     => array('07:00–19:00', lang('Closed'), lang('Closed')),
        'features' => array(array('bi-briefcase', lang('Meeting tables')), array('bi-plug', lang('Power at every table')), array('bi-bag-heart', lang('Order ahead for pick-up'))),
        'seed'     => 'business',
        'topics'   => lang('business district, office, meeting, pick-up, weekdays'),
    ),
);
$cafe_names = array();
foreach ($cafes as $c) $cafe_names[] = $c['name'];

// The menu's categories, in the order of the menu page's tabs: each tab
// lists the dishes of its category (a list filtered by it).
$menu_categories = array(
    'coffee'    => array('title' => lang('Coffee'), 'icon' => 'bi-cup-hot',
                         'note'  => lang('Our house blend for the espresso drinks, a single origin for the filter. Oat, almond or soy milk at no extra cost.')),
    'drinks'    => array('title' => lang('Tea & Cold Drinks'), 'icon' => 'bi-cup-straw',
                         'note'  => lang('Loose-leaf teas, fresh lemonade and smoothies made to order.')),
    'breakfast' => array('title' => lang('Breakfast'), 'icon' => 'bi-egg-fried',
                         'note'  => lang('Served until noon on weekdays and until 14:00 at the weekend.')),
    'lunch'     => array('title' => lang('Lunch'), 'icon' => 'bi-basket',
                         'note'  => lang('Sandwiches, bowls and the soup of the day, from 11:30.')),
    'bakery'    => array('title' => lang('Bakery & Desserts'), 'icon' => 'bi-cake2',
                         'note'  => lang('Baked in our kitchen every morning; ask what came out of the oven last.')),
);
$category_titles = array();
foreach ($menu_categories as $c) $category_titles[] = $c['title'];

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

// The dishes the menu opens with: category, name, description, price, diet
// tags, badge, shown on the home page, photo. Each is a record of the Menu
// form, edited or deleted like any other.
$V = lang('Vegetarian'); $VG = lang('Vegan'); $GF = lang('Gluten free'); $NUT = lang('Contains nuts'); $HOT = lang('Spicy');
$menu_items = array(
    array('coffee', lang('Espresso'), lang('A double shot of our house blend.'), lang('$2.80'), array(), '', false, 'espresso'),
    array('coffee', lang('Flat White'), lang('Espresso with a thin layer of silky milk.'), lang('$3.80'), array(), '', true, 'flat-white'),
    array('coffee', lang('Cappuccino'), lang('Equal parts espresso, milk and foam.'), lang('$3.90'), array(), '', false, 'cappuccino'),
    array('coffee', lang('Caffè Latte'), lang('Oat, almond or whole milk.'), lang('$4.10'), array(), '', false, 'latte'),
    array('coffee', lang('Filter Coffee'), lang('A single origin that changes every week.'), lang('$3.20'), array($VG), '', false, 'filter'),
    array('coffee', lang('Pumpkin Spice Latte'), lang('Espresso, roasted pumpkin, cinnamon and nutmeg, for the autumn only.'), lang('$4.80'), array($V), lang('Seasonal'), true, 'pumpkin-latte'),
    array('drinks', lang('Chai Latte'), lang('Black tea, warm spices and milk.'), lang('$4.20'), array($V), '', false, 'chai'),
    array('drinks', lang('Cold Brew'), lang('Steeped for eighteen hours, served over ice.'), lang('$4.20'), array($VG), '', false, 'cold-brew'),
    array('drinks', lang('Fresh Lemonade'), lang('Lemon, mint and a little sugar.'), lang('$3.60'), array($VG, $GF), '', false, 'lemonade'),
    array('drinks', lang('Berry Smoothie'), lang('Berries, banana and oat milk.'), lang('$5.40'), array($VG, $GF), '', false, 'smoothie'),
    array('breakfast', lang('Avocado Toast'), lang('Sourdough, lime, chilli flakes and seeds.'), lang('$9.50'), array($VG), '', true, 'avocado-toast'),
    array('breakfast', lang('Eggs Benedict'), lang('Poached eggs, ham and hollandaise on a muffin.'), lang('$11.00'), array(), lang('Chef\'s pick'), false, 'eggs-benedict'),
    array('breakfast', lang('Shakshuka'), lang('Eggs baked in a spiced tomato sauce, with bread.'), lang('$10.50'), array($V, $HOT), '', false, 'shakshuka'),
    array('breakfast', lang('Granola Bowl'), lang('Yoghurt, honey, berries and house granola.'), lang('$7.50'), array($V, $NUT), '', false, 'granola'),
    array('breakfast', lang('Pancake Stack'), lang('Three pancakes, maple syrup and butter.'), lang('$8.90'), array($V), '', false, 'pancakes'),
    array('lunch', lang('Roasted Pumpkin Soup'), lang('Slow-roasted pumpkin, ginger and coconut milk, with warm sourdough.'), lang('$8.50'), array($VG), lang('Seasonal'), false, 'pumpkin-soup'),
    array('lunch', lang('Chicken Pesto Sandwich'), lang('Grilled chicken, basil pesto, tomato and rocket on ciabatta.'), lang('$10.90'), array($NUT), '', false, 'pesto-sandwich'),
    array('lunch', lang('Halloumi Grain Bowl'), lang('Grilled halloumi, bulgur, roasted vegetables and a lemon dressing.'), lang('$12.50'), array($V), lang('New'), false, 'halloumi-bowl'),
    array('lunch', lang('Falafel Wrap'), lang('Falafel, hummus, pickles and tahini in a warm flatbread.'), lang('$9.90'), array($VG, $HOT), '', false, 'falafel-wrap'),
    array('bakery', lang('Butter Croissant'), lang('Baked here every morning.'), lang('$3.20'), array($V), '', false, 'croissant'),
    array('bakery', lang('Pistachio Croissant'), lang('Flaky, buttery and filled to the edges.'), lang('$4.20'), array($V, $NUT), lang('New'), true, 'pistachio-croissant'),
    array('bakery', lang('Baked Cheesecake'), lang('With a berry compote.'), lang('$5.90'), array($V), '', false, 'cheesecake'),
    array('bakery', lang('Chocolate Brownie'), lang('Dark chocolate and sea salt.'), lang('$4.50'), array($V, $GF), '', false, 'brownie'),
    array('bakery', lang('Carrot Cake'), lang('Walnuts, cinnamon and cream cheese icing.'), lang('$5.20'), array($V, $NUT), '', false, 'carrot-cake'),
);
$menu_records = array();
foreach ($menu_items as $m) {
    $menu_records[] = array(
        'category'    => $menu_categories[$m[0]]['title'],
        'name'        => $m[1],
        'description' => $m[2],
        'price'       => $m[3],
        'diet'        => $m[4],
        'badge'       => $m[5],
        'featured'    => $m[6] ? lang('Yes') : lang('No'),
        'photo'       => $photo('dish-' . $m[7], 600, 600),
    );
}

// This season's coffee: the three origins the roastery buys, what they
// taste of and how dark they are roasted (the bar's width).
$origins = array(
    array('country' => lang('Ethiopia'), 'region' => lang('Yirgacheffe'), 'meta' => lang('Washed · 1,900–2,200 m'),
          'notes' => array(lang('Jasmine'), lang('Bergamot'), lang('Lemon')), 'roast' => lang('Light'), 'level' => 25,
          'use' => lang('In the filter and the cold brew')),
    array('country' => lang('Colombia'), 'region' => lang('Huila'), 'meta' => lang('Washed · 1,600–1,800 m'),
          'notes' => array(lang('Red apple'), lang('Caramel'), lang('Cocoa')), 'roast' => lang('Medium'), 'level' => 50,
          'use' => lang('Half of our house blend')),
    array('country' => lang('Brazil'), 'region' => lang('Cerrado Mineiro'), 'meta' => lang('Natural · 1,000–1,200 m'),
          'notes' => array(lang('Hazelnut'), lang('Milk chocolate'), lang('Brown sugar')), 'roast' => lang('Medium-dark'), 'level' => 75,
          'use' => lang('The body of every espresso')),
);

// The catering packages, priced per person: the middle one is the one most
// companies order.
$packages = array(
    array('icon' => 'bi-cup-hot', 'title' => lang('Coffee Break'), 'price' => lang('$9'), 'min' => lang('From 10 people'), 'featured' => false,
          'text'  => lang('Coffee, tea and something sweet for a meeting that runs long.'),
          'items' => array(lang('Two hot drinks per person'), lang('Pastries and cookies'), lang('Cups, milk and sugar'))),
    array('icon' => 'bi-egg-fried', 'title' => lang('Office Breakfast'), 'price' => lang('$16'), 'min' => lang('From 10 people'), 'featured' => true,
          'text'  => lang('A breakfast spread that starts the day before the first call.'),
          'items' => array(lang('Coffee and fresh juice'), lang('Sandwiches, croissants and fruit'), lang('Vegan and gluten-free choices'))),
    array('icon' => 'bi-basket', 'title' => lang('Lunch Boxes'), 'price' => lang('$22'), 'min' => lang('From 15 people'), 'featured' => false,
          'text'  => lang('A sandwich or bowl, a salad, a drink and a dessert for each guest.'),
          'items' => array(lang('Named box for every guest'), lang('Choice of three mains'), lang('Delivered at the time you set'))),
);

// What the catering does, on the corporate page.
$catering_services = array(
    array('bi-cup-hot', lang('Coffee breaks'), lang('Coffee, tea and pastries for meetings and trainings, set up before the first guest arrives.')),
    array('bi-egg-fried', lang('Breakfasts and lunch boxes'), lang('A breakfast spread or a named lunch box for every guest, delivered at the time you set.')),
    array('bi-building', lang('Office coffee'), lang('A coffee corner in your office with our beans and a weekly visit from a barista.')),
    array('bi-people', lang('Events and launches'), lang('A coffee bar, finger food and staff for launches, open days and celebrations.')),
);

// The open positions on the careers page, each a search section of its
// own (#position-<key>).
$positions = array(
    'barista' => array('title' => lang('Barista'), 'where' => lang('Old Town and Riverside'), 'type' => lang('Full time or part time'),
        'text'   => lang('You make the coffee and the first impression. No experience? We train you in our own academy.'),
        'items'  => array(lang('Espresso, milk and filter coffee'), lang('Serving guests at the counter and at the tables'), lang('A weekend shift every other week')),
        'topics' => lang('barista, coffee, counter, part time, job')),
    'kitchen' => array('title' => lang('Kitchen Team Member'), 'where' => lang('Central kitchen, Old Town'), 'type' => lang('Full time'),
        'text'   => lang('Early mornings in the bakery and the lunch kitchen: croissants, cakes, sandwiches and bowls.'),
        'items'  => array(lang('Baking from 05:30'), lang('Preparing the lunch menu'), lang('A food hygiene certificate, or we help you get one')),
        'topics' => lang('kitchen, cook, baker, bakery, chef, job')),
    'supervisor' => array('title' => lang('Shift Supervisor'), 'where' => lang('Business District'), 'type' => lang('Full time'),
        'text'   => lang('You run the café on your shift: the team, the stock and the catering orders that leave the kitchen.'),
        'items'  => array(lang('Two years in a café or a restaurant'), lang('Opening and closing the café'), lang('Weekdays only, no weekends')),
        'topics' => lang('supervisor, manager, shift lead, team lead, job')),
    'roastery' => array('title' => lang('Roastery Assistant'), 'where' => lang('Roastery, Old Town'), 'type' => lang('Part time'),
        'text'   => lang('Weigh, roast, taste and pack: help our head roaster turn green beans into this week\'s coffee.'),
        'items'  => array(lang('Roasting and cupping twice a week'), lang('Packing beans for the cafés and the offices'), lang('Curiosity matters more than experience')),
        'topics' => lang('roastery, roaster, coffee roasting, cupping, job')),
);

// The people on the about page.
$team = array(
    array('name' => lang('Jane Cooper'), 'role' => lang('Founder'), 'text' => lang('Opened the first café in 2009 with one machine and a borrowed roaster.')),
    array('name' => lang('Michael Brooks'), 'role' => lang('Head Roaster'), 'text' => lang('Buys the green beans, roasts every Monday and tastes every batch.')),
    array('name' => lang('Emily Turner'), 'role' => lang('Head Baker'), 'text' => lang('In the kitchen at half past five, so the croissants are warm at eight.')),
    array('name' => lang('Daniel Ross'), 'role' => lang('Head of Catering'), 'text' => lang('Plans the breakfasts, the lunch boxes and the coffee bars for our companies.')),
);

// The answers of the reservation form's pick lists. Each answer's value is
// its own words, so the e-mails and the lists read as the guest chose.
$table_times = array();
for ($minute = 7 * 60 + 30; $minute <= 21 * 60 + 30; $minute += 30) {
    $table_times[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
}
$party_sizes = array('1', '2', '3', '4', '5', '6', '7', '8', lang('9 or more'));
$occasions = array(lang('Coffee or breakfast'), lang('Lunch'), lang('A business meeting'), lang('A birthday'), lang('Something else'));

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: what is new. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center column-gap-2 mb-0', lang('Notice'), array(
        $icon('bi-megaphone', 'text-primary'),
        $span(lang('The autumn menu is here.'), '', lang('Text')),
        $link(lang('See the menu'), '{{page:menu}}', 'link-primary fw-semibold text-decoration-none'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. A shared component like the header
// under it: the login region and the notice are placed inside it.
$account_bar = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body-tertiary', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'),
            )),
        )),
    ));
};
// Every page places the bar as one shared component, so a change to it is
// made once for the whole site. The builder above is the component's tree.
$account_bar_tree = $account_bar;
$account_bar = function () use ($shared) {
    return $shared('account_bar');
};

// The brand: a cup in a round tile and the site's name.
$brand = function ($class, $tile = 'p-2') use ($el, $icon, $span) {
    return $el('a', $class, lang('Brand'), array(
        $el('span', 'd-inline-flex rounded-circle text-bg-primary lh-1 ' . $tile, lang('Icon Box'), array($icon('bi-cup-hot-fill'))),
        $span('{{site_name}}', '', lang('Name')),
    ), array('href' => '{{page:home}}'));
};

// The header: the name, the menu and the way to book a table. The links to
// the current page are marked active on the server (smart active state),
// which is what lets one header serve every page.
$header = function () use ($el, $link, $icon, $span, $attr, $brand) {
    $items = array();
    foreach (array(
        array('menu', lang('Menu')),
        array('locations', lang('Our Cafés')),
        array('corporate', lang('Corporate')),
        array('events', lang('Events & Workshops')),
        array('about', lang('About Us')),
        array('contact', lang('Contact us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $brand('navbar-brand d-flex align-items-center gap-2 fw-bold fs-5'),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#cf-nav'),
                    $attr('aria-controls', 'cf-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav mx-xl-auto column-gap-xl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-3 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar2-check'),
                            $span(lang('Book a Table'), '', lang('Link Text')),
                        ), array('href' => '{{page:reservation}}')),
                    )),
                ), array('id' => 'cf-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The hours of the cafés, as a definition list: the café, then its hours.
$hours = function ($class = 'row small mb-0', $long = false) use ($el, $cafes) {
    $children = array();
    foreach ($cafes as $c) {
        $children[] = $el('dt', 'col-6 fw-normal text-body-secondary', lang('Café'), array(), array('text' => $c['name']));
        $children[] = $el('dd', 'col-6 mb-2', lang('Time'), array(), array('text' => $long ? $c['hours'] : $c['week'][0]));
    }
    return $el('dl', $class, lang('List'), $children);
};

// The footer, on a dark band: the company in a sentence, the links for
// guests and for companies, how to reach the head office and the hours;
// under them the copyright and a word on prices and allergens.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $cafes, $attr, $brand) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };
    $cafe_links = array();
    foreach ($cafes as $key => $c) {
        $cafe_links[] = $el('li', 'mb-2', lang('List Item'), array(
            $link($c['name'], '{{page:locations}}#cafe-' . str_replace('_', '-', $key), $plain . ' d-block fw-semibold'),
            $span($c['hours'], 'd-block text-body-secondary', lang('Hours')),
        ));
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Company'), array(
                    $brand('d-inline-flex align-items-center gap-2 fw-bold fs-5 mb-3 ' . $plain),
                    $para(lang('Coffee we roast ourselves, a kitchen that bakes every morning and three cafés in the city. Catering for companies, too.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-linkedin', 'LinkedIn'),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Guests'), array(
                    $heading('h2', lang('Guests'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Menu'), '{{page:menu}}', $plain),
                        $link(lang('Book a Table'), '{{page:reservation}}', $plain),
                        $link(lang('My Reservations'), '{{page:my_reservations}}', $plain),
                        $link(lang('Events & Workshops'), '{{page:events}}', $plain),
                        $link(lang('Blog'), '{{page:blog}}', $plain),
                        $link(lang('Help Center'), '{{page:search}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Company'), array(
                    $heading('h2', lang('Company'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Corporate & Catering'), '{{page:corporate}}', $plain),
                        $link(lang('Request a Quote'), '{{page:corporate}}#quote', $plain),
                        $link(lang('Careers'), '{{page:careers}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact us'), '{{page:contact}}', $plain),
                    )),
                )),
                $el('div', 'col-12 col-md-4 col-lg-4', lang('Our Cafés'), array(
                    $heading('h2', lang('Our Cafés'), 'h6 text-uppercase small fw-bold mb-3'),
                    $el('ul', 'list-unstyled small mb-3', lang('Cafés'), $cafe_links),
                    $contact_line('bi-telephone', $link('+90 555 000 00 00', 'tel:+905550000000', $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link('hello@example.com', 'mailto:hello@example.com', $plain . ' text-break'), lang('Email')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Prices include VAT. Ask at the counter for the allergens in every dish.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The table band: a full-width band in the palette's colour with the way to
// book a table and the way to order for a team. Shared, so every page that
// ends with it carries the same offer.
$table_band = function () use ($el, $heading, $para, $link, $icon, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'd-flex align-items-start gap-3', lang('Text'), array(
                        $icon('bi-cup-hot', 'fs-1 lh-1'),
                        $el('div', '', lang('Words'), array(
                            $heading('h2', lang('A table for two, or breakfast for the whole team?'), 'h3 fw-bold mb-2'),
                            $para(lang('Book a table in a minute and we confirm by e-mail. For ten people or more, ask for a catering quote.'), 'mb-0 opacity-75'),
                        )),
                    )),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Table'), '{{page:reservation}}', 'btn btn-light btn-lg px-4'),
                        $link(lang('Catering'), '{{page:corporate}}', 'btn btn-outline-light btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A page of the site: the account bar, the shared header, the page's own
// content, the newsletter band (left off where it would be in the way: the
// forms, the account pages) and the shared footer.
$page = function ($main, $newsletter = true) use ($root, $account_bar, $shared, $el, $widget) {
    $children = array(
        $account_bar(),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($newsletter) $children[] = $widget('newsletter_band');
    $children[] = $shared('footer');
    return $root($children);
};

// The staff pages' own menu, under the header: one shared component.
$staff_bar = function () use ($el, $link, $span) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center column-gap-4 row-gap-2 py-2', lang('Staff Bar Container'), array(
            $span(lang('Staff'), 'badge rounded-pill text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline column-gap-4 row-gap-1', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('my_reservations', lang('All Reservations')),
                $item('my_quotes', lang('All Catering Requests')),
                $item('menu_new', lang('Add to the Menu')),
                $item('event_new', lang('New Event')),
                $item('blog_new', lang('New Blog Post')),
                $item('staff_entry', lang('Add to the Staff Directory')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no newsletter.
$staff_page = function ($main) use ($root, $account_bar, $shared, $el) {
    return $root(array(
        $account_bar(),
        $shared('header'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
    ));
};

// The top of the inner pages: where the page sits, its title and one
// sentence, and whatever the page adds under them.
$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-primary-subtle', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'display-6 fw-bold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'small text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'h3 fw-bold mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what guests say; $label is what a screen reader hears.
$stars = function ($class = 'mb-2', $label = '') use ($el, $icon, $span) {
    return $el('p', 'd-flex gap-1 text-warning ' . $class, lang('Rating'), array(
        $span($label !== '' ? $label : lang('Five out of five stars'), 'visually-hidden', lang('Text')),
        $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'),
    ));
};

// A line of a check list: a tick and the words.
$check_item = function ($text, $class = 'mb-2') use ($el, $icon, $span) {
    return $el('li', 'd-flex gap-2 ' . $class, lang('List Item'), array(
        $icon('bi-check2-circle', 'text-primary'),
        $span($text, '', lang('Text')),
    ));
};
$check_list = function ($items, $class = 'list-unstyled mb-0') use ($el, $check_item) {
    $children = array();
    foreach (array_values($items) as $n => $text) $children[] = $check_item($text, ($n === count($items) - 1) ? 'mb-0' : 'mb-2');
    return $el('ul', $class, lang('Check List'), $children);
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

// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answer, $topics = '', $parent = 'cf-faq') use ($el, $para, $attr, $searchable) {
    $id = 'cf-faq-' . $n;
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

// The diet key over the menu: what each tag means, and where to ask about
// allergens.
$diet_key = function () use ($el, $heading, $para, $icon, $span, $diets) {
    $tags = array();
    foreach ($diets as $d) {
        $tags[] = $el('li', '', $d[0], array(
            $el('span', 'badge rounded-pill d-inline-flex align-items-center gap-1 bg-' . $d[2] . '-subtle text-' . $d[2] . '-emphasis', lang('Diet Tag'), array(
                $icon($d[1]),
                $span($d[0], '', lang('Text')),
            )),
        ));
    }
    return $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-3 p-4 rounded-4 bg-body-tertiary', lang('Diet Key'), array(
        $heading('h2', lang('Good to know'), 'h6 fw-bold flex-shrink-0 mb-0'),
        $el('ul', 'list-unstyled d-flex flex-wrap gap-2 flex-grow-1 mb-0', lang('Diet Tags'), $tags),
        $para(lang('Allergies? Every dish has its allergen list at the counter; just ask.'), 'small text-body-secondary mb-0'),
    ));
};

// This season's coffee: a card for each origin, with its tasting notes and
// a bar for the roast.
$origin_cards = function () use ($el, $heading, $para, $icon, $span, $col, $attr, $origins) {
    $cards = array();
    foreach ($origins as $o) {
        $notes = array();
        foreach ($o['notes'] as $t) $notes[] = $span($t, 'badge rounded-pill bg-body-tertiary text-body border', lang('Tasting Note'));
        $cards[] = $col(array(
            $el('article', 'card h-100 border-0 shadow-sm', $o['country'], array(
                $el('div', 'card-body p-4', lang('Card Body'), array(
                    $el('div', 'd-flex align-items-start justify-content-between gap-2 mb-3', lang('Origin'), array(
                        $span($o['country'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Country')),
                        $icon('bi-globe-americas', 'fs-5 text-primary'),
                    )),
                    $heading('h3', $o['region'], 'h5 fw-bold mb-1'),
                    $para($o['meta'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex flex-wrap gap-1 mb-4', lang('Tasting Notes'), $notes),
                    $el('div', 'd-flex justify-content-between small mb-1', lang('Roast Label'), array(
                        $span(lang('Roast'), 'text-body-secondary', lang('Label')),
                        $span($o['roast'], 'fw-semibold', lang('Value')),
                    )),
                    $el('div', 'progress mb-3', lang('Roast Level'), array(
                        $el('div', 'progress-bar w-' . (int)$o['level'], lang('Roast Bar')),
                    ), array('_attrs' => array(
                        $attr('role', 'progressbar'),
                        $attr('aria-label', lang('Roast level')),
                        $attr('aria-valuenow', (string)(int)$o['level']),
                        $attr('aria-valuemin', '0'),
                        $attr('aria-valuemax', '100'),
                    ))),
                    $el('p', 'd-flex align-items-center gap-2 small mb-0', lang('In Your Cup'), array(
                        $icon('bi-cup-hot', 'text-primary'),
                        $span($o['use'], '', lang('Text')),
                    )),
                )),
            )),
        ), '6', '4');
    }
    return $cards;
};

// A café as a card: its photo, name, address and hours, linked to its
// section of the cafés page.
$cafe_cards = function () use ($el, $heading, $para, $image, $icon, $span, $col, $attr, $cafes, $photo) {
    $cards = array();
    foreach ($cafes as $key => $c) {
        $cards[] = $col(array(
            $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', $c['name'], array(
                $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo('cafe-' . $c['seed'], 800, 450), $c['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                )),
                $el('div', 'card-body p-4', lang('Card Body'), array(
                    $span($c['badge'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Badge')),
                    $el('h3', 'h5 fw-bold mb-2 position-static', lang('Café Name'), array(
                        array('type' => 'content', 'props' => array('contentType' => 'link', 'text' => $c['name'],
                            'href' => '{{page:locations}}#cafe-' . str_replace('_', '-', $key), 'cssClass' => 'stretched-link link-body-emphasis text-decoration-none'), 'children' => array()),
                    )),
                    $el('p', 'd-flex align-items-start gap-2 small mb-1', lang('Address'), array($icon('bi-geo-alt', 'text-primary'), $span($c['address'], '', lang('Text')))),
                    $el('p', 'd-flex align-items-start gap-2 small mb-0', lang('Hours'), array($icon('bi-clock', 'text-primary'), $span($c['hours'], '', lang('Text')))),
                )),
            )),
        ), '6', '4');
    }
    return $cards;
};

// What guests say.
$testimonial = function ($quote, $who, $what) use ($el, $para, $stars, $col) {
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Guest Review'), array(
            $stars(),
            $el('blockquote', 'mb-3', lang('Quote'), array($para($quote, 'mb-0'))),
            $el('figcaption', 'small mt-auto', lang('Author'), array(
                $para($who, 'fw-semibold mb-0'),
                $para($what, 'text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3') use ($el, $attr) {
    return $el('div', $class, $label, array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    ));
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    foreach ($more as $k => $v) $attrs[] = $attr($k, $v);
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true, $placeholder = '') use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    if ($placeholder !== '') $attrs[] = $attr('placeholder', $placeholder);
    return $el('textarea', 'form-control', lang('Field'), array(), array('id' => $id, '_attrs' => $attrs));
};
// A pick list: the first, empty option is the prompt, not an answer. Each
// answer's value is its own words, so the e-mails and the subject line read
// as the visitor chose. $selected is the answer chosen to begin with (no
// prompt then).
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
// A group of radio buttons (or, with $type 'checkbox', of boxes that may all
// be ticked) under its legend: the legend names the field.
$choices = function ($id, $name, $legend, $options, $checked = array(), $type = 'radio', $class = 'mb-3') use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $rattrs = array($attr('type', $type), $attr('name', $name), $attr('value', $o));
        if (in_array($o, (array)$checked, true)) $rattrs[] = $attr('checked', '');
        $children[] = $el('div', 'form-check form-check-inline', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => $rattrs)),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o, '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', $class, $legend, $children);
};
$submit = function ($text, $class = 'btn btn-primary px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// A box the visitor must tick: what was agreed to is stored with the form.
$agree = function ($id, $name, $text) use ($el, $attr) {
    return $el('div', 'form-check small mb-3', lang('Consent'), array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
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
    return $agree($id, 'consent', lang('I would like to receive the café\'s letter by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named.
$file = function ($id, $name, $folder, $accept = 'image/*', $required = false) use ($el, $attr) {
    $attrs = array($attr('type', 'file'), $attr('name', $name), $attr('accept', $accept));
    if ($required) $attrs[] = $attr('required', '');
    return $el('input', 'form-control', '', array(), array('id' => $id, '_attrs' => $attrs,
        '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')));
};

// The numbered heading of a part of a long form.
$form_step = function ($n, $title) use ($el, $span, $heading) {
    return $el('div', 'd-flex align-items-center gap-2 mt-2 mb-3', lang('Step'), array(
        $span((string)$n, 'badge rounded-circle text-bg-primary', lang('Step Number')),
        $heading('h2', $title, 'h6 fw-bold mb-0'),
    ));
};

// The name, e-mail and phone of whoever sends a form; the contact's own
// fields, so the site knows them next time.
$person_fields = function ($prefix, $last_required = true, $phone_required = true) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', $last_required, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', $phone_required, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

// ── Lists, records and staff parts ─────────────────────────────────────

// A picture that leaves with its row when the record has none (the
// pg-hide-if-empty row of the form widgets).
$optional_image = function ($token, $alt_token, $class, $frame, $sample, $alt, $extra = array()) use ($el, $image) {
    return $el('div', 'pg-hide-if-empty', lang('Picture'), array(
        $el('span', 'pg-field-value d-block ' . $frame, lang('Picture Frame'), array(
            $image($sample, $alt, $class, array_merge(array('_bindings' => array('src' => $token, 'alt' => $alt_token)), $extra)),
        )),
    ));
};

// A dish of the menu: its photo, the name with a rule that leads the eye to
// the price, the description and its tags. A tag the dish has not got
// leaves with its badge.
$dish_line = function () use ($el, $span, $para, $icon, $col, $optional_image, $bind, $attr) {
    return $col(array(
        $el('article', 'd-flex gap-3 h-100 py-3 border-bottom', lang('Dish'), array(
            $optional_image('photo', 'name', 'rounded-3 object-fit-cover', 'flex-shrink-0',
                'https://picsum.photos/seed/pinegrap-cafe-dish-flat-white/160/160', lang('Photo'),
                array('width' => '72', 'height' => '72', '_attrs' => array($attr('loading', 'lazy')))),
            $el('div', 'flex-grow-1 min-w-0', lang('Details'), array(
                $el('div', 'd-flex align-items-baseline gap-2', lang('Dish Head'), array(
                    $el('h3', 'h6 fw-semibold mb-0', lang('Dish Name'), array(), array_merge(array('text' => lang('Flat White')), $bind('name'))),
                    $el('div', 'flex-grow-1 border-bottom', lang('Leader')),
                    $span(lang('$3.80'), 'fw-semibold text-primary text-nowrap', lang('Price'), $bind('price')),
                )),
                $para(lang('Espresso with a thin layer of silky milk.'), 'small text-body-secondary mt-1 mb-2', $bind('description')),
                $el('div', 'd-flex flex-wrap gap-1', lang('Tags'), array(
                    $span(lang('New'), 'pg-hide-if-empty badge text-bg-warning', lang('Badge'), $bind('badge')),
                    $el('span', 'pg-hide-if-empty badge rounded-pill bg-success-subtle text-success-emphasis', lang('Diet'), array(
                        $icon('bi-leaf', 'me-1'),
                        $span(lang('Vegetarian'), 'pg-field-value', lang('Value'), $bind('diet')),
                    )),
                )),
            )),
        )),
    ), '', '6');
};

// A favourite of the home page: a square photo with its badge, the name,
// the price and the description.
$dish_card = function () use ($el, $span, $para, $col, $image, $bind, $attr) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Dish Card'), array(
            $el('div', 'position-relative', lang('Picture Area'), array(
                $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                    $image('https://picsum.photos/seed/pinegrap-cafe-dish-flat-white/600/600', lang('Flat White'), 'object-fit-cover',
                        array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $span(lang('New'), 'pg-hide-if-empty badge text-bg-warning position-absolute top-0 start-0 m-3', lang('Badge'), $bind('badge')),
            )),
            $el('div', 'card-body', lang('Card Body'), array(
                $el('div', 'd-flex justify-content-between align-items-baseline gap-2 mb-1', lang('Dish Head'), array(
                    $el('h3', 'h6 fw-semibold mb-0', lang('Dish Name'), array(), array_merge(array('text' => lang('Flat White')), $bind('name'))),
                    $span(lang('$3.80'), 'fw-semibold text-primary text-nowrap', lang('Price'), $bind('price')),
                )),
                $para(lang('Espresso with a thin layer of silky milk.'), 'small text-body-secondary mb-0', $bind('description')),
            )),
        )),
    ), '', '3', '', '6');
};

// An event of the lists: its photo with the date on a leaf over it, the
// kind, the title, when and where, the words, the price and the way to
// book a table for it.
$event_card = function () use ($el, $span, $para, $icon, $col, $image, $bind, $bind_date, $attr, $link) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Event Card'), array(
            $el('div', 'position-relative', lang('Picture Area'), array(
                $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image('https://picsum.photos/seed/pinegrap-cafe-event-music/800/450', lang('Acoustic Friday'), 'object-fit-cover',
                        array('_bindings' => array('src' => 'image', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $el('div', 'position-absolute top-0 start-0 m-3 text-center rounded-3 bg-body text-body px-3 py-2 shadow-sm', lang('Date Leaf'), array(
                    $span('16', 'd-block fs-4 fw-bold lh-1', lang('Day'), $bind_date('event_date', 'd')),
                    $span('10.2026', 'd-block small fw-semibold text-primary mt-1', lang('Month'), $bind_date('event_date', 'm.Y')),
                )),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span(lang('Live music'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis align-self-start mb-2', lang('Kind'), $bind('kind')),
                $el('h3', 'h5 fw-bold mb-2', lang('Event Title'), array(), array_merge(array('text' => lang('Acoustic Friday')), $bind('title'))),
                $el('p', 'd-flex flex-wrap align-items-center column-gap-3 row-gap-1 small text-body-secondary mb-2', lang('When and Where'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Time'), array($icon('bi-clock'), $span('20:00', '', lang('Time'), $bind('event_time')))),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Café'), array($icon('bi-geo-alt'), $span(lang('Riverside'), '', lang('Café'), $bind('cafe')))),
                )),
                $para(lang('Two guitars, one voice and the autumn menu until late.'), 'mb-3', $bind('summary')),
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto', lang('Card Footer'), array(
                    $span(lang('Free entry'), 'small fw-semibold', lang('Price'), $bind('price')),
                    $link(lang('Book a Table'), '{{page:reservation}}', 'btn btn-sm btn-outline-primary'),
                )),
            )),
        )),
    ), '6', '4');
};

// A blog post card of the lists (the blog page and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                'https://picsum.photos/seed/pinegrap-cafe-post/800/450', lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('How we choose the coffee we roast'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'card-text small text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold text-primary', lang('Read More')),
            )),
        )),
    ), '6', '4');
};

// The record's Edit button, shown to whoever may edit the submissions of
// its form; it opens the record in the control panel and comes back.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
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

// The comments of a record page (a post's comments, a conversation's
// replies, the messages of a reservation or a catering request), in the
// reading column.
$comments_section = function ($name) use ($el, $container, $row, $col) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array(
                    array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array()),
                ), '', '8'),
            ), '', 'center'),
        )),
    ));
};

// The notice a record page shows above the record: what happens next, once
// the form is sent, and the account opened for a visitor who had none.
$sent_notices = function ($thanks) use ($el, $icon, $span, $para, $link, $attr, $bind, $show_when) {
    return array(
        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
            $icon('bi-check-circle', 'mt-1'),
            $span($thanks, '', lang('Text')),
        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
        $el('div', 'alert alert-info', lang('New Account'), array(
            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
            $el('p', 'mb-1', lang('Sign-in Email'), array(
                $span(lang('Email') . ': ', '', lang('Label')),
                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
            )),
            $el('p', 'mb-2', lang('Temporary Password'), array(
                $span(lang('Temporary password') . ': ', '', lang('Label')),
                $span('a1b2c3d4', 'fw-semibold font-monospace', lang('Value'), $bind('__new_account_password')),
            )),
            $para(lang('You are signed in. Keep these details, and choose a password of your own.'), 'small mb-2'),
            $link(lang('Change Password'), '{{page:change_password}}', 'btn btn-sm btn-primary'),
        ), $show_when('has_new_account')),
    );
};

// A record's toolbar: back to its list on the left, the staff's button on
// the right.
$record_toolbar = function ($back_text, $back_page, $edit_text = '') use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($back_text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $back_page . '}}')),
        $edit_button($edit_text),
    ));
};

// A label and its value in a record's definition list.
$detail = function ($label, $sample, $token, $class = 'col-sm-8') use ($el, $bind) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', $class, $label, array(), array_merge(array('text' => $sample), $bind($token))),
    );
};
// The guest's name, in a definition list or a line.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// The four ways in under the hero.
$quick_action = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('a', 'card h-100 border-0 shadow-sm text-decoration-none link-body-emphasis', $title, array(
            $el('div', 'card-body d-flex align-items-start gap-3 p-4', lang('Card Body'), array(
                $icon_tile($icon_name, 'flex-shrink-0'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h6 fw-bold mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
        ), array('href' => $href)),
    ), '6', '3');
};

// The numbers band.
$fact = function ($number, $text) use ($el, $para, $col) {
    return $col(array(
        $el('div', 'text-center', lang('Number'), array(
            $para($number, 'display-6 fw-bold mb-1'),
            $para($text, 'small opacity-75 mb-0'),
        )),
    ), '', '3', '', '6');
};

// A point the company stands for: an icon, a title and a sentence.
$value = function ($icon_name, $title, $text, $md = '', $lg = '4') use ($el, $icon_tile, $heading, $para, $col) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 border bg-body', lang('Feature'), array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'h5 fw-bold mb-2'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), $md, $lg);
};

// The coffee club's stamp card: ten stamps, seven of them collected, the
// tenth a free coffee.
$stamp_card = function () use ($el, $heading, $para, $icon, $span, $attr) {
    $stamps = array();
    for ($i = 1; $i <= 10; $i++) {
        if ($i === 10) {
            $class = 'bg-warning-subtle text-warning-emphasis border border-2 border-warning';
            $name  = 'bi-gift';
        } elseif ($i <= 7) {
            $class = 'text-bg-primary';
            $name  = 'bi-cup-hot-fill';
        } else {
            $class = 'border border-2 border-primary border-opacity-25 text-primary text-opacity-50';
            $name  = 'bi-cup-hot';
        }
        $stamps[] = $el('div', 'col', lang('Stamp'), array(
            $el('div', 'ratio ratio-1x1', lang('Stamp Frame'), array(
                $el('div', 'd-flex align-items-center justify-content-center rounded-circle fs-5 ' . $class, lang('Stamp Mark'), array($icon($name))),
            )),
        ));
    }
    return $el('div', 'card border-0 shadow rounded-4', lang('Stamp Card'), array(
        $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
            $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-4', lang('Card Head'), array(
                $el('div', 'd-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $el('span', 'd-inline-flex rounded-circle text-bg-primary lh-1 p-2', lang('Icon Box'), array($icon('bi-cup-hot-fill'))),
                    $span(lang('Coffee Club'), '', lang('Name')),
                )),
                $span(lang('7 of 10'), 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Count')),
            )),
            $el('div', 'row row-cols-5 g-2 g-sm-3 mb-4', lang('Stamps'), $stamps, array('_attrs' => array(
                $attr('role', 'img'),
                $attr('aria-label', lang('A stamp card with seven of its ten stamps collected')),
            ))),
            $para(lang('Two more coffees, and the next one is on us.'), 'small text-body-secondary mb-0'),
        )),
    ));
};

// Home: the way in to the menu and a table, the favourites of the menu,
// this season's coffee, the cafés, the coffee club, the catering offer,
// the coming events, what guests say and the newest posts.
$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Coffee we roast ourselves, breakfast and lunch from our own kitchen, three cafés in the city and catering for companies. See the menu and book a table online.'),
    'tree'             => $page(array(
        // The welcome: the words and buttons on the left, a picture of the
        // café with the hours of each on the right.
        $el('section', 'py-5 bg-primary-subtle', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('span', 'badge rounded-pill text-bg-light text-primary-emphasis d-inline-flex align-items-center gap-1 mb-3', lang('Badge'), array(
                            $icon('bi-fire'),
                            $span(lang('Roasted in our own roastery'), '', lang('Text')),
                        )),
                        $heading('h1', lang('Good coffee, slow mornings and a table for everyone'), 'display-5 fw-bold mb-3'),
                        $para(lang('Coffee we roast every Monday, breakfast and lunch from our own kitchen and three cafés in the city. Come in, book a table, or let us bring it all to your office.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), array(
                            $link(lang('See the Menu'), '{{page:menu}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Book a Table'), '{{page:reservation}}', 'btn btn-outline-primary btn-lg px-4'),
                        )),
                        $el('ul', 'list-unstyled d-flex flex-wrap column-gap-4 row-gap-2 small mb-0', lang('Benefits'), array(
                            $el('li', 'd-flex align-items-center gap-2', lang('List Item'), array($icon('bi-egg-fried', 'text-primary'), $span(lang('Breakfast until noon'), '', lang('Text')))),
                            $el('li', 'd-flex align-items-center gap-2', lang('List Item'), array($icon('bi-leaf', 'text-primary'), $span(lang('Vegan choices every day'), '', lang('Text')))),
                            $el('li', 'd-flex align-items-center gap-2', lang('List Item'), array($icon('bi-truck', 'text-primary'), $span(lang('Catering for offices'), '', lang('Text')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'position-relative', lang('Picture Area'), array(
                            $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden shadow', lang('Picture Frame'), array(
                                $image($photo('hero', 1200, 900), lang('Coffee being poured at the counter of the café'), 'object-fit-cover'),
                            )),
                            $el('div', 'card border-0 shadow position-absolute bottom-0 start-0 m-3', lang('Hours Card'), array(
                                $el('div', 'card-body py-3 px-4', lang('Card Body'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                        $icon('bi-clock', 'text-primary'),
                                        $span(lang('Opening hours'), '', lang('Text')),
                                    )),
                                    $hours('row small mb-0', true),
                                )),
                            )),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The four ways in.
        $el('section', 'py-5', lang('Quick Actions'), array(
            $container(array(
                $row(array(
                    $quick_action('bi-journal-text', lang('Menu'), lang('Coffee, breakfast, lunch and the bakery, with prices.'), '{{page:menu}}'),
                    $quick_action('bi-calendar2-check', lang('Book a Table'), lang('Choose the café, the day and the time; we confirm by e-mail.'), '{{page:reservation}}'),
                    $quick_action('bi-briefcase', lang('Corporate Orders'), lang('Coffee breaks, breakfasts and lunch boxes for your team.'), '{{page:corporate}}'),
                    $quick_action('bi-geo-alt', lang('Find a Café'), lang('Three cafés, their hours and the way to each.'), '{{page:locations}}'),
                ), '4'),
            )),
        )),

        // The favourites: the dishes marked for the home page.
        $el('section', 'py-5 bg-body-tertiary', lang('Favourites'), array(
            $container(array(
                $section_header(lang('From the menu'), lang('Our guests\' favourites'), lang('See the whole menu'), '{{page:menu}}'),
                $widget('home_favourites'),
            )),
        )),

        // This season's coffee.
        $el('section', 'py-5', lang('This Season\'s Coffee'), array(
            $container(array(
                $section_header(lang('From our roastery'), lang('This season\'s coffee'), lang('About the roastery'), '{{page:about}}#roastery'),
                $row($origin_cards(), '4'),
            )),
        )),

        // The numbers.
        $el('section', 'py-5 text-bg-primary', lang('Numbers'), array(
            $container(array(
                $row(array(
                    $fact('2009', lang('the year our first café opened')),
                    $fact('3', lang('cafés in the city')),
                    $fact('6', lang('farms we buy our beans from')),
                    $fact('120+', lang('companies we cater for')),
                ), '4'),
            )),
        )),

        // The cafés.
        $el('section', 'py-5', lang('Our Cafés'), array(
            $container(array(
                $section_header(lang('Visit us'), lang('Three cafés, one roastery'), lang('Hours and directions'), '{{page:locations}}'),
                $row($cafe_cards(), '4'),
            )),
        )),

        // The coffee club: the stamp card and the café's letter.
        $el('section', 'py-5 bg-body-tertiary', lang('Coffee Club'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Coffee Club'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Every tenth coffee is on us'), 'h3 fw-bold mb-3'),
                        $para(lang('Ask for a stamp card at the counter: a stamp for every hot drink, in any of our cafés, and the tenth is free. The café\'s letter tells you first about new beans, tastings and workshops.'), 'text-body-secondary mb-4'),
                        $check_list(array(
                            lang('A stamp for every hot drink'),
                            lang('The tenth coffee free, in any café'),
                            lang('Invitations to tastings and workshops'),
                        ), 'list-unstyled mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Get the café\'s letter'), '{{page:newsletter}}', 'btn btn-primary px-4'),
                            $link(lang('Events & Workshops'), '{{page:events}}', 'btn btn-outline-primary'),
                        )),
                    ), '', '6'),
                    $col(array($stamp_card()), '', '5', '', '12', 'offset-lg-1'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The catering offer for companies.
        $el('section', 'py-5', lang('Corporate Catering'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
                            $image($photo('catering', 1200, 900), lang('A breakfast spread laid out for a meeting'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('For companies'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Coffee breaks, breakfasts and lunch boxes for your team'), 'h3 fw-bold mb-3'),
                        $para(lang('From a coffee break for ten to lunch for two hundred: our kitchen cooks it the same morning and our team brings it at the time you set.'), 'text-body-secondary mb-4'),
                        $check_list(array(
                            lang('Coffee Break from $9 a person'),
                            lang('Office Breakfast from $16 a person'),
                            lang('Lunch Boxes from $22 a person'),
                        ), 'list-unstyled mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Catering Packages'), '{{page:corporate}}', 'btn btn-primary px-4'),
                            $link(lang('Request a Quote'), '{{page:corporate}}#quote', 'btn btn-outline-primary'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        // The coming events.
        $el('section', 'py-5 bg-body-tertiary', lang('Coming Events'), array(
            $container(array(
                $section_header(lang('At our cafés'), lang('Coming events and workshops'), lang('All events'), '{{page:events}}'),
                $widget('home_events'),
            )),
        )),

        // What guests say, under the average of the reviews.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 pb-3 mb-4 border-bottom', lang('Section Header'), array(
                    $el('div', '', lang('Section Title'), array(
                        $para(lang('Our guests'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What guests say'), 'h3 fw-bold mb-0'),
                    )),
                    $el('div', 'd-flex align-items-center gap-3', lang('Rating Summary'), array(
                        $span('4.8', 'display-6 fw-bold lh-1', lang('Average Rating')),
                        $el('div', '', lang('Rating Details'), array(
                            $stars('mb-1', lang('Rated 4.8 out of 5')),
                            $para(lang('from more than 1,200 reviews'), 'small text-body-secondary mb-0'),
                        )),
                    )),
                )),
                $row(array(
                    $testimonial(lang('“The best flat white in town, and the croissants are still warm at eight.”'), lang('Selin A.'), lang('Old Town')),
                    $testimonial(lang('“We order the office breakfast every Monday. Always on time, and there is always a vegan choice.”'), lang('Kerem T.'), lang('Office Breakfast')),
                    $testimonial(lang('“Friday evenings at Riverside: live music, a cold brew and the sea. Hard to beat.”'), lang('Deniz Y.'), lang('Riverside')),
                ), '4'),
            )),
        )),

        // The blog's three newest posts.
        $el('section', 'py-5 bg-body-tertiary', lang('From the Blog'), array(
            $container(array(
                $section_header(lang('Stories from the café'), lang('From our blog'), lang('All posts'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),

        $shared('table_band'),
    )),
);

// The menu: the diet key, a tab for each category (each a list of the Menu
// form's records filtered by the category), this season's coffee and what
// to know before ordering.
$menu_tabs  = array();
$menu_panes = array();
$n = 0;
foreach ($menu_categories as $key => $c) {
    $pane = 'cf-menu-' . $key;
    $menu_tabs[] = $el('li', 'nav-item', lang('Nav Item'), array(
        $el('button', 'nav-link d-inline-flex align-items-center gap-2 text-nowrap' . ($n === 0 ? ' active' : ''), $c['title'], array(
            $icon($c['icon']),
            $span($c['title'], '', lang('Text')),
        ), array('id' => $pane . '-tab', '_attrs' => array(
            $attr('type', 'button'),
            $attr('role', 'tab'),
            $attr('data-bs-toggle', 'pill'),
            $attr('data-bs-target', '#' . $pane),
            $attr('aria-controls', $pane),
            $attr('aria-selected', $n === 0 ? 'true' : 'false'),
        ))),
    ), array('_attrs' => array($attr('role', 'presentation'))));
    $menu_panes[] = $el('div', 'tab-pane fade' . ($n === 0 ? ' show active' : ''), $c['title'], array(
        $el('p', 'd-flex align-items-start gap-2 small text-body-secondary mb-2', lang('Category Note'), array(
            $icon('bi-info-circle', 'text-primary mt-1'),
            $span($c['note'], '', lang('Text')),
        )),
        $widget('menu_' . $key),
    ), array('id' => $pane, '_attrs' => array(
        $attr('role', 'tabpanel'),
        $attr('aria-labelledby', $pane . '-tab'),
        $attr('tabindex', '0'),
    )));
    $n++;
}
$pages[] = array(
    'key'              => 'menu',
    'name'             => lang('our-menu'),
    'folder'           => 'public',
    'title'            => lang('Menu'),
    'meta_description' => lang('Coffee, tea and cold drinks, breakfast, lunch and the bakery: the whole menu with prices and diet tags.'),
    'tree'             => $page(array(
        $page_header(lang('Menu'), lang('Coffee from our roastery, breakfast and lunch from our kitchen and a bakery that starts at half past five. The same menu in every café.')),
        $el('section', 'py-5', lang('Menu'), array(
            $container(array(
                $diet_key(),
                $el('ul', 'nav nav-pills gap-2 mt-4 mb-4', lang('Menu Tabs'), $menu_tabs, array('_attrs' => array(
                    $attr('role', 'tablist'),
                    $attr('aria-label', lang('Parts of the menu')),
                ))),
                $el('div', 'tab-content', lang('Menu Categories'), $menu_panes),
                $para(lang('Prices include VAT. The menu changes with the seasons; the counter has today\'s.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('This Season\'s Coffee'), array(
            $container(array(
                $section_header(lang('From our roastery'), lang('This season\'s coffee')),
                $row($origin_cards(), '4'),
            )),
        )),
        $el('section', 'py-5', lang('Before You Order'), array(
            $container(array(
                $row(array(
                    $value('bi-bag', lang('Take it with you'), lang('Everything on the menu comes in a box or a cup to take away. Bring your own cup and the coffee costs less.')),
                    $value('bi-telephone', lang('Order ahead'), lang('Call the café and pick up on your way; at the Business District café the order waits on the counter.')),
                    $value('bi-heart', lang('Ask about allergens'), lang('Our team knows what is in every dish and tells you what suits your diet.')),
                ), '4'),
            )),
        )),
        $shared('table_band'),
    )),
);

// The cafés: a section for each, with its photo, address, hours, what it
// offers and the way to it; every café's hours in one table under them.
$cafe_sections = array();
$hour_rows = array();
$n = 0;
foreach ($cafes as $key => $c) {
    $features = array();
    foreach ($c['features'] as $f) {
        $features[] = $el('li', 'd-flex align-items-center gap-2 mb-2', lang('List Item'), array($icon($f[0], 'text-primary'), $span($f[1], '', lang('Text'))));
    }
    $cafe_sections[] = $el('section', 'py-5' . ($n % 2 ? ' bg-body-tertiary' : ''), $c['name'], array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
                        $image($photo('cafe-' . $c['seed'], 1200, 900), $c['name'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                    )),
                ), '', '6', '', '12', $n % 2 ? 'order-lg-2' : ''),
                $col(array(
                    $span($c['badge'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis mb-2', lang('Badge')),
                    $heading('h2', $c['name'], 'h3 fw-bold mb-2'),
                    $para($c['text'], 'lead text-body-secondary mb-4'),
                    $el('ul', 'list-unstyled mb-3', lang('Contact Details'), array(
                        $el('li', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array($icon('bi-geo-alt', 'text-primary'), $span($c['address'], '', lang('Text')))),
                        $el('li', 'd-flex align-items-start gap-2 mb-2', lang('Hours'), array($icon('bi-clock', 'text-primary'), $span($c['hours'], '', lang('Text')))),
                        $el('li', 'd-flex align-items-start gap-2', lang('Phone'), array(
                            $icon('bi-telephone', 'text-primary'),
                            $link($c['phone'], 'tel:' . str_replace(' ', '', $c['phone']), 'link-body-emphasis text-decoration-none'),
                        )),
                    )),
                    $el('ul', 'list-unstyled d-flex flex-wrap column-gap-4 small mb-3', lang('Features'), $features),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $link(lang('Book a Table'), '{{page:reservation}}', 'btn btn-primary px-4'),
                        $link(lang('Directions'), 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($c['address']), 'btn btn-outline-primary',
                            array($attr('rel', 'noopener')), array('target' => '_blank')),
                    )),
                ), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable('cafe-' . str_replace('_', '-', $key), $c['topics']));
    $hour_rows[] = $el('tr', '', lang('Row'), array(
        $el('th', 'fw-semibold', lang('Café'), array(), array('text' => $c['name'], '_attrs' => array($attr('scope', 'row')))),
        $el('td', '', lang('Monday to Friday'), array(), array('text' => $c['week'][0])),
        $el('td', '', lang('Saturday'), array(), array('text' => $c['week'][1])),
        $el('td', '', lang('Sunday'), array(), array('text' => $c['week'][2])),
    ));
    $n++;
}
$hours_head = array();
foreach (array(lang('Café'), lang('Mon–Fri'), lang('Saturday'), lang('Sunday')) as $h) {
    $hours_head[] = $el('th', 'text-nowrap', lang('Column'), array(), array('text' => $h, '_attrs' => array($attr('scope', 'col'))));
}
$pages[] = array(
    'key'              => 'locations',
    'name'             => lang('our-cafes'),
    'folder'           => 'public',
    'title'            => lang('Our Cafés'),
    'meta_description' => lang('Three cafés in the city: the addresses, the opening hours and what each one offers, from the terrace to the meeting tables.'),
    'tree'             => $page(array_merge(
        array($page_header(lang('Our Cafés'), lang('Three cafés, one roastery and the same menu in each. Find the one nearest to you.'))),
        $cafe_sections,
        array(
            $el('section', 'py-5 border-top', lang('Opening Hours'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $para(lang('Hours'), 'small text-uppercase fw-semibold text-primary mb-1'),
                            $heading('h2', lang('Opening hours'), 'h3 fw-bold mb-3'),
                            $para(lang('On public holidays the cafés open at nine; the Business District café stays closed.'), 'text-body-secondary mb-0'),
                        ), '', '4'),
                        $col(array(
                            $el('div', 'table-responsive', lang('Table Frame'), array(
                                $el('table', 'table align-middle mb-0', lang('Hours Table'), array(
                                    $el('thead', '', lang('Table Head'), array($el('tr', '', lang('Row'), $hours_head))),
                                    $el('tbody', '', lang('Rows'), $hour_rows),
                                )),
                            )),
                        ), '', '8'),
                    ), '4', '', 'g-lg-5'),
                )),
            )),
            $shared('table_band'),
        )
    )),
);

// Corporate: what the catering does, the packages, how an order works, the
// quote request (#quote, the page's form) and the common questions. What is
// sent opens the request's own page.
$catering_cards = array();
foreach ($catering_services as $s) $catering_cards[] = $value($s[0], $s[1], $s[2], '6', '3');
$package_cards = array();
foreach ($packages as $p) {
    $featured = !empty($p['featured']);
    $package_cards[] = $col(array(
        $el('div', 'card h-100 ' . ($featured ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), $p['title'], array_merge(
            $featured ? array($el('div', 'card-header text-bg-primary text-center small fw-semibold', lang('Badge'), array(), array('text' => lang('Most ordered')))) : array(),
            array($el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($p['icon'], 'align-self-start mb-3'),
                $heading('h3', $p['title'], 'h5 fw-bold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $el('p', 'd-flex align-items-baseline gap-2 mb-1', lang('Price'), array(
                    $span($p['price'], 'display-6 fw-bold', lang('Value')),
                    $span(lang('a person'), 'text-body-secondary', lang('Unit')),
                )),
                $para($p['min'], 'small fw-semibold text-primary mb-3'),
                $check_list($p['items'], 'list-unstyled small mb-4'),
                $link(lang('Ask for a quote'), '#quote', 'btn ' . ($featured ? 'btn-primary' : 'btn-outline-primary') . ' mt-auto'),
            )))
        )),
    ), '', '4');
}
$pages[] = array(
    'key'              => 'corporate',
    'name'             => lang('corporate-catering'),
    'folder'           => 'public',
    'title'            => lang('Corporate & Catering'),
    'meta_description' => lang('Coffee breaks, office breakfasts, lunch boxes and coffee bars for companies, cooked the same morning and delivered on time. Ask for a quote online.'),
    'form'             => array(
        'form_name'            => lang('Catering Requests'),
        'confirmation_message' => lang('Thank you, your request has reached us. We will send you a quote within one working day.'),
        // What is sent opens the request's own page, where the company sees
        // its status and both sides write as comments. A visitor without an
        // account gets one (auto-registration), which that page tells them.
        'confirmation_page_id' => '{{tab:quote_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New catering request: ^^company^^, ^^event_date^^'),
        'notify_page_id'       => '{{tab:email_quote_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your catering request'),
        'confirm_page_id'      => '{{tab:email_quote_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Corporate & Catering'), lang('From a coffee break for ten to lunch for two hundred: cooked in our kitchen the same morning and brought at the time you set.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Request a Quote'), '#quote', 'btn btn-primary px-4'),
                $link(lang('See the packages'), '#catering-packages', 'btn btn-outline-primary'),
            )),
        )),
        $el('section', 'py-5', lang('What We Do'), array(
            $container(array(
                $section_header(lang('Catering'), lang('What we bring to your office')),
                $row($catering_cards, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Catering Packages'), array(
            $container(array(
                $section_header(lang('Packages'), lang('Three packages, priced per person')),
                $row($package_cards, '4', '', 'align-items-stretch'),
                $para(lang('Prices before VAT, delivery in the city included from 20 people. Something else in mind? Ask us; most menus can be changed.'), 'small text-body-secondary text-center mt-4 mb-0'),
            )),
        ), array('id' => 'catering-packages')),
        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Ordering'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Three steps to your order'), 'h3 fw-bold mb-3'),
                        $para(lang('Send the request below; it opens a page of its own, where you follow the quote and write to our catering team.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col(array(
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Tell us what you need'), lang('The day, the number of guests and the package; a line about diets and allergies helps.')),
                            $step(2, lang('We send a quote'), lang('Within one working day, with the menu and the price per person.')),
                            $step(3, lang('We cook and deliver'), lang('The same morning, at the time you set, with everything needed to serve.')),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Request a Quote'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
                                $heading('h2', lang('Request a catering quote'), 'h3 fw-bold mb-2'),
                                $para(lang('Tell us about your event; we answer with a menu and a price within one working day.'), 'text-body-secondary mb-4'),
                                $widget('quote_form'),
                            )),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body mb-4', lang('What Happens Next'), array(
                            $heading('h2', lang('What happens next'), 'h5 fw-bold mb-3'),
                            $el('ol', 'small text-body-secondary ps-3 mb-0', lang('List'), array(
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('Your request opens its own page, and an account for you if you have none.'))),
                                $el('li', 'mb-2', lang('Step'), array(), array('text' => lang('We send the quote by e-mail; the page shows its status too.'))),
                                $el('li', '', lang('Step'), array(), array('text' => lang('A question or a change? Write under your request.'))),
                            )),
                        )),
                        $el('div', 'p-4 rounded-4 border bg-body', lang('Catering Contact'), array(
                            $heading('h2', lang('Talk to our catering team'), 'h5 fw-bold mb-3'),
                            $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Person'), array(
                                $image($portrait(lang('Daniel Ross'), 160), lang('Daniel Ross'), 'rounded-circle object-fit-cover', array('width' => '56', 'height' => '56', '_attrs' => array($attr('loading', 'lazy')))),
                                $el('div', '', lang('Name'), array(
                                    $para(lang('Daniel Ross'), 'fw-semibold mb-0'),
                                    $para(lang('Head of Catering'), 'small text-body-secondary mb-0'),
                                )),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link('+90 555 000 00 44', 'tel:+905550000044', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link('catering@example.com', 'mailto:catering@example.com', 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), array('id' => 'quote')),
        $el('section', 'py-5', lang('Frequently Asked Questions'), array(
            $container(array(
                $section_header(lang('Questions'), lang('Frequently asked questions')),
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(1, lang('How far ahead should we order?'), lang('Two working days for up to fifty guests, a week for more. For tomorrow morning, call us; we often manage.'), lang('order ahead, notice, lead time, last minute')),
                            $faq(2, lang('Can you cater for allergies and diets?'), lang('Yes. Tell us how many guests are vegan, vegetarian or gluten free, and any allergies; every box is labelled.'), lang('allergy, vegan, vegetarian, gluten free, halal, diet')),
                            $faq(3, lang('Do you deliver outside the city centre?'), lang('Delivery in the city is free from 20 guests. Further out, we add the cost of the trip to the quote.'), lang('delivery, delivery cost, outside the city')),
                            $faq(4, lang('How do we pay?'), lang('By bank transfer against an invoice, within 14 days. Companies that order every week can be invoiced once a month.'), lang('payment, invoice, bank transfer, monthly invoice')),
                        ), array('id' => 'cf-faq')),
                    ), '', '9'),
                ), '', 'center'),
            )),
        )),
    )),
);

// Events & Workshops: the coming events (records of the Events form, from
// today on) and the café for a private event.
$pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events & Workshops'),
    'meta_description' => lang('Live music, coffee tastings, latte art and baking workshops at our cafés: the coming dates and how to join.'),
    'tree'             => $page(array(
        $page_header(lang('Events & Workshops'), lang('Live music on Friday evenings, tastings at the roastery and workshops for grown-ups and children. Book a table to join.')),
        $el('section', 'py-5', lang('Coming Events'), array(
            $container(array($widget('events_list'))),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Private Events'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
                            $image($photo('private-event', 1200, 900), lang('A long table laid for a private event in the café'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Private events'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Your own event at our café'), 'h3 fw-bold mb-3'),
                        $para(lang('Birthdays, team breakfasts, book clubs and launches: the upstairs room at Riverside seats 40, the terrace in the Old Town 25.'), 'text-body-secondary mb-4'),
                        $check_list(array(
                            lang('A menu we plan with you'),
                            lang('Our baristas behind the bar'),
                            lang('Music, a screen and a microphone on request'),
                        ), 'list-unstyled mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Ask about a private event'), '{{page:contact}}', 'btn btn-primary px-4'),
                            $link(lang('Catering Packages'), '{{page:corporate}}', 'btn btn-outline-primary'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $shared('table_band'),
    )),
);

// About: the company's story, what it stands for, the roastery (#roastery),
// the people, how it looks after the city and the way to join the team.
$team_cards = array();
foreach ($team as $t) {
    $team_cards[] = $col(array(
        $el('article', 'card h-100 border-0 shadow-sm text-center', $t['name'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $image($portrait($t['name'], 240), $t['name'], 'rounded-circle object-fit-cover mb-3', array('width' => '112', 'height' => '112', '_attrs' => array($attr('loading', 'lazy')))),
                $heading('h3', $t['name'], 'h6 fw-bold mb-1'),
                $para($t['role'], 'small text-primary fw-semibold mb-2'),
                $para($t['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3');
}
$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A coffee company since 2009: our own roastery, a kitchen that bakes every morning, three cafés and the people behind them.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), lang('One machine and a borrowed roaster in 2009; three cafés, a roastery and a catering kitchen today.')),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Our story'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('One machine, a borrowed roaster and a market square'), 'h3 fw-bold mb-3'),
                        $para(lang('Jane Cooper opened the first café on the market square in 2009, with one espresso machine and a roaster borrowed from a friend. The neighbours came for the coffee and stayed for the cakes her mother baked.'), 'text-body-secondary'),
                        $para(lang('Today we roast our own coffee, bake in our own kitchen, run three cafés and cook for more than a hundred companies. The promise is the one from the first day: good coffee, made by people who know your name.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                            $image($photo('story', 1200, 900), lang('The first café on the market square'), 'object-fit-cover'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),
        $el('section', 'pb-5', lang('Features'), array(
            $container(array(
                $row(array(
                    $value('bi-fire', lang('Roasted by us'), lang('We buy the green beans from farms we know and roast every Monday, so the coffee in your cup is never more than a week old.')),
                    $value('bi-egg-fried', lang('Made each morning'), lang('The croissants, the bread and the cakes come out of our own oven, not out of a freezer.')),
                    $value('bi-people', lang('A place for everyone'), lang('Laptops, prams, dogs and grandparents are all welcome; so is staying for a second coffee.')),
                ), '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('The Roastery'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
                            $image($photo('roastery', 1200, 900), lang('Coffee beans cooling after the roast'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6', '', '12', 'order-lg-2'),
                    $col(array(
                        $para(lang('The roastery'), 'small text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('Where every cup begins'), 'h3 fw-bold mb-3'),
                        $para(lang('Behind the counter of the Old Town café, our head roaster turns green beans from six farms into the week\'s coffee. Every batch is tasted before it leaves for the cafés and the offices.'), 'text-body-secondary mb-4'),
                        $check_list(array(
                            lang('Green beans from six farms we visit'),
                            lang('Roasted on Mondays, in small batches'),
                            lang('Every batch tasted before it reaches you'),
                        ), 'list-unstyled mb-4'),
                        $link(lang('This season\'s coffee'), '{{page:menu}}', 'btn btn-outline-primary'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), array('id' => 'roastery')),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The people'), lang('Meet the team')),
                $row($team_cards, '4'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Sustainability'), array(
            $container(array(
                $section_header(lang('Our city'), lang('Looking after the city we work in')),
                $row(array(
                    $value('bi-recycle', lang('Bring your own cup'), lang('Every coffee in your own cup costs less, and our cups to go are made from paper.')),
                    $value('bi-tree', lang('Grounds for the gardens'), lang('The coffee grounds of all three cafés go to the city\'s community gardens every week.')),
                    $value('bi-bicycle', lang('Delivered by bike'), lang('Catering in the city centre leaves our kitchen on a cargo bike.')),
                ), '4'),
            )),
        )),
        $el('section', 'pb-5', lang('Careers'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 p-lg-5 rounded-4 bg-primary-subtle', lang('Careers Band'), array(
                    $el('div', '', lang('Words'), array(
                        $heading('h2', lang('Join the team'), 'h4 fw-bold mb-1'),
                        $para(lang('Baristas, bakers and a roastery assistant: see what is open and apply in five minutes.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Open positions'), '{{page:careers}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
        $shared('table_band'),
    )),
);

// Careers: what working here is like, the open positions (each a search
// section, #position-<key>) and the application form (#apply), which takes
// a CV. The answers are sent to the café; no account is opened.
$benefit_cards = array(
    $value('bi-mortarboard', lang('Our own academy'), lang('Coffee, milk and the espresso machine, taught by our head roaster before your first shift.'), '6', '3'),
    $value('bi-cup-hot', lang('Coffee and a meal'), lang('Free coffee on every shift and a meal from our kitchen.'), '6', '3'),
    $value('bi-calendar-week', lang('Shifts planned ahead'), lang('The rota two weeks ahead, and swaps agreed within the team.'), '6', '3'),
    $value('bi-graph-up-arrow', lang('Room to grow'), lang('Most of our supervisors started behind the counter.'), '6', '3'),
);
$position_items = array();
foreach ($positions as $key => $p) {
    $position_id = 'position-' . $key;
    $position_items[] = $el('div', 'accordion-item', $p['title'], array(
        $el('h3', 'accordion-header', lang('Question Header'), array(
            $el('button', 'accordion-button collapsed', lang('Job Opening'), array(
                $el('span', 'd-flex flex-column flex-md-row align-items-md-center gap-1 gap-md-3 me-2', lang('Title'), array(
                    $span($p['title'], 'fw-semibold', lang('Job Title')),
                    $el('span', 'd-flex flex-wrap gap-1', lang('Details'), array(
                        $el('span', 'badge rounded-pill bg-body-tertiary text-body border d-inline-flex align-items-center gap-1', lang('Place'), array($icon('bi-geo-alt'), $span($p['where'], '', lang('Text')))),
                        $span($p['type'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Hours')),
                    )),
                )),
            ), array('_attrs' => array(
                $attr('type', 'button'),
                $attr('data-bs-toggle', 'collapse'),
                $attr('data-bs-target', '#' . $position_id . '-details'),
                $attr('aria-expanded', 'false'),
                $attr('aria-controls', $position_id . '-details'),
            ))),
        )),
        $el('div', 'accordion-collapse collapse', lang('Answer'), array(
            $el('div', 'accordion-body', lang('Body'), array(
                $para($p['text'], 'text-body-secondary'),
                $check_list($p['items'], 'list-unstyled small mb-3'),
                $link(lang('Apply for this position'), '#apply', 'btn btn-sm btn-primary'),
            )),
        ), array('id' => $position_id . '-details', '_attrs' => array($attr('data-bs-parent', '#cf-positions')))),
    ), $searchable($position_id, $p['topics']));
}
$pages[] = array(
    'key'              => 'careers',
    'name'             => lang('careers'),
    'folder'           => 'public',
    'title'            => lang('Careers'),
    'meta_description' => lang('Work with us as a barista, in the kitchen, as a shift supervisor or in the roastery. See the open positions and apply online.'),
    'form'             => array(
        'form_name'            => lang('Job Applications'),
        'confirmation_message' => lang('Thank you for applying. We read every application and answer within a week.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New application: ^^position^^'),
        'notify_page_id'       => '{{tab:email_application_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your application'),
        'confirm_page_id'      => '{{tab:email_application_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Careers'), lang('Good coffee is made by people who enjoy making it. Come and make it with us.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Open positions'), '#cf-open-positions', 'btn btn-primary px-4'),
                $link(lang('Apply now'), '#apply', 'btn btn-outline-primary'),
            )),
        )),
        $el('section', 'py-5', lang('Working Here'), array(
            $container(array(
                $section_header(lang('Working here'), lang('What we offer')),
                $row($benefit_cards, '4'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Open Positions'), array(
            $container(array(
                $section_header(lang('Join us'), lang('Open positions')),
                $el('div', 'accordion', lang('Positions'), $position_items, array('id' => 'cf-positions')),
            )),
        ), array('id' => 'cf-open-positions')),
        $el('section', 'py-5', lang('Application Form'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array(
                                $heading('h2', lang('Apply now'), 'h3 fw-bold mb-2'),
                                $para(lang('Five minutes and a CV. No CV yet? Tell us about yourself in the message instead.'), 'text-body-secondary mb-4'),
                                $widget('careers_form'),
                            )),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('How We Hire'), array(
                            $heading('h2', lang('How we hire'), 'h5 fw-bold mb-3'),
                            $el('ol', 'list-unstyled mb-0', lang('List'), array(
                                $step(1, lang('We read your application'), lang('Every one of them, within a week.')),
                                $step(2, lang('A coffee together'), lang('A short talk in one of the cafés, about you and the job.')),
                                $step(3, lang('A trial shift'), lang('A paid half day with the team, then our answer.')),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        ), array('id' => 'apply')),
    )),
);

// Contact: a message to the café; the staff are told, the sender gets a
// reply. Private events and lost property are asked about here too.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('A question about a booking, a catering order, a private event or something left behind? Write to us or call the café.'),
    'form'             => array(
        'form_name'            => lang('Contact Form'),
        'confirmation_message' => lang('Thank you, your message has reached us. We will get back to you soon.'),
        // What is sent opens the conversation page: its first message is
        // this one, and the answers follow as comments. A visitor without an
        // account gets one (auto-registration), which that page tells them.
        'confirmation_page_id' => '{{tab:conversation}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Contact us'), lang('A question about a booking, a catering order or a private event: write to us, and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('contact_form')), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('Contact Details'), array(
                            $heading('h2', lang('Head office'), 'h5 fw-bold mb-3'),
                            $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                                $icon('bi-geo-alt', 'text-primary'),
                                $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link('+90 555 000 00 00', 'tel:+905550000000', 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('p', 'd-flex align-items-center gap-2 mb-4', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link('hello@example.com', 'mailto:hello@example.com', 'link-body-emphasis text-decoration-none'),
                            )),
                            $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                            $hours('row small mb-3', true),
                            $link(lang('All our cafés'), '{{page:locations}}', 'small fw-semibold link-primary text-decoration-none'),
                        )),
                        $el('div', 'p-4 rounded-4 border mt-4', lang('Inbox Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-inbox', 'text-primary'),
                                $span(lang('Follow the answer in your inbox'), '', lang('Text')),
                            )),
                            $para(lang('Your message becomes a conversation: the page that opens after you send it, and your inbox, show our answers, and you can write back there. If you have no account yet, one is opened for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-outline-primary'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    )),
);

// The newsletter: its page owns the form. What is sent adds the address to
// the newsletter's contact group, and the subscriber gets the welcome
// e-mail.
$pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('New beans, the seasonal menu, tastings and workshops, from our cafés to your inbox twice a month.'),
    'form'             => array(
        'form_name'            => lang('Newsletter'),
        'confirmation_message' => lang('Thank you! You are now subscribed to our newsletter.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to the café\'s letter'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('New beans, the seasonal menu, tastings and workshops, twice a month.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 border border-2', lang('Form Card'), array($widget('newsletter_form'))),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('What You Get'), array(
                            $heading('h2', lang('In every letter'), 'h5 fw-bold mb-3'),
                            $check_list(array(
                                lang('The new coffee before it reaches the cafés'),
                                lang('The dates of tastings, workshops and live music'),
                                lang('Two e-mails a month, never your address to anyone else'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// Blog: every post, newest first, with a search.
$pages[] = array(
    'key'              => 'blog',
    'name'             => lang('blog'),
    'folder'           => 'public',
    'title'            => lang('Blog'),
    'meta_description' => lang('Stories from the café: coffee, the roastery, the kitchen and the people behind them.'),
    'tree'             => $page(array(
        $page_header(lang('Blog'), lang('Stories from the café: coffee, the roastery, the kitchen and the people behind them.')),
        $el('section', 'py-5', lang('Posts'), array(
            $container(array($widget('blog_list'))),
        )),
    )),
);

// A blog post: the post the list linked to (?r=), and its comments.
$pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('blog-post'),
    'folder'           => 'public',
    'title'            => lang('Blog Post'),
    'meta_description' => lang('A post from our blog.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('New comment on the blog'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('blog_post'),
        $comments_section(lang('Comments')),
    )),
);

// Book a table: the request form, with the café's rules and the hours
// beside it. What is sent opens the booking's own page.
$pages[] = array(
    'key'              => 'reservation',
    'name'             => lang('book-a-table'),
    'folder'           => 'public',
    'title'            => lang('Book a Table'),
    'meta_description' => lang('Book a table online: choose the café, the day, the time and the number of guests; we confirm by e-mail.'),
    'form'             => array(
        'form_name'            => lang('Reservations'),
        'confirmation_message' => lang('Thank you, your booking request has reached us. We will confirm it by e-mail.'),
        // What is sent opens the booking's own page, where the guest sees
        // its status and both sides write as comments. A visitor without an
        // account gets one (auto-registration), which that page tells them.
        'confirmation_page_id' => '{{tab:reservation_view}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New table booking: ^^cafe^^, ^^reservation_date^^ ^^reservation_time^^'),
        'notify_page_id'       => '{{tab:email_reservation_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your table booking'),
        'confirm_page_id'      => '{{tab:email_reservation_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Book a Table'), lang('Choose the café, the day and the time. We confirm your table by e-mail, usually within the hour.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'card border-0 shadow-sm', lang('Form Card'), array(
                            $el('div', 'card-body p-4 p-lg-5', lang('Card Body'), array($widget('reservation_form'))),
                        )),
                    ), '', '8'),
                    $col(array(
                        $el('div', 'alert alert-info d-flex gap-2', lang('Group Note'), array(
                            $icon('bi-people', 'mt-1'),
                            $span(lang('Nine or more of you? Choose "9 or more" and we call you to plan it, or ask for a catering quote.'), '', lang('Text')),
                        ), array('_attrs' => array($attr('role', 'note')))),
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Good to Know'), array(
                            $heading('h2', lang('Good to know'), 'h5 fw-bold mb-3'),
                            $check_list(array(
                                lang('We keep your table for fifteen minutes.'),
                                lang('Terrace tables depend on the weather; we tell you on the day.'),
                                lang('The Business District café is closed at the weekend.'),
                                lang('Need to change or cancel? Write under your booking.'),
                            ), 'list-unstyled small mb-0'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Opening hours'), array(
                            $heading('h2', lang('Opening hours'), 'h5 fw-bold mb-3'),
                            $hours('row small mb-0', true),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One booking: the request, its status and the messages under it. The
// form's confirmation page; My Reservations and the e-mails link to it.
$pages[] = array(
    'key'      => 'reservation_view',
    'name'     => lang('reservation'),
    'folder'   => 'registration',
    'title'    => lang('Reservation'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your booking'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('reservation_detail'),
        $comments_section(lang('Messages')),
    ), false),
);

// My Reservations: the guest's bookings (for the staff, everybody's).
$pages[] = array(
    'key'     => 'my_reservations',
    'name'    => lang('my-reservations'),
    'folder'  => 'registration',
    'title'   => lang('My Reservations'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Reservations'), lang('Your table bookings, their status and our messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_reservations_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Book Another Table'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-calendar-plus', 'text-primary'),
                                $span(lang('Another table?'), '', lang('Text')),
                            )),
                            $para(lang('Book again in a minute; the details you gave last time are filled in for you.'), 'small text-body-secondary mb-3'),
                            $link(lang('Book a Table'), '{{page:reservation}}', 'btn btn-sm btn-primary'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Change or Cancel'), array(
                            $heading('h2', lang('Need to change or cancel?'), 'h6 fw-bold mb-2'),
                            $para(lang('Open the booking and write to us under it, or call the café. A few hours\' notice lets another guest have the table.'), 'small text-body-secondary mb-0'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// One catering request: what was asked for, its status and the messages
// under it. The quote form's confirmation page.
$pages[] = array(
    'key'      => 'quote_view',
    'name'     => lang('catering-request'),
    'folder'   => 'registration',
    'title'    => lang('Catering Request'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new message about your catering request'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('quote_detail'),
        $comments_section(lang('Messages')),
    ), false),
);

// My Catering Requests: the company's requests (for the staff, everybody's).
$pages[] = array(
    'key'     => 'my_quotes',
    'name'    => lang('my-catering-requests'),
    'folder'  => 'registration',
    'title'   => lang('My Catering Requests'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('My Catering Requests'), lang('Your catering requests, their quotes and our messages, newest first.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('my_quotes_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Order Again'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-basket', 'text-primary'),
                                $span(lang('Another event coming up?'), '', lang('Text')),
                            )),
                            $para(lang('Send a new request; we answer with a quote within one working day.'), 'small text-body-secondary mb-3'),
                            $link(lang('Request a Quote'), '{{page:corporate}}#quote', 'btn btn-sm btn-primary'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Catering Packages'), array(
                            $heading('h2', lang('Our packages'), 'h6 fw-bold mb-2'),
                            $para(lang('Coffee breaks, office breakfasts and lunch boxes, priced per person.'), 'small text-body-secondary mb-3'),
                            $link(lang('Catering Packages'), '{{page:corporate}}', 'btn btn-sm btn-outline-primary'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);

// Signing in and out.
$sign_page = function ($key, $name, $title, $widget_key) use ($page, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'public',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array($widget($widget_key)), false),
    );
};
$pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login');
$pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register');
$pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// My account: the guest's own pages, behind the registration folder.
$pages[] = array(
    'key'     => 'my_account',
    'name'    => lang('my-account'),
    'folder'  => 'registration',
    'title'   => lang('My Account'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('my_account'), $widget('account_security')), false),
);
$pages[] = array_merge($sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book'), array('folder' => 'registration', 'requires' => 'ecommerce'));

// The inbox: the guest's conversations (for the staff, everybody's), and
// the conversation page, where both sides answer as comments.
$pages[] = array(
    'key'     => 'inbox',
    'name'    => lang('inbox'),
    'folder'  => 'registration',
    'title'   => lang('Inbox'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Inbox'), lang('The messages you sent us from the contact page, and our answers.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('inbox_list')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Inbox Note'), array(
                            $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                                $icon('bi-chat-left-text', 'text-primary'),
                                $span(lang('A question for us?'), '', lang('Text')),
                            )),
                            $para(lang('Write to us from the contact page; your message and our answers stay here, in one conversation.'), 'small text-body-secondary mb-3'),
                            $link(lang('New message'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Bookings Note'), array(
                            $para(lang('Messages about a booking or a catering request are written under the request itself.'), 'small text-body-secondary mb-3'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('My Reservations'), '{{page:my_reservations}}', 'btn btn-sm btn-outline-primary'),
                                $link(lang('My Catering Requests'), '{{page:my_quotes}}', 'btn btn-sm btn-outline-primary'),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
    ), false),
);
$pages[] = array(
    'key'      => 'conversation',
    'name'     => lang('conversation'),
    'folder'   => 'registration',
    'title'    => lang('Conversation'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Reply'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new reply to your conversation'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('conversation'),
        $comments_section(lang('Replies')),
    ), false),
);
$pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'registration',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Staff Directory'), lang('The people of our cafés, the kitchen and the office, and how to reach them.')),
        $el('section', 'py-5', lang('Directory'), array(
            $container(array($widget('staff_directory'))),
        )),
    ), false),
);

// The staff area, behind the private folder: the coming bookings and the
// newest catering requests first.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary text-decoration-none link-body-emphasis', $title, array(
            $icon($icon_name, 'fs-3 text-primary'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 fw-bold mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ), array('href' => $href)),
    ), '6', '3');
};
$pages[] = array(
    'key'     => 'staff',
    'name'    => lang('staff'),
    'folder'  => 'private',
    'title'   => lang('Staff Area'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $el('section', 'py-5 bg-body-tertiary border-bottom', lang('Staff Welcome'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $span(lang('Staff only'), 'badge rounded-pill text-bg-primary mb-3', lang('Badge')),
                        $heading('h1', lang('Welcome to the staff area'), 'display-6 fw-bold mb-3'),
                        $para(lang('The coming table bookings, the catering requests, the menu and the events are kept here. To confirm a booking or send a quote, open it and press Change Status; what you write under it reaches the guest by e-mail.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-primary px-4'),
                            $link(lang('All Reservations'), '{{page:my_reservations}}', 'btn btn-outline-primary'),
                        )),
                    ), '', '8'),
                )),
            )),
        )),
        $el('section', 'py-5', lang('Coming Reservations'), array(
            $container(array(
                $section_header(lang('From today'), lang('Coming table bookings'), lang('All Reservations'), '{{page:my_reservations}}'),
                $widget('staff_reservations'),
            )),
        )),
        $el('section', 'pb-5', lang('Catering Requests'), array(
            $container(array(
                $section_header(lang('Corporate'), lang('Newest catering requests'), lang('All Catering Requests'), '{{page:my_quotes}}'),
                $widget('staff_quotes'),
            )),
        )),
        $el('section', 'pb-5', lang('Shortcuts'), array(
            $container(array(
                $section_header(lang('Staff'), lang('Shortcuts')),
                $row(array(
                    $staff_shortcut('bi-calendar2-week', lang('All Reservations'), lang('Every booking, newest first, with its status.'), '{{page:my_reservations}}'),
                    $staff_shortcut('bi-briefcase', lang('All Catering Requests'), lang('Every request from companies, with its quote and status.'), '{{page:my_quotes}}'),
                    $staff_shortcut('bi-journal-plus', lang('Add to the Menu'), lang('A new dish or drink; it appears in its tab as soon as you send it.'), '{{page:menu_new}}'),
                    $staff_shortcut('bi-calendar-event', lang('New Event'), lang('A concert, a tasting or a workshop for the events page.'), '{{page:event_new}}'),
                    $staff_shortcut('bi-pencil-square', lang('New Blog Post'), lang('Write a post; it appears on the blog as soon as you send it.'), '{{page:blog_new}}'),
                    $staff_shortcut('bi-chat-left-text', lang('All Conversations'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                    $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the directory guests see.'), '{{page:staff_entry}}'),
                    $staff_shortcut('bi-speedometer2', lang('Control Panel'), lang('Job applications, every form\'s records and the newsletter.'), $panel_url),
                ), '4'),
                $el('div', 'alert alert-secondary d-flex gap-2 mt-4 mb-0', lang('Access Note'), array(
                    $icon('bi-info-circle', 'mt-1'),
                    $span(lang('Only the site staff and the users given access to the private folder see these pages. To let someone in, open their account under Users and tick the folder in Private Content Access.'), '', lang('Text')),
                )),
            )),
        )),
    )),
);

// The blog's first posts, written into its form when the form is made:
// the template carries them, so they are there on every site. Each is
// edited or deleted like any other post.
$post_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$sample_posts = array(
    array(
        'title'       => lang('How we choose the coffee we roast'),
        'summary'     => lang('From the farm to the roaster on a Monday morning: what our head roaster looks for in a green bean.'),
        'content'     => $post_body(array(
            lang('Every season our head roaster tastes samples from a few dozen farms. Most are good; we buy the ones that taste of the place they grow in.'),
            lang('We buy from the same six farms year after year, and pay more than the market price, so they can plan ahead and so can we.'),
            lang('The green beans rest for a few weeks, then go into the roaster on Mondays, in small batches. Every batch is tasted before it leaves for the cafés.'),
        )),
        'cover_image' => $photo('post-1', 1200, 675),
    ),
    array(
        'title'       => lang('Five ways to better coffee at home'),
        'summary'     => lang('Fresh beans, the right grind and water that is not quite boiling: our baristas\' advice for your kitchen.'),
        'content'     => $post_body(array(
            lang('Buy whole beans and grind them just before you brew. Ground coffee loses most of its taste within a day.'),
            lang('Weigh the coffee and the water: sixty grams of coffee for a litre is a good start. Water just off the boil, around 94 degrees, is hot enough.'),
            lang('Keep the beans in a closed tin, away from the light and out of the fridge, and use them within a month of the roast date.'),
        )),
        'cover_image' => $photo('post-2', 1200, 675),
    ),
    array(
        'title'       => lang('A morning in our bakery'),
        'summary'     => lang('Half past five, the ovens are warm: how the croissants reach the counter by eight.'),
        'content'     => $post_body(array(
            lang('The dough for the croissants is made the day before and rests in the cold overnight. At half past five it is shaped, left to rise and baked.'),
            lang('By seven the first trays leave for the cafés, together with the bread for the sandwiches and the cakes of the day.'),
            lang('What is left at closing time goes to a food bank near the Old Town café, so nothing is thrown away.'),
        )),
        'cover_image' => $photo('post-3', 1200, 675),
    ),
    array(
        'title'       => lang('Our coffee grounds now grow vegetables'),
        'summary'     => lang('Every week the grounds of all three cafés go to the city\'s community gardens. Here is why.'),
        'content'     => $post_body(array(
            lang('A busy café fills a bin with coffee grounds every day. Thrown away, they end up in the landfill; in a garden, they feed the soil.'),
            lang('Since the spring, a cargo bike takes the grounds of our cafés to three community gardens in the city every Friday.'),
            lang('The gardeners send back photos of tomatoes and courgettes, and in the autumn some of the pumpkins come back to our kitchen as soup.'),
        )),
        'cover_image' => $photo('post-4', 1200, 675),
    ),
);

// The events the events page opens with, a few days apart from the day
// the template is opened, so they are still to come. Each is a record of
// the Events form, edited or deleted like any other.
$event_day = function ($days) {
    return date('Y-m-d', time() + $days * 86400);
};
$event_kinds = array(lang('Live music'), lang('Tasting'), lang('Workshop'), lang('For children'), lang('Talk'));
$sample_events = array(
    array('title' => lang('Acoustic Friday'), 'event_date' => $event_day(3), 'event_time' => '20:00', 'cafe' => $cafes['riverside']['name'], 'kind' => lang('Live music'),
          'summary' => lang('Two guitars, one voice and the autumn menu until late.'), 'price' => lang('Free entry'), 'image' => $photo('event-music', 800, 450)),
    array('title' => lang('Coffee Tasting: Ethiopia'), 'event_date' => $event_day(6), 'event_time' => '18:30', 'cafe' => $cafes['old_town']['name'], 'kind' => lang('Tasting'),
          'summary' => lang('Taste this season\'s Yirgacheffe brewed three ways, with our head roaster at the roaster.'), 'price' => lang('$15, coffee included'), 'image' => $photo('event-tasting', 800, 450)),
    array('title' => lang('Latte Art for Beginners'), 'event_date' => $event_day(10), 'event_time' => '10:00', 'cafe' => $cafes['old_town']['name'], 'kind' => lang('Workshop'),
          'summary' => lang('Steam milk and pour your first heart: two hours, and all the coffee you make.'), 'price' => lang('$35'), 'image' => $photo('event-latte-art', 800, 450)),
    array('title' => lang('Little Bakers'), 'event_date' => $event_day(13), 'event_time' => '11:00', 'cafe' => $cafes['riverside']['name'], 'kind' => lang('For children'),
          'summary' => lang('Children from six bake and decorate their own cookies; their grown-ups get a coffee.'), 'price' => lang('$12 a child'), 'image' => $photo('event-bakers', 800, 450)),
    array('title' => lang('Brunch & Book Club'), 'event_date' => $event_day(17), 'event_time' => '10:30', 'cafe' => $cafes['old_town']['name'], 'kind' => lang('Talk'),
          'summary' => lang('This month\'s book, a long brunch and good company. New readers welcome.'), 'price' => lang('$18 with brunch'), 'image' => $photo('event-book-club', 800, 450)),
    array('title' => lang('Better Coffee at Home'), 'event_date' => $event_day(20), 'event_time' => '18:00', 'cafe' => $cafes['business']['name'], 'kind' => lang('Workshop'),
          'summary' => lang('Filter, French press and AeroPress: café coffee in your own kitchen, and a bag of beans to take home.'), 'price' => lang('$30'), 'image' => $photo('event-brewing', 800, 450)),
);

// The staff's forms: the menu, the events, the blog and the staff
// directory.
$staff_form_page = function ($key, $name, $title, $lead, $widget_key, $form) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'noindex' => true,
        'form'    => $form,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget($widget_key))),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};
$pages[] = $staff_form_page('menu_new', lang('add-to-the-menu'), lang('Add to the Menu'),
    lang('A new dish or drink appears in its tab of the menu as soon as you send it. To change or remove one, open the Menu form in the control panel.'), 'menu_form',
    array(
        'form_name'            => lang('Menu'),
        'confirmation_message' => lang('The dish is on the menu.'),
        'confirmation_page_id' => '{{tab:menu}}',
        // The template's dishes are written in when the form is made, so the
        // menu and the home page do not open empty (pg_cf_seed_sample_records()).
        'sample_records'       => $menu_records,
    ));
$pages[] = $staff_form_page('event_new', lang('new-event'), lang('New Event'),
    lang('The event appears on the events page and the home page until its day has passed.'), 'event_form',
    array(
        'form_name'            => lang('Event Calendar'),
        'confirmation_message' => lang('The event is published.'),
        'confirmation_page_id' => '{{tab:events}}',
        'sample_records'       => $sample_events,
    ));
$pages[] = $staff_form_page('blog_new', lang('new-blog-post'), lang('New Blog Post'),
    lang('Write the post; it appears on the blog as soon as you send it.'), 'blog_form',
    array(
        'form_name'            => lang('Blog Posts'),
        'confirmation_message' => lang('The post is published.'),
        'confirmation_page_id' => '{{tab:blog}}',
        'sample_records'       => $sample_posts,
    ));
$pages[] = $staff_form_page('staff_entry', lang('add-to-staff-directory'), lang('Add to the Staff Directory'),
    lang('Signed-in guests see the directory from their account.'), 'staff_form',
    array(
        'form_name'            => lang('Staff Directory'),
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ));

// The help center: a search over the whole site - pages and the parts of
// pages marked as search sections (the cafés, the positions, the questions)
// - with the common questions under it, each a search section of its own.
$help_topic = function ($icon_name, $text, $href) use ($el, $icon, $span) {
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Topic'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), array('href' => $href));
};
$pages[] = array(
    'key'              => 'search',
    'name'             => lang('help-center'),
    'folder'           => 'public',
    'title'            => lang('Help Center'),
    'meta_description' => lang('Search the café\'s site: the menu, the cafés and their hours, catering, events and answers to common questions.'),
    'tree'             => $page(array(
        $page_header(lang('Help Center'), lang('Search the whole site: the menu, the cafés, catering, events and the answers to the questions we hear most.')),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array($widget('site_search')), '', '8'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-body-tertiary mb-4', lang('Popular Topics'), array(
                            $heading('h2', lang('Popular topics'), 'h5 fw-bold mb-3'),
                            $el('nav', 'list-group', lang('Topics'), array(
                                $help_topic('bi-journal-text', lang('Menu'), '{{page:menu}}'),
                                $help_topic('bi-calendar2-check', lang('Book a Table'), '{{page:reservation}}'),
                                $help_topic('bi-clock', lang('Opening hours'), '{{page:locations}}'),
                                $help_topic('bi-briefcase', lang('Corporate & Catering'), '{{page:corporate}}'),
                                $help_topic('bi-person-workspace', lang('Careers'), '{{page:careers}}'),
                            ), array('_attrs' => array($attr('aria-label', lang('Popular topics'))))),
                        )),
                        $el('div', 'p-4 rounded-4 border', lang('Still Need Help'), array(
                            $heading('h2', lang('Still need help?'), 'h5 fw-bold mb-2'),
                            $para(lang('Write to us from the contact page; the answer comes to your inbox on this site.'), 'small text-body-secondary mb-3'),
                            $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5 border-top', lang('Frequently Asked Questions'), array(
            $container(array(
                $section_header(lang('Questions'), lang('Frequently asked questions')),
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('FAQ'), array(
                            $faq(21, lang('How do I book a table?'), lang('Open Book a Table, choose the café, the day, the time and the number of guests, and send the form. We confirm by e-mail, usually within the hour.'), lang('book, table, reservation, booking'), 'cf-help-faq'),
                            $faq(22, lang('How do I know my table is confirmed?'), lang('We send you an e-mail, and the status on your booking\'s page changes to Confirmed. My Reservations lists every booking with its status.'), lang('confirmation, confirmed, status'), 'cf-help-faq'),
                            $faq(23, lang('Can I come without a booking?'), lang('Of course. Most tables are kept for guests who walk in; a booking only makes sure there is one for you at a busy time.'), lang('walk in, no booking, without reservation'), 'cf-help-faq'),
                            $faq(24, lang('Do you have vegan and gluten-free dishes?'), lang('Yes, every day. The menu marks each dish with its diet tags, and our team tells you the allergens of anything on the counter.'), lang('vegan, vegetarian, gluten free, allergens, diet'), 'cf-help-faq'),
                            $faq(25, lang('Can I bring my dog?'), lang('Dogs are welcome on the terraces and inside the Old Town and Riverside cafés. We have water bowls, and sometimes a biscuit.'), lang('dog, pets, animals'), 'cf-help-faq'),
                            $faq(26, lang('Where do I find your answer to my message?'), lang('In your inbox: sign in and open Inbox from the menu under your name. Every reply is on the conversation page, and you can write back there.'), lang('inbox, reply, answer, conversation'), 'cf-help-faq'),
                        ), array('id' => 'cf-help-faq')),
                    ), '', '9'),
                ), '', 'center'),
            )),
        )),
    )),
);

// The site's error page.
$pages[] = array(
    'key'     => 'error',
    'name'    => lang('page-not-found'),
    'folder'  => 'public',
    'title'   => lang('Page Not Found'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array($widget('error_page')), false),
);

// ── E-mail pages ────────────────────────────────────────────────────────
// Sent as the body of an e-mail, never visited. E-mail programs drop linked
// stylesheets, so the few rules that shape them are written on the elements
// next to the Bootstrap classes.
$mail_style = function ($css) use ($attr) {
    return array('_attrs' => array($attr('style', $css)));
};
$mail_page = function ($children) use ($el, $shared, $mail_style) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border rounded-3 overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #eadfd5;border-radius:10px;overflow:hidden')),
        ), $mail_style('background:#f7f1ec;padding:24px 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#2b1d14')),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-bold mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:700;margin:0 0 16px;color:#2b1d14'))));
};
$mail_para = function ($text, $class = 'mb-3') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', 'margin:0 0 16px'))));
};
$mail_button = function ($text, $href) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;border-radius:6px;font-weight:600;text-decoration:none;'
            . 'background-color:#6f4e37;background-color:var(--bs-primary);color:#ffffff;color:var(--pg-on-primary,#ffffff)'))),
    ), $mail_style('margin:24px 0'));
};
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#75655a')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary rounded-3 p-3 my-3', $name, $children,
        $mail_style('background:#f7f1ec;border-radius:8px;padding:16px;margin:16px 0'));
};
$mail_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello') . ' ', '', lang('Text')),
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
    ), $mail_style('margin:0 0 16px'));
};
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#75655a;margin:24px 0 0'));
};
// The account the form opened for a visitor who had none: the address it
// signs in with, and how to choose a password (the temporary one is shown
// on the request's page, not mailed).
$mail_new_account = function () use ($el, $span, $link, $bind, $mail_style, $show_when) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #eadfd5;padding-top:16px'), $show_when('has_new_account')));
};
$mail_person = function ($label) use ($mail_box, $mail_line, $span, $bind) {
    return $mail_box(array(
        $mail_line($label, array(
            $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
            $span(' ', '', lang('Text')),
            $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
        )),
        $mail_line(lang('Email'), array($span('jane.cooper@example.com', '', lang('Email'), $bind('email')))),
        $mail_line(lang('Phone'), array($span('+90 555 000 00 00', '', lang('Phone'), $bind('phone')))),
    ), $label);
};
// The lines of a table booking, and of a catering request, for both of
// their e-mails.
$mail_reservation = function () use ($mail_box, $mail_line, $span, $bind) {
    return $mail_box(array(
        $mail_line(lang('Café'), array($span(lang('Old Town'), '', lang('Café'), $bind('cafe')))),
        $mail_line(lang('Date'), array($span('2026-10-20', '', lang('Date'), $bind('reservation_date')))),
        $mail_line(lang('Arrival time'), array($span('19:30', '', lang('Time'), $bind('reservation_time')))),
        $mail_line(lang('Guests'), array($span('2', '', lang('Guests'), $bind('guests')))),
        $mail_line(lang('Seating'), array($span(lang('Terrace'), '', lang('Seating'), $bind('seating')))),
        $mail_line(lang('Occasion'), array($span(lang('A birthday'), '', lang('Occasion'), $bind('occasion')))),
    ), lang('Reservation'));
};
$mail_quote = function () use ($mail_box, $mail_line, $span, $bind) {
    return $mail_box(array(
        $mail_line(lang('Company'), array($span(lang('Acme Ltd.'), '', lang('Company'), $bind('company')))),
        $mail_line(lang('Catering service'), array($span(lang('Office Breakfast'), '', lang('Catering service'), $bind('service')))),
        $mail_line(lang('Date'), array($span('2026-10-20', '', lang('Date'), $bind('event_date')))),
        $mail_line(lang('Serving time'), array($span('09:00', '', lang('Time'), $bind('serving_time')))),
        $mail_line(lang('Guests'), array($span('25', '', lang('Guests'), $bind('guests')))),
        $mail_line(lang('Delivery'), array($span(lang('Delivered to us'), '', lang('Delivery'), $bind('delivery')))),
        $mail_line(lang('Delivery address'), array($span(lang('Vertex Tower, 12th floor'), '', lang('Address'), $bind('address')))),
    ), lang('Catering Request'));
};

$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);
$mail_pages = array(
    // The booking form's notification and its reply to the guest.
    array('email_reservation_new', lang('email-new-reservation'), lang('New Reservation E-mail'), 'email_reservation_admin'),
    array('email_reservation_received', lang('email-reservation-received'), lang('Reservation Received E-mail'), 'email_reservation_guest'),
    // The quote form's notification and its reply to the company.
    array('email_quote_new', lang('email-new-catering-request'), lang('New Catering Request E-mail'), 'email_quote_admin'),
    array('email_quote_received', lang('email-catering-request-received'), lang('Catering Request Received E-mail'), 'email_quote_customer'),
    // The application form's notification and its reply to the applicant.
    array('email_application_new', lang('email-new-application'), lang('New Application E-mail'), 'email_application_admin'),
    array('email_application_received', lang('email-application-received'), lang('Application Received E-mail'), 'email_application_applicant'),
    // The contact form's notification and its reply to the sender.
    array('email_new_message', lang('email-new-message'), lang('New Message E-mail'), 'email_message_admin'),
    array('email_message_received', lang('email-message-received'), lang('Message Received E-mail'), 'email_message_sender'),
);
foreach ($mail_pages as $m) {
    $pages[] = array_merge($mail_settings, array(
        'key'   => $m[0],
        'name'  => $m[1],
        'title' => $m[2],
        'tree'  => $mail_page(array($widget($m[3]))),
    ));
}
// A new reply, comment or message: sent to the guest of a booking, the
// company of a catering request, the sender of a conversation and the
// visitors watching a page. The System region carries the comment and a
// "View or Reply" button.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: your booking, your catering request, a conversation or a post. You can read it below and answer it on the page.')),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));
// The newsletter's welcome, sent to a new subscriber.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-newsletter-welcome'),
    'title' => lang('Newsletter Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to the café\'s letter')),
        $mail_para(lang('Thank you for subscribing. Twice a month you hear about the new coffee, the seasonal menu and the dates of our tastings, workshops and live music.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3'),
        $mail_button(lang('See the coming events'), '{{page:events}}'),
    )),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$login_item = function ($text, $token, $flag = '') use ($el, $link) {
    $extra = ($flag !== '') ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return $el('li', '', lang('Menu Item'), array(
        $link($text, '#', 'dropdown-item', array(), array('_bindings' => array('href' => $token))),
    ), $extra);
};
$login_page_item = function ($text, $href) use ($el, $link) {
    return $el('li', '', lang('Menu Item'), array($link($text, $href, 'dropdown-item')));
};
$login_divider = function () use ($el) {
    return $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
};
// $flag shows the link only while that widget flag is on.
$account_link = function ($icon_name, $text, $href, $token = '', $flag = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($token !== '') $extra['_bindings'] = array('href' => $token);
    if ($flag !== '') $extra['_bindings']['eo_visible_if'] = $flag;
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Menu Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), $extra);
};

// The guest's account menu: the bookings and the catering requests first.
$account_links = array(
    $account_link('bi-calendar2-check', lang('My Reservations'), '{{page:my_reservations}}'),
    $account_link('bi-calendar-plus', lang('Book a Table'), '{{page:reservation}}'),
    $account_link('bi-briefcase', lang('My Catering Requests'), '{{page:my_quotes}}'),
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-paper', lang('Email Preferences'), '#', '__email_preferences_url');
$account_links[] = $account_link('bi-person-lines-fill', lang('Staff Directory'), '{{page:staff_directory}}');
// Drawn only for the members the staff area's private folder lets in.
$account_links[] = $account_link('bi-person-badge', lang('Staff Area'), '#', '__staff_url', 'is_staff');

// A detail of a list row: an icon and a value.
$row_detail = function ($icon_name, $name, $sample, $token, $class = '') use ($el, $icon, $span, $bind) {
    return $el('span', 'd-inline-flex align-items-center gap-1', $name, array($icon($icon_name), $span($sample, $class, $name, $bind($token))));
};
$status_badge = function ($sample, $class = 'badge rounded-pill bg-primary-subtle text-primary-emphasis') use ($span, $bind) {
    return $span($sample, $class, lang('Status'), $bind('status'));
};

// A table booking in a list: the café, the status, the day, the time and
// the party, linked to its page.
$reservation_row = function () use ($el, $span, $icon, $bind, $row_detail, $status_badge) {
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Reservation'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $span(lang('Old Town'), 'fw-semibold', lang('Café'), $bind('cafe')),
            $status_badge(lang('Requested')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
            $row_detail('bi-calendar3', lang('Date'), '2026-10-20', 'reservation_date'),
            $row_detail('bi-clock', lang('Time'), '19:30', 'reservation_time'),
            $el('span', 'd-inline-flex align-items-center gap-1', lang('Guests'), array(
                $icon('bi-people'),
                $span('2', '', lang('Guests'), $bind('guests')),
                $span(lang('guests'), '', lang('Label')),
            )),
            $row_detail('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments'),
        )),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// A catering request in a list: the company, the status, the service, the
// day and the number of guests, linked to its page.
$quote_row = function ($staff = false) use ($el, $span, $icon, $bind, $row_detail, $status_badge, $person_name) {
    $details = array(
        $row_detail('bi-basket', lang('Catering service'), lang('Office Breakfast'), 'service'),
        $row_detail('bi-calendar3', lang('Date'), '2026-10-20', 'event_date', $staff ? 'fw-semibold' : ''),
        $el('span', 'd-inline-flex align-items-center gap-1', lang('Guests'), array(
            $icon('bi-people'),
            $span('25', '', lang('Guests'), $bind('guests')),
            $span(lang('guests'), '', lang('Label')),
        )),
    );
    if ($staff) {
        $details[] = $el('span', 'd-inline-flex align-items-center gap-1', lang('Contact person'), array_merge(array($icon('bi-person')), $person_name()));
        $details[] = $row_detail('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone');
    } else {
        $details[] = $row_detail('bi-chat-left-text', lang('Messages'), '0', 'number_of_comments');
    }
    return $el('a', 'list-group-item list-group-item-action p-3', lang('Catering Request'), array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
            $span(lang('Acme Ltd.'), 'fw-semibold', lang('Company'), $bind('company')),
            $status_badge(lang('Request received')),
        )),
        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), $details),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};

// The record page of a booking or a catering request: not found, or the
// notices, the toolbar, the card with its details, the words the guest
// wrote and a line on writing to the café.
$record_page = function ($not_found_icon, $not_found_title, $not_found_text, $back_text, $back_page, $thanks, $card, $notes, $note_text) use ($el, $heading, $para, $icon, $span, $link, $bind, $show_when, $container, $row, $col, $sent_notices, $record_toolbar) {
    return array(
        $container(array(
            $row(array(
                $col(array(
                    $el('div', 'text-center py-5', lang('Not Found'), array(
                        $icon($not_found_icon, 'display-5 text-body-tertiary'),
                        $heading('h2', $not_found_title, 'h3 fw-bold mt-3'),
                        $para($not_found_text, 'text-body-secondary mb-4', $bind('not_found')),
                        $link($back_text, '{{page:' . $back_page . '}}', 'btn btn-primary px-4'),
                    ), $show_when('record_not_found')),
                    $el('div', '', lang('Record'), array_merge(
                        $sent_notices($thanks),
                        array($record_toolbar($back_text, $back_page, lang('Change Status')), $card),
                        $notes,
                        array($el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                            $icon('bi-chat-left-text', 'text-primary mt-1'),
                            $span($note_text, '', lang('Text')),
                        )))
                    ), $show_when('record_found')),
                ), '', '8'),
            ), '', 'center'),
        ), 'py-5'),
    );
};
// The card at the top of a record page: what it is, its title and status,
// and its details.
$record_card = function ($eyebrow, $title_sample, $title_token, $status_sample, $details) use ($el, $heading, $para, $span, $bind) {
    return $el('div', 'card border-0 shadow-sm mb-4', lang('Record Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                $el('div', '', lang('Words'), array(
                    $para($eyebrow, 'small text-uppercase fw-semibold text-primary mb-1'),
                    $heading('h1', $title_sample, 'h3 fw-bold mb-0', $bind($title_token)),
                )),
                $span($status_sample, 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6', lang('Status'), $bind('status')),
            )),
            $el('dl', 'row small mb-0', lang('Details'), $details),
        )),
    ));
};
// A box of words the guest wrote, which leaves when they wrote none.
$record_words = function ($label, $sample, $token) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 rounded-4 bg-body-tertiary mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
$name_detail = function ($label) use ($el, $person_name) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', 'col-sm-8', $label, $person_name()),
    );
};

// The account bar's view of the session.
$widgets = array(
    'login_region' => array(
        'page'   => '',
        'slug'   => lang('login-region'),
        'config' => array(
            'regionType'         => 'login_region',
            'login_page_id'      => '{{tab:login}}',
            'register_page_id'   => '{{tab:register}}',
            'show_register_link' => true,
            'account_page_id'    => '{{tab:my_account}}',
            'staff_page_id'      => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $el('div', 'd-flex align-items-center gap-2', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'btn btn-sm btn-link link-body-emphasis text-decoration-none text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-outline-primary text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('a', 'd-flex align-items-center gap-2 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/6f4e37/ffffff?text=JC', lang('Jane Cooper'), 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url', 'alt' => '__user_name'))),
                    $span(lang('Jane'), 'fw-semibold', lang('First Name'), $bind('__user_first_name')),
                ), array('href' => '#', '_attrs' => array(
                    $attr('role', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_page_item(lang('My Reservations'), '{{page:my_reservations}}'),
                    $login_page_item(lang('My Catering Requests'), '{{page:my_quotes}}'),
                    $login_page_item(lang('Inbox'), '{{page:inbox}}'),
                    $login_item(lang('Edit my profile'), '__profile_url'),
                    $login_item(lang('Staff area'), '__staff_url', 'is_staff'),
                    $login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $login_divider(),
                    $login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // The newsletter's own form, on its page: the address and the consent.
    'newsletter_form' => array(
        'page'   => 'newsletter',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $icon('bi-envelope-heart', 'fs-1 text-primary d-block mb-2'),
            $heading('h2', lang('The café\'s letter'), 'h4 fw-bold mb-2'),
            $para(lang('Leave your e-mail address; we write twice a month, with the new coffee and the coming events.'), 'text-body-secondary mb-4'),
            $newsletter_line('cf-newsletter-email'),
            $newsletter_consent('cf-newsletter-consent'),
            $captcha(),
            $loop(array()),
        )),
    ),

    // The band at the foot of the pages: the same form, the newsletter
    // page's, so every sign-up lands in one list.
    'newsletter_band' => array(
        'page'   => '',
        'slug'   => lang('newsletter'),
        'config' => array(
            'regionType'          => 'custom_form',
            'form_source'         => 'existing',
            'form_id'             => '{{tab:newsletter}}',
            'custom_form_page_id' => '{{tab:newsletter}}',
        ),
        'tree'   => $widget_root(array(
            $el('section', 'py-5', lang('Newsletter'), array(
                $container(array(
                    $el('div', 'rounded-4 bg-body-tertiary p-4 p-lg-5', lang('Newsletter Band'), array(
                        $row(array(
                            $col(array(
                                $el('div', 'd-flex align-items-start gap-3', lang('Text'), array(
                                    $icon('bi-envelope-heart', 'fs-1 lh-1 text-primary'),
                                    $el('div', '', lang('Words'), array(
                                        $heading('h2', lang('The café\'s letter'), 'h4 fw-bold mb-1'),
                                        $para(lang('New beans, the seasonal menu and the coming events, twice a month. Unsubscribe whenever you like.'), 'text-body-secondary mb-0'),
                                    )),
                                )),
                            ), '', '5'),
                            $col(array(
                                $newsletter_line('cf-band-email'),
                                $newsletter_consent('cf-band-consent'),
                                $captcha(),
                            ), '', '7'),
                        ), '4', '', 'align-items-center'),
                    )),
                )),
            )),
            $loop(array()),
        )),
    ),

    // The home page's favourites: the dishes marked for it, four of them.
    'home_favourites' => array(
        'page'   => 'home',
        'slug'   => lang('favourites'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:menu_new}}',
            'items_per_page'      => 4,
            'max_results'         => 4,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'filters'             => array(
                array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => ''),
            ),
            'empty_message'       => lang('Mark a dish for the home page and it appears here.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($dish_card()))), '4'),
        )),
    ),

    // The home page's next three events.
    'home_events' => array(
        'page'   => 'home',
        'slug'   => lang('coming-events'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:event_new}}',
            'items_per_page'      => 3,
            'max_results'         => 3,
            'order_by_field'      => 'event_date',
            'order_by_direction'  => 'ASC',
            'filters'             => array(
                array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
            ),
            'empty_message'       => lang('No events planned right now. Subscribe to the café\'s letter to hear about the next ones.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($event_card()))), '4'),
        )),
    ),

    // The three newest posts on the home page.
    'home_posts' => array(
        'page'   => 'home',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'detail_page_id'      => '{{tab:blog_post}}',
            'items_per_page'      => 3,
            'max_results'         => 3,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'empty_message'       => lang('No posts yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4'),
        )),
    ),

    // The events page: every event from today on, the nearest first.
    'events_list' => array(
        'page'   => 'events',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:event_new}}',
            'items_per_page'      => 12,
            'order_by_field'      => 'event_date',
            'order_by_direction'  => 'ASC',
            'filters'             => array(
                array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
            ),
            'empty_message'       => lang('No events planned right now. Subscribe to the café\'s letter to hear about the next ones.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($event_card()))), '4'),
        )),
    ),

    // The table booking: the fields are the page's form. The Status is the
    // staff's (office use only): a visitor never sees it, and every booking
    // starts as Requested.
    'reservation_form' => array(
        'page'   => 'reservation',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Where and when?')),
            $row(array(
                $col(array($field('cf-res-cafe', lang('Café'), $select('cf-res-cafe', 'cafe', lang('Choose a café…'), $cafe_names))), '6'),
                $col(array($field('cf-res-guests', lang('Guests'), $select('cf-res-guests', 'guests', '', $party_sizes, true, '2'))), '6'),
                $col(array($field('cf-res-date', lang('Date'), $input('cf-res-date', 'date', 'reservation_date', 'off'))), '6'),
                $col(array($field('cf-res-time', lang('Arrival time'), $select('cf-res-time', 'reservation_time', lang('Choose a time…'), $table_times))), '6'),
            ), '3'),
            $choices('cf-res-seating', 'seating', lang('Where would you like to sit?'), array(lang('No preference'), lang('Indoors'), lang('Terrace')), array(lang('No preference'))),
            $field('cf-res-occasion', lang('Occasion'), $select('cf-res-occasion', 'occasion', lang('Choose… (optional)'), $occasions, false)),
            $form_step(2, lang('About you')),
            $person_fields('cf-res'),
            $field('cf-res-notes', lang('Anything we should know?'), $textarea('cf-res-notes', 'notes', 3, false, lang('A high chair, a quiet corner, a birthday cake…'))),
            $field('cf-res-status', lang('Status'), $select('cf-res-status', 'status', '', array(
                lang('Requested'), lang('Confirmed'), lang('Completed'), lang('Cancelled'),
            ), false, lang('Requested'), array('office_use_only' => true))),
            $agree('cf-res-consent', 'consent', lang('I agree that the café keeps these details to manage my booking, as its privacy notice explains.')),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Request a Table'), 'btn btn-primary btn-lg px-4'),
                $para(lang('We confirm by e-mail, usually within the hour.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One booking: its details and status; the messages follow as
    // comments.
    'reservation_detail' => array(
        'page'   => 'reservation_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:reservation}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This booking could not be found, or it is not yours to see.'),
        ),
        'tree'   => $widget_root($record_page('bi-calendar-x', lang('Booking not found'), lang('This booking could not be found, or it is not yours to see.'),
            lang('My Reservations'), 'my_reservations',
            lang('Thank you, your booking request has reached us. We confirm it by e-mail; this page shows the status, and you can write to us below.'),
            $record_card(lang('Table booking'), lang('Old Town'), 'cafe', lang('Requested'), array_merge(
                $detail(lang('Date'), '2026-10-20', 'reservation_date', 'col-sm-8 fw-semibold'),
                $detail(lang('Arrival time'), '19:30', 'reservation_time', 'col-sm-8 fw-semibold'),
                $detail(lang('Guests'), '2', 'guests'),
                $detail(lang('Seating'), lang('Terrace'), 'seating'),
                $name_detail(lang('Name')),
                $detail(lang('Phone'), '+90 555 000 00 00', 'phone'),
                $detail(lang('Reference Code'), 'ABC123', 'reference_code', 'col-sm-8 mb-0 font-monospace')
            )),
            array(
                $record_words(lang('Occasion'), lang('A birthday'), 'occasion'),
                $record_words(lang('Anything we should know?'), lang('A high chair for our daughter, please.'), 'notes'),
            ),
            lang('Running late, or need to change something? Write to us below; we answer here and by e-mail.'))),
    ),

    // My Reservations: the bookings the guest made (or watches); the staff
    // see them all. Newest first.
    'my_reservations_list' => array(
        'page'   => 'my_reservations',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:reservation}}',
            'detail_page_id'          => '{{tab:reservation_view}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No bookings yet. Book your first table in a minute.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Reservation List'), array(
                $loop(array($reservation_row())),
            )),
        )),
    ),

    // The staff area's coming bookings: from today on, the nearest first.
    'staff_reservations' => array(
        'page'   => 'staff',
        'slug'   => lang('reservations'),
        'config' => array(
            'regionType'           => 'form_list_view',
            'custom_form_page_id'  => '{{tab:reservation}}',
            'detail_page_id'       => '{{tab:reservation_view}}',
            'items_per_page'       => 20,
            'order_by_field'       => 'reservation_date',
            'order_by_direction'   => 'ASC',
            'order_by_2_field'     => 'reservation_time',
            'order_by_2_direction' => 'ASC',
            'filters'              => array(
                array('field' => 'reservation_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
            ),
            'search_enabled'       => true,
            'search_fields'        => array('first_name', 'last_name', 'cafe', 'status'),
            'search_width'         => 'half',
            'search_label'         => lang('Search by guest, café or status...'),
            'empty_message'        => lang('No bookings from today on.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Reservation List'), array(
                $loop(array(
                    $el('a', 'list-group-item list-group-item-action p-3', lang('Reservation'), array(
                        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-1', lang('Top Line'), array(
                            $el('span', 'fw-semibold', lang('Guest'), $person_name()),
                            $status_badge(lang('Requested')),
                        )),
                        $el('div', 'd-flex flex-wrap column-gap-3 row-gap-1 small text-body-secondary', lang('Details'), array(
                            $row_detail('bi-calendar3', lang('Date'), '2026-10-20', 'reservation_date', 'fw-semibold'),
                            $row_detail('bi-clock', lang('Time'), '19:30', 'reservation_time', 'fw-semibold'),
                            $row_detail('bi-geo-alt', lang('Café'), lang('Old Town'), 'cafe'),
                            $el('span', 'd-inline-flex align-items-center gap-1', lang('Guests'), array(
                                $icon('bi-people'),
                                $span('2', '', lang('Guests'), $bind('guests')),
                                $span(lang('guests'), '', lang('Label')),
                            )),
                            $row_detail('bi-telephone', lang('Phone'), '+90 555 000 00 00', 'phone'),
                        )),
                    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view'))),
                )),
            )),
        )),
    ),

    // The catering request: the fields are the corporate page's form. The
    // Status is the staff's, as on the booking.
    'quote_form' => array(
        'page'   => 'corporate',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $form_step(1, lang('Your event')),
            $row(array(
                $col(array($field('cf-quote-service', lang('Catering service'), $select('cf-quote-service', 'service', lang('Choose a package…'), array(
                    lang('Coffee Break'), lang('Office Breakfast'), lang('Lunch Boxes'), lang('Office coffee'), lang('An event or a launch'), lang('Something else'),
                )))), '6'),
                $col(array($field('cf-quote-guests', lang('Number of guests'), $input('cf-quote-guests', 'number', 'guests', 'off', true, array(), array('min' => '1', 'step' => '1')))), '6'),
                $col(array($field('cf-quote-date', lang('Date'), $input('cf-quote-date', 'date', 'event_date', 'off'))), '6'),
                $col(array($field('cf-quote-time', lang('Serving time'), $input('cf-quote-time', 'text', 'serving_time', 'off', false, array(), array('placeholder' => '09:00')))), '6'),
            ), '3'),
            $choices('cf-quote-delivery', 'delivery', lang('Delivery'), array(lang('Delivered to us'), lang('We collect it from a café')), array(lang('Delivered to us'))),
            $field('cf-quote-address', lang('Delivery address'), $input('cf-quote-address', 'text', 'address', 'street-address', false)),
            $form_step(2, lang('Your company')),
            $field('cf-quote-company', lang('Company'), $input('cf-quote-company', 'text', 'company', 'organization', true, array('contact_field' => 'company'))),
            $person_fields('cf-quote'),
            $field('cf-quote-details', lang('Details'), $textarea('cf-quote-details', 'details', 4, false, lang('Diets and allergies, how to serve, anything else…'))),
            $field('cf-quote-status', lang('Status'), $select('cf-quote-status', 'status', '', array(
                lang('Request received'), lang('Quote sent'), lang('Confirmed'), lang('Completed'), lang('Cancelled'),
            ), false, lang('Request received'), array('office_use_only' => true))),
            $agree('cf-quote-consent', 'consent', lang('I agree that the café keeps these details to prepare the quote and the order, as its privacy notice explains.')),
            $captcha(),
            $el('div', 'd-flex flex-wrap align-items-center gap-3', lang('Send Area'), array(
                $submit(lang('Request a Quote'), 'btn btn-primary btn-lg px-4'),
                $para(lang('We answer within one working day.'), 'small text-body-secondary mb-0'),
            )),
            $loop(array()),
        )),
    ),

    // One catering request: its details and status; the messages follow as
    // comments.
    'quote_detail' => array(
        'page'   => 'quote_view',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:corporate}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This catering request could not be found, or it is not yours to see.'),
        ),
        'tree'   => $widget_root($record_page('bi-basket', lang('Request not found'), lang('This catering request could not be found, or it is not yours to see.'),
            lang('My Catering Requests'), 'my_quotes',
            lang('Thank you, your request has reached us. We send the quote by e-mail within one working day; this page shows the status, and you can write to us below.'),
            $record_card(lang('Catering request'), lang('Acme Ltd.'), 'company', lang('Request received'), array_merge(
                $detail(lang('Catering service'), lang('Office Breakfast'), 'service', 'col-sm-8 fw-semibold'),
                $detail(lang('Date'), '2026-10-20', 'event_date', 'col-sm-8 fw-semibold'),
                $detail(lang('Serving time'), '09:00', 'serving_time'),
                $detail(lang('Guests'), '25', 'guests'),
                $detail(lang('Delivery'), lang('Delivered to us'), 'delivery'),
                $name_detail(lang('Contact person')),
                $detail(lang('Phone'), '+90 555 000 00 00', 'phone'),
                $detail(lang('Reference Code'), 'ABC123', 'reference_code', 'col-sm-8 mb-0 font-monospace')
            )),
            array(
                $record_words(lang('Delivery address'), lang('Vertex Tower, 12th floor'), 'address'),
                $record_words(lang('Details'), lang('Five of us are vegan, one is allergic to nuts.'), 'details'),
            ),
            lang('A question about the quote, or a change to the order? Write to us below; we answer here and by e-mail.'))),
    ),

    // My Catering Requests: the requests the company sent (or watches); the
    // staff see them all. Newest first.
    'my_quotes_list' => array(
        'page'   => 'my_quotes',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:corporate}}',
            'detail_page_id'          => '{{tab:quote_view}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No catering requests yet. Ask for your first quote from the corporate page.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Catering Request List'), array(
                $loop(array($quote_row())),
            )),
        )),
    ),

    // The staff area's newest catering requests: the five last sent.
    'staff_quotes' => array(
        'page'   => 'staff',
        'slug'   => lang('catering-requests'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:corporate}}',
            'detail_page_id'      => '{{tab:quote_view}}',
            'items_per_page'      => 5,
            'max_results'         => 5,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'empty_message'       => lang('No catering requests yet.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Catering Request List'), array(
                $loop(array($quote_row(true))),
            )),
        )),
    ),

    // The job application: the fields are the careers page's form. The CV
    // is kept in the private folder; the Status is the staff's.
    'careers_form' => array(
        'page'   => 'careers',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('cf-job-position', lang('Position applied for'), $select('cf-job-position', 'position', lang('Choose a position…'), array_merge(
                    array_values(array_map(function ($p) { return $p['title']; }, $positions)), array(lang('Any position'))
                )))), '6'),
                $col(array($field('cf-job-cafe', lang('Café'), $select('cf-job-cafe', 'cafe', lang('Any café'), $cafe_names, false))), '6'),
            ), '3'),
            $person_fields('cf-job'),
            $choices('cf-job-availability', 'availability', lang('How much would you like to work?'), array(lang('Full time'), lang('Part time'), lang('Weekends only')), array(lang('Full time'))),
            $field('cf-job-cv', lang('CV (PDF or Word)'), $file('cf-job-cv', 'cv', 'private', '.pdf,.doc,.docx')),
            $field('cf-job-message', lang('A few words about you'), $textarea('cf-job-message', 'message', 4, false)),
            $field('cf-job-status', lang('Status'), $select('cf-job-status', 'status', '', array(
                lang('New'), lang('Interview'), lang('Hired'), lang('Not this time'),
            ), false, lang('New'), array('office_use_only' => true))),
            $agree('cf-job-consent', 'consent', lang('I agree that the café keeps my application and CV for six months to fill this and similar positions.')),
            $captcha(),
            $submit(lang('Send Application'), 'btn btn-primary btn-lg px-4'),
            $loop(array()),
        )),
    ),

    // Contact: the fields are the page's form.
    'contact_form' => array(
        'page'   => 'contact',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $person_fields('cf-contact', false, false),
            $row(array(
                $col(array($field('cf-contact-topic', lang('What is it about?'), $select('cf-contact-topic', 'topic', lang('Choose a topic…'), array(
                    lang('A question about a booking'),
                    lang('Catering and corporate orders'),
                    lang('A private event'),
                    lang('Something left behind'),
                    lang('Feedback about a visit'),
                    lang('Something else'),
                )))), '6'),
                $col(array($field('cf-contact-cafe', lang('Café'), $select('cf-contact-cafe', 'cafe', lang('Any café'), $cafe_names, false))), '6'),
            ), '3'),
            $field('cf-contact-message', lang('Your Message'), $textarea('cf-contact-message', 'message', 6)),
            $captcha(),
            $submit(lang('Send Message')),
            $loop(array()),
        )),
    ),

    // The blog: every post, newest first, with a search over the posts.
    'blog_list' => array(
        'page'   => 'blog',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'detail_page_id'      => '{{tab:blog_post}}',
            'items_per_page'      => 9,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'search_enabled'      => true,
            'search_fields'       => array('title', 'summary', 'content'),
            'search_width'        => 'full',
            'search_label'        => lang('Search the blog...'),
            'empty_message'       => lang('No posts yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($post_card()))), '4', '', 'g-lg-5'),
        )),
    ),

    // One post.
    'blog_post' => array(
        'page'   => 'blog_post',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'access_control'      => 'public',
            'not_found_message'   => lang('This post could not be found. It may have been removed.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-journal-x', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Post not found'), 'h3 fw-bold mt-3'),
                            $para(lang('This post could not be found. It may have been removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the blog'), '{{page:blog}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('article', '', lang('Post'), array(
                            $record_toolbar(lang('All posts'), 'blog'),
                            $heading('h1', lang('How we choose the coffee we roast'), 'display-6 fw-bold mb-3', $bind('title')),
                            $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary pb-3 mb-4 border-bottom', lang('Post Details'), array(
                                $icon('bi-calendar3'),
                                $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                $icon('bi-person', 'ms-2'),
                                $span(lang('Jane Cooper'), '', lang('Author'), $bind('submitter')),
                            )),
                            $ai_summary(),
                            $optional_image('cover_image', 'title', 'rounded-4 object-fit-cover', 'ratio ratio-16x9 mb-4',
                                $photo('post', 1200, 675), lang('Cover Image')),
                            $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'), 'lead mb-4', $bind('summary')),
                            $el('div', 'fs-6 lh-lg', lang('Post Content'), array(), array_merge(
                                array('text' => lang('The body of the post. Write it on the staff page; line breaks are kept.')), $bind('content'))),
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3 p-4 rounded-4 bg-body-tertiary mt-5', lang('Post Call to Action'), array(
                                $para(lang('Read it with a coffee? Our cafés are open every day.'), 'fw-semibold mb-0'),
                                $link(lang('Book a Table'), '{{page:reservation}}', 'btn btn-sm btn-primary'),
                            )),
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The staff page's blog form: what a post is made of.
    'blog_form' => array(
        'page'   => 'blog_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('cf-post-title', lang('Title'), pg_cf_with_role($input('cf-post-title', 'text', 'title', 'off'), 'title')),
            $field('cf-post-summary', lang('Summary'), pg_cf_with_role($textarea('cf-post-summary', 'summary', 3), 'description')),
            $field('cf-post-cover', lang('Cover Image'), pg_cf_with_role($file('cf-post-cover', 'cover_image', 'public'), 'media')),
            $field('cf-post-content', lang('Content'), $textarea('cf-post-content', 'content', 14)),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The staff page's menu form: a dish or a drink. Its category puts it
    // in its tab of the menu; "Show on the home page" among the favourites.
    'menu_form' => array(
        'page'   => 'menu_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('cf-dish-category', lang('Category'), $select('cf-dish-category', 'category', lang('Choose a category…'), $category_titles))), '6'),
                $col(array($field('cf-dish-price', lang('Price'), $input('cf-dish-price', 'text', 'price', 'off', true, array(), array('placeholder' => lang('$3.80'))))), '6'),
            ), '3'),
            $field('cf-dish-name', lang('Name'), pg_cf_with_role($input('cf-dish-name', 'text', 'name', 'off'), 'title')),
            $field('cf-dish-description', lang('Description'), pg_cf_with_role($textarea('cf-dish-description', 'description', 2, false), 'description')),
            $choices('cf-dish-diet', 'diet', lang('Diet tags'), array_map(function ($d) { return $d[0]; }, $diets), array(), 'checkbox'),
            $row(array(
                $col(array($field('cf-dish-badge', lang('Badge'), $select('cf-dish-badge', 'badge', lang('No badge'), $badges, false))), '6'),
                $col(array($field('cf-dish-featured', lang('Show on the home page'), $select('cf-dish-featured', 'featured', '', array(lang('No'), lang('Yes')), false, lang('No')))), '6'),
            ), '3'),
            $field('cf-dish-photo', lang('Photo'), pg_cf_with_role($file('cf-dish-photo', 'photo', 'public'), 'media')),
            $submit(lang('Add to the Menu')),
            $loop(array()),
        )),
    ),

    // The staff page's events form: one event.
    'event_form' => array(
        'page'   => 'event_new',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $field('cf-event-title', lang('Title'), pg_cf_with_role($input('cf-event-title', 'text', 'title', 'off'), 'title')),
            $row(array(
                $col(array($field('cf-event-date', lang('Date'), $input('cf-event-date', 'date', 'event_date', 'off'))), '6'),
                $col(array($field('cf-event-time', lang('Starts at'), $input('cf-event-time', 'text', 'event_time', 'off', true, array(), array('placeholder' => '19:30')))), '6'),
                $col(array($field('cf-event-cafe', lang('Café'), $select('cf-event-cafe', 'cafe', lang('Choose a café…'), $cafe_names))), '6'),
                $col(array($field('cf-event-kind', lang('Kind'), $select('cf-event-kind', 'kind', lang('Choose…'), $event_kinds))), '6'),
            ), '3'),
            $field('cf-event-summary', lang('Summary'), pg_cf_with_role($textarea('cf-event-summary', 'summary', 3), 'description')),
            $field('cf-event-price', lang('Price'), $input('cf-event-price', 'text', 'price', 'off', false, array(), array('placeholder' => lang('Free entry')))),
            $field('cf-event-image', lang('Image'), pg_cf_with_role($file('cf-event-image', 'image', 'public'), 'media')),
            $submit(lang('Publish')),
            $loop(array()),
        )),
    ),

    // The staff page's directory form: one person.
    'staff_form' => array(
        'page'   => 'staff_entry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('cf-staff-name', lang('Full Name'), $input('cf-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('cf-staff-title', lang('Job Title'), $input('cf-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('cf-staff-department', lang('Department'), $input('cf-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('cf-staff-photo', lang('Photo'), $file('cf-staff-photo', 'photo', 'public'))), '6'),
                $col(array($field('cf-staff-email', lang('Email'), $input('cf-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('cf-staff-phone', lang('Phone'), $input('cf-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('cf-staff-bio', lang('A few words'), $textarea('cf-staff-bio', 'bio', 3, false)),
            $submit(lang('Add')),
            $loop(array()),
        )),
    ),

    // The directory members see.
    'staff_directory' => array(
        'page'   => 'staff_directory',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:staff_entry}}',
            'items_per_page'      => 24,
            'order_by_field'      => 'full_name',
            'order_by_direction'  => 'ASC',
            'search_enabled'      => true,
            'search_fields'       => array('full_name', 'job_title', 'department'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by name or department...'),
            'empty_message'       => lang('The directory is empty for now.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array(
                $col(array(
                    $el('div', 'd-flex align-items-start gap-3 h-100 p-4 rounded-4 bg-body-tertiary', lang('Person Card'), array(
                        $optional_image('photo', 'full_name', 'rounded-circle object-fit-cover', 'flex-shrink-0',
                            'https://placehold.co/128x128/f1e6dc/6f4e37?text=JC', lang('Photo'), array('width' => '64', 'height' => '64')),
                        $el('div', 'min-w-0', lang('Details'), array(
                            $heading('h2', lang('Jane Cooper'), 'h6 fw-bold mb-0', $bind('full_name')),
                            $para(lang('Founder'), 'small text-primary fw-semibold mb-2', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span(lang('Operations'), 'pg-field-value', lang('Value'), $bind('department')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary me-1'),
                                $span(lang('jane.cooper@example.com'), 'pg-field-value', lang('Value'), $bind('email')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary me-1'),
                                $span('+90 555 000 00 00', 'pg-field-value', lang('Value'), $bind('phone')),
                            )),
                            $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                                $span(lang('Happy to help with orders and accounts.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),

    // The inbox: conversations the guest started (a signed-in submission of
    // the contact form), or is watching; the staff see them all. The contact
    // form's subject is its topic.
    'inbox_list' => array(
        'page'   => 'inbox',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:contact}}',
            'detail_page_id'          => '{{tab:conversation}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No conversations yet. Start one from the contact page.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Conversation List'), array(
                $loop(array(
                    $el('a', 'list-group-item list-group-item-action py-3', lang('Conversation'), array(
                        $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                            $span(lang('A private event'), 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
                            $span(lang('Date'), 'small text-body-secondary text-nowrap', lang('Date'), $bind('submitted_date_and_time')),
                        )),
                        $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mt-1', lang('Replies'), array(
                            $icon('bi-chat-left-text'),
                            $span('0', 'fw-semibold', lang('Reply Count'), $bind('number_of_comments')),
                            $span(lang('replies'), '', lang('Label')),
                        )),
                    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view'))),
                )),
            )),
        )),
    ),

    // One conversation: the first message; the answers follow as comments.
    'conversation' => array(
        'page'   => 'conversation',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This conversation could not be found, or it is not yours to read.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-chat-left-dots', 'display-5 text-body-tertiary'),
                            $heading('h2', lang('Conversation not found'), 'h3 fw-bold mt-3'),
                            $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Conversation'), array_merge(
                            $sent_notices(lang('Thank you, your message has reached us. Our answers appear on this page and in your inbox, and you can write back below.')),
                            array(
                                $record_toolbar(lang('Inbox'), 'inbox'),
                                $heading('h1', lang('A private event'), 'h3 fw-bold mb-1', $bind('topic')),
                                $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Conversation Details'), array(
                                    $icon('bi-calendar3'),
                                    $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                    $icon('bi-hash', 'ms-2'),
                                    $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
                                    $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-1 ms-2', lang('Café'), array(
                                        $icon('bi-geo-alt'),
                                        $span(lang('Riverside'), 'pg-field-value', lang('Value'), $bind('cafe')),
                                    )),
                                )),
                                $el('div', 'p-4 rounded-4 bg-body-tertiary', lang('First Message'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array_merge(
                                        array($icon('bi-person-circle', 'fs-5 text-primary')), $person_name())),
                                    $el('div', 'lh-lg', lang('Message'), array(), array_merge(
                                        array('text' => lang('Hello, we would like to celebrate a birthday upstairs at Riverside for about thirty people.')), $bind('message'))),
                                )),
                            )
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The guest's account: who is signed in and the way to each account
    // page.
    'my_account' => array(
        'page'   => 'my_account',
        'slug'   => lang('my-account'),
        'config' => array(
            'regionType'       => 'my_account',
            'login_page_id'    => '{{tab:login}}',
            'show_submissions' => false,
            'staff_page_id'    => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 bg-primary-subtle mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'small text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'h4 fw-bold mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-outline-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $el('div', 'p-4 rounded-4 border mb-4', lang('Reservations Card'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
                                $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                                    $icon('bi-calendar2-check', 'fs-2 text-primary'),
                                    $el('div', '', lang('Text'), array(
                                        $heading('h2', lang('Your table bookings'), 'h5 fw-bold mb-1'),
                                        $para(lang('Your bookings, their status and our messages.'), 'small text-body-secondary mb-0'),
                                    )),
                                )),
                                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                    $link(lang('My Reservations'), '{{page:my_reservations}}', 'btn btn-primary'),
                                    $link(lang('Book a Table'), '{{page:reservation}}', 'btn btn-outline-primary'),
                                )),
                            )),
                        )),
                        $el('div', 'p-4 rounded-4 border mb-4', lang('Catering Card'), array(
                            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
                                $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                                    $icon('bi-briefcase', 'fs-2 text-primary'),
                                    $el('div', '', lang('Text'), array(
                                        $heading('h2', lang('Your catering requests'), 'h5 fw-bold mb-1'),
                                        $para(lang('Quotes for your company\'s breakfasts, lunches and events.'), 'small text-body-secondary mb-0'),
                                    )),
                                )),
                                $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                    $link(lang('My Catering Requests'), '{{page:my_quotes}}', 'btn btn-primary'),
                                    $link(lang('Request a Quote'), '{{page:corporate}}#quote', 'btn btn-outline-primary'),
                                )),
                            )),
                        )),
                        $heading('h2', lang('My Orders'), 'h5 fw-bold mb-3', $show_when('has_orders')),
                        $el('div', '', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),

    // Signing in, up and out, and the password: the layouts their widgets
    // get from the palette.
    'login' => array(
        'page'   => 'login',
        'slug'   => lang('login'),
        'config' => array(
            'regionType'              => 'login_form',
            'show_remember_me'        => true,
            'register_page_id'        => '{{tab:register}}',
            'show_register_link'      => true,
            'forgot_password_page_id' => '{{tab:forgot_password}}',
        ),
        'tree'   => 'starter',
    ),
    'register' => array(
        'page'   => 'register',
        'slug'   => lang('registration'),
        'config' => array(
            'regionType'       => 'registration',
            'show_remember_me' => true,
            'login_page_id'    => '{{tab:login}}',
            'redirect_page_id' => '{{tab:my_account}}',
        ),
        'tree'   => 'starter',
    ),
    'forgot_password' => array(
        'page'   => 'forgot_password',
        'slug'   => lang('forgot-password'),
        'config' => array('regionType' => 'forgot_password', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'set_password' => array(
        'page'   => 'set_password',
        'slug'   => lang('set-password'),
        'config' => array('regionType' => 'set_password', 'redirect_page_id' => '{{tab:my_account}}', 'forgot_password_page_id' => '{{tab:forgot_password}}'),
        'tree'   => 'starter',
    ),
    'logout' => array(
        'page'   => 'logout',
        'slug'   => lang('logout'),
        'config' => array('regionType' => 'logout', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'profile' => array(
        'page'   => 'profile',
        'slug'   => lang('profile'),
        'config' => array('regionType' => 'account_profile', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'change_password' => array(
        'page'   => 'change_password',
        'slug'   => lang('change-password'),
        'config' => array('regionType' => 'change_password', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'email_preferences' => array(
        'page'   => 'email_preferences',
        'slug'   => lang('email-preferences'),
        'config' => array('regionType' => 'email_preferences', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    // Google connection, remembered devices and two-step verification,
    // under the account overview on the same page.
    'account_security' => array(
        'page'   => 'my_account',
        'slug'   => lang('security'),
        'config' => array('regionType' => 'account_security', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'address_book' => array(
        'page'     => 'address_book',
        'requires' => 'ecommerce',
        'slug'     => lang('address-book'),
        'config'   => array('regionType' => 'address_book', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'     => 'starter',
    ),
    // The help center's search: the whole site for ?query=, ten results a
    // page. A result that matches a search section links to that part of
    // its page (^^__result_section^^ names it).
    'site_search' => array(
        'page'   => 'search',
        'slug'   => lang('search'),
        'config' => array(
            'regionType'       => 'search_results',
            'search_scope'     => 'all',
            'results_per_page' => 10,
            'empty_message'    => lang('Nothing matched your search. Try fewer or different words.'),
        ),
        'tree'   => $widget_root(array(
            $el('form', 'mb-4', lang('Search Form'), array(
                $el('div', 'input-group input-group-lg', lang('Search Box'), array(
                    $el('input', 'form-control', '', array(), array(
                        '_attrs'    => array($attr('type', 'search'), $attr('placeholder', lang('Search the site…')), $attr('aria-label', lang('Search the site'))),
                        '_bindings' => array('value' => 'search_query'),
                    )),
                    $el('button', 'btn btn-primary px-4', lang('Search Button'), array($icon('bi-search')), array(
                        '_attrs'    => array($attr('type', 'submit'), $attr('aria-label', lang('Search'))),
                        '_bindings' => array('action' => 'submit_search'),
                    )),
                )),
            ), array('_bindings' => array('action' => 'search_form'))),
            $para(lang('Results heading'), 'fw-semibold pb-3 mb-0 border-bottom', $bind('__results_heading')),
            $el('div', '', lang('Results'), array(
                $loop(array(
                    $el('article', 'py-3 border-bottom position-relative', lang('Result Row'), array(
                        $span(lang('Page'), 'badge bg-secondary-subtle text-secondary-emphasis mb-2', lang('Result Type'), $bind('__result_type_label')),
                        $el('h2', 'h5 fw-bold mb-1 position-static', lang('Result Title'), array(
                            $link(lang('Title'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                                array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                        )),
                        $para(lang('Opening hours'), 'small fw-semibold text-primary mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
                        $para(lang('Excerpt'), 'small text-body-secondary mb-0', $bind('__result_excerpt')),
                    )),
                )),
            )),
            $el('div', 'mt-4', lang('Pagination'), array(), array('_bindings' => array('section' => 'pagination'))),
        )),
    ),

    'error_page' => array(
        'page'   => 'error',
        'slug'   => lang('error'),
        'config' => array('regionType' => 'error_page'),
        'tree'   => 'starter',
    ),
);

// The menu page's lists: a widget for each category's tab, the dishes of
// that category in the order they were added, the newest first.
foreach ($menu_categories as $key => $c) {
    $widgets['menu_' . $key] = array(
        'page'   => 'menu',
        'slug'   => $c['title'],
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:menu_new}}',
            'items_per_page'      => 60,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'filters'             => array(
                array('field' => 'category', 'operator' => 'is equal to', 'value' => $c['title'], 'dynamic_value' => ''),
            ),
            'empty_message'       => lang('Nothing in this part of the menu yet.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array($dish_line()))), '', '', 'gx-lg-5'),
        )),
    );
}

// The e-mail pages' widgets: drawn for the submission just sent.
$mail_widget = function ($page_key, $form_page, $not_found, $children) use ($widget_root) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form_page . '}}',
            'access_control'      => 'public',
            'not_found_message'   => $not_found,
        ),
        'tree'   => $widget_root($children),
    );
};
$widgets['email_reservation_admin'] = $mail_widget('email_reservation_new', 'reservation', lang('The booking could not be found.'), array(
    $mail_heading(lang('A new table booking')),
    $mail_para(lang('It came from the booking form. Check the café\'s tables, then set the status on the booking\'s page; what you write under it reaches the guest by e-mail.')),
    $mail_reservation(),
    $mail_person(lang('Guest')),
    $mail_line(lang('Anything we should know?'), array($span(lang('A high chair for our daughter, please.'), '', lang('Notes'), $bind('notes')))),
    $mail_button(lang('Open the booking'), '{{page:reservation_view}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_reservation_guest'] = $mail_widget('email_reservation_received', 'reservation', lang('The booking could not be found.'), array(
    $mail_heading(lang('We received your table booking')),
    $mail_greeting(),
    $mail_para(lang('Thank you for booking with us. We check the tables of the café and confirm by e-mail, usually within the hour.')),
    $mail_reservation(),
    $mail_para(lang('We keep your table for fifteen minutes. Running late, or need to change something? Write to us on the booking\'s page.')),
    $mail_button(lang('Open my booking'), '{{page:reservation_view}}?r=^^reference_code^^'),
    $mail_new_account(),
));
$widgets['email_quote_admin'] = $mail_widget('email_quote_new', 'corporate', lang('The catering request could not be found.'), array(
    $mail_heading(lang('A new catering request')),
    $mail_para(lang('It came from the corporate page. Send the quote under the request, then set its status; what you write there reaches the company by e-mail.')),
    $mail_quote(),
    $mail_person(lang('Contact person')),
    $mail_line(lang('Details'), array($span(lang('Five of us are vegan, one is allergic to nuts.'), '', lang('Details'), $bind('details')))),
    $mail_button(lang('Open the request'), '{{page:quote_view}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_quote_customer'] = $mail_widget('email_quote_received', 'corporate', lang('The catering request could not be found.'), array(
    $mail_heading(lang('We received your catering request')),
    $mail_greeting(),
    $mail_para(lang('Thank you for asking us. Our catering team prepares a menu and a price and sends you the quote within one working day.')),
    $mail_quote(),
    $mail_para(lang('A question, or something to add? Write to us on the request\'s page; every answer comes by e-mail too.')),
    $mail_button(lang('Open my request'), '{{page:quote_view}}?r=^^reference_code^^'),
    $mail_new_account(),
));
$widgets['email_application_admin'] = $mail_widget('email_application_new', 'careers', lang('The application could not be found.'), array(
    $mail_heading(lang('A new job application')),
    $mail_para(lang('It came from the careers page. The CV is with the application in the control panel, under the Job Applications form, where you also set its status.')),
    $mail_box(array(
        $mail_line(lang('Position applied for'), array($span(lang('Barista'), '', lang('Job Opening'), $bind('position')))),
        $mail_line(lang('Café'), array($span(lang('Old Town'), '', lang('Café'), $bind('cafe')))),
        $mail_line(lang('Preferred hours'), array($span(lang('Part time'), '', lang('Preferred hours'), $bind('availability')))),
    ), lang('Job Application')),
    $mail_person(lang('Applicant')),
    $mail_line(lang('A few words about you'), array($span(lang('Two summers behind the counter of a beach café, and I love latte art.'), '', lang('Message'), $bind('message')))),
    $mail_reference(),
));
$widgets['email_application_applicant'] = $mail_widget('email_application_received', 'careers', lang('The application could not be found.'), array(
    $mail_heading(lang('Thank you for applying')),
    $mail_greeting(),
    $mail_para(lang('Your application has reached us. We read every one and answer within a week; if it fits, we invite you for a coffee and a short talk in one of our cafés.')),
    $mail_box(array(
        $mail_line(lang('Position applied for'), array($span(lang('Barista'), '', lang('Job Opening'), $bind('position')))),
        $mail_line(lang('Café'), array($span(lang('Old Town'), '', lang('Café'), $bind('cafe')))),
    ), lang('Job Application')),
    $mail_button(lang('Get to know us'), '{{page:about}}'),
));
$widgets['email_message_admin'] = $mail_widget('email_new_message', 'contact', lang('The message could not be found.'), array(
    $mail_heading(lang('A new message has arrived')),
    $mail_para(lang('It came from the contact form. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
    $mail_person(lang('Sender')),
    $mail_box(array(
        $mail_line(lang('Subject'), array($span(lang('A private event'), '', lang('Subject'), $bind('topic')))),
        $mail_line(lang('Café'), array($span(lang('Riverside'), '', lang('Café'), $bind('cafe')))),
    ), lang('Subject')),
    $el('div', 'lh-lg', lang('Message'), array(), array_merge(
        array('text' => lang('Hello, we would like to celebrate a birthday upstairs at Riverside for about thirty people.')), $bind('message'))),
    $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_message_sender'] = $mail_widget('email_message_received', 'contact', lang('The message could not be found.'), array(
    $mail_heading(lang('Your message has reached us')),
    $mail_greeting(),
    $mail_para(lang('Thank you for writing to us. Our answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
    $mail_box(array(
        $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
            array('text' => lang('A private event'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
        $el('div', 'lh-lg', lang('Message'), array(), array_merge(
            array('text' => lang('Hello, we would like to celebrate a birthday upstairs at Riverside for about thirty people.')), $bind('message'))),
    ), lang('Your Message')),
    $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
    $mail_new_account(),
));

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// Parts inside a shared component or a widget belong to that, not to the
// page, so only the page's own parts are listed here. The cafés, the open
// positions and the questions are marked where they are built.
$search_sections = array(
    'menu' => array(
        lang('Diet Key')             => array('diet-key', lang('allergens, allergy, vegan, vegetarian, gluten free, nuts, spicy, diet')),
        lang('This Season\'s Coffee') => array('this-seasons-coffee', lang('coffee beans, origin, single origin, roast, house blend, filter coffee')),
    ),
    'locations' => array(
        lang('Opening Hours') => array('opening-hours', lang('hours, open, closed, public holidays, weekend, Sunday')),
    ),
    'corporate' => array(
        '#catering-packages' => array('catering-packages', lang('catering, packages, price per person, coffee break, office breakfast, lunch boxes')),
        '#quote'             => array('quote', lang('quote, catering order, offer, event catering, request')),
    ),
    'events' => array(
        lang('Private Events') => array('private-events', lang('private event, birthday, party, room hire, team breakfast, book club')),
    ),
    'about' => array(
        lang('Our Story')      => array('our-story', lang('about us, our story, history, founder, since 2009')),
        '#roastery'            => array('roastery', lang('roastery, roasting, green beans, farms, head roaster')),
        lang('Sustainability') => array('sustainability', lang('sustainability, recycling, reusable cup, coffee grounds, environment')),
    ),
    'careers' => array(
        '#apply' => array('apply', lang('apply, application, CV, job, work with us, vacancies')),
    ),
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, head office, opening hours')),
    ),
);
$searchable_tree = function ($node, $marks, &$used) use (&$searchable_tree) {
    if (!is_array($node)) return $node;
    if (isset($node['props']) && is_array($node['props'])) {
        $p = $node['props'];
        $name = isset($p['customName']) ? (string)$p['customName'] : (isset($p['_label']) ? (string)$p['_label'] : '');
        $key = null;
        if (!empty($p['id']) && isset($marks['#' . $p['id']]) && !isset($used['#' . $p['id']])) {
            $key = '#' . $p['id'];
        } elseif ($name !== '' && isset($marks[$name]) && !isset($used[$name])) {
            $key = $name;
        }
        if ($key !== null) {
            $used[$key] = true;
            if (empty($p['id'])) $node['props']['id'] = $marks[$key][0];
            $node['props']['searchSection'] = true;
            if ($marks[$key][1] !== '') $node['props']['searchKeywords'] = $marks[$key][1];
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) $node['children'][$i] = $searchable_tree($child, $marks, $used);
    }
    return $node;
};
foreach ($pages as $i => $p) {
    if (!isset($search_sections[$p['key']])) continue;
    $used = array();
    $pages[$i]['tree'] = $searchable_tree($p['tree'], $search_sections[$p['key']], $used);
}

return array(
    'name'        => lang('Café'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 60,
    'icon'        => 'bi-cup-hot',
    // The picture on the template's card and beside the designs made from
    // it: a café's home page (pg_design_thumb_svg()).
    'thumb'       => 'cafe',
    'description' => lang('A coffee company with several cafés: a menu the staff keep dish by dish, in tabs with prices and diet tags, the cafés and their hours, catering packages with a quote request the company follows from its account, table bookings, events and workshops, careers with an application form, a blog, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor.
    'look'        => 'organic',
    'palette'     => 'cocoa',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A menu kept as records: each dish in its category\'s tab with its price, diet tags, badge and photo, and the favourites on the home page.'),
        lang('Table bookings and catering quotes: each request gets its own page with a status and messages, listed in the guest\'s account.'),
        lang('The cafés with their hours, events and workshops the staff keep, and careers with an application form that takes a CV.'),
        lang('A staff area with the coming bookings and the newest catering requests, a help center that searches the whole site and the café\'s letter for e-mail campaigns.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_cafe', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Café Newsletter'),
            'description'  => lang('The addresses left on the letter form of the café.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Café Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Café Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Café Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Café Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Café Staff Bar'), 'tree' => $staff_bar()),
        'table_band'   => array('name' => lang('Café Table Band'), 'tree' => $table_band()),
        'email_header' => array('name' => lang('Café E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-3 border-bottom', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bold text-decoration-none link-body-emphasis',
                    array($attr('style', 'font-size:18px;font-weight:700;color:#6f4e37;text-decoration:none'))),
            ), $mail_style('padding:16px 24px;border-bottom:1px solid #eadfd5'))),
        'email_footer' => array('name' => lang('Café E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $para(lang('Three cafés, one roastery and catering for companies.'), 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit our website'), '{{page:home}}', 'link-secondary',
                    array($attr('style', 'color:#75655a'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #eadfd5;font-size:13px;color:#75655a'))),
    ),
);
