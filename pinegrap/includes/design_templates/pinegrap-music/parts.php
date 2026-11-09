<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Music Artist", part 1 of its files: the node builders,
 * the artist's own data (the band members, the release formats, the sample
 * releases with their tracks, the sample shows dated from the day the
 * template opens, the ticket states, the merch and gift cards, what the
 * press says, the videos, where the music can be heard and the answers of
 * the forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, mailing-list band, release, show, member, press and
 * video cards, the track list, page shells, section headers, form fields,
 * the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-music.php puts them together.
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
// every opening until the artist puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-music-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/1e1b4b/e0e7ff?text=' . rawurlencode($initials !== '' ? $initials : 'M');
};

// ── The artist: the band, the records, the shows and the merch ──────────

// The artist itself. The about page tells its story; the footer, the
// mailing-list band and the press kit use the addresses for bookings,
// management and press.
$artist = array(
    'tagline'    => lang('A four-piece band playing loud, warm guitar songs about cities at night'),
    'story'      => lang('Four friends started the band in a rehearsal room under a bakery, playing covers on Friday nights. The covers became songs of their own, the Friday nights became tours, and the bakery still sends bread to the first show of every tour.'),
    'based'      => lang('Based in Istanbul, touring everywhere'),
    'booking'    => 'booking@example.com',
    'management' => 'management@example.com',
    'press'      => 'press@example.com',
    'label'      => lang('Released on our own label, distributed worldwide'),
);

// The band members (the about page and the press kit). Page content, not a
// collection: a band changes its line-up rarely, and the about page is
// edited in the designer like any other page.
$members = array(
    'lead'  => array('name' => lang('Deniz Aksoy'), 'role' => lang('Vocals and rhythm guitar'),
        'bio' => lang('Writes most of the words, usually on night buses between two shows.')),
    'guitar' => array('name' => lang('Maya Lindqvist'), 'role' => lang('Lead guitar'),
        'bio' => lang('Came to the first rehearsal to borrow an amplifier and never left.')),
    'bass'  => array('name' => lang('Caner Öz'), 'role' => lang('Bass and keys'),
        'bio' => lang('Keeps the band in time and the tour van on the road.')),
    'drums' => array('name' => lang('Leo Martins'), 'role' => lang('Drums'),
        'bio' => lang('Plays every show as if it were the last one of the tour.')),
);

// The release formats, the discography's filter. Each answer's value is its
// own words, so a filter link (?format=) reads as it shows.
$formats = array(
    'album'  => array('icon' => 'bi-vinyl', 'title' => lang('Album')),
    'ep'     => array('icon' => 'bi-disc', 'title' => lang('EP')),
    'single' => array('icon' => 'bi-music-note', 'title' => lang('Single')),
    'live'   => array('icon' => 'bi-broadcast', 'title' => lang('Live Recording')),
);

// The sample releases: the records the Releases form starts with. 'note'
// places the release in the band's story in words, never a year; each
// track is its title and its length (minutes:seconds).
$releases = array(
    'nightbus' => array('title' => lang('Night Bus Hymns'), 'format' => 'album', 'note' => lang('The second album'),
        'summary' => lang('Eleven songs written on the road: loud where the city is loud, quiet where it sleeps.'),
        'credits' => lang('Recorded live in one room over ten days. Produced by the band, mixed by Selin Arda.'),
        'tracks'  => array(
            array(lang('Last Ferry'), '3:41'), array(lang('Neon on the Water'), '4:05'), array(lang('Night Bus Hymn'), '3:58'),
            array(lang('Paper Lanterns'), '3:12'), array(lang('Bakery Lights'), '4:27'), array(lang('Rooftop Static'), '3:33'),
            array(lang('Slow Traffic'), '5:02'), array(lang('Harbour Choir'), '3:49'), array(lang('Streetlight Waltz'), '3:20'),
            array(lang('Long Way Home'), '4:44'), array(lang('Morning Shift'), '6:10'),
        ), 'featured' => true),
    'firstlight' => array('title' => lang('First Light'), 'format' => 'album', 'note' => lang('The debut album'),
        'summary' => lang('The songs from the rehearsal room under the bakery, recorded before the first tour.'),
        'credits' => lang('Recorded and mixed by the band in their rehearsal room.'),
        'tracks'  => array(
            array(lang('First Light'), '3:27'), array(lang('Under the Bakery'), '3:55'), array(lang('Friday Covers'), '2:58'),
            array(lang('Empty Platform'), '4:16'), array(lang('Borrowed Amplifier'), '3:39'), array(lang('Coastline'), '4:48'),
            array(lang('Small Hours'), '3:30'), array(lang('Goodnight Station'), '5:21'),
        ), 'featured' => true),
    'harbour' => array('title' => lang('Harbour Sessions'), 'format' => 'ep', 'note' => lang('Between the two albums'),
        'summary' => lang('Four songs recorded in a boathouse, with strings and the sound of the water.'),
        'credits' => lang('Strings arranged by Elif Kaya. Recorded in the boathouse on the old harbour.'),
        'tracks'  => array(
            array(lang('Boathouse'), '3:44'), array(lang('Tide Tables'), '4:02'), array(lang('Rope and Anchor'), '3:18'), array(lang('Coastline (Strings Version)'), '5:06'),
        ), 'featured' => false),
    'rooftop' => array('title' => lang('Rooftop Static'), 'format' => 'single', 'note' => lang('The newest single'),
        'summary' => lang('The single from the second album, with a new song on the other side.'),
        'credits' => lang('Produced by the band, mixed by Selin Arda.'),
        'tracks'  => array(array(lang('Rooftop Static'), '3:33'), array(lang('Fire Escape'), '3:07')),
        'featured' => true),
    'alive' => array('title' => lang('Alive at the Harbour Hall'), 'format' => 'live', 'note' => lang('Recorded on the last tour'),
        'summary' => lang('The last night of the tour, recorded from the desk with the crowd singing every chorus.'),
        'credits' => lang('Recorded live at the Harbour Hall. Mixed by Selin Arda.'),
        'tracks'  => array(
            array(lang('Last Ferry'), '4:02'), array(lang('Neon on the Water'), '4:31'), array(lang('Under the Bakery'), '4:20'),
            array(lang('Slow Traffic'), '6:15'), array(lang('Goodnight Station'), '7:48'),
        ), 'featured' => false),
);

