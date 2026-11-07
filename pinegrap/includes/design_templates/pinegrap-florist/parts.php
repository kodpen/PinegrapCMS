<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Florist", part 1 of its files: the node builders, the
 * florist's own data (the occasions, the bouquets with their prices and
 * sizes, the flower subscription plans, the wedding and event services,
 * the delivery areas and times, the flowers of each season, the care
 * steps, the sample work, why customers choose the florist, what they say
 * and the answers of the forms' pick lists) and the parts every page
 * repeats (account bar, header, footer, delivery band, bouquet, occasion,
 * plan, event service, delivery area, season, work and customer cards,
 * page shells, section headers, form fields, the comment thread). The page
 * files of the same folder are included after it in the same scope and
 * build their trees with these variables;
 * includes/design_templates/pinegrap-florist.php puts them together.
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
// every opening until the florist puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-florist-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/fde8ec/9f1239?text=' . rawurlencode($initials !== '' ? $initials : 'F');
};

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// ── The florist: occasions, bouquets, subscriptions, events, delivery ──

// The florist itself. The about page tells its story; the footer, the
// delivery band and the contact lines use the address, phone and opening
// hours; 'cutoff' is the time an order must reach the shop to go out the
// same day.
$florist = array(
    'tagline' => lang('Hand-tied bouquets, flower subscriptions and wedding flowers, delivered across the city'),
    'story'   => lang('We began with a market stall and a bucket of tulips. Today our studio arranges flowers for birthdays, new babies, weddings and quiet days of remembrance, still by hand, still with stems bought fresh from the growers every morning.'),
    'address' => lang('Garden Lane 7, Old Town'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'hello@example.com',
    'hours'   => lang('Monday to Saturday, 08:00 to 19:00; Sunday, 10:00 to 14:00'),
    'cutoff'  => '14:00',
    'promise' => lang('Fresh flowers every morning, arranged by hand on the day they leave'),
);
// The same-day line: "Order by 14:00 for delivery today".
$cutoff_text = function () use ($florist) {
    return sprintf(lang('Order by %s for delivery today'), $florist['cutoff']);
};

// The occasions, in the order the occasions page shows them. A bouquet
// names the occasion it suits; an order request starts with one chosen.
$occasions = array(
    'birthday'    => array('icon' => 'bi-balloon', 'title' => lang('Birthday'),
        'text' => lang('Bright, cheerful flowers that make the day: sunflowers, gerberas and whatever is bursting in season.')),
    'love'        => array('icon' => 'bi-heart', 'title' => lang('Love and Romance'),
        'text' => lang('Roses, peonies and soft colours, for an anniversary or simply to say it again.')),
    'thank_you'   => array('icon' => 'bi-emoji-smile', 'title' => lang('Thank You'),
        'text' => lang('A gift that says what words do not: a jar of garden flowers or a bouquet in their favourite colour.')),
    'new_baby'    => array('icon' => 'bi-stars', 'title' => lang('New Baby'),
        'text' => lang('Gentle pastels and soft scents, arranged low so they are safe beside a cot.')),
    'get_well'    => array('icon' => 'bi-sun', 'title' => lang('Get Well'),
        'text' => lang('Sunny, long-lasting flowers in a vase, ready for a bedside table.')),
    'sympathy'    => array('icon' => 'bi-flower3', 'title' => lang('Sympathy'),
        'text' => lang('Calm whites and greens for a home in mourning, and tributes we deliver to the service.')),
);

// The bouquets, each made for an occasion. 'style' is how it comes (hand
// tied, in a vase, in a box); 'flowers' the stems it is made of. A price
// is the regular size's; the deluxe and grand sizes add more stems.
$bouquets = array(
    'sunny-days'   => array('occasion' => 'birthday', 'title' => lang('Sunny Days'), 'style' => lang('Hand-tied'),
        'text'     => lang('Sunflowers, yellow roses and white daisies in a bright, generous bunch.'),
        'flowers'  => array(lang('Sunflowers'), lang('Yellow roses'), lang('Daisies')),
        'featured' => true),
    'blush'        => array('occasion' => 'love', 'title' => lang('Blush'), 'style' => lang('Hand-tied'),
        'text'     => lang('Pink garden roses and peonies with eucalyptus, soft and full.'),
        'flowers'  => array(lang('Garden roses'), lang('Peonies'), lang('Eucalyptus')),
        'featured' => true),
    'red-roses'    => array('occasion' => 'love', 'title' => lang('Twelve Red Roses'), 'style' => lang('In a vase'),
        'text'     => lang('A dozen long-stemmed red roses in a clear glass vase, ready to stand.'),
        'flowers'  => array(lang('Red roses'), lang('Ruscus')),
        'featured' => false),
    'meadow'       => array('occasion' => 'thank_you', 'title' => lang('Meadow Jar'), 'style' => lang('In a jar'),
        'text'     => lang('A little jar of seasonal garden flowers, the way they grow in a meadow.'),
        'flowers'  => array(lang('Seasonal garden flowers'), lang('Wild grasses')),
        'featured' => true),
    'cloud'        => array('occasion' => 'new_baby', 'title' => lang('Little Cloud'), 'style' => lang('In a box'),
        'text'     => lang('White and pale pink flowers arranged low in a keepsake box.'),
        'flowers'  => array(lang('Spray roses'), lang('Lisianthus'), lang('Gypsophila')),
        'featured' => false),
    'sunshine-vase' => array('occasion' => 'get_well', 'title' => lang('Sunshine Vase'), 'style' => lang('In a vase'),
        'text'     => lang('Long-lasting alstroemeria and chrysanthemums in a sturdy vase.'),
        'flowers'  => array(lang('Alstroemeria'), lang('Chrysanthemums'), lang('Solidago')),
        'featured' => false),
    'serenity'     => array('occasion' => 'sympathy', 'title' => lang('Serenity'), 'style' => lang('Hand-tied'),
        'text'     => lang('White lilies, roses and soft greenery, calm and quiet.'),
        'flowers'  => array(lang('White lilies'), lang('White roses'), lang('Soft greenery')),
        'featured' => false),
    'florists-choice' => array('occasion' => 'birthday', 'title' => lang('Florist\'s Choice'), 'style' => lang('Hand-tied'),
        'text'     => lang('Leave it to us: the best stems of the morning, arranged in the colours you name.'),
        'flowers'  => array(lang('The freshest stems of the day')),
        'featured' => true),
);
$bouquet_prices = array(
    'sunny-days'      => array('USD' => 55, 'EUR' => 50, 'GBP' => 45, 'TRY' => 1900),
    'blush'           => array('USD' => 75, 'EUR' => 70, 'GBP' => 60, 'TRY' => 2600),
    'red-roses'       => array('USD' => 85, 'EUR' => 80, 'GBP' => 70, 'TRY' => 2900),
    'meadow'          => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1200),
    'cloud'           => array('USD' => 60, 'EUR' => 55, 'GBP' => 48, 'TRY' => 2100),
    'sunshine-vase'   => array('USD' => 50, 'EUR' => 46, 'GBP' => 40, 'TRY' => 1700),
    'serenity'        => array('USD' => 70, 'EUR' => 65, 'GBP' => 56, 'TRY' => 2400),
    'florists-choice' => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1500),
);
// The sizes a bouquet comes in, with what each adds.
$sizes = array(
    'regular' => array('title' => lang('Regular'), 'text' => lang('As in the picture')),
    'deluxe'  => array('title' => lang('Deluxe'), 'text' => lang('Half as many stems again')),
    'grand'   => array('title' => lang('Grand'), 'text' => lang('Twice the stems, for a big moment')),
);

