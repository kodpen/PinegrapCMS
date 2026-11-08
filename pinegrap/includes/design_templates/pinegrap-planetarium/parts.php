<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Planetarium", part 1 of its files: the node builders,
 * the planetarium's own data (the planetarium and its opening hours, the
 * audiences of the dome shows, the sample shows counted from the day the
 * template opens, the places and states of a telescope night, the sample
 * nights, the telescopes, tickets and memberships with their prices, the
 * kinds of school visit and the grades, the astronomers, the answers of
 * the forms' pick lists) and the small helpers the pages share (page
 * shell, night section, page header, section header, times, the night
 * status pill, show facts, steps, check lists, questions). cards.php and
 * the page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-planetarium.php puts them together.
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
// The interactions, which the operator can change or remove in the
// Interactions section. Showtimes, planets and phases rise one after
// another as they scroll into view, objects fade in, and the home's sky
// photo drifts slowly as the page scrolls; nothing moves by itself, and
// the platform keeps all of it still under reduced motion.
$rise = function ($node, $stagger = 90) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => (int)$stagger);
    return $node;
};
$fade_in = function ($node, $stagger = 90) {
    $node['props']['_fx'] = array('effect' => 'fade', 'trigger' => 'view', 'stagger' => (int)$stagger);
    return $node;
};
$drift = function ($node, $speed = 15) {
    $node['props']['_fx'] = array('effect' => 'parallax', 'speed' => (int)$speed);
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
// every opening until the planetarium puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-planetarium-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on a night blue. The initials follow the
// name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/1e1b4b/e0e7ff?text=' . rawurlencode($initials !== '' ? $initials : 'P');
};

// ── The planetarium: its hours, its dome and its telescopes ─────────────

