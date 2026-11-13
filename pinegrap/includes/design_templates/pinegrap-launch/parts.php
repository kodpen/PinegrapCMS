<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Phone Launch Site", part 1 of its files: the node
 * builders, the phone itself (its models, colours, storage sizes,
 * specifications, prices and accessories, the highlights and what the press
 * says) and the parts every page repeats (account bar, header, footer,
 * launch band, page shells, section headers, model card, spec table,
 * colour swatches, form fields, comment thread, staff bar). The page files
 * of the same folder are included after it in the same scope and build
 * their trees with these variables; includes/design_templates/pinegrap-launch.php
 * puts them together.
 *
 * Every figure of the phone is written here once: the model cards, the
 * specification and compare tables and the shop's products all read it, so
 * a figure changed here changes everywhere. The site names no launch date:
 * the phone is "new", never "coming on" a day that goes stale.
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
// opening until the maker puts its own in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-launch-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample render of a model: its name on a tile of the palette's colour,
// until the maker's product shots take its place.
$render = function ($name, $width = 800, $height = 1000) {
    return 'https://placehold.co/' . (int)$width . 'x' . (int)$height . '/ede9fe/5b21b6?text=' . rawurlencode($name);
};

// ── The phone: models, colours, storage, specifications ─────────────────

// The family's name, the way every page writes it before a model's name.
$family = lang('Orbit');

// The three models, smallest first: the order of the cards, the columns of
// the spec and compare tables and the products. 'storage' lists the sizes
// the model is sold in (keys of $storage_sizes); 'featured' is the model
// most buyers take, marked on the cards.
$models = array(
    'mini' => array('name' => lang('Orbit Mini'), 'featured' => false, 'storage' => array('128', '256'),
        'tagline' => lang('All of Orbit, in one hand.'),
        'screen'  => lang('5.9-inch display'), 'battery' => lang('Up to 22 hours of video'),
        'camera'  => lang('Dual 50 MP camera'),
        'text'    => lang('The small one: the same chip and main camera in a phone that fits a pocket and a palm.')),
    'one'  => array('name' => lang('Orbit One'), 'featured' => true, 'storage' => array('128', '256', '512'),
        'tagline' => lang('The phone most people need.'),
        'screen'  => lang('6.3-inch display'), 'battery' => lang('Up to 27 hours of video'),
        'camera'  => lang('Triple 50 MP camera'),
        'text'    => lang('The middle one: a bigger screen, a 3x telephoto camera and a battery that lasts past the second day.')),
    'plus' => array('name' => lang('Orbit One Plus'), 'featured' => false, 'storage' => array('256', '512'),
        'tagline' => lang('More screen, more battery, more zoom.'),
        'screen'  => lang('6.8-inch display'), 'battery' => lang('Up to 33 hours of video'),
        'camera'  => lang('Triple camera with 5x zoom'),
        'text'    => lang('The big one: the largest screen, the longest battery and a 5x periscope camera for the stage and the stands.')),
);

// The storage sizes, as a buyer reads them.
$storage_sizes = array(
    '128' => lang('128 GB'),
    '256' => lang('256 GB'),
    '512' => lang('512 GB'),
);

// The colours every model comes in. Each swatch is drawn by its own plx-
// class (the template's styles), never by a fixed inline colour.
$colours = array(
    'graphite' => array('name' => lang('Graphite'), 'text' => lang('A deep grey with a satin back.')),
    'glacier'  => array('name' => lang('Glacier'), 'text' => lang('An icy white that hides fingerprints.')),
    'sage'     => array('name' => lang('Sage'), 'text' => lang('A soft green from recycled glass.')),
    'coral'    => array('name' => lang('Coral'), 'text' => lang('A warm coral with a matching frame.')),
);