// A release's length: its tracks counted and their minutes added up,
// "11 tracks, 46 min".
$length_text = function ($key) use ($releases) {
    $seconds = 0;
    foreach ($releases[$key]['tracks'] as $t) {
        list($m, $s) = explode(':', $t[1]);
        $seconds += 60 * (int)$m + (int)$s;
    }
    return sprintf(lang('%1$d tracks, %2$d min'), count($releases[$key]['tracks']), (int)round($seconds / 60));
};
// A release's track list as the value of its Tracks field: one line per
// track, its number, its title and its length.
$tracks_text = function ($key) use ($releases) {
    $lines = array();
    foreach ($releases[$key]['tracks'] as $n => $t) {
        $lines[] = sprintf(lang('%1$d. %2$s — %3$s'), $n + 1, $t[0], $t[1]);
    }
    return implode("\n", $lines);
};

// The states of a show's tickets, set by the band's staff on the show's
// record. The tickets are sold by the venue or a ticket service: the show
// links its page (ticket_url).
$ticket_states = array(
    'on_sale'  => array('title' => lang('Tickets on sale'), 'badge' => 'text-bg-primary'),
    'few_left' => array('title' => lang('Few tickets left'), 'badge' => 'text-bg-warning'),
    'sold_out' => array('title' => lang('Sold out'), 'badge' => 'text-bg-secondary'),
    'free'     => array('title' => lang('Free entry'), 'badge' => 'text-bg-success'),
);

// The countries of the sample shows, the tour list's filter.
$countries = array(
    'tr' => lang('Türkiye'),
    'de' => lang('Germany'),
    'nl' => lang('Netherlands'),
    'uk' => lang('United Kingdom'),
);

// The day of a sample show, counted from the day the template is opened so
// the tour is still to come (and the past shows are behind it): the first
// day after today with the ISO weekday given (1 = Monday), plus $weeks
// weeks; a negative $weeks counts back. Each show is a record of the Shows
// form, edited or deleted like any other.
$show_on = function ($weekday, $weeks = 0) {
    $day = new DateTime('today');
    do {
        $day->modify('+1 day');
    } while ((int)$day->format('N') !== $weekday);
    if ($weeks !== 0) $day->modify(($weeks > 0 ? '+' : '-') . (7 * abs($weeks)) . ' days');
    return $day->format('Y-m-d');
};

