<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Home Services", part 1 of its files: the node builders
 * and the interactions the pages put on them, the company's own data (the
 * service categories, the sample services with their prices and what they
 * include, the service areas, the arrival windows, the team, how a
 * job goes, why customers choose the company, what they say, the care
 * plans and the answers of the forms' pick lists) and the parts every page
 * repeats (account bar, header, footer, visit band, service, area, team
 * and customer cards, service facts, page shells, section headers, form
 * fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-homeservices.php puts them together.
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
// A time of day the operator edits like a link's words; 'datetime' keeps
// the machine-readable value (the editor's kit.time()).
$time = function ($datetime, $text, $class, $name) use ($el, $attr) {
    return $el('time', $class, $name, array(), array('text' => $text, '_attrs' => array($attr('datetime', $datetime))));
};
// An interaction on a node (Interactions in the editor): the operator can
// change or remove it there, and the platform keeps the node still for a
// visitor who prefers reduced motion.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// A row whose items rise one after another as it scrolls into view.
$rise = function ($node, $stagger = 90) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger));
};
// A section heading that fades in on view.
$fade_in = array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700);
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
// every opening until the company puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-homeservices-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e2e8f0/1e3a5f?text=' . rawurlencode($initials !== '' ? $initials : 'H');
};

// ── The company: its services, its areas and its team ──────────────────

// The company itself. The about page tells its story; the footer, the
// visit band and the contact lines use the address, the phones and the
// opening hours.
$company = array(
    'tagline'   => lang('Cleaning, plumbing, electrical work and repairs for your home, by one trusted team'),
    'story'     => lang('A plumber and a cleaner who kept sending customers to each other started the company so that a home would need one call for every job. Today our team cleans, mends and fits in homes across the city, with fixed prices agreed before the work starts.'),
    'address'   => lang('Workshop Lane 14, Industrial Quarter'),
    'phone'     => '+90 555 000 00 00',
    'emergency' => '+90 555 000 00 99',
    'email'     => 'help@example.com',
    'hours'     => lang('Monday to Saturday, 8:00 to 19:00'),
    'licence'   => lang('Fully insured; every job carries a twelve-month workmanship guarantee'),
);

// The service categories, in the order the service list filters them.
// Each answer's value is its own words, so a filter link (?category=)
// reads as it shows.
$categories = array(
    'cleaning'   => array('icon' => 'bi-stars', 'title' => lang('Cleaning'),
        'text' => lang('Regular and deep cleaning, move-out cleans and carpets.')),
    'plumbing'   => array('icon' => 'bi-droplet', 'title' => lang('Plumbing'),
        'text' => lang('Leaks, taps, drains, toilets and water heaters.')),
    'electrical' => array('icon' => 'bi-lightning-charge', 'title' => lang('Electrical'),
        'text' => lang('Sockets, lights, fuse boxes and safety checks.')),
    'repairs'    => array('icon' => 'bi-hammer', 'title' => lang('Repairs and Handyman'),
        'text' => lang('Doors, windows, shelves, furniture and the small jobs.')),
);