// The specifications, grouped as the specs page shows them. Each row is a
// label and its value for each model (keys of $models); a value the three
// share is written once for each, so every column reads on its own.
$same = function ($value) use ($models) {
    return array_fill_keys(array_keys($models), $value);
};
$spec_groups = array(
    'display' => array('title' => lang('Screen'), 'icon' => 'bi-phone', 'rows' => array(
        array(lang('Size'), array('mini' => lang('5.9 inches'), 'one' => lang('6.3 inches'), 'plus' => lang('6.8 inches'))),
        array(lang('Resolution'), array('mini' => '2340 × 1080', 'one' => '2640 × 1220', 'plus' => '3120 × 1440')),
        array(lang('Refresh rate'), $same(lang('1–120 Hz adaptive'))),
        array(lang('Peak brightness'), array('mini' => lang('2,000 nits'), 'one' => lang('2,600 nits'), 'plus' => lang('3,000 nits'))),
    )),
    'performance' => array('title' => lang('Chip and memory'), 'icon' => 'bi-cpu', 'rows' => array(
        array(lang('Chip'), $same(lang('Orbit O3, 3 nm, eight cores'))),
        array(lang('Memory'), array('mini' => lang('8 GB'), 'one' => lang('12 GB'), 'plus' => lang('16 GB'))),
        array(lang('Storage'), array('mini' => lang('128 or 256 GB'), 'one' => lang('128, 256 or 512 GB'), 'plus' => lang('256 or 512 GB'))),
    )),
    'camera' => array('title' => lang('Cameras'), 'icon' => 'bi-camera', 'rows' => array(
        array(lang('Main camera'), $same(lang('50 MP, f/1.7, optical stabilisation'))),
        array(lang('Ultra wide'), $same(lang('50 MP, 120° field of view'))),
        array(lang('Telephoto'), array('mini' => lang('Not on this model'), 'one' => lang('12 MP, 3x optical'), 'plus' => lang('50 MP, 5x optical periscope'))),
        array(lang('Front camera'), $same(lang('32 MP with autofocus'))),
        array(lang('Video'), array('mini' => lang('4K at 60 fps'), 'one' => lang('4K at 120 fps'), 'plus' => lang('8K at 30 fps, 4K at 120 fps'))),
    )),
    'battery' => array('title' => lang('Battery and charging'), 'icon' => 'bi-battery-charging', 'rows' => array(
        array(lang('Capacity'), array('mini' => '4,300 mAh', 'one' => '5,000 mAh', 'plus' => '5,600 mAh')),
        array(lang('Video playback'), array('mini' => lang('Up to 22 hours'), 'one' => lang('Up to 27 hours'), 'plus' => lang('Up to 33 hours'))),
        array(lang('Wired charging'), array('mini' => lang('45 W, 50% in 20 minutes'), 'one' => lang('65 W, 50% in 15 minutes'), 'plus' => lang('65 W, 50% in 15 minutes'))),
        array(lang('Wireless charging'), $same(lang('25 W, and 5 W to charge earbuds'))),
    )),
    'build' => array('title' => lang('Design'), 'icon' => 'bi-rulers', 'rows' => array(
        array(lang('Height'), array('mini' => lang('142 mm'), 'one' => lang('152 mm'), 'plus' => lang('163 mm'))),
        array(lang('Weight'), array('mini' => lang('168 g'), 'one' => lang('194 g'), 'plus' => lang('221 g'))),
        array(lang('Frame and back'), $same(lang('Recycled aluminium, matte glass'))),
        array(lang('Water and dust'), $same(lang('IP68, 6 m for 30 minutes'))),
    )),
    'connectivity' => array('title' => lang('Connections'), 'icon' => 'bi-broadcast', 'rows' => array(
        array(lang('Mobile network'), $same(lang('5G, dual SIM (nano and eSIM)'))),
        array(lang('Wireless'), $same(lang('Wi-Fi 7, Bluetooth 5.4, NFC'))),
        array(lang('Port'), $same(lang('USB-C 3.2, DisplayPort out'))),
        array(lang('Updates'), $same(lang('7 years of system and security updates'))),
    )),
);

// The prices of each model and storage size per currency, in major units:
// the buy page's cards and the shop's products read this one table (the
// shop's SKU is ORBIT-<MODEL>-<GB>).
$phone_prices = array(
    'mini' => array(
        '128' => array('USD' => 599, 'EUR' => 629, 'GBP' => 549, 'TRY' => 34999),
        '256' => array('USD' => 699, 'EUR' => 729, 'GBP' => 639, 'TRY' => 39999),
    ),
    'one'  => array(
        '128' => array('USD' => 799, 'EUR' => 849, 'GBP' => 729, 'TRY' => 45999),
        '256' => array('USD' => 899, 'EUR' => 949, 'GBP' => 819, 'TRY' => 51999),
        '512' => array('USD' => 1099, 'EUR' => 1149, 'GBP' => 999, 'TRY' => 62999),
    ),
    'plus' => array(
        '256' => array('USD' => 1099, 'EUR' => 1149, 'GBP' => 999, 'TRY' => 62999),
        '512' => array('USD' => 1299, 'EUR' => 1349, 'GBP' => 1179, 'TRY' => 73999),
    ),
);
$phone_sku = function ($model, $gb) {
    return 'ORBIT-' . strtoupper($model) . '-' . $gb;
};