// The sample shows: six to come, three already played. 'doors' is the
// time the doors open; 'support' the band before, if any.
$shows = array(
    'istanbul' => array('city' => lang('Istanbul'), 'country' => 'tr', 'venue' => lang('Harbour Hall'), 'weekday' => 5, 'weeks' => 1,
        'doors' => '20:00', 'state' => 'few_left', 'support' => lang('The Quiet Coast'), 'featured' => true),
    'ankara'   => array('city' => lang('Ankara'), 'country' => 'tr', 'venue' => lang('The Old Mill'), 'weekday' => 6, 'weeks' => 1,
        'doors' => '19:30', 'state' => 'on_sale', 'support' => lang('The Quiet Coast'), 'featured' => true),
    'izmir'    => array('city' => lang('Izmir'), 'country' => 'tr', 'venue' => lang('Seafront Stage'), 'weekday' => 7, 'weeks' => 1,
        'doors' => '18:00', 'state' => 'free', 'support' => '', 'featured' => true),
    'berlin'   => array('city' => lang('Berlin'), 'country' => 'de', 'venue' => lang('Canal Club'), 'weekday' => 4, 'weeks' => 3,
        'doors' => '20:00', 'state' => 'on_sale', 'support' => lang('Night Swimmers'), 'featured' => false),
    'amsterdam' => array('city' => lang('Amsterdam'), 'country' => 'nl', 'venue' => lang('The Warehouse'), 'weekday' => 5, 'weeks' => 3,
        'doors' => '19:30', 'state' => 'sold_out', 'support' => lang('Night Swimmers'), 'featured' => false),
    'london'   => array('city' => lang('London'), 'country' => 'uk', 'venue' => lang('Riverside Ballroom'), 'weekday' => 6, 'weeks' => 3,
        'doors' => '19:00', 'state' => 'on_sale', 'support' => lang('Night Swimmers'), 'featured' => false),
    'hamburg'  => array('city' => lang('Hamburg'), 'country' => 'de', 'venue' => lang('Dockside Hall'), 'weekday' => 5, 'weeks' => -6,
        'doors' => '20:00', 'state' => 'sold_out', 'support' => '', 'featured' => false),
    'rotterdam' => array('city' => lang('Rotterdam'), 'country' => 'nl', 'venue' => lang('Port Stage'), 'weekday' => 6, 'weeks' => -6,
        'doors' => '19:30', 'state' => 'sold_out', 'support' => '', 'featured' => false),
    'eskisehir' => array('city' => lang('Eskişehir'), 'country' => 'tr', 'venue' => lang('Student Hall'), 'weekday' => 3, 'weeks' => -10,
        'doors' => '19:00', 'state' => 'sold_out', 'support' => '', 'featured' => false),
);
// A show's venue and city in one line, "Harbour Hall, Istanbul".
$where_text = function ($key) use ($shows) {
    return sprintf(lang('%1$s, %2$s'), $shows[$key]['venue'], $shows[$key]['city']);
};

