<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Craft Brewery", part 1 of its files: the node builders,
 * the brewery's own data (the beer styles and availabilities, the sample
 * beers, the event kinds, the sample events dated from the day the
 * template opens, the tours, the taproom's hours and food, the beer packs,
 * glassware, gift cards and Beer Club terms, what drinkers say and the
 * answers of the forms' pick lists) and the parts every page repeats
 * (account bar, header, footer, newsletter band, beer, event, tour and
 * testimonial cards, page shells, section headers, form fields, the
 * comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-brewery.php puts them together.
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
// every opening until the brewery puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-brewery-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/451a03/fef3c7?text=' . rawurlencode($initials !== '' ? $initials : 'B');
};

// Motion from the interactions on the nodes (pg_fx; nothing moves under
// reduced motion): $fx gives a node one effect, $rise brings the children
// of a row or a list in one after another as they scroll into view, and
// $fade_in is the way a section's heading comes in.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
$rise = function ($node, $stagger = 90) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger));
};
$fade_in = array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700);


// ── The brewery: the beers, the taproom, the tours and the shop ─────────

// The brewery itself. The about page tells its story; the footer, the
// newsletter band and the taproom page use the addresses and the hours.
$brewery = array(
    'tagline'  => lang('A small brewery and taproom by the old harbour, brewing honest beer in small batches'),
    'story'    => lang('Two home brewers turned an old boat workshop into a brewhouse, one fermenter at a time. The workshop\'s crane still hangs over the bar, and every new beer is poured for the neighbours first.'),
    'address'  => lang('Harbour Street 12, Old Harbour'),
    'phone'    => '+90 212 555 01 99',
    'email'    => 'hello@example.com',
    'tours'    => 'tours@example.com',
    'adults'   => lang('Our beers are for adults of legal drinking age. Please drink responsibly.'),
);

// The beer styles, the beers' filter. Each answer's value is its own
// words, so a filter link (?style=) reads as it shows.
$styles = array(
    'lager'  => array('icon' => 'bi-droplet', 'title' => lang('Lager')),
    'pale'   => array('icon' => 'bi-sun', 'title' => lang('Pale Ale')),
    'ipa'    => array('icon' => 'bi-flower1', 'title' => lang('IPA')),
    'stout'  => array('icon' => 'bi-moon-stars', 'title' => lang('Stout')),
    'sour'   => array('icon' => 'bi-lightning', 'title' => lang('Sour')),
    'wheat'  => array('icon' => 'bi-flower3', 'title' => lang('Wheat Beer')),
);

// How long a beer stays: set by the brewery's staff on the beer's record.
$availabilities = array(
    'core'     => array('title' => lang('Brewed all year'), 'badge' => 'text-bg-primary'),
    'seasonal' => array('title' => lang('Seasonal beer'), 'badge' => 'text-bg-success'),
    'limited'  => array('title' => lang('Limited release'), 'badge' => 'text-bg-warning'),
    'taproom'  => array('title' => lang('Taproom only'), 'badge' => 'text-bg-secondary'),
);

