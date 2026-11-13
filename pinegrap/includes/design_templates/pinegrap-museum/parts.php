<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", part 1 of its files: the node builders, the
 * museum's own data (opening hours, admission tickets and membership tiers
 * with their prices, the departments of the collection, the sample
 * highlights, exhibitions and events, the learning sessions, facilities,
 * the way there, the people, the figures, what visitors say and the
 * answers of the forms' pick lists) and the parts every page repeats
 * (account bar, header, footer, visit band, the opening hours, exhibition,
 * object, department, event, ticket, membership, session, staff and
 * visitor cards, page shells, section headers, form fields, the comment
 * thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-museum.php puts them together.
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
// every opening until the museum puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-museum-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/f3e8ea/7f1d1d?text=' . rawurlencode($initials !== '' ? $initials : 'M');
};

// ── The museum: its hours, its tickets, its collection and its people ──

// The museum itself. The about page tells its story; the footer, the visit
// band and the visit page use the address, phone and hours.
$museum = array(
    'tagline'  => lang('Art and objects from every age, in a city museum open to everyone'),
    'story'    => lang('The museum began with one family\'s gift of paintings and drawings to the city. It now cares for some forty thousand works, from ancient pottery to photographs, shows them in twelve galleries and lends them across the country, and it still keeps the gallery of the first gift open free to all.'),
    'address'  => lang('Museum Square 1, Old Town'),
    'phone'    => '+90 555 000 00 00',
    'email'    => 'visit@example.com',
    'hours'    => lang('Open Tuesday to Sunday, 10:00 to 18:00; Fridays until 21:00'),
    'last'     => lang('Last entry 45 minutes before closing'),
    'free'     => lang('Children under six and members visit free'),
    'licence'  => lang('An accredited museum, run by a charitable trust'),
);

// The opening hours, day by day (the visit page, the home and the footer
// say them in a line).
$opening_hours = array(
    array('days' => lang('Monday'), 'hours' => lang('Closed')),
    array('days' => lang('Tuesday to Thursday'), 'hours' => '10:00 - 18:00'),
    array('days' => lang('Friday'), 'hours' => '10:00 - 21:00'),
    array('days' => lang('Saturday and Sunday'), 'hours' => '10:00 - 18:00'),
);

// The admission tickets. With the shop each is a product of the Admission
// group; without it the visit page lists them with their prices. One table
// of prices feeds both, so the visit page and the shop show one figure.
$admission = array(
    'adult' => array('title' => lang('Adult'), 'who' => lang('Ages 18 to 64'),
        'includes' => lang('The collection galleries and the free exhibitions')),
    'concession' => array('title' => lang('Concession'), 'who' => lang('Over 65s, students and job seekers'),
        'includes' => lang('The collection galleries and the free exhibitions')),
    'young' => array('title' => lang('Young Person'), 'who' => lang('Ages 6 to 17'),
        'includes' => lang('The collection galleries and the free exhibitions')),
    'family' => array('title' => lang('Family'), 'who' => lang('Two adults and up to three children'),
        'includes' => lang('The collection galleries and the free exhibitions')),
    'exhibition' => array('title' => lang('Exhibition Ticket'), 'who' => lang('Every visitor over six'),
        'includes' => lang('The ticketed exhibition and the whole museum on the same day')),
);
$admission_prices = array(
    'adult'      => array('USD' => 18, 'EUR' => 16, 'GBP' => 14, 'TRY' => 450),
    'concession' => array('USD' => 12, 'EUR' => 11, 'GBP' => 9, 'TRY' => 300),
    'young'      => array('USD' => 8, 'EUR' => 7, 'GBP' => 6, 'TRY' => 200),
    'family'     => array('USD' => 40, 'EUR' => 36, 'GBP' => 32, 'TRY' => 1000),
    'exhibition' => array('USD' => 24, 'EUR' => 22, 'GBP' => 19, 'TRY' => 600),
);

