<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marketplace", part 1 of its files: the node builders,
 * the market itself (its categories, the makers whose shops it carries,
 * their products and prices, what every order comes with) and the parts
 * every page repeats (account bar, header with the search, footer, maker
 * band, page shells, section headers, product, maker and category cards,
 * stars, form fields, comment thread, staff bar). The page files of the
 * same folder are included after it in the same scope and build their
 * trees with these variables; includes/design_templates/pinegrap-market.php
 * puts them together.
 *
 * The site owner sells everything: one cart, one checkout and one order for
 * products of every shop. A maker is a profile (a record of the Sellers
 * collection) and a product group of the catalog, never an account of its
 * own. Every figure is written here once: the cards, the maker samples and
 * the shop's products all read it.
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


// A sample photo with a fixed seed, so the same picture comes back on every


// A sample photo with a fixed seed, so the same picture comes back on every
// opening until the site owner puts its own in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-market-' . $seed . '/' . (int)$width . '/' . (int)$height;
};

// ── The market: categories, makers, products ────────────────────────────

// The categories, in the order of the menu and the category tiles. Each has
// its section on the categories page (#category-<key>), its product group
// in the shop and the words a visitor may search for.
$categories = array(
    'home'      => array('title' => lang('Home and Living'), 'icon' => 'bi-house-heart',
        'text'   => lang('Bowls, boards and blankets made to be used every day.'),
        'topics' => lang('ceramics, pottery, woodwork, kitchen, tableware, decor')),
    'jewellery' => array('title' => lang('Jewellery'), 'icon' => 'bi-gem',
        'text'   => lang('Rings, earrings and necklaces cut, cast and polished by hand.'),
        'topics' => lang('rings, earrings, necklaces, silver, gold, gifts')),
    'art'       => array('title' => lang('Art and Prints'), 'icon' => 'bi-palette',
        'text'   => lang('Prints, cards and small originals from independent artists.'),
        'topics' => lang('prints, posters, illustration, cards, paintings, wall art')),
    'fashion'   => array('title' => lang('Bags and Clothing'), 'icon' => 'bi-bag-heart',
        'text'   => lang('Woven bags, scarves and knitwear in natural fibres.'),
        'topics' => lang('bags, scarves, knitwear, wool, linen, accessories')),
    'pantry'    => array('title' => lang('Pantry'), 'icon' => 'bi-cup-hot',
        'text'   => lang('Olive oil, honey, tea and preserves from small producers.'),
        'topics' => lang('food, olive oil, honey, tea, jam, gifts to eat')),
);

// The cities the makers work in: the maker list's ?city= filter offers them.
$cities = array(
    'lisbon'   => lang('Lisbon'),
    'bristol'  => lang('Bristol'),
    'izmir'    => lang('Izmir'),
    'krakow'   => lang('Krakow'),
    'oslo'     => lang('Oslo'),
    'valencia' => lang('Valencia'),
);

// The makers whose shops the market carries. Each is a sample record of
// the Sellers collection and a product group under "Shops" in the catalog;
// 'category' and 'city' are keys of the lists above, 'featured' a shop the
// home page puts first. The names are fictional.
$sellers = array(
    'clay'   => array('name' => lang('Tide and Clay Studio'), 'maker' => lang('Inês Duarte'), 'city' => 'lisbon',
        'category' => 'home', 'featured' => true,
        'tagline'  => lang('Stoneware thrown by the sea.'),
        'story'    => lang('Inês throws every bowl and cup on one wheel in a studio by the river, and glazes them in the colours of the coast.')),
    'silver' => array('name' => lang('Little Silver Works'), 'maker' => lang('Hannah Webb'), 'city' => 'bristol',
        'category' => 'jewellery', 'featured' => true,
        'tagline'  => lang('Recycled silver, hammered by hand.'),
        'story'    => lang('Hannah melts down old silver and hammers it into rings and earrings light enough to wear every day.')),
    'ink'    => array('name' => lang('Inkwell Press'), 'maker' => lang('Mert Aydın'), 'city' => 'izmir',
        'category' => 'art', 'featured' => true,
        'tagline'  => lang('Prints pulled one sheet at a time.'),
        'story'    => lang('Mert carves linocuts of harbours and markets and prints them by hand on cotton paper, in small numbered runs.')),
    'loom'   => array('name' => lang('North Loom'), 'maker' => lang('Kasia Nowak'), 'city' => 'krakow',
        'category' => 'fashion', 'featured' => false,
        'tagline'  => lang('Woven on a wooden loom from undyed wool.'),
        'story'    => lang('Kasia weaves bags and scarves from the wool of mountain sheep, in the natural greys and creams of the fleece.')),
    'timber' => array('name' => lang('Timber and Twine'), 'maker' => lang('Lars Berg'), 'city' => 'oslo',
        'category' => 'home', 'featured' => false,
        'tagline'  => lang('Boards and spoons from fallen trees.'),
        'story'    => lang('Lars carves serving boards and spoons from storm-fallen birch and oak, finished with oil and beeswax.')),
    'grove'  => array('name' => lang('Olive Grove Pantry'), 'maker' => lang('Lucía Ferrer'), 'city' => 'valencia',
        'category' => 'pantry', 'featured' => false,
        'tagline'  => lang('From our family grove to your table.'),
        'story'    => lang('Lucía presses olives from her family\'s grove and fills each tin, jar and bottle in the same village.')),
);