// The sample beers: the records the Beers form starts with. 'abv' is the
// strength and 'ibu' the bitterness in words; a seasonal beer names its
// season in words, never a month.
$beers = array(
    'harbour' => array('name' => lang('Harbour Lager'), 'style' => 'lager', 'availability' => 'core', 'abv' => '4.8%', 'ibu' => '22',
        'summary' => lang('A clean, crisp lager brewed slowly and kept cold for six weeks.'),
        'notes'   => lang('Bread crust, a little honey and a dry, bitter finish.'),
        'pairing' => lang('Fried fish, grilled halloumi and a sunny bench by the water.'),
        'grain'   => lang('Pilsner malt; Saaz and Hallertau hops'), 'featured' => true),
    'crane' => array('name' => lang('Crane Pale Ale'), 'style' => 'pale', 'availability' => 'core', 'abv' => '5.2%', 'ibu' => '35',
        'summary' => lang('Our first beer, named after the workshop crane that still hangs over the bar.'),
        'notes'   => lang('Orange peel, pine and a soft malt middle.'),
        'pairing' => lang('Burgers, roast chicken and sharp cheddar.'),
        'grain'   => lang('Pale and crystal malts; Cascade and Centennial hops'), 'featured' => true),
    'lighthouse' => array('name' => lang('Lighthouse IPA'), 'style' => 'ipa', 'availability' => 'core', 'abv' => '6.5%', 'ibu' => '60',
        'summary' => lang('A bright, hoppy IPA dry-hopped twice for a big tropical nose.'),
        'notes'   => lang('Mango, passion fruit and grapefruit, with a firm bitterness.'),
        'pairing' => lang('Spicy wings, tacos and curry.'),
        'grain'   => lang('Pale malt and oats; Citra, Mosaic and Simcoe hops'), 'featured' => true),
    'anchor' => array('name' => lang('Anchor Stout'), 'style' => 'stout', 'availability' => 'seasonal', 'abv' => '6.0%', 'ibu' => '40',
        'summary' => lang('Our winter stout, dark and smooth, back when the evenings draw in.'),
        'notes'   => lang('Roasted coffee, dark chocolate and a creamy head.'),
        'pairing' => lang('Oysters, beef stew and chocolate cake.'),
        'grain'   => lang('Roasted barley, chocolate malt and oats; Fuggles hops'), 'featured' => false),
    'tide' => array('name' => lang('Low Tide Sour'), 'style' => 'sour', 'availability' => 'limited', 'abv' => '4.2%', 'ibu' => '8',
        'summary' => lang('A tart summer sour with sour cherries from the hills behind the town.'),
        'notes'   => lang('Sour cherry, lemon and a light, refreshing finish.'),
        'pairing' => lang('Goat cheese salads and fruit tarts.'),
        'grain'   => lang('Pilsner and wheat malts; sour cherries'), 'featured' => false),
    'dockside' => array('name' => lang('Dockside Wheat'), 'style' => 'wheat', 'availability' => 'taproom', 'abv' => '5.0%', 'ibu' => '14',
        'summary' => lang('A hazy wheat beer poured only from the taproom\'s taps.'),
        'notes'   => lang('Banana, clove and fresh bread.'),
        'pairing' => lang('Brunch, white sausages and pretzels.'),
        'grain'   => lang('Wheat and pilsner malts; Tettnang hops'), 'featured' => false),
);
// A beer's strength and bitterness in one line, "6.5% ABV · 60 IBU".
$strength_text = function ($key) use ($beers) {
    return sprintf(lang('%1$s ABV · %2$s IBU'), $beers[$key]['abv'], $beers[$key]['ibu']);
};

// The kinds of taproom events, the events' filter.
$event_kinds = array(
    'music'    => array('icon' => 'bi-music-note-beamed', 'title' => lang('Live Music')),
    'quiz'     => array('icon' => 'bi-question-circle', 'title' => lang('Quiz Night')),
    'takeover' => array('icon' => 'bi-arrow-left-right', 'title' => lang('Tap Takeover')),
    'release'  => array('icon' => 'bi-stars', 'title' => lang('Beer Release')),
    'food'     => array('icon' => 'bi-egg-fried', 'title' => lang('Food Pop-up')),
);

// The day of a sample event, counted from the day the template is opened
// so the coming events are still to come (and the past ones behind them):
// the first day after today with the ISO weekday given (1 = Monday), plus
// $weeks weeks; a negative $weeks counts back. Each event is a record of
// the Taproom Events form, edited or deleted like any other.
$event_on = function ($weekday, $weeks = 0) {
    $day = new DateTime('today');
    do {
        $day->modify('+1 day');
    } while ((int)$day->format('N') !== $weekday);
    if ($weeks !== 0) $day->modify(($weeks > 0 ? '+' : '-') . (7 * abs($weeks)) . ' days');
    return $day->format('Y-m-d');
};

