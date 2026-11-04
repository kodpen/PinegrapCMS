<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Real Estate Agency", part 1 of its files: the node
 * builders, the agency itself (its neighbourhoods, property types, agents,
 * figures, services, the sample listings and the answers of the forms'
 * pick lists) and the parts every page repeats (account bar, header,
 * footer, valuation band, listing and agent cards, page shells, section
 * headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-realestate.php puts them together.
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
// opening until the agency puts its own in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-realestate-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: the person's initials on the palette's colour. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ccfbf1/115e59?text=' . rawurlencode($initials !== '' ? $initials : 'R');
};

// ── The agency: its neighbourhoods, agents, services and listings ───────

// The neighbourhoods the agency works in, in the order the pages list them.
// Each has its own page (area_<key>), what living there is like, a typical
// price, the agent who knows it best and the words a visitor may search for.
$areas = array(
    'seafront'  => array('icon' => 'bi-water', 'title' => lang('Seafront'), 'agent' => 'deniz',
        'text'       => lang('Flats with a sea view, a promenade for the evening walk and the ferry a few minutes away.'),
        'highlights' => array(lang('Ferry and tram within walking distance'), lang('Cafés and restaurants on the promenade'),
                              lang('Mostly flats, many with a balcony to the sea')),
        'price'      => lang('Flats from $320,000; rents from $1,400 a month'),
        'topics'     => lang('sea view, seaside, promenade, ferry, coast')),
    'oldtown'   => array('icon' => 'bi-buildings', 'title' => lang('Old Town'), 'agent' => 'selin',
        'text'       => lang('Restored stone houses on quiet lanes, with the markets, museums and the old harbour on foot.'),
        'highlights' => array(lang('Listed buildings, restored with care'), lang('Markets, museums and the old harbour'),
                              lang('Lively in the day, quiet at night')),
        'price'      => lang('Houses from $450,000; rents from $1,600 a month'),
        'topics'     => lang('historic, stone house, centre, old harbour, character')),
    'parkside'  => array('icon' => 'bi-tree', 'title' => lang('Parkside'), 'agent' => 'emre',
        'text'       => lang('A green district for families: good schools, the city park and new buildings with a garden.'),
        'highlights' => array(lang('Three primary schools and a high school'), lang('The city park and its playgrounds'),
                              lang('New buildings with parking and a garden')),
        'price'      => lang('Family flats from $280,000; rents from $1,200 a month'),
        'topics'     => lang('family, schools, park, garden, quiet')),
    'hillside'  => array('icon' => 'bi-sun', 'title' => lang('Hillside'), 'agent' => 'deniz',
        'text'       => lang('Detached houses and villas on the slope, with a garden, a pool and the whole bay in view.'),
        'highlights' => array(lang('Detached houses and villas with a garden'), lang('Views over the bay'),
                              lang('Ten minutes by car to the centre')),
        'price'      => lang('Villas from $750,000; rents from $3,000 a month'),
        'topics'     => lang('villa, detached house, pool, garden, view')),
    'business'  => array('icon' => 'bi-briefcase', 'title' => lang('Business District'), 'agent' => 'emre',
        'text'       => lang('Offices, shops and city flats beside the metro, for working and living close to work.'),
        'highlights' => array(lang('Metro, buses and the airport line'), lang('Offices and shops on the main avenue'),
                              lang('Compact flats that let quickly')),
        'price'      => lang('Offices from $2,500 a month; flats from $240,000'),
        'topics'     => lang('office, shop, commercial, metro, investment')),
);

// The deals, property types and states of a listing: the answers of the
// Listings form's pick lists and the words the lists filter on. Each
// answer's value is its own words, so a filter link reads as it shows.
$deals = array('sale' => lang('For sale'), 'rent' => lang('For rent'));
$property_types = array(
    'apartment' => array('icon' => 'bi-building', 'title' => lang('Apartment')),
    'house'     => array('icon' => 'bi-house', 'title' => lang('House')),
    'villa'     => array('icon' => 'bi-house-heart', 'title' => lang('Villa')),
    'office'    => array('icon' => 'bi-briefcase', 'title' => lang('Office')),
    'shop'      => array('icon' => 'bi-shop', 'title' => lang('Shop')),
    'land'      => array('icon' => 'bi-map', 'title' => lang('Land')),
);
$listing_statuses = array(lang('On the market'), lang('Under offer'), lang('Sale completed'), lang('Rented'));