// The products of the shop, three for each maker. The SKU is
// MK-<MAKER>-<N> ($product_sku, N from 1); a product sits in its maker's
// group and in its maker's category; prices per currency in major units.
// 'featured' marks the products the home page shows first.
$market_products = array(
    'clay' => array(
        array('title' => lang('Speckled Breakfast Bowl'), 'featured' => true,
            'text'  => lang('A wide stoneware bowl with a sea-green glaze; safe in the dishwasher and the oven.'),
            'price' => array('USD' => 34, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1490)),
        array('title' => lang('Pair of Tide Cups'), 'featured' => false,
            'text'  => lang('Two handleless cups that hold a long coffee, glazed inside and raw outside.'),
            'price' => array('USD' => 42, 'EUR' => 40, 'GBP' => 35, 'TRY' => 1850)),
        array('title' => lang('Stoneware Serving Plate'), 'featured' => false,
            'text'  => lang('A large plate for the middle of the table, its rim left unglazed.'),
            'price' => array('USD' => 58, 'EUR' => 55, 'GBP' => 48, 'TRY' => 2550)),
    ),
    'silver' => array(
        array('title' => lang('Hammered Silver Ring'), 'featured' => true,
            'text'  => lang('A slim band of recycled silver with a hammered finish, made to your size.'),
            'price' => array('USD' => 65, 'EUR' => 62, 'GBP' => 54, 'TRY' => 2850)),
        array('title' => lang('Pebble Stud Earrings'), 'featured' => false,
            'text'  => lang('Small round studs, each one a little different, on silver posts.'),
            'price' => array('USD' => 48, 'EUR' => 45, 'GBP' => 40, 'TRY' => 2100)),
        array('title' => lang('Crescent Necklace'), 'featured' => false,
            'text'  => lang('A crescent moon on a fine silver chain, in a recycled card box.'),
            'price' => array('USD' => 79, 'EUR' => 75, 'GBP' => 66, 'TRY' => 3450)),
    ),
    'ink' => array(
        array('title' => lang('Harbour at Dawn, Linocut'), 'featured' => true,
            'text'  => lang('A hand-pulled linocut in two blues on cotton paper, signed and numbered.'),
            'price' => array('USD' => 85, 'EUR' => 80, 'GBP' => 70, 'TRY' => 3700)),
        array('title' => lang('Market Day Print'), 'featured' => false,
            'text'  => lang('A busy market in three colours, printed in a run of fifty.'),
            'price' => array('USD' => 60, 'EUR' => 57, 'GBP' => 50, 'TRY' => 2600)),
        array('title' => lang('Set of Six Cards'), 'featured' => false,
            'text'  => lang('Six folded cards of the harbour prints, blank inside, with envelopes.'),
            'price' => array('USD' => 22, 'EUR' => 21, 'GBP' => 18, 'TRY' => 950)),
    ),
    'loom' => array(
        array('title' => lang('Undyed Wool Tote'), 'featured' => true,
            'text'  => lang('A sturdy woven tote with leather handles, big enough for a laptop.'),
            'price' => array('USD' => 95, 'EUR' => 90, 'GBP' => 79, 'TRY' => 4150)),
        array('title' => lang('Mountain Wool Scarf'), 'featured' => false,
            'text'  => lang('A long, soft scarf in the natural grey of the fleece.'),
            'price' => array('USD' => 70, 'EUR' => 66, 'GBP' => 58, 'TRY' => 3050)),
        array('title' => lang('Woven Pouch'), 'featured' => false,
            'text'  => lang('A small zipped pouch for pens, cables or make-up.'),
            'price' => array('USD' => 30, 'EUR' => 28, 'GBP' => 25, 'TRY' => 1300)),
    ),
    'timber' => array(
        array('title' => lang('Birch Serving Board'), 'featured' => true,
            'text'  => lang('A long board for bread and cheese, oiled and ready to use.'),
            'price' => array('USD' => 55, 'EUR' => 52, 'GBP' => 45, 'TRY' => 2400)),
        array('title' => lang('Carved Oak Spoon'), 'featured' => false,
            'text'  => lang('A cooking spoon carved from one piece of oak, finished with beeswax.'),
            'price' => array('USD' => 26, 'EUR' => 25, 'GBP' => 22, 'TRY' => 1150)),
        array('title' => lang('Set of Four Coasters'), 'featured' => false,
            'text'  => lang('Four round coasters cut from one branch, so the rings match.'),
            'price' => array('USD' => 28, 'EUR' => 26, 'GBP' => 23, 'TRY' => 1200)),
    ),
    'grove' => array(
        array('title' => lang('Early Harvest Olive Oil'), 'featured' => true,
            'text'  => lang('Half a litre of green, peppery extra virgin oil from the first pressing.'),
            'price' => array('USD' => 24, 'EUR' => 22, 'GBP' => 20, 'TRY' => 1050)),
        array('title' => lang('Orange Blossom Honey'), 'featured' => false,
            'text'  => lang('A jar of raw honey from hives among the orange groves.'),
            'price' => array('USD' => 16, 'EUR' => 15, 'GBP' => 13, 'TRY' => 690)),
        array('title' => lang('Pantry Gift Box'), 'featured' => false,
            'text'  => lang('Oil, honey and almonds in a wooden box, ready to give.'),
            'price' => array('USD' => 49, 'EUR' => 46, 'GBP' => 40, 'TRY' => 2150)),
    ),
);
$product_sku = function ($seller, $n) {
    return 'MK-' . strtoupper($seller) . '-' . (int)$n;
};

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A card and the product it sells therefore show one amount.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// What every order comes with, on the home page and the how-it-works page:
// sample terms the site owner changes to its own.
$market_promises = array(
    array('icon' => 'bi-basket', 'title' => lang('One cart for every shop'), 'text' => lang('Buy from several makers and pay once; we send it all.')),
    array('icon' => 'bi-hand-thumbs-up', 'title' => lang('Makers chosen by hand'), 'text' => lang('We meet every maker and see their work before their shop opens.')),
    array('icon' => 'bi-truck', 'title' => lang('Tracked delivery'), 'text' => lang('Every order is packed with care and tracked to your door.')),
    array('icon' => 'bi-arrow-counterclockwise', 'title' => lang('30 days to return'), 'text' => lang('Not right? Send it back within 30 days, whichever shop it came from.')),
);