// The sample events: five to come, three already held. 'starts' is the
// time the event starts; 'entry' says what it costs to come in.
$events = array(
    'quiz'      => array('title' => lang('Thursday Pub Quiz'), 'kind' => 'quiz', 'weekday' => 4, 'weeks' => 0, 'starts' => '19:30',
        'entry' => lang('Free entry, teams of up to six'), 'text' => lang('Six rounds, one picture round and a pint for the winning team.'), 'featured' => true),
    'band'      => array('title' => lang('Harbour Blues Band'), 'kind' => 'music', 'weekday' => 6, 'weeks' => 0, 'starts' => '20:00',
        'entry' => lang('Free entry'), 'text' => lang('Two sets of slow blues by the window, with the sea behind the band.'), 'featured' => true),
    'release'   => array('title' => lang('Anchor Stout Release Party'), 'kind' => 'release', 'weekday' => 5, 'weeks' => 1, 'starts' => '18:00',
        'entry' => lang('Free entry, first pour on the house for club members'), 'text' => lang('The winter stout is back: meet the brewers and taste it straight from the tank.'), 'featured' => true),
    'takeover'  => array('title' => lang('Guest Brewery Tap Takeover'), 'kind' => 'takeover', 'weekday' => 6, 'weeks' => 2, 'starts' => '15:00',
        'entry' => lang('Free entry'), 'text' => lang('A friendly brewery from up the coast takes over eight of our taps for the afternoon.'), 'featured' => false),
    'popup'     => array('title' => lang('Wood-fired Pizza Pop-up'), 'kind' => 'food', 'weekday' => 7, 'weeks' => 2, 'starts' => '13:00',
        'entry' => lang('Free entry, pizzas paid at the oven'), 'text' => lang('A mobile oven in the yard all Sunday afternoon.'), 'featured' => false),
    'jazz'      => array('title' => lang('Sunday Jazz Trio'), 'kind' => 'music', 'weekday' => 7, 'weeks' => -2, 'starts' => '17:00',
        'entry' => lang('Free entry'), 'text' => lang('Quiet jazz for a slow Sunday.'), 'featured' => false),
    'sourfest'  => array('title' => lang('Low Tide Sour Launch'), 'kind' => 'release', 'weekday' => 5, 'weeks' => -5, 'starts' => '18:00',
        'entry' => lang('Free entry'), 'text' => lang('The first pour of the cherry sour, with cherries from the hills.'), 'featured' => false),
    'oldquiz'   => array('title' => lang('Music Quiz Special'), 'kind' => 'quiz', 'weekday' => 4, 'weeks' => -7, 'starts' => '19:30',
        'entry' => lang('Free entry, teams of up to six'), 'text' => lang('Name the song from the first five seconds.'), 'featured' => false),
);

// The brewery tours, each booked as a request (the tour booking form,
// ?tour=). The prices are per person, paid on the day.
$tours = array(
    'classic' => array('icon' => 'bi-gear-wide-connected', 'title' => lang('Brewery Tour'), 'length' => lang('About an hour'),
        'text'  => lang('Walk through the brewhouse with a brewer, from the grain to the tank, and finish with three beers in the taproom.'),
        'price' => array('USD' => 20, 'EUR' => 18, 'GBP' => 16, 'TRY' => 650)),
    'tasting' => array('icon' => 'bi-cup', 'title' => lang('Tasting Flight Tour'), 'length' => lang('About ninety minutes'),
        'text'  => lang('The tour, then six beers side by side with the brewer, from the lightest to the darkest.'),
        'price' => array('USD' => 32, 'EUR' => 29, 'GBP' => 25, 'TRY' => 1050)),
    'brewday' => array('icon' => 'bi-fire', 'title' => lang('Brewer for a Day'), 'length' => lang('A whole brew day'),
        'text'  => lang('Brew with our team from the first mash to the last clean, with lunch and a case of the beer you brewed.'),
        'price' => array('USD' => 150, 'EUR' => 140, 'GBP' => 120, 'TRY' => 4900)),
);