// The flower subscription plans: fresh flowers on a set day, every week,
// every two weeks or every month. 'deliveries' is how many a three-month
// subscription brings; the price is per delivery.
$plans = array(
    'weekly'      => array('icon' => 'bi-calendar-week', 'title' => lang('Weekly Flowers'), 'every' => lang('Every week'), 'deliveries' => 12,
        'text'     => lang('A fresh bouquet every week, so the vase is never empty.'),
        'includes' => array(lang('A new seasonal bouquet each time'), lang('Free delivery on your chosen day'), lang('Pause or skip a week when you are away'), lang('A care card with every bouquet')),
        'featured' => false),
    'fortnightly' => array('icon' => 'bi-calendar2-range', 'title' => lang('Fortnightly Flowers'), 'every' => lang('Every two weeks'), 'deliveries' => 6,
        'text'     => lang('Our most loved plan: a bouquet every other week, while the last one fades.'),
        'includes' => array(lang('A new seasonal bouquet each time'), lang('Free delivery on your chosen day'), lang('Pause or skip a delivery when you are away'), lang('A care card with every bouquet')),
        'featured' => true),
    'monthly'     => array('icon' => 'bi-calendar-heart', 'title' => lang('Monthly Flowers'), 'every' => lang('Every month'), 'deliveries' => 3,
        'text'     => lang('A treat once a month, for your home, your office or someone you love.'),
        'includes' => array(lang('A larger seasonal bouquet each time'), lang('Free delivery on your chosen day'), lang('A vase with the first delivery'), lang('A care card with every bouquet')),
        'featured' => false),
);
$plan_prices = array(
    'weekly'      => array('USD' => 40, 'EUR' => 37, 'GBP' => 32, 'TRY' => 1400),
    'fortnightly' => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1550),
    'monthly'     => array('USD' => 60, 'EUR' => 55, 'GBP' => 48, 'TRY' => 2100),
);

