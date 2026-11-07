<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Coworking", part 1 of its files: the node builders, the
 * space's own data (the staffed hours, the floors, the kinds of space, the
 * spaces with their seats and rates, the membership plans and the day pass
 * with their prices, the amenities, the members' perks, the community
 * events, what members say and the answers of the forms' pick lists) and
 * the parts every page repeats (account bar, header, footer, tour band,
 * space, kind, plan, amenity, event and member cards, page shells, section
 * headers, form fields, the comment thread, the staff bar and the blog
 * parts).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-coworking.php puts them together.
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
// every opening until the space puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-coworking-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ffe4dc/9a3412?text=' . rawurlencode($initials !== '' ? $initials : 'C');
};

// ── The space: its floors, its desks and rooms, its plans and its people ─

// The coworking space itself. The about page tells its story; the footer,
// the tour band and the contact lines use the address, phone and hours.
$hub = array(
    'tagline' => lang('Desks, private offices and meeting rooms in a bright old warehouse, with good coffee and a community that shares what it knows'),
    'story'   => lang('Three freelancers who were tired of working from kitchen tables turned an empty tea warehouse into the place they wanted to work in. Today it is home to designers, developers, writers and small teams who come for a quiet desk and stay for the people around it.'),
    'address' => lang('Harbour Lane 21, Old Town'),
    'region'  => lang('Two minutes from the tram stop, with bike racks in the courtyard'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'hello@example.com',
);

// The hours the front desk is staffed, in the order the footer and the
// contact page show them. Members with a fixed desk or an office have a
// key card and come in at any hour.
$opening_hours = array(
    array('days' => lang('Monday to Friday'), 'hours' => '08:00–20:00'),
    array('days' => lang('Saturday'), 'hours' => '10:00–16:00'),
    array('days' => lang('Residents and offices'), 'hours' => '24/7'),
);

// The floors of the building, where each space is.
$floors = array(
    'ground' => lang('Ground Floor'),
    'first'  => lang('First Floor'),
    'second' => lang('Second Floor'),
);

// The kinds of space: the Spaces list's filter (?kind=) and the overview of
// the home page. Each answer's value is its own words, so a filter link
// reads as it shows. 'unit' is what a space of the kind is let by.
$kinds = array(
    'hot'     => array('icon' => 'bi-laptop', 'title' => lang('Hot Desks'), 'unit' => 'day',
        'text' => lang('Any free seat on the open floor or in the quiet library: bring your laptop and find your spot.')),
    'fixed'   => array('icon' => 'bi-pc-display', 'title' => lang('Fixed Desks'), 'unit' => 'month',
        'text' => lang('Your own desk, chair and lockable drawer, ready every morning just as you left it.')),
    'office'  => array('icon' => 'bi-door-closed', 'title' => lang('Private Offices'), 'unit' => 'month',
        'text' => lang('A furnished office with a door that locks, for teams of two to ten who want their own room.')),
    'meeting' => array('icon' => 'bi-easel', 'title' => lang('Meeting Rooms'), 'unit' => 'hour',
        'text' => lang('Rooms with a screen, a whiteboard and good light, booked by the hour for calls, workshops and interviews.')),
    'event'   => array('icon' => 'bi-mic', 'title' => lang('Event Hall'), 'unit' => 'hour',
        'text' => lang('A hall for up to eighty people for talks, launches and workshops, with sound, a projector and a kitchen.')),
);

// The spaces: the records the Spaces form starts with. The space list, the
// space page and the home read them. 'seats' is how many people it takes,
// 'price' what it costs by the kind's unit, per currency.
$spaces = array(
    'floor'     => array('name' => lang('The Open Floor'), 'kind' => 'hot', 'floor' => 'ground', 'seats' => 40,
        'price'    => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 850),
        'summary'  => lang('Long shared tables under the warehouse windows, the buzz of people at work and the coffee bar a few steps away.'),
        'features' => array(lang('Sit-stand tables by the windows'), lang('Sockets and USB at every seat'), lang('Next to the coffee bar'))),
    'library'   => array('name' => lang('The Library'), 'kind' => 'hot', 'floor' => 'second', 'seats' => 16,
        'price'    => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 850),
        'summary'  => lang('A quiet room lined with books where calls are taken outside and the only sound is typing.'),
        'features' => array(lang('A silent room, calls outside'), lang('Armchairs and reading lamps'), lang('Books the members leave behind'))),
    'studio'    => array('name' => lang('Studio Desks'), 'kind' => 'fixed', 'floor' => 'first', 'seats' => 24,
        'price'    => array('USD' => 290, 'EUR' => 270, 'GBP' => 235, 'TRY' => 9900),
        'summary'  => lang('A row of fixed desks with a second screen, a lockable drawer and a shelf of your own.'),
        'features' => array(lang('A second screen on every desk'), lang('A lockable drawer and a shelf'), lang('Key card for any hour'))),
    'office4'   => array('name' => lang('Office Four'), 'kind' => 'office', 'floor' => 'first', 'seats' => 4,
        'price'    => array('USD' => 1100, 'EUR' => 1020, 'GBP' => 890, 'TRY' => 37500),
        'summary'  => lang('A bright office for four with a window onto the courtyard and room for a small meeting table.'),
        'features' => array(lang('Furnished, with a lock on the door'), lang('A window onto the courtyard'), lang('Meeting room hours every month'))),
    'corner'    => array('name' => lang('The Corner Office'), 'kind' => 'office', 'floor' => 'second', 'seats' => 8,
        'price'    => array('USD' => 2100, 'EUR' => 1950, 'GBP' => 1700, 'TRY' => 71500),
        'summary'  => lang('The largest office in the building, with windows on two sides and a view of the harbour cranes.'),
        'features' => array(lang('Furnished, with a lock on the door'), lang('Windows on two sides'), lang('Meeting room hours every month'))),
    'boardroom' => array('name' => lang('The Boardroom'), 'kind' => 'meeting', 'floor' => 'second', 'seats' => 12,
        'price'    => array('USD' => 40, 'EUR' => 37, 'GBP' => 32, 'TRY' => 1350),
        'summary'  => lang('A long oak table for twelve, a large screen for calls and a whiteboard wall.'),
        'features' => array(lang('A large screen and a camera for calls'), lang('A whiteboard wall'), lang('Water and coffee brought in'))),
    'huddle'    => array('name' => lang('The Huddle Room'), 'kind' => 'meeting', 'floor' => 'ground', 'seats' => 4,
        'price'    => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 500),
        'summary'  => lang('A small room for four, made for a quick call, an interview or a chat that needs a closed door.'),
        'features' => array(lang('A screen for calls'), lang('Sound-proofed walls'), lang('Bookable by the hour'))),
    'hall'      => array('name' => lang('The Hall'), 'kind' => 'event', 'floor' => 'ground', 'seats' => 80,
        'price'    => array('USD' => 120, 'EUR' => 110, 'GBP' => 95, 'TRY' => 4000),
        'summary'  => lang('The old loading hall, with its iron beams and brick walls, for talks, launches and workshops.'),
        'features' => array(lang('Sound, microphones and a projector'), lang('Chairs for eighty or tables for forty'), lang('A kitchen for caterers'))),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A plan or a space therefore shows one amount wherever it appears.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// The "… an hour", "… a day" or "… a month" words of a price.
