<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Art Gallery", part 1 of its files: the node builders,
 * the gallery's own data (the gallery and its opening hours, the artists it
 * represents, the exhibitions now on, coming and past, the works, the
 * mediums, the advisory services, the journal's topics, the gallery in
 * figures, what collectors say and the answers of the forms' pick lists)
 * and the small helpers the pages share (page shell, page header, section
 * header, steps, check lists, questions).
 * cards.php and the page files of the same folder are included after it
 * in the same scope and build their trees with these variables;
 * includes/design_templates/pinegrap-gallery.php puts them together.
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
// every opening until the gallery puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-gallery-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language; a title such as "Dr." is left out.
$portrait = function ($name, $size = 480) {
    $initials = '';
    $words = array_values(array_filter(preg_split('/\s+/u', trim($name)), function ($w) { return substr($w, -1) !== '.'; }));
    foreach (array_slice($words, 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e7e5e4/292524?text=' . rawurlencode($initials !== '' ? $initials : 'G');
};




// ── The gallery: its hours, artists, exhibitions and works ──────────────

// The gallery itself. The about page tells its story; the footer, the
// viewing band and the contact lines use the address, the phone and the
// opening hours.
$gallery = array(
    'tagline' => lang('Contemporary art by living artists: exhibitions that change with the seasons, works you can live with, and a team that takes time over every question'),
    'story'   => lang('The gallery opened in a former print workshop with two rooms and five artists. It still shows only the artists it represents, follows each of them from one studio to the next, and opens every exhibition with a night for anyone who wants to come.'),
    'address' => lang('Foundry Lane 14, Old Harbour'),
    'access'  => lang('Step-free entrance, a lift to the upper room and seats in every room'),
    'phone'   => '+90 555 000 00 40',
    'email'   => 'hello@example.com',
);

// The opening hours in three lines, the way the footer, the visit page and
// the contact page show them. Entry is free.
$opening_hours = array(
    array('days' => lang('Tuesday to Friday'), 'hours' => '11:00–19:00'),
    array('days' => lang('Saturday'), 'hours' => '11:00–18:00'),
    array('days' => lang('Sunday and Monday'), 'hours' => lang('By appointment')),
);

// The mediums the gallery's artists work in: the artists' and the works'
// filter (?medium=). Each answer's value is its own words, so a filter
// link reads as it shows.
$mediums = array(
    'painting'    => lang('Painting'),
    'sculpture'   => lang('Sculpture'),
    'photography' => lang('Photography'),
    'paper'       => lang('Works on Paper'),
    'ceramics'    => lang('Ceramics'),
);

// The artists the gallery represents: the records the Artists form starts
// with. The artists page, an artist's page and the home read them;
// 'medium' is the artist's main medium (a key of $mediums), 'based' where
// the studio is.
$artists = array(
    'lena'  => array('name' => lang('Lena Varga'), 'medium' => 'painting', 'based' => lang('Lives and works in Istanbul'),
        'summary'   => lang('Large, quiet paintings of rooms at the hour the light leaves them.'),
        'statement' => lang('I paint the rooms people have just walked out of. What stays behind, a chair turned to the window, a cup on the sill, says more about them than a face would.'),
        'bio'       => lang('Lena trained as a set painter before she turned to her own canvases. She works in oil on linen in a studio by the water, in series of six to ten paintings that take a year or more.')),
    'tomas' => array('name' => lang('Tomás Reyes'), 'medium' => 'sculpture', 'based' => lang('Lives and works between Izmir and Lisbon'),
        'summary'   => lang('Sculpture in salvaged steel and stone, made to be walked around and touched.'),
        'statement' => lang('Every piece starts with something someone threw away: a beam from a demolished house, a stone from a harbour wall. I keep the marks of its first life and give it a second one.'),
        'bio'       => lang('Tomás grew up in a family of welders. His sculptures stand in public squares, private gardens and in the gallery\'s courtyard, and he makes smaller works for the home in each series.')),
    'aiko'  => array('name' => lang('Aiko Tanabe'), 'medium' => 'photography', 'based' => lang('Lives and works in Ankara'),
        'summary'   => lang('Long-exposure photographs of cities at night, printed by hand in small editions.'),
        'statement' => lang('A long exposure lets a city draw itself. People disappear, lights become lines, and the buildings show what they look like when nobody is watching.'),
        'bio'       => lang('Aiko works with a large-format camera and prints every photograph herself in her darkroom. Each image is made in an edition of no more than eight, signed and numbered.')),
    'deniz' => array('name' => lang('Deniz Aksoy'), 'medium' => 'paper', 'based' => lang('Lives and works in Bursa'),
        'summary'   => lang('Ink drawings and cut paper that map gardens, rivers and the routes of migrating birds.'),
        'statement' => lang('I draw maps of places that move: a river that changes its bed, a flock that takes a different route every spring. Paper is the right material for it, light and easy to tear.'),
        'bio'       => lang('Deniz studied botany before art. The drawings are made on handmade paper with ink she grinds herself, some cut and layered into reliefs that cast their own shadows.')),
    'mira'  => array('name' => lang('Mira Novak'), 'medium' => 'ceramics', 'based' => lang('Lives and works in Çanakkale'),
        'summary'   => lang('Hand-built vessels and wall pieces glazed with the ash of the trees around her kiln.'),
        'statement' => lang('Clay remembers every touch. I build slowly by hand, fire in a wood kiln and let the fire decide the last colour; no two pieces come out the same.'),
        'bio'       => lang('Mira built her own wood-fired kiln on a hillside above the sea. She fires three times a year, and each firing becomes a small exhibition of vessels, tiles and wall pieces.')),
);

// The states of an exhibition, the exhibitions page's filter (?status=).
$exhibition_statuses = array(
    'now'    => lang('Now On'),
    'coming' => lang('Coming Soon'),
    'past'   => lang('Past'),
);

// The exhibitions: the records the Exhibitions form starts with. 'artists'
// are keys of $artists, 'dates' and 'opening' are words, never a date: the
// gallery writes its own when it puts the exhibition on.
$exhibitions = array(
    'after_hours' => array('title' => lang('After Hours'), 'status' => 'now', 'artists' => array('lena'),
        'dates'   => lang('Until the end of the season'),
        'opening' => lang('Opened on a Thursday evening with the artist'),
        'summary' => lang('New paintings of empty rooms, offices and cafés after closing time.'),
        'text'    => lang('Lena Varga\'s first exhibition in three years brings together nine paintings made at night in the rooms of her neighbourhood. Each one is painted from the doorway, at the moment the last person has left.')),
    'second_life' => array('title' => lang('Second Life'), 'status' => 'now', 'artists' => array('tomas', 'mira'),
        'dates'   => lang('In the courtyard and the upper room for six weeks'),
        'opening' => lang('Opened with a talk by both artists'),
        'summary' => lang('Steel, stone and wood-fired clay: two artists who make new work from old material.'),
        'text'    => lang('Tomás Reyes and Mira Novak have never shown together, yet both start from what a place leaves behind. The exhibition puts large steel pieces in the courtyard beside vessels and wall pieces fired with the ash of fallen trees.')),
    'night_lines' => array('title' => lang('Night Lines'), 'status' => 'coming', 'artists' => array('aiko'),
        'dates'   => lang('Opens at the start of next month for seven weeks'),
        'opening' => lang('Opening night: Thursday, 6 to 9 pm, all welcome'),
        'summary' => lang('Long-exposure photographs of three cities, printed by hand.'),
        'text'    => lang('Aiko Tanabe spent a year photographing the same streets of three cities after midnight. The prints, made in her own darkroom, show the traffic as rivers of light and the buildings as nobody sees them by day.')),
    'routes'      => array('title' => lang('Routes'), 'status' => 'past', 'artists' => array('deniz'),
        'dates'   => lang('Shown last season in the front room'),
        'opening' => lang('Opened with a walk along the river with the artist'),
        'summary' => lang('Drawings and paper reliefs that follow birds, rivers and gardens.'),
        'text'    => lang('Deniz Aksoy\'s maps of moving places filled the front room with ink drawings and layered paper. Some of the works are still available from the gallery.')),
);

// The states of a work, set by the gallery in the panel and shown as a
// badge on the work's card and page.
$availability = array(
    'available' => lang('Available'),
    'reserved'  => lang('Reserved'),
    'sold'      => lang('Sold'),
);

// The works: the records the Works form starts with. 'artist' is a key of
// $artists, 'exhibition' a key of $exhibitions (or '' for a work shown
// only on the artist's page), 'technique' and 'size' are words, 'price'
// the price per currency read with $money, or null for "Price on request".
$works = array(
    'last_table'   => array('title' => lang('The Last Table'), 'artist' => 'lena', 'exhibition' => 'after_hours', 'state' => 'available',
        'technique' => lang('Oil on linen'), 'size' => '120 × 150 cm', 'price' => array('USD' => 9500, 'EUR' => 8800, 'GBP' => 7600, 'TRY' => 320000)),
    'window_seat'  => array('title' => lang('Window Seat'), 'artist' => 'lena', 'exhibition' => 'after_hours', 'state' => 'reserved',
        'technique' => lang('Oil on linen'), 'size' => '90 × 70 cm', 'price' => array('USD' => 6200, 'EUR' => 5700, 'GBP' => 4900, 'TRY' => 210000)),
    'harbour_beam' => array('title' => lang('Harbour Beam'), 'artist' => 'tomas', 'exhibition' => 'second_life', 'state' => 'available',
        'technique' => lang('Salvaged steel and limestone'), 'size' => '210 × 60 × 45 cm', 'price' => null),
    'small_gate'   => array('title' => lang('Small Gate'), 'artist' => 'tomas', 'exhibition' => 'second_life', 'state' => 'sold',
        'technique' => lang('Forged steel'), 'size' => '48 × 30 × 12 cm', 'price' => array('USD' => 3400, 'EUR' => 3100, 'GBP' => 2700, 'TRY' => 115000)),
    'ash_vessel'   => array('title' => lang('Ash Vessel'), 'artist' => 'mira', 'exhibition' => 'second_life', 'state' => 'available',
        'technique' => lang('Wood-fired stoneware'), 'size' => '38 × 26 cm', 'price' => array('USD' => 1200, 'EUR' => 1100, 'GBP' => 950, 'TRY' => 40000)),
    'bridge_2am'   => array('title' => lang('Bridge at Two'), 'artist' => 'aiko', 'exhibition' => 'night_lines', 'state' => 'available',
        'technique' => lang('Silver gelatin print, edition of 8'), 'size' => '80 × 100 cm', 'price' => array('USD' => 2800, 'EUR' => 2600, 'GBP' => 2200, 'TRY' => 95000)),
    'river_map'    => array('title' => lang('River Map'), 'artist' => 'deniz', 'exhibition' => 'routes', 'state' => 'available',
        'technique' => lang('Ink and cut paper on handmade paper'), 'size' => '70 × 100 cm', 'price' => array('USD' => 2100, 'EUR' => 1900, 'GBP' => 1650, 'TRY' => 70000)),
    'spring_route' => array('title' => lang('Spring Route'), 'artist' => 'deniz', 'exhibition' => '', 'state' => 'available',
        'technique' => lang('Ink on handmade paper'), 'size' => '50 × 65 cm', 'price' => null),
);

// What the gallery does beyond its exhibitions (the advisory page and the
// home). Each card sends the visitor to an enquiry with its kind chosen.
$advisory = array(
    'first_work' => array('icon' => 'bi-house-heart', 'title' => lang('Your First Work'),
        'text' => lang('Not sure where to start? We show you works within your budget, explain how prices are set and let you live with a piece at home before you decide.')),
    'collection' => array('icon' => 'bi-collection', 'title' => lang('Building a Collection'),
        'text' => lang('For collectors who want their works to speak to each other: studio visits, early previews of new series and advice on what to add next.')),
    'workplace'  => array('icon' => 'bi-building', 'title' => lang('Art for Workplaces'),
        'text' => lang('Works chosen for an office, a hotel or a clinic, with a plan for every wall, installation by our team and loans for a season.')),
    'commission' => array('icon' => 'bi-palette', 'title' => lang('Commissions'),
        'text' => lang('A work made for a place or an occasion by one of our artists: we agree the brief, the size, the price and the time it takes before the artist begins.')),
    'framing'    => array('icon' => 'bi-bounding-box', 'title' => lang('Framing and Installation'),
        'text' => lang('Museum framing, careful packing and delivery, and a technician who hangs or sets the work in its place.')),
);

// The journal's topics: the Journal list's filter (?topic=).
$topics = array(
    'studio'        => lang('Studio Visits'),
    'exhibitions'   => lang('Exhibitions'),
    'collecting'    => lang('Collecting'),
    'conversations' => lang('Conversations'),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A work's price, or "Price on request" when the gallery gives it only to
// an enquiry.
$work_price = function ($key) use ($works, $money) {
    if ($works[$key]['price'] === null) return lang('Price on request');
    return $money($works[$key]['price']);
};

// Buying a work in four steps (the advisory page and a work's page).
$buying_steps = array(
    array('title' => lang('Ask about the work'), 'text' => lang('Send an enquiry from the work\'s page; we answer with the price, its condition and how it can reach you.')),
    array('title' => lang('See it in person'), 'text' => lang('Come to the gallery, book a private viewing, or ask for photographs of the work in daylight.')),
    array('title' => lang('Reserve it'), 'text' => lang('We hold the work for you for a few days while you decide; nothing is paid before you are sure.')),
    array('title' => lang('Take it home'), 'text' => lang('We frame, pack and deliver the work, and hang it if you wish, with its certificate signed by the artist.')),
);

// The gallery in four figures (the home and about pages).
$figures = array(
    array('value' => '5', 'label' => lang('Artists we represent')),
    array('value' => '6', 'label' => lang('Exhibitions every year')),
    array('value' => '2', 'label' => lang('Rooms and a courtyard')),
    array('value' => '0', 'label' => lang('Entry fee, always')),
);

// What collectors say, each about one of the artists.
$testimonials = array(
    array('artist' => 'lena', 'name' => lang('Ayla Demir'), 'role' => lang('Collector'),
        'quote' => lang('They let me take the painting home for a weekend before I decided. It has been on the same wall ever since, and I still notice something new in it.')),
    array('artist' => 'tomas', 'name' => lang('Jonas Weber'), 'role' => lang('Architect'),
        'quote' => lang('We commissioned a sculpture for the entrance of our studio. The gallery handled the brief, the timing and the installation; Tomás only had to do what he does best.')),
    array('artist' => 'mira', 'name' => lang('Elif Kara'), 'role' => lang('First-time buyer'),
        'quote' => lang('I came to an opening night without meaning to buy anything and left with a small vessel. Nobody pushed; they simply told me the story of the firing.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$artist_names = array();
foreach ($artists as $a) $artist_names[] = $a['name'];
$medium_titles = array_values($mediums);
$work_titles = array();
foreach ($works as $w) $work_titles[] = $w['title'];
// The enquiry's work: each work by title, or another one.
$work_choices = array_merge($work_titles, array(lang('Another work')));
// The enquiry's kind; the advisory cards choose one of them (?kind=).
$enquiry_choices = array(lang('Buying a work'), lang('Price and availability'), lang('A commission'), lang('Shipping and framing'), lang('Art for a workplace'), lang('Something else'));
$exhibition_titles = array();
foreach ($exhibitions as $x) $exhibition_titles[] = $x['title'];
// The private viewing's exhibition: the exhibitions now on or coming, or
// the gallery's other works.
$exhibition_choices = array();
foreach ($exhibitions as $x) if ($x['status'] !== 'past') $exhibition_choices[] = $x['title'];
$exhibition_choices[] = lang('The gallery\'s other works');
$status_titles = array_values($exhibition_statuses);
$availability_titles = array_values($availability);
$time_choices = array(lang('Morning'), lang('Afternoon'), lang('Evening'));
$party_choices = array(lang('Just me'), lang('Two people'), lang('Three to five people'), lang('Six or more people'));
$topic_titles = array_values($topics);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of an enquiry, set by the gallery in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Answered'), lang('Reserved for you'), lang('Sold'), lang('Closed'));
// The states of a private viewing request, set in the panel.
$viewing_statuses = array(lang('Viewing requested'), lang('Confirmed'), lang('Another time offered'), lang('Cancelled'));

// The query string of a link that opens a list with a filter set
// (?medium=, ?artist=, ?status=, ?topic=) or a form with a choice made
// (?artwork=, ?kind=, ?exhibition=): the form's fields start with these
// values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the private viewing band above the footer unless the
// page is a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('viewing_band');
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
            $heading('h1', $title, 'pgl-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pgl-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pgl-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'gl-faq') use ($el, $para, $attr, $searchable) {
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