// How the market works, in the order the how-it-works page tells it.
$market_steps = array(
    array(lang('Find something made by hand'), lang('Browse by category or by maker, or search for what you have in mind.')),
    array(lang('Order from several shops at once'), lang('Everything goes into one cart and one checkout.')),
    array(lang('We pack and send it'), lang('Your order is checked, packed and tracked to your door.')),
    array(lang('Tell the maker what you think'), lang('Leave a review on the maker\'s page; every review is read.')),
);

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: what the market offers. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-truck', 'text-primary'),
        $span(lang('One cart for every shop, tracked delivery and 30 days to return.'), '', lang('Text')),
        $link(lang('How it works'), '{{page:how_it_works}}', 'link-primary fw-semibold text-nowrap'),
    ));
};

// The bar above the header: the notice on the left, the language switcher,
// the login region and the cart (with the shop) on the right. Every page
// places it as one shared component, so a change to it is made once.
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

// The search box of the header: the site search, which reads ?query= and
// finds products, makers and pages alike. It stands without the shop.
$search_box = function ($id, $class = '') use ($el, $attr, $icon) {
    return $el('form', $class, lang('Search Form'), array(
        $el('div', 'input-group', lang('Search Box'), array(
            $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Search the market'), '_attrs' => array($attr('for', $id)))),
            $el('input', 'form-control rounded-start-pill', lang('Field'), array(), array('id' => $id, '_attrs' => array(
                $attr('type', 'search'), $attr('name', 'query'), $attr('placeholder', lang('Bowls, rings, prints…')),
            ))),
            $el('button', 'btn btn-primary rounded-end-pill px-3', lang('Search Button'), array($icon('bi-search')), array('_attrs' => array(
                $attr('type', 'submit'), $attr('aria-label', lang('Search')),
            ))),
        )),
    ), array('_attrs' => array($attr('action', '{{page:search}}'), $attr('method', 'get'), $attr('role', 'search'))));
};