// The membership tiers: a year of free entry and more. With the shop each
// is a product of the Membership group; the support page lists them.
$tiers = array(
    'individual' => array('title' => lang('Individual Member'), 'who' => lang('One adult'),
        'benefits' => array(lang('Free entry to every exhibition, as often as you like'), lang('Member previews before each exhibition opens'), lang('10% off in the shop and the café'), lang('The members\' magazine three times a year'))),
    'joint' => array('title' => lang('Joint Members'), 'who' => lang('Two adults at the same address'),
        'benefits' => array(lang('Free entry to every exhibition for both of you'), lang('Member previews before each exhibition opens'), lang('10% off in the shop and the café'), lang('The members\' magazine three times a year'))),
    'family' => array('title' => lang('Family Members'), 'who' => lang('Two adults and their children under 18'),
        'benefits' => array(lang('Free entry to every exhibition for the whole family'), lang('Family days booked before anyone else'), lang('10% off in the shop and the café'), lang('A members\' trail for children in every exhibition'))),
    'patron' => array('title' => lang('Patron'), 'who' => lang('Two adults who give more'),
        'benefits' => array(lang('Everything a joint membership gives'), lang('A private tour with a curator each year'), lang('Invitations to exhibition openings'), lang('Your name on the patrons\' wall'))),
);
$tier_prices = array(
    'individual' => array('USD' => 70, 'EUR' => 65, 'GBP' => 55, 'TRY' => 1800),
    'joint'      => array('USD' => 110, 'EUR' => 100, 'GBP' => 85, 'TRY' => 2800),
    'family'     => array('USD' => 130, 'EUR' => 120, 'GBP' => 100, 'TRY' => 3300),
    'patron'     => array('USD' => 500, 'EUR' => 460, 'GBP' => 400, 'TRY' => 12500),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A ticket's "… per visit" and a tier's "… a year" words.
$admission_price_text = function ($key) use ($admission_prices, $money) {
    return sprintf(lang('%s per visit'), $money($admission_prices[$key]));
};
$tier_price_text = function ($key) use ($tier_prices, $money) {
    return sprintf(lang('%s a year'), $money($tier_prices[$key]));
};

// The departments of the collection. The highlight list filters on them
// (?department=) in their own words.
$departments = array(
    'paintings'   => array('icon' => 'bi-easel', 'title' => lang('Paintings'),
        'summary' => lang('Portraits, landscapes and city views from five centuries.')),
    'sculpture'   => array('icon' => 'bi-person-standing', 'title' => lang('Sculpture'),
        'summary' => lang('Marble, bronze and wood, from temple fragments to modern forms.')),
    'paper'       => array('icon' => 'bi-pencil', 'title' => lang('Works on Paper'),
        'summary' => lang('Drawings, prints and watercolours, shown in turn to protect them from light.')),
    'decorative'  => array('icon' => 'bi-cup', 'title' => lang('Decorative Arts'),
        'summary' => lang('Ceramics, glass, textiles and furniture made for everyday life.')),
    'photography' => array('icon' => 'bi-camera', 'title' => lang('Photography'),
        'summary' => lang('The city and its people seen by photographers over a century and a half.')),
    'archaeology' => array('icon' => 'bi-hourglass-split', 'title' => lang('Archaeology'),
        'summary' => lang('Coins, pottery and tools found in the region, some thousands of years old.')),
);

// The sample highlights: the records the Collection Highlights form starts
// with. A period is words ("Late 19th century"), never a year the sample
// would have to keep right.
$objects = array(
    'harbour' => array('title' => lang('Harbour at Dusk'), 'department' => 'paintings',
        'maker' => lang('A painter of the city\'s harbour school'), 'period' => lang('Late 19th century'),
        'material' => lang('Oil on canvas'), 'gallery' => lang('Gallery 3'),
        'story' => lang('Fishing boats come home under a pink sky while the lamps of the quay are lit. The painting was the first work the museum was given and has hung in the same gallery ever since.'),
        'featured' => true),
    'dancer' => array('title' => lang('Resting Dancer'), 'department' => 'sculpture',
        'maker' => lang('A sculptor trained in the city\'s academy'), 'period' => lang('Early 20th century'),
        'material' => lang('Bronze'), 'gallery' => lang('Gallery 5'),
        'story' => lang('A dancer sits on a low step to tie her shoe. The sculptor cast it from a clay figure made in a single afternoon at the theatre.'),
        'featured' => true),
    'tile' => array('title' => lang('Tile Panel with Tulips'), 'department' => 'decorative',
        'maker' => lang('Workshops of the region'), 'period' => lang('16th century'),
        'material' => lang('Glazed ceramic'), 'gallery' => lang('Gallery 7'),
        'story' => lang('Twelve tiles in blue, turquoise and red once lined a fountain in a garden pavilion. The red was the hardest colour to fire and the pride of the workshop.'),
        'featured' => true),
    'market' => array('title' => lang('Morning Market'), 'department' => 'photography',
        'maker' => lang('A street photographer of the city'), 'period' => lang('Mid 20th century'),
        'material' => lang('Gelatin silver print'), 'gallery' => lang('In store'),
        'story' => lang('Traders set out fruit in the half light before the market opens. The photographer walked the same streets every morning for thirty years.'),
        'featured' => false),
    'map' => array('title' => lang('Map of the Old Town'), 'department' => 'paper',
        'maker' => lang('An unknown surveyor'), 'period' => lang('18th century'),
        'material' => lang('Ink and watercolour on paper'), 'gallery' => lang('In store'),
        'story' => lang('Every house, well and garden of the old town is drawn and coloured by hand. It is shown for a few months at a time to keep its colours bright.'),
        'featured' => false),
    'coin' => array('title' => lang('Silver Coin with an Owl'), 'department' => 'archaeology',
        'maker' => lang('A mint of the ancient city'), 'period' => lang('5th century BC'),
        'material' => lang('Silver'), 'gallery' => lang('Gallery 1'),
        'story' => lang('Found in a field outside the town with forty others, the coin travelled a long way in a merchant\'s purse before it was buried.'),
        'featured' => false),
);

// The sample exhibitions: the records the Exhibitions form starts with.
// The dates are words without a year; the museum writes its own dates in
// each record and moves an exhibition from Coming Soon to Now On to Past.
$exhibitions = array(
    'light' => array('title' => lang('Painting the Light'), 'status' => 'now',
        'when' => lang('Until the end of spring'), 'gallery' => lang('The Garden Galleries'), 'curator' => lang('Selin Aydın'),
        'summary' => lang('Sixty landscapes that chase the light of morning, noon and evening, from the museum and from collections abroad.'),
        'details' => array(lang('Sixty paintings and oil sketches'), lang('Twelve loans from abroad'), lang('An audio tour told by the curator'), lang('A ticketed exhibition: members free')),
        'featured' => true),
    'clay' => array('title' => lang('Made of Clay'), 'status' => 'now',
        'when' => lang('Until the summer holidays'), 'gallery' => lang('Gallery 7'), 'curator' => lang('Murat Şahin'),
        'summary' => lang('Four thousand years of pots, tiles and figures, and the potters of the region who still make them.'),
        'details' => array(lang('Pots, tiles and figures from the collection'), lang('Films of potters at work'), lang('A clay table for children'), lang('Free with admission')),
        'featured' => true),
    'faces' => array('title' => lang('Faces of the City'), 'status' => 'soon',
        'when' => lang('Opens in the autumn'), 'gallery' => lang('The Garden Galleries'), 'curator' => lang('Emma Lewis'),
        'summary' => lang('Portraits of the people who built, ran and loved the city, painted, carved and photographed.'),
        'details' => array(lang('Portraits in paint, stone and photographs'), lang('Stories collected from today\'s citizens'), lang('Member previews the week before it opens'), lang('A ticketed exhibition: members free')),
        'featured' => true),
    'paper_trail' => array('title' => lang('Paper Trail'), 'status' => 'soon',
        'when' => lang('Opens next winter'), 'gallery' => lang('The Print Room'), 'curator' => lang('Deniz Koç'),
        'summary' => lang('Drawings and prints rarely shown, chosen by the museum\'s volunteers.'),
        'details' => array(lang('Works on paper from the store'), lang('Labels written by the volunteers'), lang('Drawing sessions every Sunday'), lang('Free with admission')),
        'featured' => false),
    'river' => array('title' => lang('The River'), 'status' => 'past',
        'when' => lang('Shown last year'), 'gallery' => lang('The Garden Galleries'), 'curator' => lang('Selin Aydın'),
        'summary' => lang('Photographs, maps and paintings of the river that made the city.'),
        'details' => array(lang('Photographs, maps and paintings'), lang('A film made with the river\'s boatmen'), lang('The catalogue is still in the shop'), lang('Seen by over 80,000 visitors')),
        'featured' => false),
    'glass' => array('title' => lang('Fragile: Glass from the Collection'), 'status' => 'past',
        'when' => lang('Shown the year before last'), 'gallery' => lang('Gallery 7'), 'curator' => lang('Murat Şahin'),
        'summary' => lang('Bottles, lamps and bowls from ancient workshops to modern design.'),
        'details' => array(lang('Glass from three thousand years'), lang('A glassblower\'s workshop on Saturdays'), lang('Objects lent by local families'), lang('Free with admission')),
        'featured' => false),
);
// The states of an exhibition: the Status field's answers and the list's
// filter (?status=).
$exhibition_status_titles = array(
    'now'  => lang('Now On'),
    'soon' => lang('Coming Soon'),
    'past' => lang('Past Exhibitions'),
);

// The kinds of event. The event list filters on them (?kind=).
$event_kinds = array(
    'talk'     => array('icon' => 'bi-mic', 'title' => lang('Talks')),
    'tour'     => array('icon' => 'bi-signpost-split', 'title' => lang('Guided Tours')),
    'workshop' => array('icon' => 'bi-brush', 'title' => lang('Workshops')),
    'family'   => array('icon' => 'bi-balloon', 'title' => lang('Family Days')),
    'late'     => array('icon' => 'bi-moon-stars', 'title' => lang('Late Openings')),
    'concert'  => array('icon' => 'bi-music-note-beamed', 'title' => lang('Concerts')),
);

// The sample events: the records the Events form starts with. When is
// words, never a date; the price is words too, since most events come
// with the ticket or are free.
$events = array(
    'curator_talk' => array('title' => lang('Curator\'s Talk: Painting the Light'), 'kind' => 'talk',
        'when' => lang('Every first Thursday, 18:30'), 'place' => lang('The Lecture Theatre'), 'price' => lang('Free, booking needed'),
        'summary' => lang('The curator walks through the ideas behind the exhibition and answers your questions.'),
        'details' => array(lang('A 45-minute talk with pictures'), lang('Questions from the audience'), lang('The exhibition stays open until 20:00 for those who attend'), lang('Step-free access and a hearing loop')),
        'featured' => true),
    'highlights_tour' => array('title' => lang('Highlights Tour'), 'kind' => 'tour',
        'when' => lang('Every Saturday and Sunday, 11:00 and 14:00'), 'place' => lang('Meet at the information desk'), 'price' => lang('Free with admission'),
        'summary' => lang('An hour with a guide among the ten works every visitor should see.'),
        'details' => array(lang('A one-hour walk with a guide'), lang('Up to 20 people per tour'), lang('Folding stools to borrow'), lang('Tours in English on Sundays at 14:00')),
        'featured' => true),
    'print_workshop' => array('title' => lang('Printmaking Workshop'), 'kind' => 'workshop',
        'when' => lang('A Saturday each month, 10:00 to 13:00'), 'place' => lang('The Studio'), 'price' => lang('A small fee for materials'),
        'summary' => lang('Learn to cut and print a block of your own, inspired by the prints in the collection.'),
        'details' => array(lang('All materials and aprons provided'), lang('For adults; no experience needed'), lang('Up to 12 places'), lang('You take your prints home')),
        'featured' => true),
    'family_day' => array('title' => lang('Family Day: Animals in Art'), 'kind' => 'family',
        'when' => lang('A Sunday in the school holidays, 10:00 to 16:00'), 'place' => lang('Across the museum'), 'price' => lang('Free with admission'),
        'summary' => lang('Trails, stories and making for children of every age, looking for animals hidden in the galleries.'),
        'details' => array(lang('A trail through the galleries'), lang('Story time every hour'), lang('Making tables in the Studio'), lang('Buggy park at the entrance')),
        'featured' => false),
    'late_friday' => array('title' => lang('Friday Late'), 'kind' => 'late',
        'when' => lang('The last Friday of each month, 18:00 to 21:00'), 'place' => lang('Across the museum'), 'price' => lang('Free with admission'),
        'summary' => lang('Music, drinks and short talks in the galleries after dark.'),
        'details' => array(lang('A DJ in the courtyard'), lang('Ten-minute talks in the galleries'), lang('The café bar open until 21:00'), lang('Over 18s only')),
        'featured' => false),
    'quartet' => array('title' => lang('Strings in the Gallery'), 'kind' => 'concert',
        'when' => lang('A Sunday each season, 17:00'), 'place' => lang('Gallery 3'), 'price' => lang('Tickets on the door'),
        'summary' => lang('A string quartet plays among the paintings of the harbour school.'),
        'details' => array(lang('A one-hour concert'), lang('Seats for 80 listeners'), lang('Doors open at 16:30'), lang('Members get the best seats')),
        'featured' => false),
);
// An event's or an exhibition's details as the value of its Details field:
// one line per item.
$details_text = function ($list) {
    return implode("\n", $list);
};

// The learning sessions for schools (the learning page and the school
// visit request's Session list), by age.
$sessions = array(
    'early' => array('title' => lang('Stories and Colours'), 'ages' => lang('Ages 3 to 5'), 'length' => lang('45 minutes'),
        'summary' => lang('A story in front of a painting, then colours and shapes to find and make.')),
    'primary' => array('title' => lang('Museum Detectives'), 'ages' => lang('Ages 5 to 11'), 'length' => lang('90 minutes'),
        'summary' => lang('Pupils handle real objects, ask questions and solve the mystery of where they came from.')),
    'secondary' => array('title' => lang('Looking Closely'), 'ages' => lang('Ages 11 to 16'), 'length' => lang('90 minutes'),
        'summary' => lang('Drawing in the galleries and talking about how artists tell a story.')),
    'college' => array('title' => lang('Behind the Scenes'), 'ages' => lang('Ages 16 to 18'), 'length' => lang('2 hours'),
        'summary' => lang('A curator and a conservator show how the museum cares for and studies its collection.')),
);

// What a visitor finds in the building (the visit page).
$facilities = array(
    array('icon' => 'bi-cup-hot', 'title' => lang('Café'),
        'text' => lang('Lunch, cakes and coffee in the courtyard, open during museum hours.')),
    array('icon' => 'bi-bag', 'title' => lang('Museum Shop'),
        'text' => lang('Books, prints and gifts made with the collection in mind.')),
    array('icon' => 'bi-archive', 'title' => lang('Cloakroom'),
        'text' => lang('Free lockers for coats and bags; large bags cannot go into the galleries.')),
    array('icon' => 'bi-universal-access', 'title' => lang('Access'),
        'text' => lang('Step-free entrance and lifts to every floor, wheelchairs to borrow and a hearing loop.')),
    array('icon' => 'bi-balloon-heart', 'title' => lang('Families'),
        'text' => lang('Baby changing, a buggy park and free trails for children at the desk.')),
    array('icon' => 'bi-wifi', 'title' => lang('Free Wi-Fi'),
        'text' => lang('Across the building, with the guide to the galleries on your phone.')),
);

// How to get to the museum (the visit page).
$getting_there = array(
    array('icon' => 'bi-train-front', 'title' => lang('By metro'), 'text' => lang('Museum Square station, two minutes on foot.')),
    array('icon' => 'bi-bus-front', 'title' => lang('By bus'), 'text' => lang('Lines 12 and 40 stop at the museum gate.')),
    array('icon' => 'bi-bicycle', 'title' => lang('By bike'), 'text' => lang('Racks in the courtyard, free of charge.')),
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('A public car park behind the square; two places for disabled visitors at the gate.')),
);