// The taproom's opening hours (the taproom page and the footer).
$hours = array(
    array('days' => lang('Monday and Tuesday'), 'time' => lang('Closed')),
    array('days' => lang('Wednesday and Thursday'), 'time' => '16:00–23:00'),
    array('days' => lang('Friday'), 'time' => '15:00–00:00'),
    array('days' => lang('Saturday'), 'time' => '12:00–00:00'),
    array('days' => lang('Sunday'), 'time' => '12:00–20:00'),
);

// The taproom's food: page content, edited in the designer.
$food = array(
    array('title' => lang('Brewer\'s Board'), 'text' => lang('Cheeses, cured meats, pickles and spent-grain bread.'), 'price' => array('USD' => 16, 'EUR' => 15, 'GBP' => 13, 'TRY' => 520)),
    array('title' => lang('Beer-battered Fish'), 'text' => lang('Today\'s catch in a lager batter, with chips.'), 'price' => array('USD' => 14, 'EUR' => 13, 'GBP' => 11, 'TRY' => 460)),
    array('title' => lang('Soft Pretzel'), 'text' => lang('Warm from the oven, with a mustard made with our stout.'), 'price' => array('USD' => 6, 'EUR' => 6, 'GBP' => 5, 'TRY' => 200)),
);

// What the shop sells (with the shop), with its prices. The SKUs name the
// template, so the products are its own; beer and glassware are posted,
// so each is a shippable product.
$packs = array(
    'mixed12' => array('sku' => 'BREWERY-MIXED-12', 'icon' => 'bi-box-seam', 'title' => lang('Mixed Case of Twelve'),
        'text'  => lang('Two cans of each of our six beers.'),
        'price' => array('USD' => 42, 'EUR' => 39, 'GBP' => 34, 'TRY' => 1400)),
    'core6' => array('sku' => 'BREWERY-CORE-6', 'icon' => 'bi-box', 'title' => lang('Core Six-pack'),
        'text'  => lang('Two cans each of the lager, the pale ale and the IPA.'),
        'price' => array('USD' => 22, 'EUR' => 20, 'GBP' => 18, 'TRY' => 720)),
    'ipa12' => array('sku' => 'BREWERY-IPA-12', 'icon' => 'bi-box-seam', 'title' => lang('Lighthouse IPA, Case of Twelve'),
        'text'  => lang('Twelve cans of our IPA, canned the week you order.'),
        'price' => array('USD' => 40, 'EUR' => 37, 'GBP' => 32, 'TRY' => 1320)),
    'glass' => array('sku' => 'BREWERY-GLASS', 'icon' => 'bi-cup-straw', 'title' => lang('Brewery Glass'),
        'text'  => lang('Our tulip glass with the crane on it, as poured in the taproom.'),
        'price' => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 300)),
    'shirt' => array('sku' => 'BREWERY-SHIRT', 'icon' => 'bi-tag', 'title' => lang('Crane T-shirt'),
        'text'  => lang('Soft cotton with the workshop crane on the front. Sizes S to XXL.'),
        'price' => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 850)),
);
// The gift cards the shop sells (with the shop).
$gift_cards = array(
    'gift25' => array('sku' => 'BREWERY-GIFT-25', 'icon' => 'bi-gift', 'title' => lang('Brewery Gift Card'),
        'text'  => lang('A gift card for the shop, a tour or a round in the taproom.'),
        'price' => array('USD' => 25, 'EUR' => 25, 'GBP' => 25, 'TRY' => 1000)),
    'gift50' => array('sku' => 'BREWERY-GIFT-50', 'icon' => 'bi-gift', 'title' => lang('Brewery Gift Card, Large'),
        'text'  => lang('A larger gift card, enough for a tasting tour for two.'),
        'price' => array('USD' => 50, 'EUR' => 50, 'GBP' => 50, 'TRY' => 2000)),
);
// The Beer Club's terms (with the shop): each renews the buyer's
// membership for its days, which opens the members' area. A term is
// bought again to renew; nothing is charged by itself.
$club_terms = array(
    'quarter' => array('sku' => 'BREWERY-CLUB-3', 'days' => 90, 'title' => lang('Beer Club, Three Months'),
        'text'  => lang('A mixed case every month for three months, and the club\'s perks in the taproom.'),
        'price' => array('USD' => 99, 'EUR' => 92, 'GBP' => 80, 'TRY' => 3300)),
    'half' => array('sku' => 'BREWERY-CLUB-6', 'days' => 182, 'title' => lang('Beer Club, Six Months'),
        'text'  => lang('Six monthly cases, a club glass and the club\'s perks in the taproom.'),
        'price' => array('USD' => 189, 'EUR' => 175, 'GBP' => 152, 'TRY' => 6300)),
    'year' => array('sku' => 'BREWERY-CLUB-12', 'days' => 365, 'title' => lang('Beer Club, Twelve Months'),
        'text'  => lang('Twelve monthly cases, a club glass, a T-shirt and a free brewery tour for two.'),
        'price' => array('USD' => 359, 'EUR' => 333, 'GBP' => 289, 'TRY' => 11900)),
);
// What a club member gets (the club page and the home).
$club_perks = array(
    lang('A mixed case of new and favourite beers every month'),
    lang('The first taste of every new beer at its release party'),
    lang('Ten per cent off in the taproom and the shop'),
    lang('Club-only beers in the members\' area'),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// What drinkers say (the home and the taproom page), each about a sample
// beer. The names are made up.
$testimonials = array(
    array('beer' => 'lighthouse', 'name' => lang('Selin K.'), 'quote' => lang('The IPA tastes like a holiday. We came for one and stayed for the quiz.')),
    array('beer' => 'crane', 'name' => lang('Tom B.'), 'quote' => lang('The tour was the best hour of our trip, and the pale ale is now my favourite beer.')),
    array('beer' => 'anchor', 'name' => lang('Aylin D.'), 'quote' => lang('A real stout, smooth as velvet. The release party was packed and friendly.')),
);

// The brewery in four figures (the home and the about page).
$figures = array(
    array('value' => '6', 'label' => lang('Beers on our own taps')),
    array('value' => '20', 'label' => lang('Hectolitres in each batch')),
    array('value' => '12', 'label' => lang('Taps in the taproom')),
    array('value' => '1', 'label' => lang('Crane that still hangs over the bar')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$style_titles = array();
foreach ($styles as $s) $style_titles[] = $s['title'];
$availability_titles = array();
foreach ($availabilities as $a) $availability_titles[] = $a['title'];
$event_kind_titles = array();
foreach ($event_kinds as $k) $event_kind_titles[] = $k['title'];
$tour_titles = array();
foreach ($tours as $t) $tour_titles[] = $t['title'];
$tour_times = array('12:00', '14:00', '16:00', '18:00');
$group_sizes = array(lang('1 to 2 people'), lang('3 to 6 people'), lang('7 to 12 people'), lang('More than 12 people'));
// The states of a tour booking, set by the brewery's staff in the panel.
$booking_statuses = array(lang('Request received'), lang('Confirmed'), lang('Moved to another time'), lang('Declined'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The query string of a link that opens a list with a filter set
// (?style=, ?kind=) or a form with a choice made (?tour=):
// the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the newsletter band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('news_band');
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
            $heading('h1', $title, 'pbw-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pbw-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pbw-display h3 mb-0'),
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

// Five stars, for what the drinkers say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'bw-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: what is on in the taproom and where the
// events are. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-megaphone', 'text-primary'),
        $span(lang('The taproom is open from Wednesday: quiz on Thursdays'), '', lang('Text')),
        $link(lang('See what is on'), '{{page:events}}', 'link-primary fw-semibold'),
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

// The header: the brewery's name, the menu and the way to a tour. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page. The shop and the club are the
// shop's and are linked from the shop pages only, so the header stands on
// a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('beers', lang('Our Beers')),
        array('taproom', lang('Taproom')),
        array('events', lang('Events')),
        array('tours', lang('Tours')),
        array('blog', lang('News')),
        array('about', lang('About')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-cup-straw', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pbw-brand fs-3', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#bw-nav'),
                    $attr('aria-controls', 'bw-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar2-check'),
                            $span(lang('Book a Tour'), '', lang('Link Text')),
                        ), array('href' => '{{page:tour_booking}}')),
                    )),
                ), array('id' => 'bw-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The brewery's profiles as a row of round icon links (the footer and the
// about page). The addresses are placeholders.
$social_links = function ($class = 'd-flex flex-wrap gap-3') use ($el, $icon, $attr) {
    $links = array();
    foreach (array(array('bi-instagram', 'Instagram'), array('bi-facebook', 'Facebook'), array('bi-youtube', 'YouTube')) as $s) {
        $links[] = $el('a', 'link-body-emphasis fs-5', $s[1], array($icon($s[0])), array('href' => '#', '_attrs' => array($attr('aria-label', $s[1]))));
    }
    return $el('div', $class, lang('Social Links'), $links);
};

// The footer, on a dark band: the brewery in a sentence, the address, the
// phone and the e-mail, the opening hours, the beers by style, the
// taproom's pages and the brewery's pages; at the bottom the year, the
// site's name and the words on drinking responsibly.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $styles, $brewery, $hours, $query, $attr, $social_links) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $style_links = array($link(lang('All Beers'), '{{page:beers}}', $plain));
    foreach ($styles as $s) $style_links[] = $link($s['title'], '{{page:beers}}?' . $query(array('style' => $s['title'])), $plain);
    $hour_lines = array();
    foreach ($hours as $h) {
        $hour_lines[] = $el('li', 'd-flex justify-content-between gap-3 mb-2', lang('Opening Hours'), array(
            $span($h['days'], '', lang('Days')),
            $span($h['time'], 'text-body-secondary text-nowrap', lang('Time')),
        ));
    }
    $contact_line = function ($icon_name, $label, $value, $href) use ($el, $icon, $span, $link, $plain) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $label, array(
            $icon($icon_name, 'text-primary'),
            $span($label, 'visually-hidden', lang('Label')),
            $href !== '' ? $link($value, $href, $plain . ' text-break') : $span($value, '', lang('Value')),
        ));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Brewery'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-3 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-cup-straw', 'text-primary'),
                        $span('{{site_name}}', 'pbw-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($brewery['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', lang('Address'), $brewery['address'], ''),
                        $contact_line('bi-telephone', lang('Phone'), $brewery['phone'], 'tel:' . str_replace(' ', '', $brewery['phone'])),
                        $contact_line('bi-envelope', lang('E-mail'), $brewery['email'], 'mailto:' . $brewery['email']),
                    )),
                    $social_links('d-flex flex-wrap gap-3 mt-3'),
                )),
                $el('div', 'col-12 col-md-6 col-lg-3', lang('Opening Hours'), array(
                    $heading('h2', lang('Taproom Hours'), 'h6 text-uppercase small fw-bold mb-3'),
                    $el('ul', 'list-unstyled small mb-0', lang('Opening Hours'), $hour_lines),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Beer Links'), array(
                    $heading('h2', lang('Our Beers'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($style_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-3', lang('Footer Links'), array(
                    $heading('h2', lang('Visit Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Taproom'), '{{page:taproom}}', $plain),
                        $link(lang('Events'), '{{page:events}}', $plain),
                        $link(lang('Brewery Tours'), '{{page:tours}}', $plain),
                        $link(lang('Private Tastings'), '{{page:book_online}}', $plain),
                        $link(lang('My Tour Bookings'), '{{page:my_bookings}}', $plain),
                        $link(lang('About the Brewery'), '{{page:about}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $el('p', 'd-flex align-items-center gap-2 small text-body-secondary mb-0', lang('Drink Responsibly'), array(
                    $icon('bi-person-check', 'text-primary'),
                    $span($brewery['adults'], '', lang('Text')),
                )),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The newsletter band: a full-width band in the palette's colour with the
// way onto the newsletter and the way to a tour. Shared, so every page
// that ends with it carries the same call. The Beer Club is the shop's,
// so the band names it only in words.
$news_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('First pour, first to know'), 'pbw-display display-6 mb-2'),
                    $para(lang('Join the newsletter for new beers, release parties and the taproom\'s events before anyone else.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Join the Newsletter'), '{{page:newsletter}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('Book a Tour'), '{{page:tour_booking}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A beer's card of the lists (the home and the beers page): the picture,
// the style, the name linking the beer's page, the strength and the
// bitterness, and how long it stays. Every value is bound to the Beers
// form's field of the same name; the sample fills the canvas.
$beer_card = function ($sample_key = 'lighthouse') use ($el, $para, $span, $link, $image, $col, $bind, $beers, $styles, $availabilities, $strength_text, $photo, $attr) {
    $b = $beers[$sample_key];
    $avail = $availabilities[$b['availability']];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pbw-lift', lang('Beer Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('beer-' . $sample_key, 800, 600), $b['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'picture', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($styles[$b['style']]['title'], 'pbw-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Beer Style'), $bind('style')),
                $el('h3', 'pbw-display h4 position-static mb-1', lang('Beer Name'), array(
                    $link($b['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($strength_text($sample_key), 'pbw-abv small fw-semibold mb-2', $bind('strength')),
                $para($b['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('div', 'mt-auto', lang('Beer Availability'), array(
                    $span($avail['title'], 'badge rounded-pill ' . $avail['badge'], lang('Beer Availability'), $bind('availability')),
                )),
            )),
        )),
    ), '6', '4');
};

// An event's row of the events list: the date, the start time, the kind,
// the title linking the event's page, what entry costs and, when the
// event has one, the ticket link (another site's page, in a new window).
// Every value is bound to the Taproom Events form's field of the same name.
$event_row = function ($sample_key = 'quiz') use ($el, $span, $link, $icon, $bind, $events, $event_kinds, $event_on, $attr) {
    $e = $events[$sample_key];
    return $el('article', 'd-flex flex-column flex-md-row align-items-md-center gap-3 py-3 border-bottom', lang('Event Row'), array(
        $el('div', 'pbw-date flex-shrink-0 fw-bold text-primary', lang('Event Date'), array(), array_merge(
            array('text' => $event_on($e['weekday'], $e['weeks'])), $bind('event_date'))),
        $el('div', 'flex-grow-1', lang('Event Details'), array(
            $span($event_kinds[$e['kind']]['title'], 'pbw-eyebrow small text-uppercase fw-semibold text-primary', lang('Event Kind'), $bind('kind')),
            $el('h3', 'pbw-display h5 mb-1', lang('Event Title'), array(
                $link($e['title'], '#', 'link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $el('p', 'd-flex flex-wrap gap-3 small text-body-secondary mb-0', lang('Event Facts'), array(
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Starts'), array(
                    $icon('bi-clock', 'text-primary'),
                    $span(lang('Starts'), '', lang('Label')),
                    $span($e['starts'], '', lang('Value'), $bind('starts')),
                )),
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Admission'), array(
                    $icon('bi-door-open', 'text-primary'),
                    $span($e['entry'], '', lang('Value'), $bind('entry')),
                )),
            )),
        )),
        $el('div', 'd-flex flex-wrap align-items-center gap-2 flex-shrink-0', lang('Tickets'), array(
            $link(lang('Tickets'), '#', 'btn btn-sm btn-outline-primary rounded-pill px-3', array($attr('rel', 'noopener')),
                array('target' => '_blank', '_bindings' => array('href' => 'ticket_url', 'show_if' => 'ticket_url'))),
        )),
    ));
};

// A tour's card (the tours page and the home): what it is, how long it
// takes, the price per person and the way to book it with the tour chosen.
$tour_card = function ($key) use ($el, $heading, $para, $span, $link, $col, $icon_tile, $tours, $money, $query) {
    $t = $tours[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 p-4 pbw-lift', lang('Tour Card'), array(
            $el('div', 'mb-3', lang('Icon'), array($icon_tile($t['icon']))),
            $heading('h3', $t['title'], 'pbw-display h4 mb-1'),
            $para($t['length'], 'small fw-semibold text-primary mb-2'),
            $para($t['text'], 'text-body-secondary mb-4'),
            $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto', lang('Price and Button'), array(
                $el('p', 'mb-0', lang('Price'), array(
                    $span($money($t['price']), 'pbw-price fs-4 fw-bold', lang('Price')),
                    $span(lang('per person'), 'small text-body-secondary ms-1', lang('Text')),
                )),
                $link(lang('Book This Tour'), '{{page:tour_booking}}?' . $query(array('tour' => $t['title'])), 'btn btn-primary rounded-pill px-4'),
            )),
        )),
    ), '6', '4');
};