// The agents, in the order the pages list them, with the neighbourhoods
// they know best and the languages they work in.
$agents = array(
    'deniz' => array('name' => lang('Deniz Aydın'), 'role' => lang('Founder and Sales Director'), 'areas' => array('seafront', 'hillside'),
        'bio'       => lang('Fifteen years of selling homes by the sea; she prices every one as if it were her own.'),
        'languages' => lang('Turkish, English, Russian'),
        'phone'     => '+90 555 000 00 01'),
    'selin' => array('name' => lang('Selin Kaya'), 'role' => lang('Senior Agent'), 'areas' => array('oldtown'),
        'bio'       => lang('Knows every lane of the old town and the rules for restoring a listed house.'),
        'languages' => lang('Turkish, English, Italian'),
        'phone'     => '+90 555 000 00 02'),
    'emre'  => array('name' => lang('Emre Şahin'), 'role' => lang('Lettings and Commercial'), 'areas' => array('parkside', 'business'),
        'bio'       => lang('Finds tenants for flats and offices and looks after the homes the agency manages.'),
        'languages' => lang('Turkish, English, German'),
        'phone'     => '+90 555 000 00 03'),
    'leyla' => array('name' => lang('Leyla Demir'), 'role' => lang('Buyer Adviser'), 'areas' => array('parkside', 'seafront'),
        'bio'       => lang('Helps buyers from the first viewing to the title deed, mortgage and paperwork included.'),
        'languages' => lang('Turkish, English, Arabic'),
        'phone'     => '+90 555 000 00 04'),
);

// The agency in four figures (the home and about pages).
$figures = array(
    array('value' => '18', 'label' => lang('Years in the city')),
    array('value' => '2,400+', 'label' => lang('Homes sold and let')),
    array('value' => '27', 'label' => lang('Days to sell, on average')),
    array('value' => '4.9/5', 'label' => lang('Client rating')),
);

// What the agency does, in the order the services page lists them. Each
// sends to the request that starts it.
$services = array(
    'sell'   => array('icon' => 'bi-tag', 'title' => lang('Selling your home'), 'page' => 'valuation',
        'text'   => lang('A free valuation, professional photos and a buyer who has seen the paperwork before the offer.'),
        'points' => array(lang('Free valuation within two working days'), lang('Photos, floor plan and video tour'),
                          lang('Viewings arranged and accompanied'), lang('Offer, contract and title deed handled for you'))),
    'let'    => array('icon' => 'bi-key', 'title' => lang('Letting your home'), 'page' => 'valuation',
        'text'   => lang('Tenants checked before they are shown in, a written contract and a deposit kept by the rules.'),
        'points' => array(lang('Rent advice from recent lettings'), lang('Tenant references and income checked'),
                          lang('Contract, inventory and the keys handed over'), lang('Deposit held and returned by the rules'))),
    'manage' => array('icon' => 'bi-tools', 'title' => lang('Property management'), 'page' => 'contact',
        'text'   => lang('Rent collected, repairs arranged and a report every month, for owners who live far away.'),
        'points' => array(lang('Rent collected and paid on to you'), lang('Repairs by trusted trades, approved by you'),
                          lang('Inspections twice a year'), lang('A monthly statement'))),
    'buy'    => array('icon' => 'bi-search-heart', 'title' => lang('Finding a home'), 'page' => 'listings',
        'text'   => lang('Tell us what you are looking for; we show you the homes that fit, including some not yet listed.'),
        'points' => array(lang('Viewings in one day, in one car'), lang('Title deed and zoning checked before the offer'),
                          lang('Help with the mortgage and the paperwork'), lang('No fee for buyers on our own listings'))),
);