// The museum's people (the about page). Fixed data, not a collection.
$people = array(
    array('name' => lang('Nur Erdem'), 'role' => lang('Director'),
        'bio' => lang('Leads the museum and its trust, and opened the galleries to schools free of charge.')),
    array('name' => lang('Selin Aydın'), 'role' => lang('Chief Curator'),
        'bio' => lang('Looks after the paintings and plans the exhibition programme.')),
    array('name' => lang('Murat Şahin'), 'role' => lang('Curator of Decorative Arts'),
        'bio' => lang('Studies ceramics and glass and works with the potters of the region.')),
    array('name' => lang('Emma Lewis'), 'role' => lang('Curator of Photography'),
        'bio' => lang('Builds the photography collection and the city\'s portrait archive.')),
    array('name' => lang('Deniz Koç'), 'role' => lang('Head of Learning'),
        'bio' => lang('Runs the school sessions, the family days and the volunteers.')),
    array('name' => lang('Hakan Yurt'), 'role' => lang('Head of Conservation'),
        'bio' => lang('Keeps every work safe, from the light in the galleries to the store.')),
);

// The museum in four figures (the home and the about page).
$figures = array(
    array('value' => '40,000', 'label' => lang('Works in the collection')),
    array('value' => '12', 'label' => lang('Galleries to explore')),
    array('value' => '250,000', 'label' => lang('Visitors a year')),
    array('value' => '6,000', 'label' => lang('Pupils on school visits a year')),
);