// What a drinker says: the stars, the words, the name and the beer.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $stars, $testimonials, $beers) {
    $q = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Review Card'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($q['quote'], 'pbw-quote mb-0'))),
            $el('figcaption', 'mt-auto', lang('Source'), array(
                $span($q['name'], 'd-block fw-semibold', lang('Name')),
                $span($beers[$q['beer']]['name'], 'd-block small text-body-secondary', lang('Beer Name')),
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
                $item('beer_new', lang('New Beer')),
                $item('event_new', lang('New Event')),
                $item('staff_bookings', lang('Tour Bookings')),
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
    return $agree($id, 'consent', lang('I would like to receive the brewery\'s news, new beers and taproom events by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. The
// default accepts PDFs, office files, text and pictures; one that takes
// only pictures is a collection's image kind (a beer's picture).
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
                    $link(lang('Notes from our first brew day'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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


// ── Collection lists and record pages ───────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address; or today's date).
$bw_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// A date filter against today: the events to come (on or after today) or
// those already held (before today).
$bw_date_filter = function ($field, $operator) {
    return array('field' => $field, 'operator' => $operator, 'value' => '', 'dynamic_value' => 'current date');
};
// The answers of the Featured list: the first marks a record for the home.
$bw_featured_choices = array(lang('Yes'), lang('No'));

// A list's settings: the form, its record page, the order and the words
// shown when nothing matches.
$bw_list_config = function ($form_page, $detail_page, $order_field, $direction, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => $order_field,
        'order_by_direction'  => $direction,
        'empty_message'       => $empty,
    ), $more);
};

// The links above a list that narrow it: one per answer of a pick list,
// each opening the list with the filter set.
$bw_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The record pages' back link and Edit button, above the record.
$bw_toolbar = function ($page_key, $text) use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $page_key . '}}')),
        $edit_button(),
    ));
};
// What a record page shows when its record is not there.
$bw_not_found = function ($icon_name, $title, $text, $back_text, $back_page) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_page . '}}', 'btn btn-primary rounded-pill px-4'),
    ), $show_when('record_not_found'));
};
// A long field of a record page under its heading, its lines kept; the
// block leaves when the record has no value.
$bw_long_field = function ($title, $token, $sample, $class = 'mb-4') use ($el, $heading, $bind) {
    return $el('div', 'pg-hide-if-empty ' . $class, $title, array(
        $heading('h2', $title, 'pbw-display h4 mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// A link to an address the record holds, in a new window, shown only when
// the record has it (a ticket seller).
$bw_record_link = function ($text, $token, $class) use ($link, $attr) {
    return $link($text, '#', $class, array($attr('rel', 'noopener')),
        array('target' => '_blank', '_bindings' => array('href' => $token, 'show_if' => $token)));
};
// A staff page holding a collection's form in a card.
$bw_form_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return $staff_page(array(
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
    ));
};