// The sample listings: the records the Listings form starts with. The
// lists, the listing page and the viewing form read them; the home shows
// the first three. Each names a deal, type, neighbourhood, status and agent
// of the lists above; 'price' is the number the lists sort on, 'price_label'
// the words the cards show.
$listings = array(
    'seaview'   => array('title' => lang('Bright two-bedroom flat with a sea view'), 'deal' => 'sale', 'type' => 'apartment',
        'area' => 'seafront', 'status' => 0, 'agent' => 'deniz', 'price' => 385000, 'price_label' => '$385,000',
        'bedrooms' => 2, 'bathrooms' => 1, 'size' => 95,
        'features' => array(lang('Balcony to the sea'), lang('Lift'), lang('Renovated kitchen'), lang('Five minutes to the ferry')),
        'text'     => lang('A third-floor flat on the promenade, full of light, with a balcony that looks over the bay.')),
    'stone'     => array('title' => lang('Restored stone house in the Old Town'), 'deal' => 'sale', 'type' => 'house',
        'area' => 'oldtown', 'status' => 1, 'agent' => 'selin', 'price' => 520000, 'price_label' => '$520,000',
        'bedrooms' => 3, 'bathrooms' => 2, 'size' => 160,
        'features' => array(lang('Courtyard garden'), lang('Original stone walls'), lang('Roof terrace'), lang('Restoration permits on file')),
        'text'     => lang('A nineteenth-century house restored in 2022, with a quiet courtyard and a terrace over the rooftops.')),
    'villa'     => array('title' => lang('Villa with a pool and a view of the bay'), 'deal' => 'sale', 'type' => 'villa',
        'area' => 'hillside', 'status' => 0, 'agent' => 'deniz', 'price' => 890000, 'price_label' => '$890,000',
        'bedrooms' => 4, 'bathrooms' => 3, 'size' => 240,
        'features' => array(lang('Private pool'), lang('Garden of 600 m²'), lang('Garage for two cars'), lang('Underfloor heating')),
        'text'     => lang('A detached villa on the slope, every room facing the bay, with a pool and a garden around it.')),
    'family'    => array('title' => lang('Three-bedroom family flat by the park'), 'deal' => 'rent', 'type' => 'apartment',
        'area' => 'parkside', 'status' => 0, 'agent' => 'emre', 'price' => 1500, 'price_label' => lang('$1,500 a month'),
        'bedrooms' => 3, 'bathrooms' => 2, 'size' => 130,
        'features' => array(lang('Shared garden and playground'), lang('Parking space'), lang('Two schools nearby'), lang('Available now')),
        'text'     => lang('A new-build flat on the second floor, a short walk from the park and the schools.')),
    'studio'    => array('title' => lang('Furnished studio near the metro'), 'deal' => 'rent', 'type' => 'apartment',
        'area' => 'business', 'status' => 3, 'agent' => 'emre', 'price' => 850, 'price_label' => lang('$850 a month'),
        'bedrooms' => 1, 'bathrooms' => 1, 'size' => 42,
        'features' => array(lang('Fully furnished'), lang('Two minutes to the metro'), lang('Concierge'), lang('Bills included')),
        'text'     => lang('A compact studio for working in the city, furnished and ready to move into.')),
    'office'    => array('title' => lang('Open-plan office on the main avenue'), 'deal' => 'rent', 'type' => 'office',
        'area' => 'business', 'status' => 0, 'agent' => 'emre', 'price' => 3200, 'price_label' => lang('$3,200 a month'),
        'bedrooms' => 0, 'bathrooms' => 2, 'size' => 210,
        'features' => array(lang('Meeting room'), lang('Reception desk'), lang('Fibre internet'), lang('Four parking spaces')),
        'text'     => lang('A bright office floor for twenty desks, with a meeting room and a view of the avenue.')),
);

// The answers of the request forms' pick lists. Each answer's value is its
// own words, so the e-mails and the lists read as the client chose.
$area_titles = array();
foreach ($areas as $a) $area_titles[] = $a['title'];
$type_titles = array();
foreach ($property_types as $t) $type_titles[] = $t['title'];
$agent_choices = array(lang('No preference'));
foreach ($agents as $a) $agent_choices[] = $a['name'];
$contact_choices = array(
    array(lang('Phone'), lang('Phone')),
    array(lang('E-mail'), lang('E-mail')),
    array(lang('WhatsApp'), lang('WhatsApp')),
);
// The times of day, inside the office hours ($hours).
$time_windows = array(
    lang('Morning (09:00–12:00)'),
    lang('Afternoon (12:00–16:00)'),
    lang('Evening (16:00–19:00)'),
);
// The query string of a link that opens a list with filters set (?deal=,
// ?type=, ?area=) or a request form with a choice made (?listing=,
// ?agent=): the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar. A shared component.
$notice = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-house-check', 'text-primary'),
        $span(lang('Selling or letting? Ask for a free valuation; an agent visits within two working days.'), '', lang('Text')),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body-tertiary', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'),
            )),
        )),
    ));
};