$per_unit = function (array $prices, $unit) use ($money) {
    $words = array('hour' => lang('%s an hour'), 'day' => lang('%s a day'), 'month' => lang('%s a month'));
    return sprintf($words[$unit], $money($prices));
};
// A space's price, by its kind's unit.
$space_price = function ($key) use ($spaces, $kinds, $per_unit) {
    return $per_unit($spaces[$key]['price'], $kinds[$spaces[$key]['kind']]['unit']);
};

// The membership plans and the day pass, with their price per currency.
// The plans page and the home show them; with the shop each is a product
// that gives the buyer membership for 'days'. 'unit' is what the price is
// for: a day pass is paid by the day, the others by the month.
$plans = array(
    'day'      => array('sku' => 'CW-DAYPASS', 'icon' => 'bi-ticket-perforated', 'title' => lang('Day Pass'), 'days' => 1, 'unit' => 'day', 'featured' => false,
        'price'  => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 850),
        'text'   => lang('One day on the open floor or in the library, coffee included: for a visit to the city or a change of scene.'),
        'points' => array(lang('Any free hot desk from 8:00 to 20:00'), lang('Coffee, tea and fast Wi-Fi'), lang('Printing up to twenty pages'))),
    'flex'     => array('sku' => 'CW-FLEX', 'icon' => 'bi-calendar-week', 'title' => lang('Flex Ten'), 'days' => 30, 'unit' => 'month', 'featured' => false,
        'price'  => array('USD' => 160, 'EUR' => 148, 'GBP' => 128, 'TRY' => 5400),
        'text'   => lang('Ten days a month at any hot desk, for those who split the week between home and the space.'),
        'points' => array(lang('Ten days a month at any hot desk'), lang('Two meeting room hours a month'), lang('Members\' events and the community board'))),
    'open'     => array('sku' => 'CW-OPEN', 'icon' => 'bi-infinity', 'title' => lang('Open Membership'), 'days' => 30, 'unit' => 'month', 'featured' => true,
        'price'  => array('USD' => 240, 'EUR' => 222, 'GBP' => 192, 'TRY' => 8100),
        'text'   => lang('Every weekday and Saturday at any hot desk, with meeting room hours and a locker of your own.'),
        'points' => array(lang('Any hot desk on every staffed day'), lang('Five meeting room hours a month'), lang('A locker of your own'), lang('Members\' events and the community board'))),
    'resident' => array('sku' => 'CW-RESIDENT', 'icon' => 'bi-pc-display', 'title' => lang('Resident Desk'), 'days' => 30, 'unit' => 'month', 'featured' => false,
        'price'  => array('USD' => 290, 'EUR' => 270, 'GBP' => 235, 'TRY' => 9900),
        'text'   => lang('Your own fixed desk with a second screen, a key card for any hour and a business address for your post.'),
        'points' => array(lang('Your own desk and a key card for any hour'), lang('Eight meeting room hours a month'), lang('A business address and post handling'), lang('Members\' events and the community board'))),
);
// A plan's price words.
$plan_price = function ($key) use ($plans, $per_unit) {
    return $per_unit($plans[$key]['price'], $plans[$key]['unit']);
};