// The sample services: the records the Services form starts with. 'crew'
// is how many people come (the Crew field holds it in words, $crew_text());
// 'unit' says whether the price is per visit or per hour; 'duration' is
// words.
$services = array(
    'deep_clean' => array('name' => lang('Deep Home Cleaning'), 'category' => 'cleaning', 'unit' => 'visit', 'crew' => 2,
        'duration' => lang('4 to 6 hours'),
        'summary'  => lang('Every room cleaned from top to bottom, inside the oven and the fridge, behind the furniture and along every skirting board.'),
        'includes' => array(lang('Kitchen and bathrooms degreased and descaled'), lang('Inside the oven, fridge and cupboards'), lang('Windows cleaned inside, with their frames'), lang('Our own products and equipment')),
        'same_day' => false, 'featured' => true),
    'move_out' => array('name' => lang('Move-out Cleaning'), 'category' => 'cleaning', 'unit' => 'visit', 'crew' => 3,
        'duration' => lang('5 to 7 hours'),
        'summary'  => lang('An empty home made ready for its next people, to the standard a landlord or a buyer checks.'),
        'includes' => array(lang('Every room, cupboard and appliance'), lang('Limescale and grease removed'), lang('Walls spot-cleaned and floors washed'), lang('A second visit if anything is missed')),
        'same_day' => false, 'featured' => false),
    'leak_repair' => array('name' => lang('Leak and Tap Repair'), 'category' => 'plumbing', 'unit' => 'visit', 'crew' => 1,
        'duration' => lang('1 to 2 hours'),
        'summary'  => lang('A dripping tap, a leaking pipe or a running toilet found and fixed, usually in a single visit.'),
        'includes' => array(lang('Finding where the water comes from'), lang('Washers, seals and small parts'), lang('Testing the repair under pressure'), lang('Clearing up after the work')),
        'same_day' => true, 'featured' => true),
    'water_heater' => array('name' => lang('Water Heater Service'), 'category' => 'plumbing', 'unit' => 'visit', 'crew' => 1,
        'duration' => lang('1 to 2 hours'),
        'summary'  => lang('A yearly service of your boiler or water heater: cleaned, checked for safety and set to use less energy.'),
        'includes' => array(lang('A full safety check'), lang('Cleaning the burner and the filters'), lang('Pressure and settings adjusted'), lang('A written report of what we found')),
        'same_day' => false, 'featured' => false),
    'sockets_lights' => array('name' => lang('Sockets and Lighting'), 'category' => 'electrical', 'unit' => 'hour', 'crew' => 1,
        'duration' => lang('Usually 1 to 3 hours'),
        'summary'  => lang('New sockets, switches and lights fitted, or old ones moved and replaced, safely and to the regulations.'),
        'includes' => array(lang('Fitting sockets, switches and lights'), lang('Moving a socket or a light point'), lang('Testing every circuit we touch'), lang('Taking away the old fittings')),
        'same_day' => false, 'featured' => true),
    'safety_check' => array('name' => lang('Electrical Safety Check'), 'category' => 'electrical', 'unit' => 'visit', 'crew' => 1,
        'duration' => lang('2 to 3 hours'),
        'summary'  => lang('A full check of your fuse box, wiring and sockets, with a certificate for a landlord or a buyer.'),
        'includes' => array(lang('Testing every circuit and the fuse box'), lang('Checking sockets, switches and earthing'), lang('A safety certificate'), lang('A price for anything that needs fixing')),
        'same_day' => false, 'featured' => false),
    'handyman' => array('name' => lang('Handyman by the Hour'), 'category' => 'repairs', 'unit' => 'hour', 'crew' => 1,
        'duration' => lang('Booked by the hour, one hour at least'),
        'summary'  => lang('The list of small jobs that has waited too long: shelves, pictures, curtain rails, flat-pack furniture and loose handles.'),
        'includes' => array(lang('Shelves, pictures and curtain rails put up'), lang('Flat-pack furniture assembled'), lang('Loose handles, hinges and fittings fixed'), lang('Fixings and small parts')),
        'same_day' => true, 'featured' => false),
    'doors_windows' => array('name' => lang('Door and Window Repair'), 'category' => 'repairs', 'unit' => 'visit', 'crew' => 1,
        'duration' => lang('1 to 3 hours'),
        'summary'  => lang('Doors that stick, windows that will not close and locks that turn badly, adjusted or repaired.'),
        'includes' => array(lang('Hinges and handles adjusted or replaced'), lang('Locks repaired or changed'), lang('Draughts sealed'), lang('Advice when a part must be ordered')),
        'same_day' => false, 'featured' => false),
);

// A service's starting price per currency. The service list, the service
// page, the price guide and the Services form's Price field show it.
$service_prices = array(
    'deep_clean'     => array('USD' => 180, 'EUR' => 165, 'GBP' => 140, 'TRY' => 6500),
    'move_out'       => array('USD' => 260, 'EUR' => 240, 'GBP' => 205, 'TRY' => 9500),
    'leak_repair'    => array('USD' => 90, 'EUR' => 85, 'GBP' => 70, 'TRY' => 3200),
    'water_heater'   => array('USD' => 120, 'EUR' => 110, 'GBP' => 95, 'TRY' => 4300),
    'sockets_lights' => array('USD' => 65, 'EUR' => 60, 'GBP' => 50, 'TRY' => 2400),
    'safety_check'   => array('USD' => 150, 'EUR' => 140, 'GBP' => 120, 'TRY' => 5400),
    'handyman'       => array('USD' => 55, 'EUR' => 50, 'GBP' => 45, 'TRY' => 2000),
    'doors_windows'  => array('USD' => 85, 'EUR' => 80, 'GBP' => 65, 'TRY' => 3000),
);