// The accessories the shop sells beside the phone, with their prices.
$accessories = array(
    'case'    => array('sku' => 'ORBIT-ACC-CASE', 'icon' => 'bi-phone-flip', 'title' => lang('Orbit Soft Case'),
        'text'  => lang('A thin case of recycled silicone, in every colour of the phone.'),
        'price' => array('USD' => 39, 'EUR' => 39, 'GBP' => 35, 'TRY' => 1999)),
    'charger' => array('sku' => 'ORBIT-ACC-CHARGER', 'icon' => 'bi-plug', 'title' => lang('65 W Charger'),
        'text'  => lang('Half a battery in fifteen minutes; charges a laptop too.'),
        'price' => array('USD' => 49, 'EUR' => 49, 'GBP' => 45, 'TRY' => 2499)),
    'pad'     => array('sku' => 'ORBIT-ACC-PAD', 'icon' => 'bi-broadcast-pin', 'title' => lang('Wireless Charging Stand'),
        'text'  => lang('25 W standing charger that turns the phone into a bedside clock.'),
        'price' => array('USD' => 59, 'EUR' => 59, 'GBP' => 55, 'TRY' => 2999)),
    'buds'    => array('sku' => 'ORBIT-ACC-BUDS', 'icon' => 'bi-earbuds', 'title' => lang('Orbit Buds'),
        'text'  => lang('Noise-cancelling earbuds that switch to the phone the moment it rings.'),
        'price' => array('USD' => 129, 'EUR' => 129, 'GBP' => 119, 'TRY' => 6499)),
    'glass'   => array('sku' => 'ORBIT-ACC-GLASS', 'icon' => 'bi-shield-check', 'title' => lang('Screen Glass, Pack of Two'),
        'text'  => lang('Tempered glass with a fitting frame, so it goes on straight the first time.'),
        'price' => array('USD' => 25, 'EUR' => 25, 'GBP' => 22, 'TRY' => 1299)),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A card and the product it sells therefore show one amount.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A model's lowest price, the "From …" line of its card.
$price_from = function ($model) use ($phone_prices, $money) {
    $prices = reset($phone_prices[$model]);
    return sprintf(lang('From %s'), $money($prices));
};

// The highlights, in the order the home page tells them. Each has its
// section on the features page (#feature-<key>) and the words a visitor
// may search for.
$highlights = array(
    'design'  => array('icon' => 'bi-gem', 'figure' => lang('194 g'), 'title' => lang('Light in the hand, hard to break'),
        'text'   => lang('A frame of recycled aluminium and matte glass that is twice as hard to scratch as last year\'s.'),
        'topics' => lang('design, weight, glass, aluminium, colours, water resistance')),
    'display' => array('icon' => 'bi-brightness-high', 'figure' => lang('2,600 nits'), 'title' => lang('A screen you can read in the sun'),
        'text'   => lang('Bright enough for a summer noon, dim enough for a dark bedroom, smooth at up to 120 Hz.'),
        'topics' => lang('display, screen, brightness, refresh rate, size')),
    'camera'  => array('icon' => 'bi-camera', 'figure' => lang('50 MP'), 'title' => lang('Three cameras, one look'),
        'text'   => lang('Every lens has the same colour science, so a zoomed photo matches the wide one beside it.'),
        'topics' => lang('camera, photo, video, zoom, night mode, portrait')),
    'battery' => array('icon' => 'bi-battery-charging', 'figure' => lang('2 days'), 'title' => lang('A battery for the whole weekend'),
        'text'   => lang('Two days of ordinary use, and half a battery in fifteen minutes when you forget.'),
        'topics' => lang('battery, charging, wireless charging, battery life')),
    'chip'    => array('icon' => 'bi-cpu', 'figure' => lang('3 nm'), 'title' => lang('Fast, cool and quiet'),
        'text'   => lang('The Orbit O3 chip edits 4K video and plays the newest games without getting warm in the hand.'),
        'topics' => lang('chip, processor, performance, games, memory')),
    'updates' => array('icon' => 'bi-shield-check', 'figure' => lang('7 years'), 'title' => lang('Updated for seven years'),
        'text'   => lang('System and security updates for seven years, so the phone lasts as long as it is built to.'),
        'topics' => lang('updates, security, software, support, lifetime')),
);

// What the press says: fictional outlets, short quotes. The home page and
// the newsroom carry them until the maker puts real reviews in their place.
$press_quotes = array(
    array('outlet' => lang('Tech Weekly'), 'quote' => lang('The best battery we have tested this year, in a phone that does not feel heavy.')),
    array('outlet' => lang('Pocket Review'), 'quote' => lang('A telephoto camera this good used to cost twice as much.')),
    array('outlet' => lang('The Daily Gadget'), 'quote' => lang('Seven years of updates make it the easiest phone to recommend.')),
);

// What every order comes with, on the buy page and the launch band: sample
// terms the maker changes to its own (delivery, returns, warranty).
$buy_promises = array(
    array('icon' => 'bi-truck', 'title' => lang('Free delivery'), 'text' => lang('Every phone ships free, tracked, in recycled packaging.')),
    array('icon' => 'bi-arrow-repeat', 'title' => lang('Trade in your old phone'), 'text' => lang('Ask for a quote; the credit comes off your new phone.')),
    array('icon' => 'bi-arrow-counterclockwise', 'title' => lang('30 days to return'), 'text' => lang('Not the one for you? Send it back within 30 days.')),
    array('icon' => 'bi-tools', 'title' => lang('Two-year warranty'), 'text' => lang('Repairs at our service centre, collected from your door.')),
);

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the news of the launch. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-stars', 'text-primary'),
        $span(lang('The new Orbit is here, with a trade-in credit for your old phone.'), '', lang('Text')),
        $link(lang('Get a quote'), '{{page:tradein}}', 'link-primary fw-semibold text-nowrap'),
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

// The header: the maker's name, the menu and the Buy button. The links to
// the current page are marked active on the server (smart active state),
// which is what lets one header serve every page. The menu names only
// pages that stand without the shop; the buy page does too.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('features', lang('Features')),
        array('camera', lang('Camera')),
        array('specs', lang('Tech Specs')),
        array('compare', lang('Compare')),
        array('tradein', lang('Trade-in')),
        array('support', lang('Support')),
        array('blog', lang('Newsroom')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom sticky-top', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-2', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-semibold', lang('Brand'), array(
                    $icon('bi-phone', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'fs-5 plx-brand', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#lx-nav'),
                    $attr('aria-controls', 'lx-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav mx-xl-auto column-gap-xl-1 py-3 py-xl-0 small', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary btn-sm rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-3 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-bag'),
                            $span(lang('Buy'), '', lang('Link Text')),
                        ), array('href' => '{{page:buy}}')),
                    )),
                ), array('id' => 'lx-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the maker in a sentence, the phone, the shop,
// support and the company. Every link names a page that stands without the
// shop (the accessories are a section of the buy page).
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $column = function ($name, $title, $links) use ($el, $heading, $list) {
        return $el('div', 'col-6 col-md-3 col-lg-2', $name, array(
            $heading('h2', $title, 'h6 text-uppercase small fw-semibold mb-3'),
            $list($links),
        ));
    };
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Maker'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-semibold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-phone', 'text-primary'),
                        $span('{{site_name}}', 'plx-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('We make one phone a year and support it for seven: fewer models, made to last.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-youtube', 'YouTube'),
                        $social('bi-tiktok', 'TikTok'),
                        $social('bi-twitter-x', 'X'),
                    )),
                )),
                $column(lang('The Phone'), lang('The Phone'), array(
                    $link(lang('Features'), '{{page:features}}', $plain),
                    $link(lang('Camera'), '{{page:camera}}', $plain),
                    $link(lang('Tech Specs'), '{{page:specs}}', $plain),
                    $link(lang('Compare Models'), '{{page:compare}}', $plain),
                )),
                $column(lang('Shop'), lang('Shop'), array(
                    $link(lang('Buy Orbit'), '{{page:buy}}', $plain),
                    $link(lang('Accessories'), '{{page:buy}}#accessories', $plain),
                    $link(lang('Trade-in'), '{{page:tradein}}', $plain),
                    $link(lang('Launch List'), '{{page:newsletter}}', $plain),
                )),
                $column(lang('Support'), lang('Support'), array(
                    $link(lang('Help Center'), '{{page:search}}', $plain),
                    $link(lang('FAQ'), '{{page:faq}}', $plain),
                    $link(lang('Repair and Support'), '{{page:support}}', $plain),
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                )),
                $column(lang('Company'), lang('Company'), array(
                    $link(lang('About Us'), '{{page:about}}', $plain),
                    $link(lang('Newsroom'), '{{page:blog}}', $plain),
                    $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Battery and charging figures are from lab tests; real use varies with settings and signal.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The launch band: a dark band with the way to buy and the way to hear
// first. Shared, so every page that ends with it carries the same offer.
$launch_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $attr) {
    return $el('section', 'py-5 bg-body text-body plx-glow', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Ready for a new phone?'), 'h3 fw-semibold mb-2'),
                    $para(lang('Pick a model, a colour and a size, and trade in your old phone for credit.'), 'text-body-secondary mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Buy'), '{{page:buy}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                        $link(lang('Compare Models'), '{{page:compare}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// A model as a card: its render, name and line, the three facts, the price
// it starts at and the ways on (its specs, the buy page at the model). The
// buy page and the home page both draw it from $models.
$model_card = function ($key) use ($el, $heading, $para, $link, $image, $span, $col, $icon, $models, $render, $price_from) {
    $m = $models[$key];
    $fact = function ($icon_name, $text) use ($el, $icon, $span) {
        return $el('li', 'd-flex align-items-center gap-2 mb-2', lang('List Item'), array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', lang('Text')),
        ));
    };
    $body = array();
    if ($m['featured']) $body[] = $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary align-self-start mb-2', lang('Badge'));
    $body[] = $heading('h3', $m['name'], 'h4 fw-semibold mb-1');
    $body[] = $para($m['tagline'], 'text-body-secondary mb-3');
    $body[] = $el('ul', 'list-unstyled small mb-4', lang('Facts'), array(
        $fact('bi-phone', $m['screen']),
        $fact('bi-camera', $m['camera']),
        $fact('bi-battery-charging', $m['battery']),
    ));
    $body[] = $para($price_from($key), 'fw-semibold mb-3 plx-price');
    $body[] = $el('div', 'd-flex flex-wrap gap-2 mt-auto', lang('Buttons'), array(
        $link(lang('Buy'), '{{page:buy}}#buy-' . $key, 'btn btn-primary rounded-pill px-4'),
        $link(lang('See the specs'), '{{page:specs}}', 'btn btn-outline-secondary rounded-pill px-4'),
    ));
    return $col(array(
        $el('article', 'card h-100 rounded-4 overflow-hidden ' . ($m['featured'] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), $m['name'], array(
            $el('div', 'ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($render($m['name'], 800, 600), $m['name'], 'object-fit-contain p-3'),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), $body),
        )),
    ), '6', '4');
};

// The colour swatches: a round chip of each colour and its name. The chips
// are decorative (the name says the colour); the plx-swatch-<key> classes
// of the template's styles paint them.
$swatches = function ($class = 'd-flex flex-wrap gap-4') use ($el, $span, $attr, $colours) {
    $items = array();
    foreach ($colours as $key => $c) {
        $items[] = $el('li', 'd-flex flex-column align-items-center gap-2 small', $c['name'], array(
            $el('span', 'plx-swatch plx-swatch-' . $key . ' rounded-circle border', lang('Swatch'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
            $span($c['name'], 'fw-semibold', lang('Name')),
        ));
    }
    return $el('ul', 'list-unstyled mb-0 ' . $class, lang('Colours'), $items);
};

// Launch: Spec Comparison — the models side by side, a head cell per model
// with its "From" price, each group of $spec_groups a row group under its
// title, figures of one width and the head row sticky from 992 px up
// (pg-launch-specs, the block's own rules). The first cell of a row is its
// header, so a screen reader reads "Weight, Orbit Mini, 168 g".
// $group_keys: the groups drawn (all when empty, one when a string).
// $opts: 'models' (the columns, all by default), 'differ' (only the rows
// whose values differ between those models; a group left empty is dropped),
// 'anchors' (each group a search section under #spec-<key>), 'caption'.
$spec_table = function ($group_keys = array(), $opts = array()) use ($el, $span, $attr, $models, $spec_groups, $price_from, $searchable) {
    $group_keys = $group_keys ? (array)$group_keys : array_keys($spec_groups);
    $model_keys = !empty($opts['models']) ? $opts['models'] : array_keys($models);
    $cell = function ($tag, $text, $class, $name, $attrs = array()) use ($el, $span) {
        return $el($tag, $class, $name, array($span($text, '', lang('Text'))), $attrs ? array('_attrs' => $attrs) : array());
    };
    $head = array($el('td', '', lang('Corner Cell')));
    foreach ($model_keys as $mk) {
        $head[] = $el('th', 'text-center pb-3', $models[$mk]['name'], array(
            $span($models[$mk]['name'], 'd-block h5 fw-bold mb-1', lang('Name')),
            $span($price_from($mk), 'd-block small fw-normal text-body-secondary', lang('Price')),
        ), array('_attrs' => array($attr('scope', 'col'))));
    }
    $groups = array();
    foreach ($group_keys as $gk) {
        $g = $spec_groups[$gk];
        $rows = array();
        foreach ($g['rows'] as $r) {
            $values = array();
            foreach ($model_keys as $mk) $values[] = $r[1][$mk];
            if (!empty($opts['differ']) && count(array_unique($values)) < 2) continue;
            $cells = array($cell('th', $r[0], 'fw-normal text-body-secondary text-start', lang('Header Cell'), array($attr('scope', 'row'))));
            foreach ($values as $v) $cells[] = $cell('td', $v, '', lang('Cell'));
            $rows[] = $el('tr', '', lang('Spec Row'), $cells);
        }
        if (!$rows) continue;
        array_unshift($rows, $el('tr', '', lang('Group Row'), array(
            $cell('th', $g['title'], 'pt-4 h5 fw-bold border-0 text-start', lang('Group Header'),
                array($attr('scope', 'rowgroup'), $attr('colspan', (string)(count($model_keys) + 1)))),
        )));
        $groups[] = $el('tbody', '', $g['title'], $rows,
            !empty($opts['anchors']) ? $searchable('spec-' . $gk, lang('specs, specifications, technical details')) : array());
    }
    $caption = !empty($opts['caption']) ? $opts['caption'] : lang('The specifications of each model, side by side.');
    return $el('div', 'pg-launch-specs', lang('Table Wrap'), array(
        $el('table', 'table align-middle text-center mb-0', lang('Specifications'), array_merge(array(
            $el('caption', 'visually-hidden', lang('Table Caption'), array(), array('text' => $caption)),
            $el('thead', '', lang('Table Head'), array($el('tr', '', lang('Header Row'), $head))),
        ), $groups)),
    ));
};

// A highlight as a tile: its icon, its figure, its title and one sentence,
// linking to its section of the features page.
$highlight_tile = function ($key) use ($el, $heading, $para, $link, $icon, $col, $highlights) {
    $h = $highlights[$key];
    return $col(array(
        $el('article', 'h-100 p-4 rounded-4 bg-body-tertiary position-relative plx-lift', $h['title'], array(
            $icon($h['icon'], 'fs-3 text-primary d-block mb-3'),
            $para($h['figure'], 'display-6 fw-semibold mb-1 plx-figure'),
            $el('h3', 'h5 fw-semibold mb-2 position-static', lang('Title'), array(
                $link($h['title'], '{{page:features}}#feature-' . $key, 'stretched-link link-body-emphasis text-decoration-none'),
            )),
            $para($h['text'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// A press quote: the words and the outlet that wrote them.
$press_quote = function ($q) use ($el, $para, $col) {
    return $col(array(
        $el('figure', 'h-100 mb-0 p-4 rounded-4 border', lang('Quote'), array(
            $el('blockquote', 'blockquote fs-6 mb-3', lang('Quote Text'), array($para('“' . $q['quote'] . '”', 'mb-0'))),
            $el('figcaption', 'small fw-semibold text-body-secondary mb-0', lang('Outlet'), array(), array('text' => $q['outlet'])),
        )),
    ), '', '4');
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
        $para($eyebrow, 'plx-eyebrow text-uppercase fw-semibold text-primary mb-1'),
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

// An icon in a soft round tile, the way the support topics are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'lx-faq') use ($el, $para, $attr, $searchable) {
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

// The comments of a record page (a trade-in's or a support request's
// messages, a conversation's replies, a post's comments), in the reading column: the page's Comments
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


// ── Staff, newsletter and newsroom parts ────────────────────────────────────

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
                $item('staff_tradeins', lang('All Trade-ins')),
                $item('staff_support', lang('All Support Requests')),
                $item('blog_new', lang('New Press Release')),
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
    return $agree($id, 'consent', lang('I would like to hear about new phones, updates and offers by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. The
// support form takes a receipt or a photo of the damage, so the default
// accepts PDFs and pictures; one that takes only pictures is a
// collection's image kind (a trade-in's photos of the old phone).
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

// A newsroom post card of the lists (the newsroom and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('Orbit One: three cameras, one look'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