// What every member gets beside the desk (the amenities page and the home).
$amenities = array(
    'wifi'     => array('icon' => 'bi-wifi', 'title' => lang('Fast Wi-Fi'),
        'text' => lang('A fibre line with a wired port at every fixed desk and a guest network for your visitors.')),
    'coffee'   => array('icon' => 'bi-cup-hot', 'title' => lang('Coffee Bar'),
        'text' => lang('Coffee from a local roaster, tea and fresh milk all day, and a kitchen to warm your lunch.')),
    'print'    => array('icon' => 'bi-printer', 'title' => lang('Printing and Scanning'),
        'text' => lang('A printer and a scanner on every floor, sent from your laptop with your member login.')),
    'booths'   => array('icon' => 'bi-telephone', 'title' => lang('Phone Booths'),
        'text' => lang('Six sound-proofed booths for calls, free to use for as long as you need one.')),
    'lockers'  => array('icon' => 'bi-lock', 'title' => lang('Lockers'),
        'text' => lang('Lockers with your own code for the laptop, the bike helmet or the gym bag.')),
    'showers'  => array('icon' => 'bi-droplet', 'title' => lang('Showers and Bike Storage'),
        'text' => lang('Covered bike racks in the courtyard and two showers for those who cycle in.')),
    'post'     => array('icon' => 'bi-mailbox', 'title' => lang('Business Address'),
        'text' => lang('Use the space as your company\'s address; the front desk takes in your post and parcels.')),
    'terrace'  => array('icon' => 'bi-tree', 'title' => lang('Roof Terrace'),
        'text' => lang('Tables in the open air above the harbour for lunch, a call or the Friday get-together.')),
);

// What members get from the partners of the space (the members' area).
$perks = array(
    array('icon' => 'bi-bicycle', 'title' => lang('Bike Repair'), 'text' => lang('A tenth off at the bike shop across the courtyard.')),
    array('icon' => 'bi-cup-straw', 'title' => lang('Lunch Nearby'), 'text' => lang('A free drink with lunch at three cafés on Harbour Lane.')),
    array('icon' => 'bi-calculator', 'title' => lang('Accounting Hour'), 'text' => lang('A free first hour with the accountant who works on the first floor.')),
    array('icon' => 'bi-cloud', 'title' => lang('Software Credits'), 'text' => lang('Start-up credits with cloud and design tools our members use.')),
);

// What a community event is about: the event list's grouping.
$event_topics = array(
    'learn'    => lang('Learn Something'),
    'business' => lang('Grow a Business'),
    'social'   => lang('Meet the Community'),
);