// The call-out charge for a visit to an area outside the core of the
// city (the areas page and the visit booking).
$call_out = array('USD' => 25, 'EUR' => 25, 'GBP' => 20, 'TRY' => 900);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A service's price therefore shows one amount wherever it appears.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A service's "From … per visit" or "From … per hour" words, the value of
// its Price field.
$price_text = function ($key) use ($services, $service_prices, $money) {
    $format = $services[$key]['unit'] === 'hour' ? lang('From %s per hour') : lang('From %s per visit');
    return sprintf($format, $money($service_prices[$key]));
};
// A service's crew in words: "1 technician", "2 technicians".
$crew_text = function ($key) use ($services) {
    $n = (int)$services[$key]['crew'];
    return $n === 1 ? lang('1 technician') : sprintf(lang('%d technicians'), $n);
};
// What a service includes as the value of its Includes field: one line
// per item.
$includes_text = function ($key) use ($services) {
    return implode("\n", $services[$key]['includes']);
};

// The service areas. 'core' areas have no call-out charge; 'days' are the
// visit days in words, never a date.
$areas = array(
    'centre'    => array('name' => lang('City Centre'), 'core' => true, 'days' => lang('Every day we are open'),
        'places' => lang('The old town, the market streets and the harbour')),
    'northside' => array('name' => lang('Northside'), 'core' => true, 'days' => lang('Every day we are open'),
        'places' => lang('The university quarter and the northern avenues')),
    'riverside' => array('name' => lang('Riverside'), 'core' => true, 'days' => lang('Every day we are open'),
        'places' => lang('Both banks of the river, from the bridge to the park')),
    'hillcrest' => array('name' => lang('Hillcrest'), 'core' => false, 'days' => lang('Monday, Wednesday and Friday'),
        'places' => lang('The hill villages and the new estates above them')),
    'lakeside'  => array('name' => lang('Lakeside'), 'core' => false, 'days' => lang('Tuesday, Thursday and Saturday'),
        'places' => lang('The lake shore and the villages around it')),
    'airport'   => array('name' => lang('Airport District'), 'core' => false, 'days' => lang('Tuesday and Friday'),
        'places' => lang('The business park and the homes along the airport road')),
);

// The sample team: the records the Team form starts with. 'role' is a
// category's title or the office; 'years' the years in the trade.
$team = array(
    array('name' => lang('Murat Demir'), 'role' => lang('Plumbing'), 'years' => 18,
        'summary' => lang('Murat founded the company and still takes the hardest leaks himself.')),
    array('name' => lang('Selin Aydın'), 'role' => lang('Cleaning'), 'years' => 11,
        'summary' => lang('Selin leads the cleaning crews and trains every new cleaner.')),
    array('name' => lang('Daniel Brooks'), 'role' => lang('Electrical'), 'years' => 14,
        'summary' => lang('A qualified electrician who writes our safety certificates.')),
    array('name' => lang('Emre Koç'), 'role' => lang('Repairs and Handyman'), 'years' => 9,
        'summary' => lang('A carpenter by training who can fix almost anything in a home.')),
);
$office_role = lang('Office');

// How a job goes, in four steps (the home and the price guide).
$how_it_works = array(
    array('title' => lang('Tell us the job'), 'text' => lang('Book a visit or ask for an estimate online, with photos if you have them.')),
    array('title' => lang('Agree the price'), 'text' => lang('We confirm a fixed price, or an hourly rate, before any work starts.')),
    array('title' => lang('We come to you'), 'text' => lang('A technician arrives in the time window you chose, with the tools and the parts.')),
    array('title' => lang('Pay when it is done'), 'text' => lang('You pay once you are happy with the work, by card or bank transfer.')),
);

