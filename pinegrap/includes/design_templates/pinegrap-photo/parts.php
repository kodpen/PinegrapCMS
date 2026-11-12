<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Photography Studio", part 1 of its files: the node
 * builders, the studio's own data (the gallery categories, the sample
 * galleries, the session packages with their prices and what they
 * include, the add-ons, the steps of a shoot, the gift vouchers, why
 * clients choose the studio, what they say and the answers of the forms'
 * pick lists) and the parts every page repeats (account bar, header,
 * footer, booking band, gallery, package and client cards, the photo
 * grid, proofing frames, page shells, section headers, form fields, the
 * comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-photo.php puts them together.
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
// every opening until the studio puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-photo-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e2e8f0/334155?text=' . rawurlencode($initials !== '' ? $initials : 'P');
};

// ── The studio: its galleries, its packages and what they include ──────

// The studio itself. The about page tells its story; the footer, the
// booking band and the contact lines use the address, phone and hours.
$studio = array(
    'tagline' => lang('Weddings, portraits, families and brands, photographed in natural light and delivered in a private online gallery'),
    'story'   => lang('The studio began with one camera and a room with a large north window. Today it is a small team of photographers who still work the same way: unhurried sessions, real moments instead of stiff poses, and every photo edited by hand before it reaches you.'),
    'address' => lang('Atelier Lane 14, Ground Floor, Arts Quarter'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'studio@example.com',
    'hours'   => lang('Tuesday to Saturday, 10:00 to 19:00; sessions by appointment'),
    'promise' => lang('Every photo is backed up in two places until you have downloaded your gallery'),
);

// The gallery categories, in the order the gallery list filters them.
// Each answer's value is its own words, so a filter link (?category=)
// reads as it shows.
$categories = array(
    'weddings'   => array('icon' => 'bi-heart', 'title' => lang('Weddings')),
    'portraits'  => array('icon' => 'bi-person-bounding-box', 'title' => lang('Portraits')),
    'family'     => array('icon' => 'bi-people', 'title' => lang('Family')),
    'newborn'    => array('icon' => 'bi-emoji-smile', 'title' => lang('Newborn')),
    'events'     => array('icon' => 'bi-stars', 'title' => lang('Events')),
    'commercial' => array('icon' => 'bi-briefcase', 'title' => lang('Commercial')),
);

// The sample galleries: the records the Galleries form starts with, one
// per category. Each has a cover and six photos (fixed seeds).
$galleries = array(
    'lakeside' => array('title' => lang('A Garden Wedding by the Lake'), 'category' => 'weddings', 'location' => lang('Lakeside Gardens'),
        'summary' => lang('Vows under the old willow, a long table on the lawn and dancing until the lanterns were the only light.'),
        'story'   => lang('We followed the day from the bride getting ready to the last dance: the quiet moments before the ceremony, the families meeting on the lawn and the speeches that made everyone laugh.'),
        'featured' => true),
    'window' => array('title' => lang('Portraits by the North Window'), 'category' => 'portraits', 'location' => lang('Our studio'),
        'summary' => lang('Soft light, a plain backdrop and an hour with no hurry: portraits that look like the person, not the pose.'),
        'story'   => lang('A portrait session in the studio\'s natural light, with a change of clothes and a few minutes outside in the courtyard.'),
        'featured' => true),
    'park' => array('title' => lang('An Afternoon in the Park'), 'category' => 'family', 'location' => lang('City Park'),
        'summary' => lang('A family of five, a picnic blanket and a game of football that nobody won.'),
        'story'   => lang('We met at the family\'s favourite park and let the children lead the way; the best photos came when they forgot about the camera.'),
        'featured' => true),
    'first_days' => array('title' => lang('First Days at Home'), 'category' => 'newborn', 'location' => lang('At the family\'s home'),
        'summary' => lang('A baby ten days old, the parents\' hands and the light of their own living room.'),
        'story'   => lang('A newborn session at home, at the baby\'s pace: feeds, naps and cuddles, and the big sister meeting her brother.'),
        'featured' => false),
    'launch' => array('title' => lang('A Product Launch Evening'), 'category' => 'events', 'location' => lang('City Gallery Hall'),
        'summary' => lang('Two hundred guests, a stage, a new product and the people who made it.'),
        'story'   => lang('Coverage of a launch evening: the set-up, the speeches, the guests trying the product and the team at the end of the night.'),
        'featured' => false),
    'cafe' => array('title' => lang('Brand Photos for a Neighbourhood Café'), 'category' => 'commercial', 'location' => lang('Corner Café, Old Town'),
        'summary' => lang('Coffee, pastries and the people behind the counter, for a new website and menu.'),
        'story'   => lang('A half day of product and team photos for a café\'s new website, menus and social media, planned around the morning rush.'),
        'featured' => false),
);

// The session packages, with what each includes. 'images' is the number
// of edited photos delivered; 'length' the session's time in words.
$packages = array(
    'mini' => array('icon' => 'bi-stopwatch', 'title' => lang('Studio Mini Session'), 'category' => 'portraits', 'images' => 10,
        'length' => lang('30 minutes in the studio'),
        'text'   => lang('A short portrait session for one person or a couple, at a free time you pick online.'),
        'includes' => array(lang('One backdrop and one outfit'), lang('A private online gallery'), lang('Download of every edited photo')),
        'popular' => false),
    'portrait' => array('icon' => 'bi-person-bounding-box', 'title' => lang('Portrait Session'), 'category' => 'portraits', 'images' => 25,
        'length' => lang('One hour in the studio or outside'),
        'text'   => lang('Portraits for you, your profile or your book cover, with time for two looks.'),
        'includes' => array(lang('Two outfits and two settings'), lang('A planning call before the session'), lang('A private online gallery'), lang('Download of every edited photo')),
        'popular' => true),
    'family' => array('icon' => 'bi-people', 'title' => lang('Family Session'), 'category' => 'family', 'images' => 40,
        'length' => lang('90 minutes on location'),
        'text'   => lang('A relaxed session at home, in a park or at the beach, for the whole family and the dog.'),
        'includes' => array(lang('A location of your choice nearby'), lang('A planning call before the session'), lang('A private online gallery'), lang('Download of every edited photo')),
        'popular' => false),
    'newborn' => array('icon' => 'bi-emoji-smile', 'title' => lang('Newborn Session'), 'category' => 'newborn', 'images' => 30,
        'length' => lang('Up to three hours at your home'),
        'text'   => lang('A gentle session in the baby\'s first weeks, at the baby\'s pace, with the family too.'),
        'includes' => array(lang('Wraps and blankets brought along'), lang('Photos with parents and siblings'), lang('A private online gallery'), lang('Download of every edited photo')),
        'popular' => false),
    'wedding' => array('icon' => 'bi-heart', 'title' => lang('Wedding Day'), 'category' => 'weddings', 'images' => 400,
        'length' => lang('Eight hours, from the preparations to the first dance'),
        'text'   => lang('The whole day told as a story, with a second photographer for the ceremony and the reception.'),
        'includes' => array(lang('Two photographers'), lang('A meeting and a visit to the venue before the day'), lang('A private online gallery to share with your guests'), lang('Download of every edited photo')),
        'popular' => true),
    'commercial' => array('icon' => 'bi-briefcase', 'title' => lang('Brand Half Day'), 'category' => 'commercial', 'images' => 60,
        'length' => lang('Four hours at your business'),
        'text'   => lang('Product, team and place photos for a website, a menu or a campaign, with the rights to use them.'),
        'includes' => array(lang('A shot list planned together'), lang('Usage rights for your business'), lang('A private online gallery'), lang('Download of every edited photo')),
        'popular' => false),
);

// A package's price per session, per currency.
$package_prices = array(
    'mini'       => array('USD' => 150, 'EUR' => 140, 'GBP' => 120, 'TRY' => 5500),
    'portrait'   => array('USD' => 290, 'EUR' => 270, 'GBP' => 230, 'TRY' => 10500),
    'family'     => array('USD' => 390, 'EUR' => 360, 'GBP' => 310, 'TRY' => 14000),
    'newborn'    => array('USD' => 450, 'EUR' => 420, 'GBP' => 360, 'TRY' => 16000),
    'wedding'    => array('USD' => 2400, 'EUR' => 2200, 'GBP' => 1900, 'TRY' => 86000),
    'commercial' => array('USD' => 950, 'EUR' => 880, 'GBP' => 760, 'TRY' => 34000),
);

// The deposit that holds a package's date once the studio confirms it
// (the shop sells it per package, with the shop).
$package_deposits = array(
    'mini'       => array('USD' => 50, 'EUR' => 45, 'GBP' => 40, 'TRY' => 1800),
    'portrait'   => array('USD' => 80, 'EUR' => 75, 'GBP' => 65, 'TRY' => 2900),
    'family'     => array('USD' => 100, 'EUR' => 90, 'GBP' => 80, 'TRY' => 3600),
    'newborn'    => array('USD' => 120, 'EUR' => 110, 'GBP' => 95, 'TRY' => 4300),
    'wedding'    => array('USD' => 600, 'EUR' => 550, 'GBP' => 480, 'TRY' => 21500),
    'commercial' => array('USD' => 250, 'EUR' => 230, 'GBP' => 200, 'TRY' => 9000),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A package's price and deposit therefore show one amount wherever they
// appear.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A package's "… per session" words.
$price_text = function ($key) use ($package_prices, $money) {
    return sprintf(lang('%s per session'), $money($package_prices[$key]));
};
// A package's number of edited photos in words.
$images_text = function ($key) use ($packages) {
    return sprintf(lang('%d edited photos'), (int)$packages[$key]['images']);
};

// What a client may add to any package (the packages page), with a price.
$addons = array(
    array('icon' => 'bi-images', 'title' => lang('Ten more edited photos'), 'price' => array('USD' => 60, 'EUR' => 55, 'GBP' => 50, 'TRY' => 2200)),
    array('icon' => 'bi-book', 'title' => lang('A printed album of 30 pages'), 'price' => array('USD' => 240, 'EUR' => 220, 'GBP' => 190, 'TRY' => 8600)),
    array('icon' => 'bi-lightning', 'title' => lang('Your gallery within three days'), 'price' => array('USD' => 90, 'EUR' => 85, 'GBP' => 70, 'TRY' => 3200)),
    array('icon' => 'bi-clock', 'title' => lang('One more hour of photography'), 'price' => array('USD' => 150, 'EUR' => 140, 'GBP' => 120, 'TRY' => 5400)),
);

// The steps of a shoot, from the request to the downloads (the home and
// the How It Works page).
$shoot_steps = array(
    array('title' => lang('Ask for a date'), 'text' => lang('Choose a package and send a booking request, or book a studio mini session at a free time online.')),
    array('title' => lang('Plan it together'), 'text' => lang('We confirm the date, talk through places, clothes and the photos that matter most to you.')),
    array('title' => lang('The shoot'), 'text' => lang('An unhurried session; we guide you gently and leave room for the real moments.')),
    array('title' => lang('Choose your favourites'), 'text' => lang('Your proofs wait in a private gallery; tell us the frames you love and anything to retouch.')),
    array('title' => lang('Download and print'), 'text' => lang('The edited photos come to the same private gallery, ready to download and share.')),
);

// Why clients choose the studio (the home and the about page).
$why_us = array(
    array('icon' => 'bi-sun', 'title' => lang('Natural light'),
        'text' => lang('A studio built around a north window, and sessions outside at the softest hours.')),
    array('icon' => 'bi-brush', 'title' => lang('Edited by hand'),
        'text' => lang('Every photo is colour-corrected and retouched by the photographer who took it.')),
    array('icon' => 'bi-shield-lock', 'title' => lang('Private galleries'),
        'text' => lang('Your proofs and photos sit in a gallery only you can open after signing in.')),
    array('icon' => 'bi-hourglass-split', 'title' => lang('No rush'),
        'text' => lang('Sessions with time for children to settle and for nerves to pass.')),
);

// The gift vouchers the shop sells (with the shop), with their prices. The
// SKUs name the template, so the products are its own.
$gift_vouchers = array(
    'gift150' => array('sku' => 'PHOTO-GIFT-150', 'icon' => 'bi-gift', 'title' => lang('Portrait Gift Voucher'),
        'text'  => lang('A gift voucher for a portrait or mini session, valid for one year.'),
        'price' => array('USD' => 150, 'EUR' => 150, 'GBP' => 150, 'TRY' => 5500)),
    'gift400' => array('sku' => 'PHOTO-GIFT-400', 'icon' => 'bi-gift', 'title' => lang('Family Gift Voucher'),
        'text'  => lang('A larger gift voucher towards a family or newborn session, valid for one year.'),
        'price' => array('USD' => 400, 'EUR' => 400, 'GBP' => 400, 'TRY' => 14500)),
);

// The studio in four figures (the home and about pages).
$figures = array(
    array('value' => '600+', 'label' => lang('Sessions photographed')),
    array('value' => '120', 'label' => lang('Weddings told as a story')),
    array('value' => '4.9', 'label' => lang('Average client rating')),
    array('value' => '12', 'label' => lang('Years behind the camera')),
);

// What clients say, each about one of the packages.
$testimonials = array(
    array('package' => 'wedding', 'name' => lang('Selin Aydın'), 'role' => lang('Booked the Wedding Day'),
        'quote' => lang('We hardly noticed the camera all day, and then the gallery made us cry all over again.')),
    array('package' => 'family', 'name' => lang('David Brooks'), 'role' => lang('Booked the Family Session'),
        'quote' => lang('Three children under six and not one forced smile. These are the photos on our walls now.')),
    array('package' => 'commercial', 'name' => lang('Zeynep Koç'), 'role' => lang('Booked the Brand Half Day'),
        'quote' => lang('The photos changed how our café looks online; our bookings went up within weeks.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the client chose.
$category_titles = array();
foreach ($categories as $c) $category_titles[] = $c['title'];
$gallery_titles = array();
foreach ($galleries as $g) $gallery_titles[] = $g['title'];
$package_titles = array();
foreach ($packages as $p) $package_titles[] = $p['title'];
$booking_package_choices = array_merge($package_titles, array(lang('Not decided yet')));
$time_choices = array(lang('Morning'), lang('Afternoon'), lang('Golden hour, before sunset'), lang('Flexible'));
$place_choices = array(
    array(lang('In the studio'), lang('In the studio')),
    array(lang('On location'), lang('On location')),
);
$people_choices = array(lang('Just me'), lang('Two people'), lang('Three to five people'), lang('Six or more'));
// The states of a booking request, set by the studio in the panel.
$booking_statuses = array(lang('Request received'), lang('Confirmed'), lang('Shoot done'), lang('Declined'), lang('Cancelled'));
$print_choices = array(lang('Downloads only'), lang('Prints as well'), lang('A printed album'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a proof selection, set by the studio in the panel.
$selection_statuses = array(lang('Selection received'), lang('Being edited'), lang('Photos delivered'));

// The query string of a link that opens a list with a filter set
// (?category=) or a form with a choice made (?package=, ?gallery=): the
// form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the booking band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('booking_band');
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
            $heading('h1', $title, 'pph-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pph-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pph-display h3 mb-0'),
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

// Five stars, for what clients say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'ph-faq') use ($el, $para, $attr, $searchable) {
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
// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the studio mini sessions and the way to
// book one at a free time. A shared component.
$notice = function () use ($el, $icon, $span, $link, $packages, $price_text) {
    return $el('p', 'mb-0', lang('Notice'), array(
        $icon('bi-camera', 'text-primary me-1'),
        $span($packages['mini']['title'] . ' — ' . $price_text('mini'), '', lang('Text')),
        $link(lang('Pick a free time'), '{{page:book_online}}', 'link-primary fw-semibold ms-1'),
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

// The header: the studio's name, the menu and the way to book a shoot. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page. The deposits and gift vouchers
// are the shop's and are linked from the shop pages only, so the header
// stands on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('galleries', lang('Galleries')),
        array('packages', lang('Packages')),
        array('experience', lang('How It Works')),
        array('about', lang('About Us')),
        array('client_area', lang('Client Area')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-camera', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pph-brand fs-4 text-truncate', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#ph-nav'),
                    $attr('aria-controls', 'ph-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-heart'),
                            $span(lang('Book a Shoot'), '', lang('Link Text')),
                        ), array('href' => '{{page:book}}')),
                    )),
                ), array('id' => 'ph-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the studio in a sentence, how to reach it and
// its profiles, the galleries by category, the client pages and the
// studio's pages; at the bottom the year, the site's name and the promise
// about the photos.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $categories, $studio, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $category_links = array($link(lang('All Galleries'), '{{page:galleries}}', $plain));
    foreach ($categories as $c) $category_links[] = $link($c['title'], '{{page:galleries}}?' . $query(array('category' => $c['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Studio'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-camera', 'text-primary'),
                        $span('{{site_name}}', 'pph-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($studio['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($studio['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($studio['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $studio['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($studio['email'], 'mailto:' . $studio['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($studio['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-pinterest', 'Pinterest'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-whatsapp', 'WhatsApp'),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Gallery Links'), array(
                    $heading('h2', lang('Galleries'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($category_links),
                )),
                $el('div', 'col-6 col-md-4 col-lg-3', lang('Clients'), array(
                    $heading('h2', lang('For Clients'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book a Shoot'), '{{page:book}}', $plain),
                        $link(lang('Book a Studio Mini Session'), '{{page:book_online}}', $plain),
                        $link(lang('My Bookings'), '{{page:my_bookings}}', $plain),
                        $link(lang('Client Area'), '{{page:client_area}}', $plain),
                        $link(lang('My Selections'), '{{page:my_selections}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-3', lang('Footer Links'), array(
                    $heading('h2', lang('The Studio'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Packages'), '{{page:packages}}', $plain),
                        $link(lang('How It Works'), '{{page:experience}}', $plain),
                        $link(lang('Photo Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($studio['promise'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The booking band: a full-width band in the palette's colour with the way
// to ask for a shoot and the way to see the packages. Shared, so every
// page that ends with it carries the same call.
$booking_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Let\'s make photos you will keep for a lifetime'), 'pph-display h2 mb-2'),
                    $para(lang('Tell us about the day or the people you want photographed; we answer within a day with dates and ideas.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Shoot'), '{{page:book}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('See the Packages'), '{{page:packages}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A gallery's card of the lists (the home and the gallery list): the cover,
// the category, the title linking the gallery page, the location and the
// summary. Every value is bound to the Galleries form's field of the same
// name; the sample fills the canvas.
$gallery_card = function ($sample_key = 'lakeside') use ($el, $para, $span, $link, $image, $col, $icon, $bind, $galleries, $categories, $photo, $attr) {
    $g = $galleries[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pph-lift', lang('Gallery Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('cover-' . $sample_key, 800, 600), $g['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'cover', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($categories[$g['category']]['title'], 'pph-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Category'), $bind('category')),
                $el('h3', 'pph-display h4 position-static mb-2', lang('Gallery Title'), array(
                    $link($g['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-center gap-1 small fw-semibold mb-2', lang('Location'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($g['location'], '', lang('Location'), $bind('location')),
                )),
                $para($g['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// A package's card (the packages page and the home): the icon, the name,
// the session's length, the photos delivered, the price, what it includes
// and a link that opens the booking request with the package chosen. The
// popular packages carry a badge and a filled button.
$package_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $icon, $packages, $price_text, $images_text, $check_list, $query) {
    $p = $packages[$key];
    $top = array($icon($p['icon'], 'fs-3 text-primary'));
    if ($p['popular']) $top[] = $span(lang('Most booked'), 'badge rounded-pill text-bg-primary', lang('Badge'));
    return $col(array(
        $el('article', 'card h-100 rounded-4 pph-lift ' . ($p['popular'] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Package Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center justify-content-between gap-2 mb-3', lang('Package Heading'), $top),
                $heading('h3', $p['title'], 'pph-display h4 mb-1'),
                $para($p['length'], 'small text-body-secondary mb-2'),
                $para($price_text($key), 'pph-price h5 fw-semibold mb-1'),
                $para($images_text($key), 'small fw-semibold mb-3'),
                $para($p['text'], 'mb-3'),
                $check_list($p['includes'], 'list-unstyled small mb-4'),
                $link(lang('Book this package'), '{{page:book}}?' . $query(array('package' => $p['title'])),
                    'btn ' . ($p['popular'] ? 'btn-primary' : 'btn-outline-primary') . ' rounded-pill mt-auto'),
            )),
        )),
    ), '6', '4');
};

// What a client says: the quote, the stars, the client and the package.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Client Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pph-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Client'), array(
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
                $item('gallery_new', lang('New Gallery')),
                $item('staff_bookings', lang('Shoot Bookings')),
                $item('staff_selections', lang('Proof Selections')),
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
    return $agree($id, 'consent', lang('I would like to receive the studio\'s news, mini session days and Photo Journal posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// booking request takes documents (a shot list, a mood board) and
// pictures, so the default accepts PDFs, office files, text and pictures;
// one that takes only pictures is a collection's image kind (a gallery's
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
                    $link(lang('What to wear for a family photo session'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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


// ── Photos of a gallery and of a client's proofs ────────────────────────

// The photos of a gallery's record page: the six image fields of the
// Galleries form in a grid, each leaving with its column when the record
// has no photo there. The sample fills the canvas.
$photo_grid = function ($sample_key = 'lakeside') use ($el, $col, $row, $image, $photo, $galleries, $attr) {
    $g = $galleries[$sample_key];
    $cols = array();
    for ($n = 1; $n <= 6; $n++) {
        $cols[] = $col(array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo($sample_key . '-' . $n, 900, 675), $g['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_' . $n, 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
        ), '6', '4', '', '12', 'pg-hide-if-empty');
    }
    return $el('div', '', lang('Photos'), array($row($cols, '3')));
};

// A proof of a client's private gallery: the photo, its frame number (the
// words the client writes in the Proof Selections form) and a link that
// downloads it. The studio puts the client's own files in their private
// folder and links them here; the sample links its full-size picture.
$proof_frame = function ($n, $seed = 'proof') use ($el, $col, $image, $link, $span, $icon, $photo, $attr) {
    $number = sprintf(lang('Frame %02d'), $n);
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden mb-0', lang('Proof'), array(
            $el('span', 'd-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo($seed . '-' . $n, 900, 675), $number, 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('figcaption', 'card-body d-flex align-items-center justify-content-between gap-2 py-2 px-3', lang('Frame Line'), array(
                $span($number, 'pph-price small fw-semibold', lang('Frame Number')),
                $el('a', 'd-inline-flex align-items-center gap-1 small link-primary text-decoration-none', lang('Download'), array(
                    $icon('bi-download'),
                    $span(lang('Download'), '', lang('Link Text')),
                ), array('href' => $photo($seed . '-' . $n, 2400, 1800), '_attrs' => array($attr('download', ''), $attr('rel', 'nofollow')))),
            )),
        )),
    ), '6', '4');
};