// The community events: the records the Events form starts with (step 5
// dates them from the day the template opens: 'in_days' after it);
// 'space' is the space it is held in, 'free' whether members pay nothing.
$events = array(
    'breakfast' => array('title' => lang('Members\' Breakfast'), 'topic' => 'social', 'in_days' => 3, 'time' => '08:30', 'minutes' => 60, 'space' => 'floor', 'free' => true,
        'summary' => lang('Fresh bread, coffee and five minutes each to say what you are working on.')),
    'office'    => array('title' => lang('Founders\' Office Hours'), 'topic' => 'business', 'in_days' => 5, 'time' => '16:00', 'minutes' => 90, 'space' => 'boardroom', 'free' => true,
        'summary' => lang('Bring a question about your company to three founders who have been there before you.')),
    'talk'      => array('title' => lang('Lunch and Learn: Pricing Your Work'), 'topic' => 'learn', 'in_days' => 8, 'time' => '12:30', 'minutes' => 60, 'space' => 'hall', 'free' => false,
        'summary' => lang('A freelance designer shows how she sets her rates and says no to the wrong clients.')),
    'critique'  => array('title' => lang('Show and Tell Evening'), 'topic' => 'learn', 'in_days' => 12, 'time' => '18:30', 'minutes' => 120, 'space' => 'hall', 'free' => true,
        'summary' => lang('Five members show a project in five minutes each and the room asks questions.')),
    'drinks'    => array('title' => lang('Friday Drinks on the Terrace'), 'topic' => 'social', 'in_days' => 10, 'time' => '17:30', 'minutes' => 120, 'space' => 'floor', 'free' => true,
        'summary' => lang('The week ends on the roof with drinks, music and the people from the other floors.')),
);

// The space in four figures (the home and about pages).
$figures = array(
    array('value' => '150+', 'label' => lang('Members from twenty trades')),
    array('value' => '3', 'label' => lang('Floors in an old warehouse')),
    array('value' => '8', 'label' => lang('Meeting rooms and offices')),
    array('value' => '24/7', 'label' => lang('Access for residents')),
);