// Why customers choose the company (the home and the about page).
$why_us = array(
    array('icon' => 'bi-person-check', 'title' => lang('Checked technicians'),
        'text' => lang('Every technician is trained, insured and checked before their first visit.')),
    array('icon' => 'bi-tag', 'title' => lang('Prices agreed first'),
        'text' => lang('You know the price before we start, and it does not change halfway.')),
    array('icon' => 'bi-clock', 'title' => lang('On time'),
        'text' => lang('An arrival window you choose, and a call if we are running late.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Twelve-month guarantee'),
        'text' => lang('If our work fails within a year, we come back and fix it at no charge.')),
);

// The company in four figures (the home and about pages).
$figures = array(
    array('value' => '12', 'label' => lang('Years in business')),
    array('value' => '24', 'label' => lang('Technicians and cleaners')),
    array('value' => '6', 'label' => lang('Areas we cover')),
    array('value' => '4.9', 'label' => lang('Average customer rating')),
);

// What customers say, each about one of the sample services.
$testimonials = array(
    array('service' => 'leak_repair', 'name' => lang('Ayşe Kara'), 'role' => lang('Had a Leak and Tap Repair'),
        'quote' => lang('The leak under the sink was fixed within an hour of the technician arriving, and the price was what they told me on the phone.')),
    array('service' => 'deep_clean', 'name' => lang('James Miller'), 'role' => lang('Had a Deep Home Cleaning'),
        'quote' => lang('Two cleaners spent the whole day on our flat and it has not looked this good since we moved in.')),
    array('service' => 'handyman', 'name' => lang('Zeynep Arslan'), 'role' => lang('Had a Handyman by the Hour'),
        'quote' => lang('Shelves, a wardrobe and three pictures in two hours. Friendly, tidy and on time.')),
);