// Wedding and event flowers, each with what it includes. A service's
// price is its starting price; the proposal after the consultation gives
// the rest.
$event_services = array(
    'weddings'  => array('icon' => 'bi-gem', 'title' => lang('Wedding Flowers'),
        'text'     => lang('From the bridal bouquet to the last table: flowers that carry your day from the ceremony to the dancing.'),
        'includes' => array(lang('A consultation and a mood board'), lang('Bridal bouquet, buttonholes and corsages'), lang('Ceremony arch and aisle flowers'), lang('Table centrepieces, set up and cleared on the day'))),
    'events'    => array('icon' => 'bi-balloon-heart', 'title' => lang('Events and Parties'),
        'text'     => lang('Birthdays, engagements, dinners and launches: flowers sized to the room and the mood.'),
        'includes' => array(lang('A plan for the room and the tables'), lang('Centrepieces and statement arrangements'), lang('Delivery and setup'), lang('Collection after the event'))),
    'corporate' => array('icon' => 'bi-building', 'title' => lang('Business Flowers'),
        'text'     => lang('Fresh arrangements for a reception, a restaurant or a hotel lobby, changed every week.'),
        'includes' => array(lang('Arrangements made for your space'), lang('A weekly or fortnightly change'), lang('Vases and care included'), lang('One monthly invoice'))),
);
$event_prices = array(
    'weddings'  => array('USD' => 1500, 'EUR' => 1400, 'GBP' => 1200, 'TRY' => 52000),
    'events'    => array('USD' => 600, 'EUR' => 550, 'GBP' => 480, 'TRY' => 21000),
    'corporate' => array('USD' => 250, 'EUR' => 230, 'GBP' => 200, 'TRY' => 8700),
);

// Where the florist delivers and what it costs. 'same_day' says whether an
// order before the cut-off arrives the same day.
$zones = array(
    'centre' => array('icon' => 'bi-geo-alt', 'title' => lang('City Centre'), 'same_day' => true,
        'text' => lang('The old town and the streets around it, by our own van.')),
    'city'   => array('icon' => 'bi-signpost-split', 'title' => lang('Rest of the City'), 'same_day' => true,
        'text' => lang('Every neighbourhood inside the ring road.')),
    'nearby' => array('icon' => 'bi-truck', 'title' => lang('Nearby Towns'), 'same_day' => false,
        'text' => lang('Towns within an hour\'s drive, delivered the next day.')),
);
$zone_fees = array(
    'centre' => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 150),
    'city'   => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 250),
    'nearby' => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 450),
);
// The delivery times an order request may ask for.
$slots = array(
    'morning'   => array('icon' => 'bi-sunrise', 'title' => lang('Morning, 09:00 to 12:00')),
    'afternoon' => array('icon' => 'bi-sun', 'title' => lang('Afternoon, 12:00 to 17:00')),
    'evening'   => array('icon' => 'bi-sunset', 'title' => lang('Evening, 17:00 to 20:00')),
);

