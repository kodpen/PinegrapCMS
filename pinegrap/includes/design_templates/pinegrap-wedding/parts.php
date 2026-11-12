<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Wedding Planner and Venue", part 1 of its files: the
 * node builders, the planner's own data (the venue's spaces, the seasons,
 * the planning services and venue packages with their prices, the sample
 * Real Weddings, the venue gallery, the planning timeline, why couples
 * choose the planner, what they say and the answers of the forms' pick
 * lists) and the parts every page repeats (account bar, header, footer,
 * date band, service, package, space, wedding and couple cards, timeline
 * stages, page shells, section headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-wedding.php puts them together.
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
// every opening until the planner puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-wedding-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e8efe9/3f5a46?text=' . rawurlencode($initials !== '' ? $initials : 'W');
};

// ── The planner and its venue: spaces, services, packages, weddings ────

// The planner itself. The about page tells its story; the footer, the date
// band and the contact lines use the address, phone and opening hours.
$planner = array(
    'tagline' => lang('Wedding planning and a garden venue by the sea, for one wedding a day'),
    'story'   => lang('We began by planning the weddings of our friends and fell in love with the work. Years later we found an old olive estate by the sea and made it the venue we had always wanted for our couples: one wedding a day, one team from the first call to the last dance.'),
    'address' => lang('Olive Estate, Coast Road 14'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'hello@example.com',
    'hours'   => lang('Tuesday to Sunday, 10:00 to 19:00; venue visits by appointment'),
    'promise' => lang('One wedding a day at the venue, so the day is only yours'),
);

// The venue's spaces, in the order the venue page shows them. 'seated' and
// 'standing' are the most guests the space takes.
$spaces = array(
    'garden'   => array('icon' => 'bi-tree', 'title' => lang('The Olive Garden'), 'seated' => 180, 'standing' => 250,
        'use'  => lang('Ceremony and dinner under the trees'),
        'text' => lang('A lawn among old olive trees, with a stone arch for the ceremony and long tables under string lights.')),
    'hall'     => array('icon' => 'bi-buildings', 'title' => lang('The Grand Hall'), 'seated' => 220, 'standing' => 300,
        'use'  => lang('Dinner and dancing in any weather'),
        'text' => lang('The estate\'s old press house: high beams, tall windows to the garden and a dance floor for the whole night.')),
    'terrace'  => array('icon' => 'bi-water', 'title' => lang('The Sea Terrace'), 'seated' => 80, 'standing' => 120,
        'use'  => lang('Ceremony with a view and drinks at sunset'),
        'text' => lang('A stone terrace above the bay, made for a ceremony at golden hour and the drinks after it.')),
    'orangery' => array('icon' => 'bi-flower1', 'title' => lang('The Orangery'), 'seated' => 40, 'standing' => 60,
        'use'  => lang('Small weddings and the morning after'),
        'text' => lang('A glass house among lemon trees, for an intimate wedding or the brunch the day after.')),
);

// The seasons, the Real Weddings list's second filter. Each answer's value
// is its own words, so a filter link (?season=) reads as it shows.
$seasons = array(
    'spring' => array('icon' => 'bi-flower2', 'title' => lang('Spring')),
    'summer' => array('icon' => 'bi-sun', 'title' => lang('Summer')),
    'autumn' => array('icon' => 'bi-tree-fill', 'title' => lang('Autumn')),
    'winter' => array('icon' => 'bi-snow', 'title' => lang('Winter')),
);

// The planning services, each with what it includes. A service's price is
// its starting price; the proposal after the consultation gives the rest.
$services = array(
    'full'    => array('icon' => 'bi-journal-check', 'title' => lang('Full Planning'),
        'text'     => lang('From the first idea to the last guest home: we plan, book and run every part of the wedding with you.'),
        'includes' => array(lang('Unlimited planning meetings'), lang('Budget and supplier planning'), lang('Design, styling and the day\'s running order'), lang('Our team on the day from setup to close')),
        'featured' => true),
    'partial' => array('icon' => 'bi-puzzle', 'title' => lang('Partial Planning'),
        'text'     => lang('You have begun and want a hand: we take over the parts you choose and bring them together.'),
        'includes' => array(lang('Six planning meetings'), lang('Supplier advice and bookings you choose'), lang('The day\'s running order'), lang('Our team on the day from setup to close')),
        'featured' => false),
    'day'     => array('icon' => 'bi-clock-history', 'title' => lang('Day Coordination'),
        'text'     => lang('You have planned it all: we take the plan from you a month before and run the day so you can enjoy it.'),
        'includes' => array(lang('Two planning meetings in the last month'), lang('Calls to every supplier the week before'), lang('A rehearsal the day before'), lang('A coordinator from setup to close')),
        'featured' => false),
);
$service_prices = array(
    'full'    => array('USD' => 6500, 'EUR' => 6000, 'GBP' => 5200, 'TRY' => 240000),
    'partial' => array('USD' => 3500, 'EUR' => 3200, 'GBP' => 2800, 'TRY' => 130000),
    'day'     => array('USD' => 1500, 'EUR' => 1400, 'GBP' => 1200, 'TRY' => 55000),
);

// The venue packages, priced per guest. 'space' is the space the package
// usually takes; 'min' and 'max' the guests it is made for.
$packages = array(
    'intimate' => array('title' => lang('Intimate'), 'space' => 'orangery', 'min' => 20, 'max' => 60,
        'text'     => lang('A small wedding in the Orangery or on the terrace, with a long table for everyone.'),
        'includes' => array(lang('The space for the afternoon and evening'), lang('A welcome drink and a three-course dinner'), lang('Flowers for the table and the ceremony'), lang('A coordinator on the day')),
        'featured' => false),
    'classic'  => array('title' => lang('Classic'), 'space' => 'garden', 'min' => 60, 'max' => 180,
        'text'     => lang('Ceremony in the Olive Garden, dinner under the trees and dancing in the Grand Hall.'),
        'includes' => array(lang('Two spaces for the whole day'), lang('Drinks at sunset, a four-course dinner and the cake'), lang('Flowers, candles and lighting'), lang('A DJ until midnight'), lang('A coordinator and the venue team')),
        'featured' => true),
    'grand'    => array('title' => lang('Grand'), 'space' => 'hall', 'min' => 120, 'max' => 300,
        'text'     => lang('The whole estate for the weekend: the welcome party, the wedding and the brunch the day after.'),
        'includes' => array(lang('The whole estate for the weekend'), lang('A welcome party the evening before'), lang('Drinks, a five-course dinner, the cake and a late-night table'), lang('A band and a DJ until two'), lang('Brunch in the Orangery the morning after')),
        'featured' => false),
);
$package_prices = array(
    'intimate' => array('USD' => 120, 'EUR' => 110, 'GBP' => 95, 'TRY' => 4400),
    'classic'  => array('USD' => 150, 'EUR' => 140, 'GBP' => 120, 'TRY' => 5500),
    'grand'    => array('USD' => 210, 'EUR' => 195, 'GBP' => 165, 'TRY' => 7700),
);

// The deposit that holds a date once the proposal is agreed (the shop
// sells it per package, with the shop).
$deposit = array('USD' => 1000, 'EUR' => 900, 'GBP' => 800, 'TRY' => 36000);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A package's "From … per guest" words and a service's "From …".
$package_price_text = function ($key) use ($package_prices, $money) {
    return sprintf(lang('From %s per guest'), $money($package_prices[$key]));
};
$service_price_text = function ($key) use ($service_prices, $money) {
    return sprintf(lang('Starting at %s'), $money($service_prices[$key]));
};
// A space's capacity in words: "180 seated, 250 standing".
$capacity_text = function ($key) use ($spaces) {
    return sprintf(lang('%1$d seated, %2$d standing'), (int)$spaces[$key]['seated'], (int)$spaces[$key]['standing']);
};
// A package's guests in words: "60 to 180 guests".
$guests_text = function ($key) use ($packages) {
    return sprintf(lang('%1$d to %2$d guests'), (int)$packages[$key]['min'], (int)$packages[$key]['max']);
};

// The sample Real Weddings: the records the Real Weddings form starts
// with. 'suppliers' are the people who made the day, one line each.
$weddings = array(
    'selin-emre'   => array('couple' => lang('Selin and Emre'), 'space' => 'garden', 'season' => 'summer', 'guests' => 160,
        'summary'   => lang('A long summer evening under the olive trees, with a table of family recipes and dancing until the stars came out.'),
        'story'     => lang('Selin and Emre met at a friend\'s wedding and wanted theirs to feel the same: relaxed, warm and full of people they love. The ceremony was under the stone arch at six; dinner was their grandmothers\' recipes on long tables; and the dancing moved to the Grand Hall when the night grew cool.'),
        'quote'     => lang('Everyone still talks about the dinner under the trees. We did not worry about a single thing all day.'),
        'suppliers' => array(array(lang('Photography'), lang('Light and Lens Studio')), array(lang('Flowers'), lang('Wild Stem Florals')), array(lang('Music'), lang('The Evening Quartet'))),
        'featured'  => true),
    'hannah-james' => array('couple' => lang('Hannah and James'), 'space' => 'terrace', 'season' => 'autumn', 'guests' => 70,
        'summary'   => lang('A ceremony at golden hour on the Sea Terrace and a candlelit dinner for seventy in the Orangery.'),
        'story'     => lang('Hannah and James flew in from abroad with their closest friends and family. We planned everything over video calls, from the menu tasting to the music, and met them for the first time three days before the wedding.'),
        'quote'     => lang('We planned a wedding in another country without a single worry. The terrace at sunset was everything.'),
        'suppliers' => array(array(lang('Photography'), lang('North Light Photography')), array(lang('Cake'), lang('Sugar and Salt Bakery')), array(lang('Hair and make-up'), lang('Bloom Beauty'))),
        'featured'  => true),
    'zeynep-can'   => array('couple' => lang('Zeynep and Can'), 'space' => 'hall', 'season' => 'winter', 'guests' => 220,
        'summary'   => lang('A winter wedding in the Grand Hall: candles, deep green and a band that kept everyone dancing.'),
        'story'     => lang('Zeynep and Can wanted a winter wedding with a big family and a big party. The Grand Hall was dressed in deep green and candlelight, the band played until two, and soup was served at midnight.'),
        'quote'     => lang('A winter wedding felt brave, but the hall was so warm and beautiful that nobody wanted to leave.'),
        'suppliers' => array(array(lang('Photography'), lang('Light and Lens Studio')), array(lang('Music'), lang('Night Owls Band')), array(lang('Lighting'), lang('Glow Event Lighting'))),
        'featured'  => true),
    'lucia-marco'  => array('couple' => lang('Lucía and Marco'), 'space' => 'orangery', 'season' => 'spring', 'guests' => 40,
        'summary'   => lang('Forty guests, a glass house full of lemon trees and a long spring lunch that lasted until evening.'),
        'story'     => lang('Lucía and Marco wanted a small wedding where they could speak with every guest. We set one long table in the Orangery, the ceremony was among the lemon trees, and lunch went on until the light turned gold.'),
        'quote'     => lang('Small, simple and exactly us. Thank you for every little detail.'),
        'suppliers' => array(array(lang('Photography'), lang('North Light Photography')), array(lang('Flowers'), lang('Wild Stem Florals')), array(lang('Stationery'), lang('Paper Garden Press'))),
        'featured'  => false),
);
// A wedding's suppliers as the value of its Suppliers field: one line
// each, the role and the name.
$suppliers_text = function ($key) use ($weddings) {
    $lines = array();
    foreach ($weddings[$key]['suppliers'] as $s) $lines[] = sprintf(lang('%1$s: %2$s'), $s[0], $s[1]);
    return implode("\n", $lines);
};

// The venue gallery: a picture per line, each in one of the spaces.
$gallery = array(
    array('seed' => 'arch', 'space' => 'garden', 'caption' => lang('The stone arch in the Olive Garden')),
    array('seed' => 'tables', 'space' => 'garden', 'caption' => lang('Long tables under the olive trees')),
    array('seed' => 'hall', 'space' => 'hall', 'caption' => lang('The Grand Hall dressed for dinner')),
    array('seed' => 'dance', 'space' => 'hall', 'caption' => lang('The first dance in the Grand Hall')),
    array('seed' => 'terrace', 'space' => 'terrace', 'caption' => lang('A ceremony on the Sea Terrace at sunset')),
    array('seed' => 'bay', 'space' => 'terrace', 'caption' => lang('The bay from the terrace')),
    array('seed' => 'orangery', 'space' => 'orangery', 'caption' => lang('A long table in the Orangery')),
    array('seed' => 'lemons', 'space' => 'orangery', 'caption' => lang('Lemon trees and candles')),
);

// The planning timeline, from a year before to the day. Each stage has a
// few things to do; no stage names a month, only the time left.
$timeline = array(
    array('icon' => 'bi-chat-heart', 'when' => lang('Twelve months before'), 'title' => lang('Dream and decide'),
        'tasks' => array(lang('Agree on a budget and a rough guest count'), lang('Visit the venue and choose a date'), lang('Book your planner and hold the date'))),
    array('icon' => 'bi-camera', 'when' => lang('Nine months before'), 'title' => lang('Book the people who make the day'),
        'tasks' => array(lang('Book the photographer and the music'), lang('Send save-the-dates'), lang('Start looking for the dress and the suit'))),
    array('icon' => 'bi-palette', 'when' => lang('Six months before'), 'title' => lang('Shape the look'),
        'tasks' => array(lang('Choose flowers, colours and styling'), lang('Book hair and make-up'), lang('Plan where guests will stay'))),
    array('icon' => 'bi-envelope-heart', 'when' => lang('Three months before'), 'title' => lang('Invite and taste'),
        'tasks' => array(lang('Send the invitations'), lang('Taste the menu and choose the cake'), lang('Plan the ceremony and its words'))),
    array('icon' => 'bi-list-check', 'when' => lang('One month before'), 'title' => lang('Fix the details'),
        'tasks' => array(lang('Confirm the final guest count'), lang('Make the seating plan'), lang('Agree the running order of the day'))),
    array('icon' => 'bi-balloon-heart', 'when' => lang('The week of the wedding'), 'title' => lang('Hand it over and enjoy it'),
        'tasks' => array(lang('Rehearse the ceremony'), lang('Hand everything to your coordinator'), lang('Rest, and enjoy every moment of the day'))),
);

// Why couples choose the planner (the home and the about page).
$why_us = array(
    array('icon' => 'bi-calendar-heart', 'title' => lang('One wedding a day'),
        'text' => lang('The whole venue and the whole team are yours on your day.')),
    array('icon' => 'bi-people', 'title' => lang('One team throughout'),
        'text' => lang('The planner you meet first is the one who runs your day.')),
    array('icon' => 'bi-geo-alt', 'title' => lang('Spaces for every size'),
        'text' => lang('From forty guests in the Orangery to three hundred in the Grand Hall.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Clear prices'),
        'text' => lang('Prices per guest, written in the proposal, with no surprises after.')),
);

// The gift cards the shop sells (with the shop), with their prices. The
// SKUs name the template, so the products are its own.
$gift_cards = array(
    'gift250' => array('sku' => 'WEDDING-GIFT-250', 'icon' => 'bi-gift', 'title' => lang('Wedding Gift Card'),
        'text'  => lang('A gift card towards a couple\'s wedding at the venue, valid for two years.'),
        'price' => array('USD' => 250, 'EUR' => 250, 'GBP' => 250, 'TRY' => 10000)),
    'gift500' => array('sku' => 'WEDDING-GIFT-500', 'icon' => 'bi-gift', 'title' => lang('Anniversary Dinner for Two'),
        'text'  => lang('A dinner for two on the Sea Terrace, for an anniversary or a couple you love, valid for a year.'),
        'price' => array('USD' => 500, 'EUR' => 500, 'GBP' => 500, 'TRY' => 20000)),
);

// The planner in four figures (the home and about pages).
$figures = array(
    array('value' => '400+', 'label' => lang('Weddings planned')),
    array('value' => '4', 'label' => lang('Spaces on one estate')),
    array('value' => '300', 'label' => lang('Guests at most in one space')),
    array('value' => '4.9', 'label' => lang('Average couple rating')),
);

// What couples say, each about one of the sample weddings.
$testimonials = array(
    array('wedding' => 'selin-emre', 'name' => lang('Selin and Emre'), 'role' => lang('Married in The Olive Garden')),
    array('wedding' => 'hannah-james', 'name' => lang('Hannah and James'), 'role' => lang('Married on The Sea Terrace')),
    array('wedding' => 'zeynep-can', 'name' => lang('Zeynep and Can'), 'role' => lang('Married in The Grand Hall')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the couple chose.
$space_titles = array();
foreach ($spaces as $s) $space_titles[] = $s['title'];
$season_titles = array();
foreach ($seasons as $s) $season_titles[] = $s['title'];
$package_titles = array();
foreach ($packages as $p) $package_titles[] = $p['title'];
$service_titles = array();
foreach ($services as $s) $service_titles[] = $s['title'];
$couple_names = array();
foreach ($weddings as $w) $couple_names[] = $w['couple'];
$package_choices = array_merge($package_titles, array(lang('Not decided yet')));
$service_choices = array_merge($service_titles, array(lang('Venue only')));
$space_choices = array_merge($space_titles, array(lang('Not sure yet')));
$season_choices = array_merge($season_titles, array(lang('Flexible')));
// The budget bands in the shop's currency, as the prices are written.
$budget_low = $money(array('USD' => 15000, 'EUR' => 14000, 'GBP' => 12000, 'TRY' => 550000));
$budget_high = $money(array('USD' => 35000, 'EUR' => 32000, 'GBP' => 28000, 'TRY' => 1300000));
$budget_choices = array(
    sprintf(lang('Up to %s in all'), $budget_low),
    sprintf(lang('%1$s to %2$s in all'), $budget_low, $budget_high),
    sprintf(lang('More than %s in all'), $budget_high),
    lang('Not sure yet'),
);
$ceremony_choices = array(
    array(lang('Ceremony and celebration at the venue'), lang('Ceremony and celebration at the venue')),
    array(lang('Celebration only'), lang('Celebration only')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of an enquiry, set by the planner in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Consultation booked'), lang('Proposal sent'), lang('Date confirmed'), lang('Declined'));

// The query string of a link that opens a list with a filter set
// (?space=, ?season=) or the enquiry with a choice made (?package=,
// ?service=, ?space=): the form's fields start with these values
// (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the date band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('date_band');
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
            $heading('h1', $title, 'pwd-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pwd-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pwd-display h3 mb-0'),
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

// Five stars, for what couples say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'wd-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the venue visits and the way to book one.
// A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-calendar-heart', 'text-primary'),
        $span(lang('Venue visits every Tuesday to Sunday'), '', lang('Text')),
        $link(lang('Book a Venue Visit'), '{{page:book_visit}}', 'link-primary fw-semibold'),
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

// The header: the planner's name, the menu and the way to enquire. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page. The deposits and gift cards
// are the shop's and are linked from the shop pages only, so the header
// stands on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('services', lang('Planning')),
        array('packages', lang('Packages')),
        array('venue', lang('The Venue')),
        array('weddings', lang('Real Weddings')),
        array('gallery', lang('Gallery')),
        array('timeline', lang('Planning Timeline')),
        array('about', lang('About Us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-balloon-heart', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pwd-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#wd-nav'),
                    $attr('aria-controls', 'wd-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav pwd-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-envelope-heart'),
                            $span(lang('Enquire'), '', lang('Link Text')),
                        ), array('href' => '{{page:enquiry}}')),
                    )),
                ), array('id' => 'wd-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the planner in a sentence, how to reach it
// and its profiles, the Real Weddings by space and by season, the couple's
// pages and the planner's pages; at the bottom the year, the site's name
// and the promise.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $spaces, $seasons, $planner, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $space_links = array($link(lang('All Real Weddings'), '{{page:weddings}}', $plain));
    foreach ($spaces as $s) $space_links[] = $link($s['title'], '{{page:weddings}}?' . $query(array('space' => $s['title'])), $plain);
    $season_links = array();
    foreach ($seasons as $s) $season_links[] = $link(sprintf(lang('%s weddings'), $s['title']), '{{page:weddings}}?' . $query(array('season' => $s['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Planner'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-balloon-heart', 'text-primary'),
                        $span('{{site_name}}', 'pwd-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($planner['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($planner['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($planner['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $planner['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($planner['email'], 'mailto:' . $planner['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($planner['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-pinterest', 'Pinterest'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-whatsapp', 'WhatsApp'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Real Wedding Links'), array(
                    $heading('h2', lang('Real Weddings'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($space_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Season Links'), array(
                    $heading('h2', lang('By Season'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($season_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('For Couples'), array(
                    $heading('h2', lang('For Couples'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Enquire'), '{{page:enquiry}}', $plain),
                        $link(lang('Book a Venue Visit'), '{{page:book_visit}}', $plain),
                        $link(lang('My Enquiries'), '{{page:my_enquiries}}', $plain),
                        $link(lang('Planning Timeline'), '{{page:timeline}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Planner'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Planning'), '{{page:services}}', $plain),
                        $link(lang('Packages'), '{{page:packages}}', $plain),
                        $link(lang('Wedding Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($planner['promise'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The date band: a full-width band in the palette's colour with the way to
// ask about a date and the way to see the venue. Shared, so every page
// that ends with it carries the same call.
$date_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Is your date still free?'), 'pwd-display h2 mb-2'),
                    $para(lang('Tell us your date and your guests, or come and walk the estate with us; we answer within a day.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Enquire'), '{{page:enquiry}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('Book a Venue Visit'), '{{page:book_visit}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// ── Cards ───────────────────────────────────────────────────────────────

// A planning service's card (the home and the services page): the icon,
// the name, a line, what it includes, the starting price and a link that
// opens the enquiry with the service chosen.
$service_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $check_list, $services, $service_price_text, $query) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 rounded-4 pwd-lift ' . ($s['featured'] ? 'border-primary border-2' : 'border-0 shadow-sm'), lang('Service Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3 align-self-start'),
                $heading('h3', $s['title'], 'pwd-display h4 mb-2'),
                $para($s['text'], 'small text-body-secondary mb-3'),
                $check_list($s['includes'], 'list-unstyled small mb-4'),
                $para($service_price_text($key), 'pwd-price fw-semibold mt-auto mb-3'),
                $link(lang('Ask about this service'), '{{page:enquiry}}?' . $query(array('service' => $s['title'])), 'btn btn-outline-primary rounded-pill'),
            )),
        )),
    ), '', '4');
};

// A venue package's card (the home and the packages page): the name, the
// guests and the space it suits, what it includes, the price per guest and
// a link that opens the enquiry with the package chosen.
$package_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon, $check_list, $packages, $spaces, $package_price_text, $guests_text, $query) {
    $p = $packages[$key];
    $head = array($heading('h3', $p['title'], 'pwd-display h3 mb-0'));
    if ($p['featured']) $head[] = $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary', lang('Badge'));
    return $col(array(
        $el('article', 'card h-100 rounded-4 pwd-lift ' . ($p['featured'] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Package Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-2', lang('Package Heading'), $head),
                $el('p', 'd-flex flex-wrap gap-3 small text-body-secondary mb-3', lang('Package Facts'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Guests'), array($icon('bi-people', 'text-primary'), $span($guests_text($key), '', lang('Text')))),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Venue Space'), array($icon('bi-geo-alt', 'text-primary'), $span($spaces[$p['space']]['title'], '', lang('Text')))),
                )),
                $para($p['text'], 'mb-3'),
                $check_list($p['includes'], 'list-unstyled small mb-4'),
                $para($package_price_text($key), 'pwd-price fs-5 fw-semibold mt-auto mb-3'),
                $link(lang('Ask about this package'), '{{page:enquiry}}?' . $query(array('package' => $p['title'])), 'btn ' . ($p['featured'] ? 'btn-primary' : 'btn-outline-primary') . ' rounded-pill'),
            )),
        )),
    ), '', '4');
};

// A space's card (the venue page and the home): the picture, what it is
// for, the name, a line, the capacity and a link that opens the enquiry
// with the space chosen.
$space_card = function ($key) use ($el, $heading, $para, $link, $span, $image, $col, $icon, $attr, $spaces, $capacity_text, $photo, $query) {
    $s = $spaces[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pwd-lift', lang('Space Card'), array(
            $el('span', 'd-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('space-' . $key, 800, 600), $s['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($s['use'], 'pwd-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Purpose')),
                $heading('h3', $s['title'], 'pwd-display h4 mb-2'),
                $para($s['text'], 'small text-body-secondary mb-3'),
                $el('p', 'd-flex align-items-center gap-1 small fw-semibold mb-3', lang('Capacity'), array(
                    $icon('bi-people', 'text-primary'),
                    $span($capacity_text($key), '', lang('Text')),
                )),
                $link(lang('Ask about this space'), '{{page:enquiry}}?' . $query(array('space' => $s['title'])), 'link-primary fw-semibold mt-auto'),
            )),
        )),
    ), '6', '3');
};

// A Real Wedding's card of the lists (the home and the Real Weddings
// list): the photo, the space and season, the couple linking the
// wedding's page, the summary and the guests. Every value is bound to the
// Real Weddings form's field of the same name; the sample fills the
// canvas.
$wedding_card = function ($sample_key = 'selin-emre') use ($el, $para, $span, $link, $image, $col, $icon, $attr, $bind, $weddings, $spaces, $seasons, $photo) {
    $w = $weddings[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pwd-lift', lang('Wedding Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('wedding-' . $sample_key, 800, 600), $w['couple'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'couple'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'pwd-eyebrow d-flex flex-wrap gap-2 small text-uppercase fw-semibold text-primary mb-1', lang('Space and Season'), array(
                    $span($spaces[$w['space']]['title'], '', lang('Venue Space'), $bind('space')),
                    $span('·', '', lang('Separator')),
                    $span($seasons[$w['season']]['title'], '', lang('Season'), $bind('season')),
                )),
                $el('h3', 'pwd-display h4 position-static mb-2', lang('Couple'), array(
                    $link($w['couple'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'couple', 'href' => 'form_item_view'))),
                )),
                $para($w['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('p', 'd-flex align-items-center gap-1 small mt-auto mb-0', lang('Guests'), array(
                    $icon('bi-people', 'text-primary'),
                    $span((string)$w['guests'], '', lang('Value'), $bind('guests')),
                    $span(lang('guests'), '', lang('Unit')),
                )),
            )),
        )),
    ), '6', '4');
};

// A stage of the planning timeline as a card of the Wedding: Planning
// Checklist block: its icon and the time left, the stage's name and the
// tasks to tick off then.
$timeline_stage = function ($n) use ($el, $heading, $span, $icon, $timeline) {
    $t = $timeline[$n];
    $tasks = array();
    foreach ($t['tasks'] as $task) {
        $tasks[] = $el('li', 'd-flex gap-2 mb-2', lang('Task'), array(
            $icon('bi-check2-square', 'text-primary flex-shrink-0'),
            $span($task, '', lang('Text')),
        ));
    }
    return $el('li', 'col', lang('Timeline Stage'), array(
        $el('div', 'h-100 p-4 rounded-4 border bg-body', lang('Stage Card'), array(
            $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Stage Head'), array(
                $el('span', 'd-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary-subtle text-primary-emphasis p-2 lh-1', lang('Icon Box'), array(
                    $icon($t['icon'], 'fs-5'),
                )),
                $span($t['when'], 'pwd-eyebrow small text-uppercase fw-semibold text-primary', lang('When')),
            )),
            $heading('h3', $t['title'], 'pwd-display h5 mb-3'),
            $el('ul', 'list-unstyled small mb-0', lang('Tasks'), $tasks),
        )),
    ));
};

// A picture of the venue gallery with its caption.
$gallery_item = function ($n) use ($el, $image, $col, $attr, $gallery, $photo) {
    $g = $gallery[$n];
    return $col(array(
        $el('figure', 'mb-0', lang('Gallery Picture'), array(
            $el('span', 'd-block ratio ratio-1x1 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('gallery-' . $g['seed'], 600, 600), $g['caption'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('figcaption', 'small text-body-secondary mt-2', lang('Caption'), array(), array('text' => $g['caption'])),
        )),
    ), '4', '3', '', '6');
};

// What a couple says: the quote, the stars, the couple and where they
// married. The quote is the sample wedding's own words.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials, $weddings) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Couple Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($weddings[$t['wedding']]['quote'], 'pwd-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Couple'), array(
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
                $item('wedding_new', lang('New Real Wedding')),
                $item('staff_enquiries', lang('Wedding Enquiries')),
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
    return $agree($id, 'consent', lang('I would like to receive the planner\'s news and Wedding Journal posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// couple's enquiry takes documents (a mood board, a guest list) and
// pictures, so the default accepts PDFs, office files, text and pictures;
// one that takes only pictures is a collection's image kind (a wedding's
// photos).
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
                    $link(lang('Ten questions to ask on a venue visit'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