// The planetarium itself. The observatory page tells its story; the
// footer, the visit page and the tickets page use the address, phone and
// hours.
$planetarium = array(
    'tagline' => lang('A planetarium dome and a public observatory under one sky'),
    'story'   => lang('The observatory began as one telescope on the roof of the old school, opened to anyone who climbed the stairs on a clear night. The dome followed, then the roof terrace, and the astronomers still answer every question at the eyepiece.'),
    'dome'    => lang('A dome of eighteen metres with 140 reclining seats and a sky of nine thousand stars'),
    'address' => lang('Observatory Hill, Starlight Road'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'info@example.com',
    'hours'   => lang('Open Tuesday to Sunday, 10:00 to 18:00'),
    'nights'  => lang('Telescope nights from dusk on clear Fridays and Saturdays'),
    'darksky' => lang('A dark-sky site forty minutes out of town for the star parties'),
);

// The opening hours, line by line (the visit page and the footer say them
// in a line).
$opening_hours = array(
    array('days' => lang('Dome shows, Tuesday to Sunday'), 'hours' => '10:00 - 18:00'),
    array('days' => lang('Exhibition hall'), 'hours' => '10:00 - 17:30'),
    array('days' => lang('Telescope nights, Friday and Saturday'), 'hours' => '20:00 - 23:30'),
    array('days' => lang('Box office'), 'hours' => '09:30 - 18:00'),
);

// The audiences of a dome show. The show list filters on them
// (?audience=) in their own words.
$audiences = array(
    'families' => array('icon' => 'bi-people', 'title' => lang('Families')),
    'schools'  => array('icon' => 'bi-backpack', 'title' => lang('Schools')),
    'adults'   => array('icon' => 'bi-person', 'title' => lang('Adults')),
    'little'   => array('icon' => 'bi-balloon', 'title' => lang('Small Children')),
);

// The sample dome shows: the records the Dome Shows form starts with.
// 'day' counts from the day the template opens ($show_on): 0 is today,
// so the home has shows today and the list starts with them. The
// showtimes are words; 'length' is in minutes, 'age' the minimum age
// (0 for all ages).
$shows = array(
    'solar_tour'  => array('title' => lang('A Tour of the Solar System'), 'audience' => 'families', 'day' => 0,
        'times' => '11:00, 13:30, 16:00', 'length' => 40, 'age' => 5, 'language' => lang('In Turkish'),
        'summary' => lang('Fly past every planet from scorching Mercury to icy Neptune, and land on a moon on the way back.'),
        'details' => array(lang('Narrated live by an astronomer'), lang('Questions answered after each show'), lang('Reclining seats under the full dome'), lang('Arrive ten minutes before the start')),
        'featured' => true),
    'little_star' => array('title' => lang('Twinkle, the Little Star'), 'audience' => 'little', 'day' => 0,
        'times' => '10:30, 12:00', 'length' => 25, 'age' => 0, 'language' => lang('In Turkish'),
        'summary' => lang('A gentle story of a small star looking for its constellation, with songs and a soft, slow sky.'),
        'details' => array(lang('Lights never go fully dark'), lang('Prams can be left at the door'), lang('Children may sit on a lap'), lang('A short show for short attention spans')),
        'featured' => true),
    'deep_sky'    => array('title' => lang('Into the Deep Sky'), 'audience' => 'adults', 'day' => 0,
        'times' => '17:00, 19:30', 'length' => 50, 'age' => 12, 'language' => lang('In English with Turkish subtitles'),
        'summary' => lang('Nebulae, star clusters and galaxies as the great telescopes see them, and what their light tells us.'),
        'details' => array(lang('Images from space telescopes'), lang('The evening show is followed by the roof terrace when clear'), lang('No late entry once the dome is dark'), lang('Best for ages twelve and over')),
        'featured' => true),
    'moon_walk'   => array('title' => lang('Footsteps on the Moon'), 'audience' => 'schools', 'day' => 1,
        'times' => '09:30, 11:00, 14:00', 'length' => 45, 'age' => 8, 'language' => lang('In Turkish'),
        'summary' => lang('Why the Moon has phases, how its craters formed and what the astronauts brought home.'),
        'details' => array(lang('Follows the science curriculum'), lang('A worksheet for the teacher'), lang('A real Moon rock replica to pass around'), lang('Book through a school visit request')),
        'featured' => false),
    'black_holes' => array('title' => lang('Where Light Cannot Escape'), 'audience' => 'adults', 'day' => 2,
        'times' => '18:00, 20:00', 'length' => 55, 'age' => 14, 'language' => lang('In English with Turkish subtitles'),
        'summary' => lang('Black holes, from the stars that make them to the first picture of one, told by our astronomers.'),
        'details' => array(lang('Some strong light effects'), lang('A talk of ten minutes after the show'), lang('No late entry once the dome is dark'), lang('Best for ages fourteen and over')),
        'featured' => false),
    'seasons'     => array('title' => lang('The Sky of the Seasons'), 'audience' => 'families', 'day' => 3,
        'times' => '11:30, 15:00', 'length' => 35, 'age' => 6, 'language' => lang('In Turkish'),
        'summary' => lang('The constellations of this season, how to find them from your own balcony and the stories behind them.'),
        'details' => array(lang('A star map to take home'), lang('Narrated live by an astronomer'), lang('Questions answered after each show'), lang('Arrive ten minutes before the start')),
        'featured' => false),
);
// The date of a show or a night as Y-m-d: $days days after today (0 is
// today), so the sample always starts on the day the template opens.
$show_on = function ($days) {
    $day = new DateTime('today');
    if ((int)$days > 0) $day->modify('+' . (int)$days . ' days');
    return $day->format('Y-m-d');
};
// A show's details as the value of its Details field: one line per item.
$details_text = function ($list) {
    return implode("\n", $list);
};

// The places of a telescope night: the Telescope Nights form's pick list.
$night_places = array(
    'dome'    => array('icon' => 'bi-circle-half', 'title' => lang('Main Dome')),
    'terrace' => array('icon' => 'bi-building', 'title' => lang('Roof Terrace')),
    'darksky' => array('icon' => 'bi-moon-stars', 'title' => lang('Dark-Sky Site')),
);

// The states of a telescope night, set by the staff in the panel: the
// call is theirs, never a weather feed. Each pill is an icon and a word.
$night_statuses = array(
    'on'     => array('icon' => 'bi-check-circle-fill', 'pill' => 'text-bg-success', 'title' => lang('On')),
    'off'    => array('icon' => 'bi-x-circle-fill', 'pill' => 'text-bg-danger', 'title' => lang('Called Off')),
    'moved'  => array('icon' => 'bi-arrow-right-circle-fill', 'pill' => 'text-bg-warning', 'title' => lang('Moved')),
);

// The sample telescope nights: the records the Telescope Nights form
// starts with. 'day' counts from today as a show's does; the target and
// the places left are words.
$nights = array(
    'saturn'   => array('title' => lang('Saturn and Its Rings'), 'day' => 1, 'time' => '20:30', 'place' => 'terrace',
        'target' => lang('Saturn, its rings and the moon Titan'), 'places' => lang('24 places'),
        'summary' => lang('The ringed planet stands high after dark: a sight nobody forgets at the eyepiece.'), 'status' => 'on'),
    'moon'     => array('title' => lang('Craters of the Moon'), 'day' => 2, 'time' => '20:00', 'place' => 'dome',
        'target' => lang('The Moon along the line of shadow'), 'places' => lang('30 places'),
        'summary' => lang('Mountains and craters stand out where day meets night on the Moon\'s face.'), 'status' => 'on'),
    'clusters' => array('title' => lang('Star Clusters of the Season'), 'day' => 8, 'time' => '21:00', 'place' => 'terrace',
        'target' => lang('Open and globular star clusters'), 'places' => lang('24 places'),
        'summary' => lang('Jewel boxes of young stars and ancient swarms of thousands, star-hopped one by one.'), 'status' => 'on'),
    'darksky'  => array('title' => lang('Star Party at the Dark-Sky Site'), 'day' => 15, 'time' => '21:30', 'place' => 'darksky',
        'target' => lang('Galaxies, nebulae and the Milky Way'), 'places' => lang('60 places, bus included'),
        'summary' => lang('A night far from the town lights with every telescope we own and a warm drink at midnight.'), 'status' => 'on'),
);

// The telescopes of the observatory (the observatory page). Fixed data,
// not a collection.
$telescopes = array(
    array('icon' => 'bi-binoculars', 'title' => lang('The Great Refractor'), 'kind' => lang('Refractor, 30 cm lens'),
        'summary' => lang('The observatory\'s first telescope, still the sharpest view of the planets and the Moon.')),
    array('icon' => 'bi-circle', 'title' => lang('The Dome Reflector'), 'kind' => lang('Reflector, 60 cm mirror'),
        'summary' => lang('Our largest mirror, for faint galaxies and nebulae on the darkest nights.')),
    array('icon' => 'bi-sun', 'title' => lang('The Solar Telescope'), 'kind' => lang('Filtered for the Sun'),
        'summary' => lang('Sunspots and flares in safety, on sunny afternoons from the roof terrace.')),
);

// ── Tickets and memberships with their prices ───────────────────────────

// The tickets. With the shop each is a product of the Tickets group; the
// tickets page lists them with their prices. One table of prices feeds
// both, so the page and the cart show one figure. A ticket is shown at the
// door from the receipt.
$tickets = array(
    'dome_adult'  => array('title' => lang('Dome Show Ticket, Adult'), 'who' => lang('Ages 16 and over')),
    'dome_child'  => array('title' => lang('Dome Show Ticket, Child'), 'who' => lang('Ages 3 to 15; under 3 free on a lap')),
    'dome_family' => array('title' => lang('Dome Show Ticket, Family'), 'who' => lang('Two adults and up to three children')),
    'night'       => array('title' => lang('Telescope Night Ticket'), 'who' => lang('One person, any age, for one night')),
);
$ticket_prices = array(
    'dome_adult'  => array('USD' => 14, 'EUR' => 13, 'GBP' => 11, 'TRY' => 450),
    'dome_child'  => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 300),
    'dome_family' => array('USD' => 40, 'EUR' => 37, 'GBP' => 32, 'TRY' => 1300),
    'night'       => array('USD' => 12, 'EUR' => 11, 'GBP' => 9, 'TRY' => 400),
);