// The header: the agency's name, the menu and the way to ask for a
// valuation. The links to the current page are marked active on the
// server (smart active state), which is what lets one header serve every
// page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('buy', lang('Homes for Sale')),
        array('rent', lang('Homes to Rent')),
        array('neighbourhoods', lang('Neighbourhoods')),
        array('services', lang('Services')),
        array('agents', lang('Our Agents')),
        array('blog', lang('Market News')),
        array('contact', lang('Contact us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-semibold', lang('Brand'), array(
                    $el('span', 'd-inline-flex p-2 rounded-3 bg-primary text-white lh-1', lang('Icon Box'), array($icon('bi-house-door', 'fs-5'))),
                    $span('{{site_name}}', 'pre-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#re-nav'),
                    $attr('aria-controls', 're-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav mx-xl-auto column-gap-xl-1 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 gap-xl-3 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-house-check'),
                            $span(lang('Free Valuation'), '', lang('Link Text')),
                        ), array('href' => '{{page:valuation}}')),
                    )),
                ), array('id' => 're-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The office hours, as a definition list: the day, then the hours.
$hours = function ($class = 'row small mb-0') use ($el) {
    $children = array();
    foreach (array(
        array(lang('Monday–Friday'), '09:00–19:00'),
        array(lang('Saturday'), '10:00–16:00'),
        array(lang('Sunday'), lang('Viewings by appointment')),
    ) as $d) {
        $children[] = $el('dt', 'col-6 fw-normal text-body-secondary', lang('Day'), array(), array('text' => $d[0]));
        $children[] = $el('dd', 'col-6 mb-2', lang('Time'), array(), array('text' => $d[1]));
    }
    return $el('dl', $class, lang('List'), $children);
};

// The footer, on a dark band: the agency in a sentence, the links for
// clients, the neighbourhoods, how to reach the office and the hours; at
// the bottom the line that a listing's details are given in good faith.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $hours, $areas, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $area_links = array();
    foreach ($areas as $key => $a) $area_links[] = $link($a['title'], '{{page:area_' . $key . '}}', $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Agency'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-semibold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-house-door', 'text-primary'),
                        $span('{{site_name}}', '', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('A local estate agency: homes to buy and to rent, honest valuations and an agent who picks up the phone.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Clients'), array(
                    $heading('h2', lang('Clients'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('All Listings'), '{{page:listings}}', $plain),
                        $link(lang('Free Valuation'), '{{page:valuation}}', $plain),
                        $link(lang('My Viewings'), '{{page:my_viewings}}', $plain),
                        $link(lang('My Valuations'), '{{page:my_valuations}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Buyer\'s Guide'), '{{page:guide}}', $plain),
                        $link(lang('Help Center'), '{{page:search}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Neighbourhoods'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($area_links),
                )),
                $el('div', 'col-12 col-md-4 col-lg-4', lang('Contact Details'), array(
                    $heading('h2', lang('Our Office'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')), lang('Address')),
                    $contact_line('bi-telephone', $link('+90 555 000 00 00', 'tel:+905550000000', $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link('hello@example.com', 'mailto:hello@example.com', $plain . ' text-break'), lang('Email')),
                    $hours('row small mt-3 mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Listing details are given in good faith and are not part of any contract; ask us to confirm what matters to you.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The valuation band: a full-width band in the palette's soft colour with
// the way to ask for a valuation. Shared, so every page that ends with it
// carries the same offer.
$valuation_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary-subtle', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('What is your home worth?'), 'h3 fw-semibold mb-2'),
                    $para(lang('Tell us about it in a few lines; an agent visits within two working days and gives you a price in writing.'), 'text-body-secondary mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Free Valuation'), '{{page:valuation}}', 'btn btn-primary btn-lg px-4'),
                        $link(lang('Call us'), 'tel:+905550000000', 'btn btn-outline-primary btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A listing's facts in one line: bedrooms, bathrooms and size. $bound reads
// them from the record (the cards of the lists and the listing page); else
// the sample's own numbers are written.
$listing_facts = function ($sample, $bound = true) use ($el, $icon, $span) {
    $fact = function ($icon_name, $value, $unit, $token) use ($el, $icon, $span, $bound) {
        return $el('li', 'd-flex align-items-center gap-1 pg-hide-if-empty', $unit, array(
            $icon($icon_name, 'text-primary'),
            $span((string)$value, 'fw-semibold', lang('Value'), $bound ? array('_bindings' => array('text' => $token)) : array()),
            $span($unit, 'text-body-secondary', lang('Unit')),
        ));
    };
    return $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-0', lang('Facts'), array(
        $fact('bi-door-closed', $sample['bedrooms'], lang('bedrooms'), 'bedrooms'),
        $fact('bi-droplet', $sample['bathrooms'], lang('bathrooms'), 'bathrooms'),
        $fact('bi-arrows-angle-expand', $sample['size'], lang('m²'), 'size'),
    ));
};

// A listing's card of the lists (the home, the buy, rent and area pages):
// the cover, the deal and status, the price, the title linking the
// listing's page, the neighbourhood and the facts. Every value is bound to
// the Listings form's field of the same name; the sample fills the canvas.
$listing_card = function ($sample_key = 'seaview') use ($el, $para, $span, $link, $image, $col, $icon, $bind, $listing_facts, $listings, $areas, $deals, $listing_statuses, $photo) {
    $s = $listings[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pre-lift', lang('Listing Card'), array(
            $el('div', 'position-relative', lang('Picture'), array(
                $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo($sample_key, 800, 600), $s['title'], 'card-img-top object-fit-cover',
                        array('_bindings' => array('src' => 'cover', 'alt' => 'title'))),
                )),
                $el('div', 'position-absolute top-0 start-0 d-flex gap-2 p-3', lang('Badges'), array(
                    $span($deals[$s['deal']], 'badge text-bg-primary', lang('Deal'), $bind('deal')),
                    $span($listing_statuses[$s['status']], 'badge text-bg-light', lang('Status'), $bind('status')),
                )),
            )),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para($s['price_label'], 'pre-price h5 fw-bold text-primary mb-1', $bind('price_label')),
                $el('h3', 'h6 card-title fw-semibold position-static mb-2', lang('Listing Title'), array(
                    $link($s['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-center gap-1 small text-body-secondary mb-3', lang('Neighbourhood'), array(
                    $icon('bi-geo-alt'),
                    $span($areas[$s['area']]['title'], '', lang('Value'), $bind('area')),
                )),
                $el('div', 'mt-auto pt-3 border-top', lang('Facts'), array($listing_facts($s))),
            )),
        )),
    ), '6', '4');
};

// An agent's card (the home, agents and area pages): portrait, name, role,
// neighbourhoods, a line about them, a phone link and a link that opens the
// valuation form with them chosen.
$agent_card = function ($key) use ($el, $heading, $para, $link, $image, $span, $col, $attr, $portrait, $agents, $areas, $query) {
    $a = $agents[$key];
    $area_names = array();
    foreach ($a['areas'] as $k) $area_names[] = $areas[$k]['title'];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pre-lift', lang('Agent Card'), array(
            $image($portrait($a['name']), $a['name'], 'card-img-top object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $heading('h3', $a['name'], 'h5 fw-semibold mb-1'),
                $para($a['role'], 'small text-primary fw-semibold mb-2'),
                $span(implode(' · ', $area_names), 'd-block small text-body-secondary mb-3', lang('Neighbourhoods')),
                $para($a['bio'], 'small mb-3'),
                $link($a['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $a['phone']), 'small link-body-emphasis text-decoration-none mb-2'),
                $link(lang('Ask for a valuation'), '{{page:valuation}}?' . $query(array('agent' => $a['name'])),
                    'mt-auto small fw-semibold link-primary text-decoration-none'),
            )),
        )),
    ), '6', '3');
};

// A page: the account bar, the header, the page's own content (the lists,
// the forms, the account pages) and the shared footer.
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
        $para($eyebrow, 'pre-eyebrow text-uppercase fw-semibold text-primary mb-1'),
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

// An icon in a soft round tile, the way the categories are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 're-faq') use ($el, $para, $attr, $searchable) {
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

// The comments of a record page (a viewing's messages, a conversation's
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
                $item('listing_new', lang('New Listing')),
                $item('staff_viewings', lang('All Viewings')),
                $item('staff_valuations', lang('All Valuations')),
                $item('blog_new', lang('New Article')),
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
    return $agree($id, 'consent', lang('I would like to receive new listings and market news by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. The
// valuation form takes documents (a title deed, a floor plan) and photos,
// so the default accepts PDFs, office files and pictures; one that takes
// only pictures is a collection's image kind (a listing's photos).
$file = function ($id, $name, $folder, $accept = '.pdf,.doc,.docx,.odt,.jpg,.jpeg,.png') use ($el, $attr) {
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
                    $link(lang('What to check before you make an offer'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