// What members say, each about one kind of space.
$testimonials = array(
    array('kind' => 'hot', 'name' => lang('Elif Şahin'), 'role' => lang('Illustrator, works at the Hot Desks'),
        'quote' => lang('I came for a quiet desk and found people to have lunch with. Two of my best clients sat at the next table.')),
    array('kind' => 'office', 'name' => lang('Daniel Okafor'), 'role' => lang('Founder, in one of the Private Offices'),
        'quote' => lang('We moved in as three and are now seven. The space grew with us without a single new contract to sign.')),
    array('kind' => 'meeting', 'name' => lang('Merve Koç'), 'role' => lang('Consultant, books the Meeting Rooms'),
        'quote' => lang('I meet my clients in the Boardroom and they always ask where my office is. It feels like my own.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$kind_titles = array();
foreach ($kinds as $k) $kind_titles[] = $k['title'];
$space_names = array();
foreach ($spaces as $s) $space_names[] = $s['name'];
$floor_titles = array_values($floors);
$topic_titles = array_values($event_topics);
$event_titles = array();
foreach ($events as $e) $event_titles[] = $e['title'];
$plan_choices = array();
foreach ($plans as $p) $plan_choices[] = $p['title'];
// The tour request offers a free trial day and a private office beside
// the plans.
$tour_choices = array_merge(array(lang('Free trial day')), $plan_choices, array(lang('A private office')));
// The times a tour starts, in order: the tour request's Time.
$tour_times = array('09:30', '11:00', '14:00', '16:30');
$team_sizes = array(lang('Just me'), lang('2 to 4 people'), lang('5 to 8 people'), lang('More than 8 people'));
$start_choices = array(lang('As soon as possible'), lang('Within a month'), lang('In one to three months'), lang('Later than that'));
$work_choices = array(lang('Design and creative work'), lang('Software and technology'), lang('Writing and media'), lang('Consulting and services'), lang('Something else'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a tour request, set by the front desk in the panel.
$tour_statuses = array(lang('Request received'), lang('Tour booked'), lang('Visited'), lang('Membership started'), lang('Cancelled'));
// The states of an office enquiry, set by the front desk in the panel.
$enquiry_statuses = array(lang('Request received'), lang('Offer sent'), lang('Office let'), lang('Declined'));
// The states of an event sign-up, set by the front desk in the panel.
$signup_statuses = array(lang('Request received'), lang('Place confirmed'), lang('Event is full'), lang('Cancelled'));

// The query string of a link that opens a list with a filter set
// (?kind=) or a form with a choice made (?plan=, ?space=, ?event=): the
// form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the tour band above the footer unless the page is
// a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('tour_band');
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
            $heading('h1', $title, 'pcw-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pcw-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pcw-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the amenities are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what members say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'cw-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the free trial day and the way to ask for
// it. A shared component.
$notice = function () use ($el, $icon, $span, $link, $query) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-cup-hot', 'text-primary'),
        $span(lang('Come for a tour and your first day at a desk is on us.'), '', lang('Text')),
        $link(lang('Book a tour'), '{{page:tour}}?' . $query(array('plan' => lang('Free trial day'))), 'link-primary fw-semibold'),
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

// The header: the space's name, the menu and the way to book a tour. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page. The memberships the shop sells
// are linked from the shop pages only, so the header stands on a site
// without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('spaces', lang('Spaces')),
        array('plans', lang('Membership Plans')),
        array('book_online', lang('Meeting Rooms')),
        array('amenities', lang('Amenities')),
        array('events', lang('Community Events')),
        array('location', lang('Find Us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-laptop', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pcw-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#cw-nav'),
                    $attr('aria-controls', 'cw-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-check'),
                            $span(lang('Book a Tour'), '', lang('Link Text')),
                        ), array('href' => '{{page:tour}}')),
                    )),
                ), array('id' => 'cw-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the space in a sentence and its profiles,
// the spaces by kind, the members' pages, the space's pages and how to
// reach it with the staffed hours; at the bottom the year and the site's
// name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $kinds, $hub, $opening_hours, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $space_links = array($link(lang('All Spaces'), '{{page:spaces}}', $plain));
    foreach ($kinds as $k) $space_links[] = $link($k['title'], '{{page:spaces}}?' . $query(array('kind' => $k['title'])), $plain);
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
            $span($h['hours'], 'pcw-price', lang('Hours')),
        ));
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Space'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-laptop', 'text-primary'),
                        $span('{{site_name}}', 'pcw-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($hub['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-linkedin', 'LinkedIn'),
                        $social('bi-mastodon', 'Mastodon'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Space Links'), array(
                    $heading('h2', lang('Work Here'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($space_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Members'), array(
                    $heading('h2', lang('For Members'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book a Tour'), '{{page:tour}}', $plain),
                        $link(lang('My Tours'), '{{page:my_tours}}', $plain),
                        $link(lang('Ask About an Office'), '{{page:office_enquiry}}', $plain),
                        $link(lang('Membership Plans'), '{{page:plans}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Space'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Amenities'), '{{page:amenities}}', $plain),
                        $link(lang('Meeting Rooms'), '{{page:book_online}}', $plain),
                        $link(lang('Community Events'), '{{page:events}}', $plain),
                        $link(lang('Blog'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Visit Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($hub['address'], '{{page:location}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($hub['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $hub['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($hub['email'], 'mailto:' . $hub['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-clock', $el('div', '', lang('Opening Hours'), $hours), lang('Opening Hours')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Good desks, good coffee, good neighbours.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The tour band: a full-width band in the palette's colour with the free
// trial day and the plans. Shared, so every page that ends with it carries
// the same offer.
$tour_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $query) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Try a day at a desk, on us'), 'pcw-display h2 mb-2'),
                    $para(lang('Book a tour, meet the people at the front desk and stay to work for the rest of the day before you choose a plan.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Free Day'), '{{page:tour}}?' . $query(array('plan' => lang('Free trial day'))), 'btn btn-light btn-lg px-4'),
                        $link(lang('See the Plans'), '{{page:plans}}', 'btn btn-outline-light btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A kind of space (the home and the spaces overview): the icon, the
// title, a line and a link that opens the space list filtered on it.
$kind_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $kinds, $query) {
    $k = $kinds[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pcw-lift', lang('Kind Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($k['icon'], 'align-self-start mb-3'),
                $heading('h3', $k['title'], 'pcw-display h5 mb-2'),
                $para($k['text'], 'small text-body-secondary mb-3'),
                $link(lang('See the spaces'), '{{page:spaces}}?' . $query(array('kind' => $k['title'])), 'stretched-link fw-semibold link-primary text-decoration-none mt-auto'),
            )),
        )),
    ), '6', '4');
};

// A space's card of the lists (the home and the space list): the photo,
// the kind, the name linking the space page, the seats, the floor, the
// price and the summary. Every value is bound to the Spaces form's field
// of the same name; the sample fills the canvas.
$space_card = function ($sample_key = 'floor') use ($el, $para, $span, $link, $image, $col, $icon, $bind, $spaces, $kinds, $floors, $space_price, $photo, $attr) {
    $s = $spaces[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pcw-lift', lang('Space Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('space-' . $sample_key, 800, 600), $s['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($kinds[$s['kind']]['title'], 'pcw-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Kind'), $bind('kind')),
                $el('h3', 'pcw-display h5 position-static mb-2', lang('Space Name'), array(
                    $link($s['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($s['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mt-auto mb-2', lang('Space Facts'), array(
                    $el('li', 'd-inline-flex align-items-center gap-1', lang('Seats'), array(
                        $icon('bi-people', 'text-primary'),
                        $span((string)$s['seats'], 'pcw-price', lang('Value'), $bind('seats')),
                        $span(lang('seats'), '', lang('Unit')),
                    )),
                    $el('li', 'd-inline-flex align-items-center gap-1', lang('Floor'), array(
                        $icon('bi-building', 'text-primary'),
                        $span($floors[$s['floor']], '', lang('Value'), $bind('floor')),
                    )),
                )),
                $para($space_price($sample_key), 'pcw-price fw-bold mb-0', $bind('rate')),
            )),
        )),
    ), '6', '4');
};

// A plan's card (the plans page and the home): the name, the price, a
// line, what it holds and a link that opens the tour request with the plan
// chosen. The plan most members choose is marked.
$plan_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $check_list, $icon_tile, $plans, $plan_price, $query) {
    $p = $plans[$key];
    $top = array($icon_tile($p['icon']));
    if ($p['featured']) $top[] = $span(lang('Most chosen'), 'badge text-bg-primary', lang('Badge'));
    return $col(array(
        $el('article', 'card h-100 shadow-sm ' . ($p['featured'] ? 'border-primary border-2' : 'border-0'), lang('Plan Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-start justify-content-between gap-2 mb-3', lang('Plan Heading'), $top),
                $heading('h3', $p['title'], 'pcw-display h4 mb-1'),
                $para($plan_price($key), 'pcw-price h5 fw-bold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $check_list($p['points'], 'list-unstyled small mb-4'),
                $link(lang('Book a tour for this plan'), '{{page:tour}}?' . $query(array('plan' => $p['title'])),
                    ($p['featured'] ? 'btn btn-primary' : 'btn btn-outline-primary') . ' mt-auto'),
            )),
        )),
    ), '6', '3');
};

// An amenity: the icon, the title and a line.
$amenity_card = function ($key) use ($el, $heading, $para, $col, $icon_tile, $amenities) {
    $a = $amenities[$key];
    return $col(array(
        $el('div', 'd-flex gap-3', lang('Amenity'), array(
            $icon_tile($a['icon'], 'flex-shrink-0 align-self-start'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $a['title'], 'h6 fw-bold mb-1'),
                $para($a['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3');
};

// An event's card of the lists (the home and the event list): the topic,
// the date and start, the title linking the event page, the space and the
// summary. Every value is bound to the Events form's field of the same
// name; the sample fills the canvas.
$event_card = function ($sample_key = 'breakfast') use ($el, $para, $span, $link, $col, $icon, $bind, $events, $event_topics, $spaces) {
    $e = $events[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pcw-lift', lang('Event Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_topics[$e['topic']], 'pcw-eyebrow small text-uppercase fw-semibold text-primary mb-2', lang('Topic'), $bind('topic')),
                $el('p', 'd-flex flex-wrap align-items-center gap-2 small fw-semibold mb-2', lang('Date and Time'), array(
                    $icon('bi-calendar-event', 'text-primary'),
                    $span(lang('Date'), '', lang('Date'), $bind('event_date')),
                    $span($e['time'], 'pcw-price', lang('Start Time'), $bind('start_time')),
                )),
                $el('h3', 'pcw-display h5 position-static mb-2', lang('Event Title'), array(
                    $link($e['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($e['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('p', 'd-flex align-items-center gap-1 small text-body-secondary mt-auto mb-0', lang('Place'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($spaces[$e['space']]['name'], '', lang('Value'), $bind('place')),
                )),
            )),
        )),
    ), '6', '4');
};

// What a member says: the quote, the stars, the member and the space.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Member Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Member'), array(
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
                $item('staff_tours', lang('Tour Requests')),
                $item('staff_enquiries', lang('Office Enquiries')),
                $item('staff_signups', lang('Event Sign-ups')),
                $item('space_new', lang('New Space')),
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
    return $agree($id, 'consent', lang('I would like to receive the space\'s news, community events and blog posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// office enquiry may carry a floor plan or a brief, so the default accepts
// PDFs, office files, text and pictures; one that takes only pictures is a
// collection's image kind (a space's photo).
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
                    $link(lang('Five habits of people who get their work done at a shared desk'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