// The memberships: a year of the sky. With the shop each is a product of
// the Memberships group; the memberships page lists them.
$memberships = array(
    'single' => array('title' => lang('Single Membership'), 'who' => lang('One adult'),
        'benefits' => array(lang('Free dome shows for a year'), lang('Half price at telescope nights'), lang('The members\' sky letter each month'), lang('10% off in the shop'))),
    'family' => array('title' => lang('Family Membership'), 'who' => lang('Two adults and their children under 16'),
        'benefits' => array(lang('Free dome shows for a year'), lang('Free telescope nights for the children'), lang('A members\' star party each season'), lang('10% off in the shop'))),
    'patron' => array('title' => lang('Patron Membership'), 'who' => lang('For friends of the observatory'),
        'benefits' => array(lang('Everything in the Family Membership'), lang('Free telescope nights for a year'), lang('Your name on the patrons\' wall'), lang('A night at the great refractor with an astronomer'))),
);
$membership_prices = array(
    'single' => array('USD' => 60, 'EUR' => 55, 'GBP' => 48, 'TRY' => 2000),
    'family' => array('USD' => 140, 'EUR' => 130, 'GBP' => 110, 'TRY' => 4700),
    'patron' => array('USD' => 400, 'EUR' => 370, 'GBP' => 320, 'TRY' => 13500),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A membership's "… a year" words.
$membership_price_text = function ($key) use ($membership_prices, $money) {
    return sprintf(lang('%s a year'), $money($membership_prices[$key]));
};

// ── School visits and the astronomers ───────────────────────────────────

// The kinds of visit a school or a group asks for: a request, never a
// booking. A card opens the visit request with its kind chosen (?visit=).
$visits = array(
    'dome'     => array('icon' => 'bi-circle-half', 'title' => lang('Dome Show and Workshop'), 'who' => lang('Classes from nursery to high school'),
        'length'  => lang('Two hours, the show and a workshop'),
        'summary' => lang('A dome show chosen for the class, then a hands-on workshop with an astronomer that follows the curriculum.')),
    'day'      => array('icon' => 'bi-binoculars', 'title' => lang('Observatory Day'), 'who' => lang('Classes from the fourth grade up'),
        'length'  => lang('A morning or an afternoon'),
        'summary' => lang('The telescopes, the solar telescope on a sunny day and a look behind the scenes of the observatory.')),
    'party'    => array('icon' => 'bi-moon-stars', 'title' => lang('Star Party'), 'who' => lang('School groups, clubs and families'),
        'length'  => lang('An evening from dusk'),
        'summary' => lang('An evening at the telescopes for your group alone, at the observatory or at the dark-sky site.')),
    'teachers' => array('icon' => 'bi-easel', 'title' => lang('Teacher Training'), 'who' => lang('Teachers of science and geography'),
        'length'  => lang('A day of workshops'),
        'summary' => lang('How to teach the sky with simple tools, and a kit of activities to take back to the classroom.')),
);

// The grades of a visit: the visit request's list (?grade=).
$grades = array(
    'nursery' => lang('Nursery'),
    'primary' => lang('Primary school'),
    'middle'  => lang('Middle school'),
    'high'    => lang('High school'),
    'teachers' => lang('Teachers'),
);

// The astronomers (the observatory page). Fixed data, not a collection.
$people = array(
    array('name' => lang('Selin Aydın'), 'role' => lang('Director of the Observatory'),
        'bio' => lang('Came for one night at the great refractor as a student and has opened its dome ever since.')),
    array('name' => lang('Marco Bellini'), 'role' => lang('Planetarium Astronomer'),
        'bio' => lang('Writes and narrates the dome shows, and answers the hardest questions afterwards.')),
    array('name' => lang('Elif Kaya'), 'role' => lang('Head of Education'),
        'bio' => lang('Plans the school workshops and answers every visit request.')),
    array('name' => lang('Tomas Lindqvist'), 'role' => lang('Telescope Night Leader'),
        'bio' => lang('Knows every star cluster by heart and calls each telescope night on the day.')),
);

// ── The answers of the forms' pick lists ────────────────────────────────
// Each answer's value is its own words, so the e-mails and the lists read
// as the visitor chose.
$audience_titles = array();
foreach ($audiences as $a) $audience_titles[] = $a['title'];
$place_titles = array();
foreach ($night_places as $p) $place_titles[] = $p['title'];
$night_status_titles = array();
foreach ($night_statuses as $s) $night_status_titles[] = $s['title'];
$grade_titles = array_values($grades);
$visit_titles = array();
foreach ($visits as $v) $visit_titles[] = $v['title'];
// The visit request's visit: each kind by title, or something else.
$visit_choices = array_merge($visit_titles, array(lang('Something else')));
$pupil_counts = array(lang('Up to 15'), lang('16 to 30'), lang('31 to 60'), lang('61 to 120'), lang('More than 120'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a visit request, set by the planetarium in the panel.
$visit_statuses = array(lang('Request received'), lang('Confirmed'), lang('Waiting list'), lang('Cancelled'));

// The words of a sample record's pick list answers.
$show_audience = function ($key) use ($shows, $audiences) {
    return $audiences[$shows[$key]['audience']]['title'];
};
$night_place = function ($key) use ($nights, $night_places) {
    return $night_places[$nights[$key]['place']]['title'];
};
$night_status = function ($key) use ($nights, $night_statuses) {
    return $night_statuses[$nights[$key]['status']]['title'];
};
// A show's length and minimum age as words.
$length_text = function ($minutes) {
    return sprintf(lang('%d minutes'), (int)$minutes);
};
$age_text = function ($age) {
    return (int)$age > 0 ? sprintf(lang('Ages %d and over'), (int)$age) : lang('All ages');
};

// The query string of a link that opens a list with a filter set
// (?audience=) or a form with a choice made (?visit=, ?grade=): the form's
// fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// ── Page helpers ────────────────────────────────────────────────────────

// A page: the account bar, the header, the page's own content and the
// shared footer. The planetarium has no band above the footer: the night
// sections of each page carry its dark ground.
$page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
    ));
};