// The care plans and the gift card the shop sells (with the shop), with
// their prices. The SKUs name the template, so the products are its own.
$care_plans = array(
    'home_care'   => array('sku' => 'HOMESVC-PLAN-HOME', 'icon' => 'bi-house-check', 'title' => lang('Home Care Plan'),
        'text'  => lang('A yearly water heater service, an electrical safety check and two hours of handyman time, at a lower price than booking them one by one.'),
        'price' => array('USD' => 290, 'EUR' => 270, 'GBP' => 230, 'TRY' => 10500)),
    'clean_four'  => array('sku' => 'HOMESVC-PLAN-CLEAN', 'icon' => 'bi-stars', 'title' => lang('Four Seasons Cleaning Plan'),
        'text'  => lang('Four deep cleans a year, one each season, booked on the days that suit you.'),
        'price' => array('USD' => 620, 'EUR' => 570, 'GBP' => 490, 'TRY' => 22500)),
    'gift'        => array('sku' => 'HOMESVC-GIFT-100', 'icon' => 'bi-gift', 'title' => lang('Home Services Gift Card'),
        'text'  => lang('A gift card towards any of our services, valid for a year.'),
        'price' => array('USD' => 100, 'EUR' => 100, 'GBP' => 100, 'TRY' => 3500)),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the customer chose.
$category_titles = array();
foreach ($categories as $c) $category_titles[] = $c['title'];
$service_names = array();
foreach ($services as $s) $service_names[] = $s['name'];
$service_choices = array_merge($service_names, array(lang('Something else')));
$area_names = array();
foreach ($areas as $a) $area_names[] = $a['name'];
$team_roles = array_merge($category_titles, array($office_role));
// The visit's time windows: the technician arrives within the window.
$time_windows = array(lang('Morning, 8:00 to 12:00'), lang('Afternoon, 12:00 to 16:00'), lang('Evening, 16:00 to 19:00'));
// The same windows in full, in the same order (the home's Arrival Windows
// and the areas page's time slots): the icon, the hours, the class of its
// bar on the working day's track (pg-window-am/-pm/-eve), what it suits,
// the days it is offered and its tag, if any.
$arrival_windows = array(
    'morning'   => array('icon' => 'bi-sunrise', 'name' => lang('Morning'), 'from' => '08:00', 'to' => '12:00', 'span' => 'pg-window-am',
        'text' => lang('The first visits of the day, so we are rarely held up on the way.'), 'days' => lang('Monday to Saturday'), 'tag' => lang('Most booked')),
    'afternoon' => array('icon' => 'bi-sun', 'name' => lang('Afternoon'), 'from' => '12:00', 'to' => '16:00', 'span' => 'pg-window-pm',
        'text' => lang('For when you are home after lunch, or can leave us a key.'), 'days' => lang('Monday to Saturday'), 'tag' => ''),
    'evening'   => array('icon' => 'bi-sunset', 'name' => lang('Evening'), 'from' => '16:00', 'to' => '19:00', 'span' => 'pg-window-eve',
        'text' => lang('After work, for the jobs that fit in a short visit.'), 'days' => lang('Monday to Friday'), 'tag' => ''),
);
$property_types = array(lang('Flat or apartment'), lang('House'), lang('Office or shop'), lang('Something else'));
$property_sizes = array(lang('Studio or one bedroom'), lang('Two bedrooms'), lang('Three bedrooms'), lang('Four bedrooms or more'));
$urgency_choices = array(lang('As soon as possible'), lang('Within a week'), lang('Within a month'), lang('No hurry'));
$access_choices = array(
    array(lang('Someone will be at home'), lang('Someone will be at home')),
    array(lang('A key will be left for you'), lang('A key will be left for you')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a visit booking, set by the company in the panel.
$visit_statuses = array(lang('Request received'), lang('Confirmed'), lang('Waiting for you'), lang('Job done'), lang('Cancelled'));
// The states of an estimate request, set by the company in the panel.
$estimate_statuses = array(lang('Estimate requested'), lang('Estimate sent'), lang('Estimate accepted'), lang('Declined'));

// The query string of a link that opens a list with a filter set
// (?category=) or a form with a choice made (?service=, ?area=): the
// form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the visit band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('visit_band');
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
            $heading('h1', $title, 'phs-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'phs-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'phs-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the why-us points are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'hs-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the emergency line for a leak that cannot
// wait. A shared component.
$notice = function () use ($el, $icon, $span, $link, $company) {
    return $el('p', 'mb-0', lang('Notice'), array(
        $icon('bi-telephone-forward', 'text-primary me-1'),
        $span(lang('Burst pipe or no power? Our emergency line:'), '', lang('Text')),
        $link($company['emergency'], 'tel:' . preg_replace('/[^0-9+]/', '', $company['emergency']), 'link-primary fw-semibold text-nowrap ms-1'),
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

// The header: the company's name, the menu, the way to ask for an estimate
// and the way to book a visit. The links to the current page are marked
// active on the server (smart active state), so one header serves every
// page. The care plans are the shop's and are linked from the shop pages
// only, so the header stands on a site without the shop. The menu opens
// out from 1200 px: six links and two buttons are wider than the 960 px
// container, and from 1200 to 1399 px Get an Estimate is left out of the
// bar (it stays in the collapsed menu and returns from 1400 px).
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('services', lang('Our Services')),
        array('prices', lang('Prices')),
        array('areas', lang('Service Areas')),
        array('team', lang('Our Team')),
        array('reviews', lang('Customer Reviews')),
        array('about', lang('About Us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-tools', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'phs-brand fs-4 text-truncate', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#hs-nav'),
                    $attr('aria-controls', 'hs-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-xl-auto me-xl-3 column-gap-xl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $link(lang('Get an Estimate'), '{{page:estimate}}', 'btn btn-outline-primary text-nowrap d-xl-none d-xxl-inline-block'),
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-3 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-check'),
                            $span(lang('Book a Visit'), '', lang('Link Text')),
                        ), array('href' => '{{page:book}}')),
                    )),
                ), array('id' => 'hs-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the company in a sentence, how to reach it
// and its profiles, the services by category, the service areas, the
// customer pages and the company's pages; at the bottom the year, the
// site's name and the guarantee.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $categories, $areas, $company, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $category_links = array($link(lang('All Services'), '{{page:services}}', $plain));
    foreach ($categories as $c) $category_links[] = $link($c['title'], '{{page:services}}?' . $query(array('category' => $c['title'])), $plain);
    $area_links = array();
    foreach ($areas as $key => $a) $area_links[] = $link($a['name'], '{{page:areas}}#hs-area-' . $key, $plain);
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
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-tools', 'text-primary'),
                        $span('{{site_name}}', 'phs-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($company['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($company['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($company['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $company['phone']), $plain), lang('Phone')),
                        $contact_line('bi-telephone-forward', $el('span', '', lang('Emergency Line'), array(
                            $span(lang('Emergencies:'), 'me-1', lang('Label')),
                            $link($company['emergency'], 'tel:' . preg_replace('/[^0-9+]/', '', $company['emergency']), $plain),
                        )), lang('Emergency Line')),
                        $contact_line('bi-envelope', $link($company['email'], 'mailto:' . $company['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($company['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-whatsapp', 'WhatsApp'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Service Links'), array(
                    $heading('h2', lang('Our Services'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($category_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Area Links'), array(
                    $heading('h2', lang('Service Areas'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($area_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Customers'), array(
                    $heading('h2', lang('For Customers'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book a Visit'), '{{page:book}}', $plain),
                        $link(lang('My Visits'), '{{page:my_visits}}', $plain),
                        $link(lang('Get an Estimate'), '{{page:estimate}}', $plain),
                        $link(lang('My Estimates'), '{{page:my_estimates}}', $plain),
                        $link(lang('Prices'), '{{page:prices}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Company'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Our Team'), '{{page:team}}', $plain),
                        $link(lang('Customer Reviews'), '{{page:reviews}}', $plain),
                        $link(lang('Home Care Tips'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($company['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The visit band: a full-width band in the palette's colour with the way to
// book a visit and the way to ask for an estimate. Shared, so every page
// that ends with it carries the same call.
$visit_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Something at home needs fixing?'), 'phs-display h2 mb-2'),
                    $para(lang('Book a visit in a time window that suits you, or send us photos for a free estimate; we answer within a working day.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-secondary btn-lg px-4'),
                        $link(lang('Get a Free Estimate'), '{{page:estimate}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The facts of a service in one line: category, how long it takes and who
// comes, each bound to the Services form's field of the same name.
$service_facts = function ($sample_key = 'leak_repair', $class = 'd-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $services, $categories, $crew_text) {
    $s = $services[$sample_key];
    $fact = function ($icon_name, $children, $name) use ($el, $icon) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array_merge(array($icon($icon_name, 'text-primary')), $children));
    };
    return $el('ul', 'list-unstyled ' . $class, lang('Service Facts'), array(
        $fact('bi-hourglass-split', array($span($s['duration'], '', lang('Duration'), $bind('duration'))), lang('Duration')),
        $fact('bi-people', array($span($crew_text($sample_key), '', lang('Crew'), $bind('crew'))), lang('Crew')),
        $fact($categories[$s['category']]['icon'], array($span($categories[$s['category']]['title'], '', lang('Category'), $bind('category'))), lang('Category')),
    ));
};

// A service's card of the lists (the home, the service list and the price
// guide): the photo, the category, the name linking the service page, the
// summary, the facts and the price. Every value is bound to the Services
// form's field of the same name; the sample fills the canvas.
$service_card = function ($sample_key = 'leak_repair') use ($el, $para, $span, $link, $image, $col, $bind, $services, $categories, $price_text, $service_facts, $photo, $attr) {
    $s = $services[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden phs-lift', lang('Service Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('service-' . $sample_key, 800, 600), $s['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($categories[$s['category']]['title'], 'phs-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Category'), $bind('category')),
                $el('h3', 'phs-display h5 position-static mb-2', lang('Service Name'), array(
                    $link($s['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($s['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $service_facts($sample_key, 'd-flex flex-wrap gap-3 small text-body-secondary mb-3'),
                $para($price_text($sample_key), 'phs-price fw-semibold mt-auto mb-0', $bind('price')),
            )),
        )),
    ), '6', '4');
};

// A category's tile (the home and the service list's top): the icon, the
// title, a line and a link that opens the service list filtered on it.
$category_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $categories, $query) {
    $c = $categories[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 p-4 phs-lift', lang('Category Card'), array(
            $icon_tile($c['icon'], 'mb-3 align-self-start'),
            $el('h3', 'phs-display h5 position-static mb-2', lang('Category Name'), array(
                $link($c['title'], '{{page:services}}?' . $query(array('category' => $c['title'])), 'stretched-link link-body-emphasis text-decoration-none'),
            )),
            $para($c['text'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// A service area's card (the areas page and the home): the name, the
// places it covers, the visit days, the call-out note and the links that
// open the visit booking and the estimate request with the area chosen.
// Its id is the anchor the footer's area links open.
$area_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon, $areas, $call_out, $money, $query) {
    $a = $areas[$key];
    $note = $a['core'] ? lang('No call-out charge') : sprintf(lang('Call-out charge %s'), $money($call_out));
    $q = $query(array('area' => $a['name']));
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3', lang('Area Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-2', lang('Area Heading'), array(
                    $heading('h3', $a['name'], 'phs-display h5 mb-0'),
                    $span($note, 'badge ' . ($a['core'] ? 'text-bg-primary' : 'text-bg-secondary'), lang('Badge')),
                )),
                $para($a['places'], 'small text-body-secondary mb-3'),
                $el('p', 'd-flex align-items-center gap-2 small mb-4', lang('Visit Days'), array(
                    $icon('bi-calendar-week', 'text-primary'),
                    $span($a['days'], '', lang('Text')),
                )),
                $el('div', 'd-flex flex-wrap gap-2 mt-auto', lang('Buttons'), array(
                    $link(lang('Book a Visit'), '{{page:book}}?' . $q, 'btn btn-sm btn-primary'),
                    $link(lang('Get an Estimate'), '{{page:estimate}}?' . $q, 'btn btn-sm btn-outline-primary'),
                )),
            )),
        ), array('id' => 'hs-area-' . $key)),
    ), '6', '4');
};

// A team member's card (the team list and the home): the photo, the role,
// the name linking the member's page, the years in the trade and a line,
// each bound to the Team form's field of the same name.
$team_card = function ($n = 0) use ($el, $para, $span, $link, $image, $col, $bind, $team, $portrait, $attr) {
    $m = $team[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden text-center phs-lift', lang('Team Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($portrait($m['name']), $m['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $span($m['role'], 'phs-eyebrow d-block small text-uppercase fw-semibold text-primary mb-1', lang('Role'), $bind('role')),
                $el('h3', 'phs-display h5 position-static mb-1', lang('Name'), array(
                    $link($m['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $el('p', 'small text-body-secondary mb-2', lang('Experience'), array(
                    $span((string)$m['years'], 'fw-semibold', lang('Value'), $bind('years')),
                    $span(lang('years in the trade'), 'ms-1', lang('Unit')),
                )),
                $para($m['summary'], 'small mb-0', $bind('summary')),
            )),
        )),
    ), '6', '3');
};

// What a customer says: the stars, the quote, the customer and the
// service.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-3 p-4 mb-0', lang('Customer Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'phs-quote mb-0'))),
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
    $card = $el('div', 'card border-0 bg-body-tertiary rounded-3 flex-grow-1', lang('Comment Card'), array(
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
            $el('form', 'card card-body border-0 bg-body-tertiary rounded-3 p-4', lang('Comment Form'), $fields, array('_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment'))),
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
                $item('service_new', lang('New Service')),
                $item('team_new', lang('New Team Member')),
                $item('staff_visits', lang('Visit Bookings')),
                $item('staff_estimates', lang('Estimate Requests')),
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
    return $agree($id, 'consent', lang('I would like to receive the company\'s seasonal offers and home care tips by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// estimate request takes documents (a floor plan, an earlier invoice) and
// pictures, so the default accepts PDFs, office files, text and pictures;
// one that takes only pictures is a collection's image kind (a service's
// photo).
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
                    $link(lang('Five small leaks to fix before winter'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
    return $el('aside', 'p-3 rounded-3 bg-body-tertiary mb-4', lang('AI Summary'), array(
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