// What visitors say (the home and the support page).
$testimonials = array(
    array('name' => lang('Zeynep Arslan'), 'role' => lang('Member'),
        'quote' => lang('I come back to the same three paintings every month and still find something new in them.')),
    array('name' => lang('James Carter'), 'role' => lang('Teacher on a school visit'),
        'quote' => lang('The detectives session had my class talking about it for weeks. The team made it easy to organise.')),
    array('name' => lang('Burak Demir'), 'role' => lang('Visitor'),
        'quote' => lang('The Friday Late was the best evening out we have had in the city: music, wine and great art.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$exhibition_statuses = array_values($exhibition_status_titles);
$department_titles = array();
foreach ($departments as $d) $department_titles[] = $d['title'];
$event_kind_titles = array();
foreach ($event_kinds as $k) $event_kind_titles[] = $k['title'];
$event_titles = array();
foreach ($events as $e) $event_titles[] = $e['title'];
$session_titles = array();
foreach ($sessions as $s) $session_titles[] = $s['title'];
$school_session_choices = array_merge($session_titles, array(lang('Not sure yet')));
$place_counts = array('1', '2', '3', '4', '5', '6');
$year_groups = array(lang('Early years'), lang('Primary school'), lang('Secondary school'), lang('Sixth form or college'));
$visit_day_choices = array(lang('A weekday morning'), lang('A weekday afternoon'), lang('Any weekday'));
// The states of a place request and of a school visit request, set by the
// museum in the panel.
$booking_statuses = array(lang('Request received'), lang('Place confirmed'), lang('Waiting list'), lang('Cancelled'));
$school_visit_statuses = array(lang('Request received'), lang('Visit booked'), lang('Visit done'), lang('Not possible this time'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The words of a sample record's pick list answers.
$exhibition_status = function ($key) use ($exhibitions, $exhibition_status_titles) {
    return $exhibition_status_titles[$exhibitions[$key]['status']];
};
$object_department = function ($key) use ($objects, $departments) {
    return $departments[$objects[$key]['department']]['title'];
};
$event_kind = function ($key) use ($events, $event_kinds) {
    return $event_kinds[$events[$key]['kind']]['title'];
};

// The query string of a link that opens a list with a filter set
// (?status=, ?department=, ?kind=) or a form with a choice made (?event=,
// ?session=): the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the visit band above the footer unless the page is
// a form or an account page.
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

$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-body-tertiary', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'pmz-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pmz-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pmz-display h3 mb-0'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'mz-faq') use ($el, $para, $attr, $searchable) {
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


// The museum's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pmz-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: when the museum is open and the way to plan
// a visit. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'mb-0', lang('Notice'), array(
        $icon('bi-clock', 'text-primary me-1'),
        $span(lang('Open Tuesday to Sunday, late on Fridays'), '', lang('Text')),
        $link(lang('Plan Your Visit'), '{{page:visit}}', 'link-primary fw-semibold ms-1'),
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

// The header: the museum's name, the menu and the way to book a timed
// entry. The links to the current page are marked active on the server
// (smart active state), so one header serves every page. Tickets and
// membership are the shop's and are linked from the shop pages only, so
// the header stands on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('visit', lang('Visit')),
        array('exhibitions', lang('Exhibitions')),
        array('collection', lang('Collection')),
        array('events', lang('Events')),
        array('learning', lang('Learning')),
        array('support', lang('Support Us')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        // The bar opens out from 1200 px: from 992 px the brand, the links
        // and the button overflowed the container in Turkish.
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-bank2', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pmz-brand fs-4 text-truncate', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#mz-nav'),
                    $attr('aria-controls', 'mz-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-xl-auto me-xl-3 column-gap-xl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-ticket-perforated'),
                            $span(lang('Book Timed Entry'), '', lang('Link Text')),
                        ), array('href' => '{{page:book_online}}')),
                    )),
                ), array('id' => 'mz-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the museum in a sentence, how to reach it and
// its profiles, the exhibitions by state, the collection by department,
// the visitors' pages and the museum's pages; at the bottom the year, the
// site's name and the museum's standing.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $museum, $exhibition_statuses, $department_titles, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $exhibition_links = array($link(lang('All Exhibitions'), '{{page:exhibitions}}', $plain));
    foreach ($exhibition_statuses as $title) $exhibition_links[] = $link($title, '{{page:exhibitions}}?' . $query(array('status' => $title)), $plain);
    $department_links = array($link(lang('Collection Highlights'), '{{page:collection}}', $plain));
    foreach ($department_titles as $title) $department_links[] = $link($title, '{{page:collection}}?' . $query(array('department' => $title)), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Museum'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-bank2', 'text-primary'),
                        $span('{{site_name}}', 'pmz-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($museum['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($museum['address'], '{{page:visit}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($museum['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $museum['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($museum['email'], 'mailto:' . $museum['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($museum['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Exhibition Links'), array(
                    $heading('h2', lang('Exhibitions'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($exhibition_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Collection Links'), array(
                    $heading('h2', lang('Collection'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($department_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Visitor Links'), array(
                    $heading('h2', lang('Visit'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Plan Your Visit'), '{{page:visit}}', $plain),
                        $link(lang('Book Timed Entry'), '{{page:book_online}}', $plain),
                        $link(lang('Events'), '{{page:events}}', $plain),
                        $link(lang('Learning'), '{{page:learning}}', $plain),
                        $link(lang('Book a School Visit'), '{{page:school_visit}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Museum'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Support Us'), '{{page:support}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($museum['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The visit band: a full-width band in the palette's colour with the ways
// to plan a visit and to book a timed entry. Shared, so every page that
// ends with it carries the same call.
$visit_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $museum) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Come and see it for yourself'), 'pmz-display h2 mb-2'),
                    $para($museum['hours'] . '. ' . $museum['free'] . '.', 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book Timed Entry'), '{{page:book_online}}', 'btn btn-secondary btn-lg px-4'),
                        $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The opening hours, day by day, as a list of terms and values (the visit
// page and the home), with the last entry under them.
$hours_list = function ($class = '') use ($el, $para, $opening_hours, $museum) {
    $rows = array();
    foreach ($opening_hours as $h) {
        $rows[] = $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom', lang('Opening Hours Row'), array(
            $el('dt', 'fw-semibold', lang('Days'), array(), array('text' => $h['days'])),
            $el('dd', 'pmz-price mb-0 text-end', lang('Hours'), array(), array('text' => $h['hours'])),
        ));
    }
    return $el('div', $class, lang('Opening Hours'), array(
        $el('dl', 'mb-2', lang('Opening Hours List'), $rows),
        $para($museum['last'], 'small text-body-secondary mb-0'),
    ));
};

// The state of an exhibition as a badge, bound to its Status field.
$status_badge = function ($text, $class = 'badge rounded-pill text-bg-primary') use ($span, $bind) {
    return $span($text, $class, lang('Status'), $bind('status'));
};

// The facts of an exhibition in one list: when, where and who chose the
// works, each bound to the Exhibitions form's field of the same name.
$exhibition_facts = function ($sample_key = 'light', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $exhibitions) {
    $x = $exhibitions[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Exhibition Facts'), array(
        $fact('bi-calendar-range', $x['when'], 'when', lang('When')),
        $fact('bi-door-open', $x['gallery'], 'gallery', lang('Gallery')),
        $fact('bi-person-badge', $x['curator'], 'curator', lang('Curator')),
    ));
};

// An exhibition's card of the lists (the home and the exhibition list):
// the photo, the state, the title linking the exhibition's page, when,
// where and the summary. Every value is bound to the Exhibitions form's
// field of the same name; the sample fills the canvas.
$exhibition_card = function ($sample_key = 'light') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $exhibitions, $exhibition_status, $status_badge, $photo, $attr) {
    $x = $exhibitions[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pmz-lift', lang('Exhibition Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('exhibition-' . $sample_key, 800, 600), $x['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'mb-2', lang('Status'), array($status_badge($exhibition_status($sample_key)))),
                $el('h3', 'pmz-display h4 position-static mb-2', lang('Exhibition Title'), array(
                    $link($x['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-start gap-1 small fw-semibold mb-1', lang('When'), array(
                    $icon('bi-calendar-range', 'text-primary'),
                    $span($x['when'], '', lang('When'), $bind('when')),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-2', lang('Gallery'), array(
                    $icon('bi-door-open', 'text-primary'),
                    $span($x['gallery'], '', lang('Gallery'), $bind('gallery')),
                )),
                $para($x['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// The facts of a highlight in one list, each bound to the Collection
// Highlights form's field of the same name.
$object_facts = function ($sample_key = 'harbour', $class = 'list-unstyled small mb-0') use ($el, $span, $bind, $objects, $object_department) {
    $o = $objects[$sample_key];
    $fact = function ($label, $text, $token) use ($el, $span, $bind) {
        return $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $label, array(
            $span($label, 'text-body-secondary', lang('Label')),
            $span($text, 'fw-semibold text-end', $label, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Object Facts'), array(
        $fact(lang('Artist or Maker'), $o['maker'], 'maker'),
        $fact(lang('Period'), $o['period'], 'period'),
        $fact(lang('Material'), $o['material'], 'material'),
        $fact(lang('Department'), $object_department($sample_key), 'department'),
        $fact(lang('Where to see it'), $o['gallery'], 'gallery'),
    ));
};

// A highlight's card of the lists (the home and the collection list): the
// photo, the department, the title linking the object's page, the maker
// and the period. Bound to the Collection Highlights form's fields.
$object_card = function ($sample_key = 'harbour') use ($el, $span, $link, $image, $col, $bind, $objects, $object_department, $photo, $attr) {
    $o = $objects[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-transparent pmz-lift', lang('Object Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary mb-3', lang('Picture Frame'), array(
                $image($photo('object-' . $sample_key, 600, 600), $o['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $span($object_department($sample_key), 'pmz-eyebrow d-block small text-uppercase fw-semibold text-primary mb-1', lang('Department'), $bind('department')),
            $el('h3', 'pmz-display h5 position-static mb-1', lang('Object Title'), array(
                $link($o['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $span($o['maker'], 'd-block small text-body-secondary', lang('Maker'), $bind('maker')),
            $span($o['period'], 'd-block small text-body-secondary', lang('Period'), $bind('period')),
        )),
    ), '6', '4', '', '12');
};

// A department's card (the home and the collection page): the icon, the
// title and a sentence; it opens the highlights of that department.
$department_card = function ($key) use ($el, $para, $link, $col, $icon, $departments, $query) {
    $d = $departments[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-body-tertiary p-4 pmz-lift', lang('Department Card'), array(
            $icon($d['icon'], 'fs-3 text-primary mb-2'),
            $el('h3', 'pmz-display h5 position-static mb-1', lang('Department Name'), array(
                $link($d['title'], '{{page:collection}}?' . $query(array('department' => $d['title'])), 'stretched-link link-body-emphasis text-decoration-none'),
            )),
            $para($d['summary'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// The facts of an event in one list: kind, when, where and the price, each
// bound to the Events form's field of the same name.
$event_facts = function ($sample_key = 'curator_talk', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $events, $event_kind) {
    $e = $events[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Event Facts'), array(
        $fact('bi-calendar-event', $e['when'], 'when', lang('When')),
        $fact('bi-geo-alt', $e['place'], 'place', lang('Place')),
        $fact('bi-ticket', $e['price'], 'price', lang('Price')),
        $fact('bi-tag', $event_kind($sample_key), 'kind', lang('Kind')),
    ));
};

// An event's card of the lists (the home and the event list): the photo,
// the kind, the title linking the event's page, when, where, the price and
// the summary, bound to the Events form's fields.
$event_card = function ($sample_key = 'curator_talk') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $events, $event_kind, $photo, $attr) {
    $e = $events[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pmz-lift', lang('Event Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('event-' . $sample_key, 800, 450), $e['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_kind($sample_key), 'pmz-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Kind'), $bind('kind')),
                $el('h3', 'pmz-display h5 position-static mb-2', lang('Event Title'), array(
                    $link($e['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-start gap-1 small fw-semibold mb-1', lang('When'), array(
                    $icon('bi-calendar-event', 'text-primary'),
                    $span($e['when'], '', lang('When'), $bind('when')),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-1', lang('Place'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($e['place'], '', lang('Place'), $bind('place')),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-2', lang('Price'), array(
                    $icon('bi-ticket', 'text-primary'),
                    $span($e['price'], '', lang('Price'), $bind('price')),
                )),
                $para($e['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// An admission ticket (the visit page): who it is for, the price and what
// it includes. It does not link the shop: the visit page stands without it.
$admission_card = function ($key) use ($el, $heading, $para, $span, $col, $admission, $admission_price_text) {
    $a = $admission[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Ticket Card'), array(
            $heading('h3', $a['title'], 'pmz-display h5 mb-1'),
            $para($a['who'], 'small text-body-secondary mb-3'),
            $span($admission_price_text($key), 'pmz-price d-block h4 fw-bold text-primary mb-2', lang('Price')),
            $para($a['includes'], 'small mb-0'),
        )),
    ), '6', '4');
};

// A membership tier (the support page): who it is for, the price a year
// and what it gives.
$tier_card = function ($key) use ($el, $heading, $para, $span, $col, $check_list, $tiers, $tier_price_text) {
    $t = $tiers[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Membership Card'), array(
            $heading('h3', $t['title'], 'pmz-display h5 mb-1'),
            $para($t['who'], 'small text-body-secondary mb-3'),
            $span($tier_price_text($key), 'pmz-price d-block h4 fw-bold text-primary mb-3', lang('Price')),
            $check_list($t['benefits'], 'list-unstyled small mb-0'),
        )),
    ), '6', '3');
};

// A learning session (the learning page): the ages, the title, the length
// and a sentence, with the way to request it (?session=).
$session_card = function ($key) use ($el, $heading, $para, $span, $link, $col, $sessions, $query) {
    $s = $sessions[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Session Card'), array(
            $span($s['ages'], 'pmz-eyebrow d-block small text-uppercase fw-semibold text-primary mb-1', lang('Ages')),
            $heading('h3', $s['title'], 'pmz-display h5 mb-1'),
            $para($s['length'], 'small fw-semibold mb-2'),
            $para($s['summary'], 'small text-body-secondary mb-3'),
            $link(lang('Request this session'), '{{page:school_visit}}?' . $query(array('session' => $s['title'])), 'btn btn-sm btn-outline-primary mt-auto align-self-start'),
        )),
    ), '6', '3');
};

// A member of the museum's team (the about page): the portrait, the name,
// the role and a sentence.
$person_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $people) {
    $t = $people[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm text-center p-4', lang('Staff Member'), array(
            $image($portrait($t['name'], 240), $t['name'], 'rounded-circle mx-auto mb-3', array('width' => '120', 'height' => '120', '_attrs' => array($attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'pmz-display h5 mb-1'),
            $para($t['role'], 'small fw-semibold text-primary mb-2'),
            $para($t['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// What a visitor says: the quote, the visitor and who they are.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $icon, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Visitor Quote'), array(
            $icon('bi-quote', 'fs-2 text-primary lh-1 mb-2'),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pmz-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Visitor Details'), array(
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
                $item('staff_bookings', lang('Event Bookings')),
                $item('staff_school_visits', lang('School Visits')),
                $item('exhibition_new', lang('New Exhibition')),
                $item('object_new', lang('New Object')),
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
    return $agree($id, 'consent', lang('I would like to receive the museum\'s news, exhibition openings and event invitations by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// school visit request may carry a risk assessment or a list of access
// needs, so the default accepts PDFs, office files, text and pictures; one
// that takes only pictures is a collection's image kind (an exhibition's,
// an object's or an event's photo).
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
                    $link(lang('How we hang a new exhibition'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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


// ── The Museum blocks ───────────────────────────────────────────────────
// The home, the exhibitions page and the collection page place the Museum
// blocks (exhibition hero, look closer, highlights trail); their
// pg-museum- rules ship in the template's User Styles.

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

// The Museum: Exhibition Hero block: the exhibition $key as a poster. Its
// state as the eyebrow, its title ($tag: the home's h1, an h2 under a page
// header), the summary, a dl.pg-museum-facts of when, where, the curator
// and the admission, and the buttons; under them
// the exhibitions $others (keys) when there are any; beside it the picture
// in a portrait pg-museum-poster frame. The words rise, the poster fades,
// the others follow, on load. $buttons: the links under the facts.
$exhibition_hero = function ($key, $others, $tag, $id, $buttons, $class = 'py-5 overflow-hidden') use ($el, $heading, $para, $image, $span, $container, $row, $col, $fx, $attr, $searchable, $photo, $exhibitions, $exhibition_status_titles) {
    $e = $exhibitions[$key];
    $fact = function ($label, $value) use ($el, $span) {
        return $el('div', '', $label, array(
            $el('dt', '', lang('Label'), array(), array('text' => $label)),
            $el('dd', '', $label, array($span($value, '', lang('Value')))),
        ));
    };
    $words = array(
        $para($exhibition_status_titles[$e['status']], 'pmz-eyebrow text-uppercase fw-semibold text-primary mb-3'),
        $heading($tag, $e['title'], 'pmz-display ' . ($tag === 'h1' ? 'display-4' : 'display-6') . ' mb-3'),
        $para($e['summary'], 'lead text-body-secondary mb-4'),
        $el('dl', 'pg-museum-facts mb-4', lang('Facts'), array(
            $fact(lang('When'), $e['when']),
            $fact(lang('Where'), $e['gallery']),
            $fact(lang('Curator'), $e['curator']),
            $fact(lang('Admission'), end($e['details'])),
        )),
        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
    );
    $columns = array($fx($col($words, '', '7'), array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700)));
    $columns[] = $fx($col(array(
        $el('figure', 'mb-0', lang('Exhibition Picture'), array(
            $el('div', 'pg-museum-poster rounded-3 shadow', lang('Picture'), array(
                $image($photo('exhibition-' . $key, 960, 1200), $e['summary'], '', $tag === 'h1' ? array() : array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('figcaption', 'small text-body-secondary mt-2', lang('Photo Caption'), array(), array('text' => $e['title'] . ' · ' . $e['gallery'])),
        )),
    ), '', '5'), array('effect' => 'fade', 'trigger' => 'load', 'duration' => 1000, 'delay' => 200));
    $more = array();
    if ($others) {
        $items = array();
        foreach ($others as $k) {
            $o = $exhibitions[$k];
            $items[] = $el('li', 'py-3 border-bottom', $o['title'], array(
                $heading('h3', $o['title'], 'h6 fw-semibold mb-1'),
                $para($o['when'] . ' · ' . $o['gallery'], 'small text-body-secondary mb-0'),
            ));
        }
        $more[] = $fx($el('div', 'mt-5', lang('Also On'), array(
            $heading($tag === 'h1' ? 'h2' : 'h3', lang('Also on'), 'small text-uppercase fw-semibold text-body-secondary border-bottom pb-2 mb-0'),
            $el('ul', 'list-unstyled row row-cols-1 row-cols-md-2 g-0 column-gap-md-4 mb-0', lang('Exhibitions'), $items),
        )), array('effect' => 'slide-up', 'trigger' => 'load', 'delay' => 400));
    }
    return $el('section', $class, lang('Museum (Exhibition Hero)'), array(
        $container(array_merge(array(
            $row($columns, '4', '', 'g-lg-5 align-items-center'),
        ), $more)),
    ), $searchable($id, lang('exhibition, now on, tickets, curator, admission')));
};

// The details of a work to look closer at (the collection page): the
// close-up's seed, its alt text, the name and why to look.
$look_details = array(
    'harbour' => array(
        array('detail-sky', lang('A pink evening sky over the masts'), lang('The sky'),
            lang('The painter laid the pink over a grey ground, so the evening seems to glow from behind the clouds.')),
        array('detail-lamps', lang('Small dots of yellow along the quay'), lang('The lamps'),
            lang('A single touch of yellow for each lamp: step back and the quay lights up.')),
        array('detail-boat', lang('A fishing boat with a red sail'), lang('The red sail'),
            lang('The only red in the painting leads your eye to the boat coming home last.')),
    ),
);

// The Museum: Look Closer block: the work $key with its wall label
// (pg-museum-label: maker, title, period, material, gallery) beside its
// title, its story and an ol of numbered details (pg-museum-no), each a
// close-up, a name and a line; the way to its department's other works.
// The work fades and the details rise on view. A search section under $id.
$look_closer = function ($key, $id) use ($el, $heading, $para, $link, $image, $span, $container, $row, $col, $fx, $rise, $attr, $searchable, $query, $photo, $objects, $departments, $look_details) {
    $o = $objects[$key];
    $dep = $departments[$o['department']];
    $lazy = array('_attrs' => array($attr('loading', 'lazy')));
    $details = array();
    foreach ($look_details[$key] as $n => $d) {
        $details[] = $el('li', 'd-flex gap-3 mb-4', $d[2], array(
            $span((string)($n + 1), 'pg-museum-no', lang('Number')),
            $el('div', 'flex-grow-1', lang('Detail Text'), array(
                $el('div', 'ratio ratio-16x9 rounded-3 overflow-hidden bg-body-tertiary mb-2', lang('Close-up'), array(
                    $image($photo($d[0], 640, 360), $d[1], 'object-fit-cover', $lazy),
                )),
                $heading('h3', $d[2], 'h6 fw-semibold mb-1'),
                $para($d[3], 'small text-body-secondary mb-0'),
            )),
        ));
    }
    return $el('section', 'py-5', lang('Museum (Look Closer)'), array(
        $container(array(
            $row(array(
                $fx($col(array(
                    $el('figure', 'mb-0', lang('Work Picture'), array(
                        $el('div', 'ratio ratio-4x3 rounded-3 overflow-hidden bg-body-tertiary mb-3', lang('Picture'), array(
                            $image($photo('object-' . $key . '-large', 1400, 1050), $o['story'], 'object-fit-cover', $lazy),
                        )),
                        $el('figcaption', 'pg-museum-label small', lang('Wall Label'), array(
                            $para($o['maker'], 'fw-semibold'),
                            $para($o['title'], 'fst-italic'),
                            $para($o['period']),
                            $para($o['material'], 'text-body-secondary'),
                            $para($o['gallery'], 'text-body-secondary mb-0'),
                        )),
                    )),
                ), '', '7'), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 900)),
                $col(array(
                    $para(lang('Look closer'), 'pmz-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', $o['title'], 'pmz-display h2 mb-3'),
                    $para($o['story'], 'text-body-secondary mb-4'),
                    $rise($el('ol', 'list-unstyled mb-4', lang('Details'), $details), 120),
                    $link($dep['title'], '{{page:collection}}?' . $query(array('department' => $dep['title'])), 'btn btn-outline-primary px-4'),
                ), '', '5'),
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable($id, lang('art, paintings, sculpture, objects, artworks, archive')));
};

// The Museum: Highlights Trail block: the works $keys in walking order on
// an ol.pg-museum-trail, each a numbered stop (pg-museum-no) with its
// picture, title, maker and period, and its room as an icon and a word;
// over them the trail's title, its length, where it starts and the guided
// tours. The header fades and the stops rise on view. A search section
// under $id.
$highlights_trail = function ($keys, $id, $class = 'py-5') use ($el, $heading, $para, $link, $image, $icon, $span, $container, $fx, $rise, $attr, $searchable, $query, $photo, $objects, $event_kinds) {
    $stops = array();
    foreach (array_values($keys) as $n => $k) {
        $o = $objects[$k];
        $stops[] = $el('li', 'col', $o['title'], array(
            $span((string)($n + 1), 'pg-museum-no mb-3', lang('Number')),
            $el('div', 'ratio ratio-4x3 rounded-3 overflow-hidden bg-body-tertiary mb-3', lang('Picture'), array(
                $image($photo('trail-' . $k, 800, 600), $o['title'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $heading('h3', $o['title'], 'pmz-display h5 mb-1'),
            $para($o['maker'] . ' · ' . $o['period'], 'small text-body-secondary mb-2'),
            $el('div', 'badge rounded-pill bg-primary-subtle text-primary-emphasis d-inline-flex align-items-center gap-1', lang('Room'), array(
                $icon('bi-geo-alt'),
                $span($o['gallery'], '', lang('Room')),
            )),
        ));
    }
    $detail = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('div', 'd-flex align-items-center gap-2 small', $name, array($icon($icon_name, 'text-primary'), $span($text, '', lang('Text'))));
    };
    return $el('section', $class, lang('Museum (Highlights Trail)'), array(
        $container(array(
            $fx($el('div', 'row g-4 align-items-end mb-5', lang('Trail Header'), array(
                $el('div', 'col-lg-7', lang('Trail Title'), array(
                    $para(lang('Highlights trail'), 'pmz-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                    $heading('h2', lang('Four works not to miss'), 'pmz-display h2 mb-3'),
                    $para(lang('A walk through the galleries from the oldest work to the newest. Follow the numbers on the floor plan you get at the desk.'), 'text-body-secondary mb-0'),
                )),
                $el('div', 'col-lg-5 d-flex flex-wrap gap-3 justify-content-lg-end align-items-center', lang('Trail Details'), array(
                    $detail('bi-clock', lang('About an hour'), lang('Length')),
                    $detail('bi-signpost-split', lang('Meet at the information desk'), lang('Start')),
                    $link(lang('Highlights Tour'), '{{page:events}}?' . $query(array('kind' => $event_kinds['tour']['title'])), 'btn btn-primary px-4'),
                )),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800)),
            $rise($el('ol', 'pg-museum-trail list-unstyled row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4 mb-0', lang('Stops'), $stops), 120),
        )),
    ), $searchable($id, lang('highlights, must see, tour, route, galleries, masterpieces')));
};