// A night section: the dark colour mode of the theme on the section, so
// every text and border inside follows it (tonight, the moon, the show
// heads). The day sections (visit, tickets, school visits) stay light.
$night = function ($children, $class, $name, $extra = array()) use ($el) {
    $extra['_attrs'] = array_merge(isset($extra['_attrs']) ? $extra['_attrs'] : array(), array(array('name' => 'data-bs-theme', 'value' => 'dark')));
    return $el('section', trim('bg-body text-body ' . $class), $name, $children, $extra);
};

// The top of the inner pages: where the page sits, its title and one
// sentence, on the night ground for the sky pages and the light one for
// the day pages, and whatever the page adds under them.
$page_header = function ($title, $lead, $more = array(), $dark = true) use ($el, $heading, $para, $link, $container, $attr) {
    $attrs = $dark ? array($attr('data-bs-theme', 'dark')) : array();
    $props = $attrs ? array('_attrs' => $attrs) : array();
    return $el('header', 'ppm-hero py-4 py-lg-5 ' . ($dark ? 'bg-body text-body' : 'bg-body-tertiary'), lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'ppm-display display-5 fw-semibold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ), $props);
};

// The heading over a section: the title, one sentence under it and a
// plain link to the rest beside it, the title set off by a thin rule at
// its left like a line of a star chart.
$section_header = function ($title, $lead = '', $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $text = array($heading('h2', $title, 'ppm-display h2 fw-semibold mb-1'));
    if ($lead !== '') $text[] = $para($lead, 'text-body-secondary mb-0');
    $children = array($el('div', 'ppm-rule border-start border-2 border-primary ps-3', lang('Section Title'), $text));
    if ($more_text !== '') {
        $children[] = $el('a', 'link-primary link-underline-opacity-0 link-underline-opacity-100-hover d-inline-flex align-items-center gap-1 fw-semibold text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), $children);
};

// A time as the sky pages write it: tabular figures, so the showtimes and
// the start of a night line up in a column.
$time = function ($text, $class = '') use ($span) {
    return $span($text, trim('ppm-time ' . $class), lang('Time'));
};

// A telescope night's state as a pill: its icon and its word.
$status_pill = function ($status) use ($el, $icon, $span, $night_statuses) {
    $s = $night_statuses[$status];
    return $el('span', 'badge rounded-pill d-inline-flex align-items-center gap-1 ' . $s['pill'], lang('Night Status'), array(
        $icon($s['icon']),
        $span($s['title'], '', lang('Text')),
    ));
};

// A fact of a show or a night: an icon and its words (the length, the
// minimum age, the language, the place).
$fact = function ($icon_name, $text) use ($el, $icon, $span) {
    return $el('span', 'd-inline-flex align-items-center gap-1 small text-body-secondary', lang('Fact'), array(
        $icon($icon_name),
        $span($text, '', lang('Text')),
    ));
};

// An icon in a soft round tile, the way the visit kinds and the
// telescopes are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// A numbered step of a short how-to.
$step = function ($n, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'd-flex gap-3 mb-4', lang('Step'), array(
        $span((string)$n, 'ppm-time badge rounded-circle text-bg-primary fs-6 align-self-start', lang('Step Number')),
        $el('div', '', lang('Text'), array(
            $heading('h3', $title, 'h6 fw-bold mb-1'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ));
};

// A check list: a star and the words on each line.
$check_list = function ($items, $class = 'list-unstyled mb-0') use ($el, $icon, $span) {
    $children = array();
    $last = count($items) - 1;
    foreach (array_values($items) as $n => $text) {
        $children[] = $el('li', 'd-flex gap-2 ' . ($n === $last ? 'mb-0' : 'mb-2'), lang('List Item'), array(
            $icon('bi-star-fill', 'text-primary small mt-1'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', $class, lang('Check List'), $children);
};

// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answer, $topics = '', $parent = 'plm-faq') use ($el, $para, $attr, $searchable) {
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