// The merch the shop sells (with the shop), with its prices. The SKUs name
// the template, so the products are its own; every piece is posted, so
// each is a shippable product.
$merch = array(
    'shirt'    => array('sku' => 'MUSIC-SHIRT', 'icon' => 'bi-tag', 'title' => lang('Night Bus Tour T-shirt'),
        'text'  => lang('Black cotton, the tour dates on the back. Sizes S to XXL.'),
        'price' => array('USD' => 28, 'EUR' => 26, 'GBP' => 22, 'TRY' => 950)),
    'hoodie'   => array('sku' => 'MUSIC-HOODIE', 'icon' => 'bi-tag', 'title' => lang('Harbour Hoodie'),
        'text'  => lang('A heavy hoodie with the band\'s name in white. Sizes S to XXL.'),
        'price' => array('USD' => 55, 'EUR' => 50, 'GBP' => 44, 'TRY' => 1900)),
    'vinyl'    => array('sku' => 'MUSIC-VINYL-NIGHTBUS', 'icon' => 'bi-vinyl', 'title' => lang('Night Bus Hymns on Vinyl'),
        'text'  => lang('The second album on a double vinyl with a lyric book, signed by the band.'),
        'price' => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1200)),
    'cassette' => array('sku' => 'MUSIC-CASSETTE-FIRSTLIGHT', 'icon' => 'bi-cassette', 'title' => lang('First Light on Cassette'),
        'text'  => lang('The debut album on a clear cassette, with a download card.'),
        'price' => array('USD' => 12, 'EUR' => 11, 'GBP' => 10, 'TRY' => 420)),
    'tote'     => array('sku' => 'MUSIC-TOTE', 'icon' => 'bi-bag', 'title' => lang('Bakery Tote Bag'),
        'text'  => lang('A strong cotton bag for records and bread.'),
        'price' => array('USD' => 18, 'EUR' => 16, 'GBP' => 14, 'TRY' => 600)),
    'poster'   => array('sku' => 'MUSIC-POSTER', 'icon' => 'bi-image', 'title' => lang('Screen-printed Tour Poster'),
        'text'  => lang('A numbered poster, printed by hand in two colours.'),
        'price' => array('USD' => 20, 'EUR' => 18, 'GBP' => 16, 'TRY' => 700)),
);
// The gift cards the shop sells (with the shop).
$gift_cards = array(
    'gift25' => array('sku' => 'MUSIC-GIFT-25', 'icon' => 'bi-gift', 'title' => lang('Merch Gift Card'),
        'text'  => lang('A gift card for anything in the merch shop.'),
        'price' => array('USD' => 25, 'EUR' => 25, 'GBP' => 25, 'TRY' => 1000)),
    'gift50' => array('sku' => 'MUSIC-GIFT-50', 'icon' => 'bi-gift', 'title' => lang('Merch Gift Card, Large'),
        'text'  => lang('A larger gift card for a record and a shirt.'),
        'price' => array('USD' => 50, 'EUR' => 50, 'GBP' => 50, 'TRY' => 2000)),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// What the press says (the home and the press kit), each about one of the
// sample releases or about the band on stage. The papers are made up.
$press_quotes = array(
    array('release' => 'nightbus', 'source' => lang('City Sound Magazine'),
        'quote' => lang('An album that sounds like the last bus home: tired, warm and full of light.')),
    array('release' => 'firstlight', 'source' => lang('The Weekly Record'),
        'quote' => lang('A debut with more good choruses than most bands manage in a career.')),
    array('release' => 'alive', 'source' => lang('Stage and Amp'),
        'quote' => lang('On stage they play every song a little faster and a lot louder, and the crowd sings every word.')),
);

// Music videos (the videos page and the home), each from a sample release.
// The address is the video's page; the band puts its own in place.
$videos = array(
    array('release' => 'rooftop', 'title' => lang('Rooftop Static (Official Video)'), 'href' => '#'),
    array('release' => 'nightbus', 'title' => lang('Last Ferry (Official Video)'), 'href' => '#'),
    array('release' => 'alive', 'title' => lang('Goodnight Station (Live at the Harbour Hall)'), 'href' => '#'),
);

// Where the music can be heard: the listening services and the band's
// profiles. The addresses are placeholders the band replaces with its own.
$listen_on = array(
    array('icon' => 'bi-spotify', 'name' => 'Spotify'),
    array('icon' => 'bi-apple', 'name' => 'Apple Music'),
    array('icon' => 'bi-youtube', 'name' => 'YouTube'),
    array('icon' => 'bi-soundwave', 'name' => 'Bandcamp'),
);

// The band in four figures (the home and the press kit).
$figures = array(
    array('value' => '2', 'label' => lang('Albums and an EP')),
    array('value' => '120', 'label' => lang('Shows in five countries')),
    array('value' => '4', 'label' => lang('Friends in one van')),
    array('value' => '1', 'label' => lang('Bakery that still sends bread')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$format_titles = array();
foreach ($formats as $f) $format_titles[] = $f['title'];
$release_titles = array();
foreach ($releases as $r) $release_titles[] = $r['title'];
$country_titles = array_values($countries);
$ticket_state_titles = array();
foreach ($ticket_states as $t) $ticket_state_titles[] = $t['title'];
// What a promoter asks the band to play.
$event_kinds = array(lang('Festival'), lang('Club show'), lang('Private event'), lang('Corporate event'), lang('Something else'));
$capacity_choices = array(lang('Up to 300 people'), lang('300 to 1,000 people'), lang('1,000 to 5,000 people'), lang('More than 5,000 people'));
// The fee bands in the shop's currency, as the merch prices are written.
$fee_low = $money(array('USD' => 2000, 'EUR' => 1850, 'GBP' => 1600, 'TRY' => 70000));
$fee_high = $money(array('USD' => 8000, 'EUR' => 7400, 'GBP' => 6400, 'TRY' => 280000));
$fee_choices = array(
    sprintf(lang('Up to %s'), $fee_low),
    sprintf(lang('%1$s to %2$s'), $fee_low, $fee_high),
    sprintf(lang('More than %s'), $fee_high),
    lang('To be agreed'),
);
// The states of a booking enquiry, set by the band's staff in the panel.
$booking_statuses = array(lang('Enquiry received'), lang('Offer sent'), lang('Confirmed'), lang('Declined'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The query string of a link that opens a list with a filter set
// (?format=, ?country=) or a form with a choice made (?event=, ?release=):
// the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the mailing-list band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('list_band');
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
            $heading('h1', $title, 'pmu-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pmu-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pmu-display h3 mb-0'),
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

// Five stars, for what the press and the fans say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'mu-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the tour is on and where its dates are.
// A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-megaphone', 'text-primary'),
        $span(lang('On tour now: new shows added'), '', lang('Text')),
        $link(lang('See the dates'), '{{page:tour}}', 'link-primary fw-semibold'),
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

// The header: the band's name, the menu and the way to the tour dates. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page. The merch is the shop's and is
// linked from the shop pages only, so the header stands on a site without
// the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('tour', lang('Tour Dates')),
        array('discography', lang('Music')),
        array('videos', lang('Videos')),
        array('blog', lang('News')),
        array('about', lang('The Band')),
        array('press', lang('Press')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-music-note-beamed', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pmu-brand fs-3', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#mu-nav'),
                    $attr('aria-controls', 'mu-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-ticket-perforated'),
                            $span(lang('Tickets'), '', lang('Link Text')),
                        ), array('href' => '{{page:tour}}')),
                    )),
                ), array('id' => 'mu-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The listening services and profiles as a row of round icon links (the
// footer, the home and a release's page). The addresses are placeholders.
$listen_links = function ($class = 'd-flex flex-wrap gap-3') use ($el, $icon, $attr, $listen_on) {
    $links = array();
    foreach ($listen_on as $s) {
        $links[] = $el('a', 'link-body-emphasis fs-5', $s['name'], array($icon($s['icon'])), array('href' => '#', '_attrs' => array(
            $attr('aria-label', sprintf(lang('Listen on %s'), $s['name'])),
        )));
    }
    $links[] = $el('a', 'link-body-emphasis fs-5', 'Instagram', array($icon('bi-instagram')), array('href' => '#', '_attrs' => array($attr('aria-label', 'Instagram'))));
    return $el('div', $class, lang('Listening Links'), $links);
};

// The footer, on a dark band: the band in a sentence, the addresses for
// bookings, management and press, the listening links, the music by
// format, the tour by country, the fans' pages and the band's pages; at
// the bottom the year, the site's name and the label.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $formats, $countries, $artist, $query, $attr, $listen_links) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $format_links = array($link(lang('All Releases'), '{{page:discography}}', $plain));
    foreach ($formats as $f) $format_links[] = $link($f['title'], '{{page:discography}}?' . $query(array('format' => $f['title'])), $plain);
    $country_links = array($link(lang('All Shows'), '{{page:tour}}', $plain));
    foreach ($countries as $c) $country_links[] = $link($c, '{{page:tour}}?' . $query(array('country' => $c)), $plain);
    $country_links[] = $link(lang('Past Shows'), '{{page:past_shows}}', $plain);
    $contact_line = function ($icon_name, $label, $address) use ($el, $icon, $span, $link, $plain) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $label, array(
            $icon($icon_name, 'text-primary'),
            $span($label, 'fw-semibold', lang('Label')),
            $link($address, 'mailto:' . $address, $plain . ' text-break'),
        ));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Band'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-3 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-music-note-beamed', 'text-primary'),
                        $span('{{site_name}}', 'pmu-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($artist['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-calendar2-check', lang('Show Bookings'), $artist['booking']),
                        $contact_line('bi-briefcase', lang('Management'), $artist['management']),
                        $contact_line('bi-newspaper', lang('Press'), $artist['press']),
                    )),
                    $listen_links('d-flex flex-wrap gap-3 mt-3'),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Music Links'), array(
                    $heading('h2', lang('Music'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($format_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Tour Date Links'), array(
                    $heading('h2', lang('Tour Dates'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($country_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Fans'), array(
                    $heading('h2', lang('For Fans'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Mailing List'), '{{page:newsletter}}', $plain),
                        $link(lang('Videos'), '{{page:videos}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Band'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About the Band'), '{{page:about}}', $plain),
                        $link(lang('Press Kit'), '{{page:press}}', $plain),
                        $link(lang('Book the Band'), '{{page:booking}}', $plain),
                        $link(lang('My Enquiries'), '{{page:my_bookings}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($artist['label'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The mailing-list band: a full-width band in the palette's colour with
// the way onto the mailing list and the way to the tour dates. Shared, so
// every page that ends with it carries the same call.
$list_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Hear it first'), 'pmu-display display-6 mb-2'),
                    $para(lang('Join the mailing list for new songs, tour dates before they go on sale and the odd story from the van.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Join the Mailing List'), '{{page:newsletter}}', 'btn btn-light btn-lg rounded-pill px-4'),
                        $link(lang('Tour Dates'), '{{page:tour}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A release's card of the lists (the home and the discography): the
// cover, the format, the title linking the release page, where it sits in
// the band's story and its length. Every value is bound to the Releases
// form's field of the same name; the sample fills the canvas.
$release_card = function ($sample_key = 'nightbus') use ($el, $para, $span, $link, $image, $col, $bind, $releases, $formats, $length_text, $photo, $attr) {
    $r = $releases[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pmu-lift', lang('Release Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('cover-' . $sample_key, 600, 600), $r['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'cover', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($formats[$r['format']]['title'], 'pmu-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Format'), $bind('format')),
                $el('h3', 'pmu-display h4 position-static mb-1', lang('Release Title'), array(
                    $link($r['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($r['note'], 'small fw-semibold mb-2', $bind('note')),
                $para($length_text($sample_key), 'small text-body-secondary mt-auto mb-0', $bind('length')),
            )),
        )),
    ), '6', '3');
};

// The track list of a release's page: the Tracks field, one line per
// track, kept in its lines by pmu-tracks.
$track_list = function ($sample_key = 'nightbus') use ($el, $heading, $tracks_text, $bind) {
    return $el('section', 'mb-4', lang('Track List'), array(
        $heading('h2', lang('Track Listing'), 'pmu-display h4 mb-3'),
        $el('div', 'pmu-tracks', lang('Track Listing'), array(), array_merge(array('text' => $tracks_text($sample_key)), $bind('tracks'))),
    ));
};

// A show's row of the tour list: the date (pg-music-day, the width the
// Music: Tour Dates block gives a show's day, so the cities line up), the city and the country, the
// venue, the doors, the ticket state and the ticket link (the venue's or a
// ticket service's page, in a new window), and the show's own page. Every
// value is bound to the Shows form's field of the same name.
$show_row = function ($sample_key = 'istanbul') use ($el, $span, $link, $icon, $bind, $shows, $countries, $ticket_states, $show_on, $attr) {
    $s = $shows[$sample_key];
    $state = $ticket_states[$s['state']];
    return $el('article', 'd-flex flex-column flex-md-row align-items-md-center gap-3 py-3 border-bottom position-relative', lang('Show Row'), array(
        $el('div', 'pg-music-day pmu-date flex-shrink-0 fw-bold text-primary', lang('Show Date'), array(), array_merge(
            array('text' => $show_on($s['weekday'], $s['weeks'])), $bind('show_date'))),
        $el('div', 'flex-grow-1', lang('Show Place'), array(
            $el('h3', 'pmu-display h5 mb-1', lang('City'), array(
                $link($s['city'], '#', 'link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'city', 'href' => 'form_item_view'))),
                $span(', ', '', lang('Text')),
                $span($countries[$s['country']], 'fw-normal text-body-secondary', lang('Country'), $bind('country')),
            )),
            $el('p', 'd-flex flex-wrap gap-3 small text-body-secondary mb-0', lang('Show Facts'), array(
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Venue'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($s['venue'], '', lang('Venue'), $bind('venue')),
                )),
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Doors'), array(
                    $icon('bi-door-open', 'text-primary'),
                    $span(lang('Doors'), '', lang('Label')),
                    $span($s['doors'], '', lang('Value'), $bind('doors')),
                )),
            )),
        )),
        $el('div', 'd-flex flex-wrap align-items-center gap-2 flex-shrink-0', lang('Tickets'), array(
            $span($state['title'], 'badge rounded-pill ' . $state['badge'], lang('Ticket State'), $bind('ticket_state')),
            $link(lang('Tickets'), '#', 'btn btn-sm btn-primary rounded-pill px-3', array($attr('rel', 'noopener')),
                array('target' => '_blank', '_bindings' => array('href' => 'ticket_url'))),
        )),
    ));
};

// A band member's card (the about page and the press kit): the portrait,
// the name, what they play and a line about them. Page content.
$member_card = function ($key) use ($el, $heading, $para, $image, $col, $attr, $portrait, $members) {
    $m = $members[$key];
    return $col(array(
        $el('article', 'text-center', lang('Member Card'), array(
            $image($portrait($m['name'], 320), $m['name'], 'rounded-circle mb-3', array('_attrs' => array($attr('width', '160'), $attr('height', '160'), $attr('loading', 'lazy')))),
            $heading('h3', $m['name'], 'pmu-display h5 mb-1'),
            $para($m['role'], 'small fw-semibold text-primary mb-2'),
            $para($m['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// What the press says: the quote, the paper and the release it is about.
$press_quote_card = function ($n) use ($el, $para, $span, $col, $press_quotes, $releases) {
    $q = $press_quotes[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Press Quote'), array(
            $el('blockquote', 'mb-4', lang('Quote'), array($para($q['quote'], 'pmu-quote mb-0'))),
            $el('figcaption', 'mt-auto', lang('Source'), array(
                $span($q['source'], 'd-block fw-semibold', lang('Name')),
                $span($releases[$q['release']]['title'], 'd-block small text-body-secondary', lang('Release Title')),
            )),
        )),
    ), '', '4');
};

// A video's card (the videos page and the home): the still with a play
// mark, linking the video's page in a new window, and its title.
$video_card = function ($n) use ($el, $span, $image, $icon, $col, $attr, $photo, $videos) {
    $v = $videos[$n];
    return $col(array(
        $el('a', 'd-block link-body-emphasis text-decoration-none pmu-lift', lang('Video Card'), array(
            $el('span', 'd-block position-relative ratio ratio-16x9 rounded-4 overflow-hidden bg-body-tertiary mb-2', lang('Picture Frame'), array(
                $image($photo('video-' . $n, 800, 450), $v['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                $el('span', 'd-flex align-items-center justify-content-center', lang('Play'), array(
                    $icon('bi-play-circle-fill', 'display-4 text-white'),
                )),
            )),
            $span($v['title'], 'd-block fw-semibold', lang('Video Title')),
        ), array('href' => $v['href'], 'target' => '_blank', 'rel' => 'noopener')),
    ), '6', '4');
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
                $item('show_new', lang('New Show')),
                $item('release_new', lang('New Release')),
                $item('staff_bookings', lang('Booking Enquiries')),
                $item('press_file_new', lang('New Press File')),
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
    return $agree($id, 'consent', lang('I would like to receive the band\'s news, new songs and tour dates by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// booking enquiry takes documents (a rider, a stage plan) and pictures,
// so the default accepts PDFs, office files, text and pictures; one that
// takes only pictures is a collection's image kind (a release's cover).
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
                    $link(lang('Notes from the first night of the tour'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
$mu_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// A date filter against today: the shows to come (on or after today) or
// those already played (before today).
$mu_date_filter = function ($field, $operator) {
    return array('field' => $field, 'operator' => $operator, 'value' => '', 'dynamic_value' => 'current date');
};
// The answers of the Featured list: the first marks a record for the home.
$mu_featured_choices = array(lang('Yes'), lang('No'));

// A list's settings: the form, its record page, the order and the words
// shown when nothing matches.
$mu_list_config = function ($form_page, $detail_page, $order_field, $direction, $empty, $more) {
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
$mu_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The record pages' back link and Edit button, above the record.
$mu_toolbar = function ($page_key, $text) use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $page_key . '}}')),
        $edit_button(),
    ));
};
// What a record page shows when its record is not there.
$mu_not_found = function ($icon_name, $title, $text, $back_text, $back_page) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_page . '}}', 'btn btn-primary rounded-pill px-4'),
    ), $show_when('record_not_found'));
};
// A long field of a record page under its heading, its lines kept; the
// block leaves when the record has no value.
$mu_long_field = function ($title, $token, $sample, $class = 'mb-4') use ($el, $heading, $bind) {
    return $el('div', 'pg-hide-if-empty ' . $class, $title, array(
        $heading('h2', $title, 'pmu-display h4 mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// A link to an address the record holds, in a new window, shown only when
// the record has it (a listening service, a video, a ticket seller).
$mu_record_link = function ($text, $token, $class) use ($link, $attr) {
    return $link($text, '#', $class, array($attr('rel', 'noopener')),
        array('target' => '_blank', '_bindings' => array('href' => $token, 'show_if' => $token)));
};
// A staff page holding a collection's form in a card.
$mu_form_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
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


// ── The Music blocks ────────────────────────────────────────────────────
// The home, the discography and the press kit place the Music blocks
// (release hero, press kit); the tour lists draw a show's day as the tour
// dates block does. Their pg-music- rules ship in the template's User
// Styles.

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

// The Music: Release Hero block: the sample release $key as a record. A
// pg-music-sleeve with the square cover (pg-music-cover) and, from md up,
// the disc behind it (pg-music-disc, hidden from screen readers) sliding
// out; beside it where the release sits in the band's story, its title
// ($tag: an h1 when the block opens the page), the format and the track
// count, the summary, $buttons and the listening links. Under them the
// track listing as an ol.pg-music-tracks: number, title and length as a
// <time> with its ISO duration, and the credits. On load when the block
// opens the page, otherwise on view. A search section under $id.
$release_hero = function ($key, $id, $tag, $class, $buttons, $theme = '')
    use ($el, $heading, $para, $image, $span, $container, $row, $col, $fx, $attr, $searchable, $photo, $releases, $formats, $listen_links) {
    $r = $releases[$key];
    $opens = $tag === 'h1';
    $trigger = $opens ? 'load' : 'view';
    $lazy = $opens ? array() : array('_attrs' => array($attr('loading', 'lazy')));
    $tracks = array();
    foreach ($r['tracks'] as $n => $t) {
        list($m, $s) = explode(':', $t[1]);
        $tracks[] = $el('li', '', $t[0], array(
            $span((string)($n + 1), '', lang('Number')),
            $span($t[0], '', lang('Title')),
            $el('time', '', lang('Length'), array(), array('text' => $t[1], '_attrs' => array($attr('datetime', 'PT' . (int)$m . 'M' . (int)$s . 'S')))),
        ));
    }
    $count = count($r['tracks']);
    $sleeve = $el('div', 'pg-music-sleeve', lang('Record Sleeve'), array(
        $fx($el('div', 'pg-music-disc', lang('Disc'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
            array('effect' => 'slide-left', 'trigger' => $trigger, 'duration' => 1200, 'delay' => 300, 'distance' => 120)),
        $el('div', 'pg-music-cover rounded-2 shadow-lg', lang('Cover'), array(
            $image($photo('cover-' . $key, 1000, 1000), sprintf(lang('The cover of %s'), $r['title']), '', $lazy),
        )),
    ));
    $extra = $searchable($id, lang('album, single, record, tracks, songs, track listing, listen'));
    if ($theme !== '') $extra['_attrs'] = array($attr('data-bs-theme', $theme));
    return $el('section', trim($class . ' overflow-hidden'), lang('Music (Release Hero)'), array(
        $container(array(
            $row(array(
                $fx($col(array($sleeve), '', '6'), array('effect' => 'fade', 'trigger' => $trigger, 'duration' => 900)),
                $fx($col(array(
                    $para($r['note'], 'pmu-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                    $heading($tag, $r['title'], 'pmu-display ' . ($opens ? 'display-2' : 'display-4') . ' mb-2'),
                    $para($formats[$r['format']]['title'] . ' · ' . sprintf($count === 1 ? lang('%d song') : lang('%d songs'), $count), 'text-body-secondary fw-semibold mb-3'),
                    $para($r['summary'], 'lead mb-4'),
                    $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), $buttons),
                    $listen_links('d-flex flex-wrap gap-3'),
                ), '', '6'), array('effect' => 'slide-up', 'trigger' => $trigger, 'duration' => 700, 'delay' => 150)),
            ), '4', '', 'g-lg-5 align-items-center'),
            $fx($el('div', 'mt-5', lang('Track Listing'), array(
                $heading($opens ? 'h2' : 'h3', lang('Track Listing'), 'pmu-display h4 mb-3'),
                $el('ol', 'pg-music-tracks', lang('Tracks List'), $tracks),
                $para($r['credits'], 'small text-body-secondary mt-3 mb-0'),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800)),
        )),
    ), $extra);
};

// The Music: Press Kit block: the one-sheet for press and promoters (a
// line about the band and a dl.pg-music-sheet of genre, home town,
// line-up, label and the latest release, then the ways to book the band
// and to write to the press office) beside the press photos, each with a
// link that saves the picture, and the way to the press files below. The
// one-sheet fades, the photos rise on view. A search section under $id.
$press_kit = function ($id, $class = 'py-5')
    use ($el, $heading, $para, $link, $image, $span, $icon, $container, $row, $col, $fx, $rise, $attr, $searchable, $photo, $artist, $releases, $members) {
    $fact = function ($label, $value) use ($el, $span) {
        return $el('div', '', $label, array(
            $el('dt', '', lang('Label'), array(), array('text' => $label)),
            $el('dd', '', lang('Value'), array(), array('text' => $value)),
        ));
    };
    $line_up = array();
    foreach ($members as $m) $line_up[] = $m['name'];
    $shots = array();
    foreach (array('live' => lang('Live'), 'portrait' => lang('Portrait'), 'studio' => lang('Studio')) as $seed => $caption) {
        $src = $photo('press-' . $seed, 1800, 1350);
        $shots[] = $el('figure', 'col mb-0', $caption, array(
            $el('div', 'ratio ratio-4x3 rounded-3 overflow-hidden bg-body-tertiary mb-2', lang('Picture'), array(
                $image($photo('press-' . $seed, 900, 675), sprintf(lang('A press photo of the band: %s'), $caption), 'object-fit-cover',
                    array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('figcaption', 'd-flex justify-content-between align-items-center gap-2 small', lang('Photo Caption'), array(
                $span($caption, 'text-body-secondary', lang('Text')),
                $link(lang('Download'), $src, 'link-primary fw-semibold', array($attr('download', ''))),
            )),
        ));
    }
    $sheet = $col(array(
        $para(lang('For press and promoters'), 'pmu-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', lang('The band on one sheet'), 'pmu-display display-6 mb-3'),
        $para($artist['tagline'] . '.', 'mb-4'),
        $el('dl', 'pg-music-sheet mb-4', lang('Facts'), array(
            $fact(lang('Genre'), lang('Indie rock')),
            $fact(lang('From'), $artist['based']),
            $fact(lang('Line-up'), implode(', ', $line_up)),
            $fact(lang('Record Label'), $artist['label']),
            $fact(lang('Latest Release'), $releases['nightbus']['title']),
        )),
        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
            $link(lang('Book the Band'), '{{page:booking}}', 'btn btn-primary rounded-pill px-4'),
            $link(lang('Write to the Press Office'), 'mailto:' . $artist['press'], 'btn btn-outline-secondary rounded-pill px-4'),
        )),
    ), '', '5');
    $media = $col(array(
        $heading('h3', lang('Press Photos'), 'pmu-display h4 mb-3'),
        $rise($el('div', 'row row-cols-1 row-cols-sm-3 g-3 mb-4', lang('Photos'), $shots), 120),
        $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Text'), array(
            $icon('bi-file-earmark-arrow-down', 'fs-5 text-primary flex-shrink-0'),
            $link(lang('The rider, the stage plot and the logos are under Downloads'), '#mu-press-files', 'fw-semibold link-primary'),
        )),
    ), '', '7');
    return $el('section', $class, lang('Music (Press Kit)'), array(
        $container(array(
            $row(array(
                $fx($sheet, array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800)),
                $media,
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable($id, lang('press kit, one-sheet, genre, label, press photos, download')));
};