// A bouquet's "From …", a plan's "… per delivery" and the price of its
// three months paid at once, an event service's "Starting at …", a delivery
// area's fee.
$bouquet_price_text = function ($key) use ($bouquet_prices, $money) {
    return sprintf(lang('From %s'), $money($bouquet_prices[$key]));
};
$plan_price_text = function ($key) use ($plan_prices, $money) {
    return sprintf(lang('%s per delivery'), $money($plan_prices[$key]));
};
$plan_total = function ($key) use ($plans, $plan_prices) {
    $out = array();
    foreach ($plan_prices[$key] as $cur => $amount) $out[$cur] = $amount * (int)$plans[$key]['deliveries'];
    return $out;
};
$plan_total_text = function ($key) use ($plans, $plan_total, $money) {
    return sprintf(lang('%1$s for %2$d deliveries over three months'), $money($plan_total($key)), (int)$plans[$key]['deliveries']);
};
$event_price_text = function ($key) use ($event_prices, $money) {
    return sprintf(lang('Starting at %s'), $money($event_prices[$key]));
};
$zone_fee_text = function ($key) use ($zone_fees, $money) {
    return sprintf(lang('Delivery %s'), $money($zone_fees[$key]));
};

// The seasons, with the flowers each brings. Our Work's second filter.
// Each answer's value is its own words, so a filter link (?season=) reads
// as it shows.
$seasons = array(
    'spring' => array('icon' => 'bi-flower2', 'title' => lang('Spring'), 'flowers' => array(lang('Tulips'), lang('Ranunculus'), lang('Hyacinths'), lang('Blossom branches'))),
    'summer' => array('icon' => 'bi-sun', 'title' => lang('Summer'), 'flowers' => array(lang('Peonies'), lang('Sunflowers'), lang('Sweet peas'), lang('Garden roses'))),
    'autumn' => array('icon' => 'bi-tree-fill', 'title' => lang('Autumn'), 'flowers' => array(lang('Dahlias'), lang('Chrysanthemums'), lang('Rose hips'), lang('Autumn leaves'))),
    'winter' => array('icon' => 'bi-snow', 'title' => lang('Winter'), 'flowers' => array(lang('Amaryllis'), lang('Anemones'), lang('Hellebores'), lang('Pine and berries'))),
);

// How to keep cut flowers fresh: the care page, one step a line.
$care = array(
    array('title' => lang('Trim the stems'), 'text' => lang('Cut two centimetres off each stem at an angle, with a sharp knife or scissors.')),
    array('title' => lang('Fresh water'), 'text' => lang('Fill a clean vase with cool water and add the flower food that came with the bouquet.')),
    array('title' => lang('Leaves out of the water'), 'text' => lang('Take off every leaf that would sit below the water line; they spoil it.')),
    array('title' => lang('A cool spot'), 'text' => lang('Keep the vase away from sunshine, radiators and the fruit bowl.')),
    array('title' => lang('Change the water'), 'text' => lang('Every two days, change the water and trim the stems again.')),
);

// The sample work: the records the Our Work form starts with. 'type' is
// the event service; 'flowers' the stems used, one line each.
$works = array(
    'garden-wedding'  => array('title' => lang('A Garden Wedding in Blush'), 'type' => 'weddings', 'season' => 'summer',
        'summary'  => lang('Blush peonies, garden roses and trailing greenery for a wedding under the trees.'),
        'story'    => lang('The couple wanted their garden wedding to feel gathered rather than arranged. We built a loose arch of roses and jasmine for the ceremony, lined the aisle with jars of sweet peas, and set long tables with low runners of peonies and candles.'),
        'flowers'  => array(lang('Peonies'), lang('Garden roses'), lang('Sweet peas'), lang('Jasmine')),
        'featured' => true),
    'winter-dinner'   => array('title' => lang('A Candlelit Winter Dinner'), 'type' => 'events', 'season' => 'winter',
        'summary'  => lang('Deep red amaryllis, pine and candlelight for a family dinner on the longest night.'),
        'story'    => lang('Sixty guests at one long table: we dressed it with pine, red amaryllis, anemones and brass candlesticks, and left a small posy at every place to take home.'),
        'flowers'  => array(lang('Amaryllis'), lang('Anemones'), lang('Pine and berries')),
        'featured' => true),
    'hotel-lobby'     => array('title' => lang('A Hotel Lobby Through the Year'), 'type' => 'corporate', 'season' => 'spring',
        'summary'  => lang('A statement arrangement for a hotel lobby, changed every week with the season.'),
        'story'    => lang('The hotel asked for one large arrangement guests would remember. Every Monday we bring a new one: blossom branches and tulips in spring, dahlias in autumn, and the reception desk gets a matching posy.'),
        'flowers'  => array(lang('Blossom branches'), lang('Tulips'), lang('Ranunculus')),
        'featured' => true),
    'autumn-wedding'  => array('title' => lang('An Autumn Wedding in Rust and Gold'), 'type' => 'weddings', 'season' => 'autumn',
        'summary'  => lang('Dahlias, rose hips and autumn leaves in warm rust and gold.'),
        'story'    => lang('Rust, copper and gold from the bridal bouquet to the cake: dahlias at their best, rose hips, dried grasses and autumn leaves from the growers\' own trees.'),
        'flowers'  => array(lang('Dahlias'), lang('Rose hips'), lang('Autumn leaves')),
        'featured' => false),
);
// A work's flowers as the value of its Flowers field: one line each.
$work_flowers_text = function ($key) use ($works) {
    return implode("\n", $works[$key]['flowers']);
};