// The header: the market's name, the search, the menu and the button for
// makers. The links to the current page are marked active on the server
// (smart active state), which is what lets one header serve every page.
// The menu names only pages that stand without the shop.
$header = function () use ($el, $link, $icon, $span, $attr, $search_box) {
    $items = array();
    foreach (array(
        array('categories', lang('Categories')),
        array('sellers', lang('Makers')),
        array('gift_guide', lang('Gift Guide')),
        array('how_it_works', lang('How it works')),
        array('blog', lang('Journal')),
        array('help', lang('Help')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom sticky-top', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-2', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $icon('bi-shop', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'fs-5 pmk-brand text-truncate', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#mk-nav'),
                    $attr('aria-controls', 'mk-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $search_box('mk-search', 'flex-grow-1 mx-xl-3 my-3 my-xl-0 pmk-search'),
                    $el('ul', 'navbar-nav column-gap-xl-1 pb-3 pb-xl-0 small', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 ms-xl-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-outline-primary btn-sm rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-3 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-shop-window'),
                            $span(lang('Sell with us'), '', lang('Link Text')),
                        ), array('href' => '{{page:sell}}')),
                    )),
                ), array('id' => 'mk-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the market in a sentence, the categories,
// help, makers and the company. Every link names a page that stands
// without the shop.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $attr, $categories) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $column = function ($title, $links) use ($el, $heading, $list) {
        return $el('div', 'col-6 col-md-3 col-lg-2', $title, array(
            $heading('h2', $title, 'h6 text-uppercase small fw-semibold mb-3'),
            $list($links),
        ));
    };
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $category_links = array();
    foreach ($categories as $key => $c) {
        $category_links[] = $link($c['title'], '{{page:categories}}#category-' . $key, $plain);
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Market'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-bold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-shop', 'text-primary'),
                        $span('{{site_name}}', 'pmk-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('A market of independent makers: things made by hand, in small numbers, by people you can name.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-pinterest', 'Pinterest'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-tiktok', 'TikTok'),
                    )),
                )),
                $column(lang('Categories'), $category_links),
                $column(lang('Help'), array(
                    $link(lang('How it works'), '{{page:how_it_works}}', $plain),
                    $link(lang('FAQ'), '{{page:faq}}', $plain),
                    $link(lang('Help with an Order'), '{{page:help}}', $plain),
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                )),
                $column(lang('Makers'), array(
                    $link(lang('All Makers'), '{{page:sellers}}', $plain),
                    $link(lang('Sell with us'), '{{page:sell}}', $plain),
                    $link(lang('Apply to Sell'), '{{page:apply}}', $plain),
                    $link(lang('Journal'), '{{page:blog}}', $plain),
                )),
                $column(lang('Company'), array(
                    $link(lang('About Us'), '{{page:about}}', $plain),
                    $link(lang('Gift Guide'), '{{page:gift_guide}}', $plain),
                    $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Every order is sold and sent by us; the makers make it.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The maker band: the invitation to open a shop. Shared, so every page that
// ends with it carries the same words.
$maker_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary-subtle pmk-band', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Do you make things by hand?'), 'h3 fw-bold mb-2'),
                    $para(lang('Open a shop with us: we take the photos, the orders and the parcels, you keep making.'), 'text-body-secondary mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Apply to Sell'), '{{page:apply}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                        $link(lang('Sell with us'), '{{page:sell}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The address of the maker list filtered to one category, the way a
// category's "See the makers" link opens it (the list reads ?category=).
$makers_in = function ($category_key) use ($categories) {
    return '{{page:sellers}}?category=' . rawurlencode($categories[$category_key]['title']);
};

// A category as a tile: its picture, icon, title and line, opening its
// section of the categories page.
$category_tile = function ($key) use ($el, $heading, $para, $link, $image, $icon, $col, $categories, $photo) {
    $c = $categories[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 rounded-4 overflow-hidden position-relative pmk-lift', $c['title'], array(
            $el('div', 'ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('category-' . $key, 800, 600), $c['title'], 'object-fit-cover'),
            )),
            $el('div', 'card-body p-3', lang('Card Body'), array(
                $el('h3', 'h6 fw-bold d-flex align-items-center gap-2 mb-1 position-static', lang('Title'), array(
                    $icon($c['icon'], 'text-primary'),
                    $link($c['title'], '{{page:categories}}#category-' . $key, 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($c['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '4', '', '6', '6');
};

// A product as a card: its picture, the maker who made it, its name and
// price. The card is the sample shown before the shop's own lists; it opens
// the maker list of its category, which stands without the shop.
$product_card = function ($seller_key, $n) use ($el, $heading, $para, $link, $image, $span, $col, $sellers, $market_products, $photo, $money, $makers_in) {
    $s = $sellers[$seller_key];
    $p = $market_products[$seller_key][$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative pmk-lift', $p['title'], array(
            $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo($seller_key . '-' . ($n + 1), 800, 800), $p['title'], 'object-fit-cover'),
            )),
            $el('div', 'card-body d-flex flex-column p-3', lang('Card Body'), array(
                $span($s['name'], 'small text-body-secondary mb-1', lang('Maker')),
                $el('h3', 'h6 fw-semibold mb-2 position-static', lang('Title'), array(
                    $link($p['title'], $makers_in($s['category']), 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($money($p['price']), 'fw-bold mt-auto mb-0 pmk-price'),
            )),
        )),
    ), '4', '3', '', '6');
};

// What every order comes with, as a row of four.
$promise_row = function () use ($el, $heading, $para, $icon, $row, $col, $market_promises) {
    $cols = array();
    foreach ($market_promises as $p) {
        $cols[] = $col(array(
            $el('div', 'd-flex gap-3', $p['title'], array(
                $icon($p['icon'], 'fs-3 text-primary'),
                $el('div', '', lang('Text'), array(
                    $heading('h3', $p['title'], 'h6 fw-bold mb-1'),
                    $para($p['text'], 'small text-body-secondary mb-0'),
                )),
            )),
        ), '6', '3');
    }
    return $row($cols, '4');
};

// A page: the account bar, the header, the page's own content, the
// newsletter band (left off where it would be in the way: the forms, the
// account pages) and the shared footer.
$page = function ($main, $newsletter = true) use ($root, $shared, $el, $widget) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($newsletter) $children[] = $widget('newsletter_band');
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
            $heading('h1', $title, 'display-6 fw-semibold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pmk-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'h3 fw-semibold mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the help topics are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'mk-faq') use ($el, $para, $attr, $searchable) {
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

// The comments of a record page (a maker's reviews, an application's or a
// help request's messages, a conversation's replies, a post's comments), in
// the reading column: the page's Comments
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



// ── Staff, newsletter and Journal parts ─────────────────────────────────

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
                $item('seller_new', lang('New Maker')),
                $item('staff_applications', lang('All Applications')),
                $item('staff_help', lang('All Help Requests')),
                $item('blog_new', lang('New Journal Post')),
                $item('staff_entry', lang('Add to the Staff Directory')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no newsletter.
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
    return $agree($id, 'consent', lang('I would like to hear about new makers, gift guides and offers by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. The
// help form takes a receipt or a photo of the damage, so the default
// accepts PDFs and pictures; one that takes only pictures is a
// collection's image kind (an application's photos of the work).
$file = function ($id, $name, $folder, $accept = '.pdf,.jpg,.jpeg,.png,.heic') use ($el, $attr) {
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

// A Journal post card of the lists (the Journal and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('A morning at the wheel with Tide and Clay'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

// A date field in the format given (date()'s letters), in place of the
// site's own date format.
$bind_date = function ($token, $format) {
    return array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => $format));
};
// A link to a template page that sends values of the record or list row it
// is drawn in: $params maps the address name to the token it takes (the
// renderer's __page_link, filled per row in a list).
$page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};