// Why customers choose the florist (the home and the about page).
$why_us = array(
    array('icon' => 'bi-flower1', 'title' => lang('Fresh every morning'),
        'text' => lang('We buy our stems from the growers each morning and arrange them the same day.')),
    array('icon' => 'bi-truck', 'title' => lang('Delivered today'),
        'text' => lang('Order by the afternoon and we deliver in the city the same day.')),
    array('icon' => 'bi-pencil', 'title' => lang('Your words on the card'),
        'text' => lang('We write your message by hand on a card that goes with the flowers.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Happy or we replace them'),
        'text' => lang('If the flowers are not right, tell us within a day and we replace them.')),
);

// The florist in four figures (the home and about pages).
$figures = array(
    array('value' => '25,000+', 'label' => lang('Bouquets delivered')),
    array('value' => '300+', 'label' => lang('Weddings and events')),
    array('value' => '3', 'label' => lang('Delivery areas')),
    array('value' => '4.9', 'label' => lang('Average customer rating')),
);

// What customers say, each about one of the bouquets or a sample work.
$testimonials = array(
    array('name' => lang('Elif Kaya'), 'role' => lang('Sent flowers for a birthday'), 'bouquet' => 'sunny-days',
        'quote' => lang('The bouquet arrived the same afternoon and looked even better than the picture. My sister cried.')),
    array('name' => lang('Daniel Brooks'), 'role' => lang('Fortnightly subscriber'), 'bouquet' => 'florists-choice',
        'quote' => lang('Every other Friday there is something new and beautiful on our table. We would not be without it.')),
    array('name' => lang('Ayşe and Mert'), 'role' => lang('Wedding flowers'), 'bouquet' => 'blush',
        'quote' => lang('Our garden wedding looked like a dream. Everything was set up before we arrived, and the bouquet was perfect.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the customer chose.
$occasion_titles = array();
foreach ($occasions as $o) $occasion_titles[] = $o['title'];
$bouquet_titles = array();
foreach ($bouquets as $b) $bouquet_titles[] = $b['title'];
$size_titles = array();
foreach ($sizes as $s) $size_titles[] = $s['title'];
$plan_titles = array();
foreach ($plans as $p) $plan_titles[] = $p['title'];
$event_titles = array();
foreach ($event_services as $s) $event_titles[] = $s['title'];
$zone_titles = array();
foreach ($zones as $z) $zone_titles[] = $z['title'];
$slot_titles = array();
foreach ($slots as $s) $slot_titles[] = $s['title'];
$season_titles = array();
foreach ($seasons as $s) $season_titles[] = $s['title'];
$work_titles = array();
foreach ($works as $w) $work_titles[] = $w['title'];
$occasion_choices = array_merge($occasion_titles, array(lang('Just because')));
// The florist's choice is a bouquet of its own, so it is one answer here.
$bouquet_choices = array_merge($bouquet_titles, array(lang('Something made for me')));
$event_choices = array_merge($event_titles, array(lang('Something else')));
$weekday_choices = array(lang('Monday'), lang('Tuesday'), lang('Wednesday'), lang('Thursday'), lang('Friday'), lang('Saturday'));
// The budget bands of an event in the shop's currency, as the prices are
// written.
$budget_low = $money(array('USD' => 1000, 'EUR' => 900, 'GBP' => 800, 'TRY' => 35000));
$budget_high = $money(array('USD' => 3000, 'EUR' => 2800, 'GBP' => 2400, 'TRY' => 105000));
$budget_choices = array(
    sprintf(lang('Up to %s in all'), $budget_low),
    sprintf(lang('%1$s to %2$s in all'), $budget_low, $budget_high),
    sprintf(lang('More than %s in all'), $budget_high),
    lang('Not sure yet'),
);
$card_choices = array(
    array(lang('Write my message on a card'), lang('Write my message on a card')),
    array(lang('No card'), lang('No card')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of an order request and of an event enquiry, set by the
// florist in the panel.
$request_statuses = array(lang('Request received'), lang('Confirmed'), lang('Being arranged'), lang('Out for delivery'), lang('Delivered'), lang('Cancelled'));
$enquiry_statuses = array(lang('Enquiry received'), lang('Consultation booked'), lang('Proposal sent'), lang('Booked'), lang('Declined'));

// The gift cards and extras the shop sells beside the bouquets (with the
// shop). The SKUs name the template, so the products are its own.
$extras = array(
    'vase'      => array('sku' => 'FLORIST-VASE', 'icon' => 'bi-cup', 'title' => lang('Glass Vase'), 'shippable' => true,
        'text'  => lang('A clear glass vase that fits the regular and deluxe bouquets.'),
        'price' => array('USD' => 18, 'EUR' => 16, 'GBP' => 14, 'TRY' => 600)),
    'chocolates' => array('sku' => 'FLORIST-CHOC', 'icon' => 'bi-box2-heart', 'title' => lang('Box of Chocolates'), 'shippable' => true,
        'text'  => lang('Twelve hand-made chocolates from the bakery next door.'),
        'price' => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 500)),
    'gift50'    => array('sku' => 'FLORIST-GIFT-50', 'icon' => 'bi-gift', 'title' => lang('Flower Gift Card'), 'shippable' => false,
        'text'  => lang('A gift card for any bouquet or subscription, valid for a year.'),
        'price' => array('USD' => 50, 'EUR' => 50, 'GBP' => 50, 'TRY' => 2000)),
);

// The query string of a link that opens a list with a filter set
// (?type=, ?season=) or a request with a choice made (?bouquet=,
// ?occasion=, ?plan=, ?event=): the form's fields start with these values
// (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the delivery band above the footer unless the page
// is a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('delivery_band');
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
            $heading('h1', $title, 'pfl-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pfl-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pfl-display h3 mb-0'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'fl-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the same-day cut-off and the delivery page.
// A shared component.
$notice = function () use ($el, $icon, $span, $link, $cutoff_text) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-truck', 'text-primary'),
        $span($cutoff_text(), '', lang('Text')),
        $link(lang('Delivery details'), '{{page:delivery}}', 'link-primary fw-semibold'),
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

// The header: the florist's name, the menu and the way to order. The links
// to the current page are marked active on the server (smart active
// state), so one header serves every page. The shop's pages are linked
// from the shop pages only, so the header stands on a site without the
// shop: "Order Flowers" opens the order request.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('bouquets', lang('Bouquets')),
        array('occasions', lang('Special Occasions')),
        array('subscriptions', lang('Subscriptions')),
        array('events', lang('Weddings and Events')),
        array('work', lang('Our Work')),
        array('delivery', lang('Delivery')),
        array('about', lang('About Us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-flower1', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pfl-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#fl-nav'),
                    $attr('aria-controls', 'fl-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-flower2'),
                            $span(lang('Order Flowers'), '', lang('Link Text')),
                        ), array('href' => '{{page:order_request}}')),
                    )),
                ), array('id' => 'fl-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the florist in a sentence, how to reach it
// and its profiles, flowers for each occasion (the order request with the
// occasion chosen), Our Work by kind, the customer's pages and the
// florist's pages; at the bottom the year, the site's name and the
// promise.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $occasions, $event_services, $florist, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $occasion_links = array();
    foreach ($occasions as $o) $occasion_links[] = $link($o['title'], '{{page:order_request}}?' . $query(array('occasion' => $o['title'])), $plain);
    $work_links = array($link(lang('All Our Work'), '{{page:work}}', $plain));
    foreach ($event_services as $s) $work_links[] = $link($s['title'], '{{page:work}}?' . $query(array('type' => $s['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Florist'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-flower1', 'text-primary'),
                        $span('{{site_name}}', 'pfl-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($florist['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($florist['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($florist['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $florist['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($florist['email'], 'mailto:' . $florist['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($florist['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-pinterest', 'Pinterest'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-whatsapp', 'WhatsApp'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Occasion Links'), array(
                    $heading('h2', lang('Flowers For'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($occasion_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Work Links'), array(
                    $heading('h2', lang('Our Work'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($work_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('For Customers'), array(
                    $heading('h2', lang('For Customers'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Order Flowers'), '{{page:order_request}}', $plain),
                        $link(lang('Start a Subscription'), '{{page:subscribe}}', $plain),
                        $link(lang('My Requests'), '{{page:my_requests}}', $plain),
                        $link(lang('Flower Care'), '{{page:care}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Florist'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Weddings and Events'), '{{page:events}}', $plain),
                        $link(lang('Book a Consultation'), '{{page:consultation}}', $plain),
                        $link(lang('Flower Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($florist['promise'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The delivery band: a full-width band in the palette's colour with the
// same-day cut-off, the way to order and the delivery page. Shared, so
// every page that ends with it carries the same call.
$delivery_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $cutoff_text) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Flowers for today?'), 'pfl-display h2 mb-2'),
                    $para($cutoff_text() . ' ' . lang('We write your message on the card and deliver across the city.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Order Flowers'), '{{page:order_request}}', 'btn btn-light btn-lg rounded-pill px-4'),
                        $link(lang('Delivery details'), '{{page:delivery}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// ── Cards ───────────────────────────────────────────────────────────────

// A bouquet's card (the home and the bouquets page): the picture, the
// occasion and the way it comes, the name, a line, the stems, the price
// and a link that opens the order request with the bouquet chosen.
$bouquet_card = function ($key) use ($el, $heading, $para, $link, $span, $image, $col, $attr, $bouquets, $occasions, $bouquet_price_text, $photo, $query) {
    $b = $bouquets[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pfl-lift', lang('Bouquet Card'), array(
            $el('span', 'd-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('bouquet-' . $key, 600, 600), $b['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'pfl-eyebrow d-flex flex-wrap gap-2 small text-uppercase fw-semibold text-primary mb-1', lang('Occasion and Style'), array(
                    $span($occasions[$b['occasion']]['title'], '', lang('Special Occasion')),
                    $span('·', '', lang('Separator')),
                    $span($b['style'], '', lang('Style')),
                )),
                $heading('h3', $b['title'], 'pfl-display h4 mb-2'),
                $para($b['text'], 'small text-body-secondary mb-2'),
                $para(implode(', ', $b['flowers']), 'small mb-3'),
                $para($bouquet_price_text($key), 'pfl-price fs-5 fw-semibold mt-auto mb-3'),
                $link(lang('Order this bouquet'), '{{page:order_request}}?' . $query(array('bouquet' => $b['title'])), 'btn btn-outline-primary rounded-pill'),
            )),
        )),
    ), '6', '3');
};

// An occasion's card (the home and the occasions page): the icon, the
// name, a line and a link that opens the order request with the occasion
// chosen. Its anchor lets a page link straight to it.
$occasion_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $occasions, $query) {
    $o = $occasions[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pfl-lift', lang('Special Occasion Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($o['icon'], 'mb-3 align-self-start'),
                $heading('h3', $o['title'], 'pfl-display h4 mb-2'),
                $para($o['text'], 'small text-body-secondary mb-3'),
                $link(lang('Send flowers'), '{{page:order_request}}?' . $query(array('occasion' => $o['title'])), 'link-primary fw-semibold mt-auto'),
            )),
        ), array('id' => 'fl-occasion-' . str_replace('_', '-', $key))),
    ), '6', '4');
};

// A subscription plan's card (the home and the subscriptions page): the
// icon, the name, how often, what it includes, the price per delivery and
// for three months, and a link that opens the subscription request with
// the plan chosen.
$plan_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon_tile, $check_list, $plans, $plan_price_text, $plan_total_text, $query) {
    $p = $plans[$key];
    $head = array($heading('h3', $p['title'], 'pfl-display h3 mb-0'));
    if ($p['featured']) $head[] = $span(lang('Most loved'), 'badge rounded-pill text-bg-primary', lang('Badge'));
    return $col(array(
        $el('article', 'card h-100 rounded-4 pfl-lift ' . ($p['featured'] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Plan Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($p['icon'], 'mb-3 align-self-start'),
                $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-1', lang('Plan Heading'), $head),
                $span($p['every'], 'pfl-eyebrow d-block small text-uppercase fw-semibold text-primary mb-2', lang('How Often')),
                $para($p['text'], 'mb-3'),
                $check_list($p['includes'], 'list-unstyled small mb-4'),
                $para($plan_price_text($key), 'pfl-price fs-5 fw-semibold mt-auto mb-1'),
                $para($plan_total_text($key), 'small text-body-secondary mb-3'),
                $link(lang('Start this subscription'), '{{page:subscribe}}?' . $query(array('plan' => $p['title'])), 'btn ' . ($p['featured'] ? 'btn-primary' : 'btn-outline-primary') . ' rounded-pill'),
            )),
        )),
    ), '', '4');
};

// A wedding or event service's card (the home and the events page): the
// icon, the name, a line, what it includes, the starting price and a link
// that opens the event enquiry with the service chosen.
$event_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $check_list, $event_services, $event_price_text, $query) {
    $s = $event_services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pfl-lift', lang('Event Service Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3 align-self-start'),
                $heading('h3', $s['title'], 'pfl-display h4 mb-2'),
                $para($s['text'], 'small text-body-secondary mb-3'),
                $check_list($s['includes'], 'list-unstyled small mb-4'),
                $para($event_price_text($key), 'pfl-price fw-semibold mt-auto mb-3'),
                $link(lang('Ask about your event'), '{{page:event_enquiry}}?' . $query(array('event' => $s['title'])), 'btn btn-outline-primary rounded-pill'),
            )),
        )),
    ), '', '4');
};

// A delivery area's card (the delivery page): the icon, the name, where it
// reaches, whether it is same day, and the fee.
$zone_card = function ($key) use ($el, $heading, $para, $span, $col, $icon, $icon_tile, $zones, $zone_fee_text) {
    $z = $zones[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Delivery Area Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($z['icon'], 'mb-3 align-self-start'),
                $heading('h3', $z['title'], 'pfl-display h4 mb-2'),
                $para($z['text'], 'small text-body-secondary mb-3'),
                $el('p', 'd-flex align-items-center gap-1 small fw-semibold mb-2', lang('When'), array(
                    $icon($z['same_day'] ? 'bi-lightning-charge' : 'bi-calendar-event', 'text-primary'),
                    $span($z['same_day'] ? lang('Same-day delivery') : lang('Next-day delivery'), '', lang('Text')),
                )),
                $para($zone_fee_text($key), 'pfl-price fw-semibold mt-auto mb-0'),
            )),
        )),
    ), '', '4');
};

// A season's card (the home and the care page): the icon, the season and
// the flowers it brings.
$season_card = function ($key) use ($el, $heading, $col, $icon_tile, $check_list, $seasons) {
    $s = $seasons[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-body-tertiary rounded-4', lang('Season Card'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3'),
                $heading('h3', $s['title'], 'pfl-display h4 mb-3'),
                $check_list($s['flowers'], 'list-unstyled small mb-0'),
            )),
        )),
    ), '6', '3');
};

// A work's card of the lists (the home and Our Work): the photo, the kind
// and season, the title linking the work's page, the summary. Every value
// is bound to the Our Work form's field of the same name; the sample fills
// the canvas.
$work_card = function ($sample_key = 'garden-wedding') use ($el, $para, $span, $link, $image, $col, $attr, $bind, $works, $event_services, $seasons, $photo) {
    $w = $works[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pfl-lift', lang('Work Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('work-' . $sample_key, 800, 600), $w['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'pfl-eyebrow d-flex flex-wrap gap-2 small text-uppercase fw-semibold text-primary mb-1', lang('Kind and Season'), array(
                    $span($event_services[$w['type']]['title'], '', lang('Kind'), $bind('type')),
                    $span('·', '', lang('Separator')),
                    $span($seasons[$w['season']]['title'], '', lang('Season'), $bind('season')),
                )),
                $el('h3', 'pfl-display h4 position-static mb-2', lang('Title'), array(
                    $link($w['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($w['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// What a customer says: the quote, the stars, the name and what they
// ordered.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Customer Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pfl-quote mb-0'))),
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
                $item('staff_requests', lang('Flower Orders')),
                $item('staff_events', lang('Event Enquiries')),
                $item('work_new', lang('New Work')),
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
    return $agree($id, 'consent', lang('I would like to receive the florist\'s news and Flower Journal posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// event enquiry takes documents (a mood board, a floor plan) and pictures,
// so the default accepts PDFs, office files, text and pictures; one that
// takes only pictures is a collection's image kind (a work's photos).
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
                    $link(lang('How to make a bouquet last a week longer'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

