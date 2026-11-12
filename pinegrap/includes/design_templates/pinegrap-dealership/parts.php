<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Dealership", part 1 of its files: the node
 * builders, the dealer's own data (the makes, body styles, fuels and
 * conditions the stock is filtered by, the sample vehicles, the service
 * centre, the ways to pay, the team, what customers say and the answers
 * of the request forms' pick lists) and the parts every page repeats
 * (account bar, header, footer, test drive band, vehicle card and facts,
 * body style tile, finance and testimonial cards, page shells, section
 * headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-dealership.php puts them together.
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
// every opening until the dealer puts its own photos in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-dealership-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/dbeafe/1e3a8a?text=' . rawurlencode($initials !== '' ? $initials : 'A');
};

// ── The dealer: its stock, its services and the ways to pay ─────────────

// The dealership itself. The about and visit pages tell its story; the
// footer, the bands and the contact lines use the address and phone.
$dealer = array(
    'since'   => '1998',
    'tagline' => lang('New and used cars, chosen and checked by people who drive them'),
    'bio'     => lang('A family dealership by the coast road since 1998: new cars from four makes, used cars we would drive ourselves and a workshop that knows them all.'),
    'address' => lang('48 Coast Road, Izmir'),
    'email'   => 'sales@example.com',
    'phone'   => '+90 555 000 00 00',
);

// The makes the dealer sells, in the order the lists filter them. The makes
// are invented brand names and stay as they are in every language; a
// filter link (?make=) carries the name as the Make list stores it.
$makes = array(
    'aster'   => array('title' => 'Aster', 'text' => lang('Small, light cars for the city, with low running costs.')),
    'norda'   => array('title' => 'Norda', 'text' => lang('Roomy estates and SUVs for families and long trips.')),
    'velo'    => array('title' => 'Velo', 'text' => lang('Electric cars only, with fast charging on every model.')),
    'kestrel' => array('title' => 'Kestrel', 'text' => lang('Saloons and hybrids with a sporting edge.')),
);

// The body styles, in the order the home's browse tiles show them. Each
// answer's value is its own words, so a filter link (?body=) reads as it shows.
$bodies = array(
    'hatchback' => array('icon' => 'bi-car-front', 'title' => lang('Hatchback')),
    'saloon'    => array('icon' => 'bi-car-front-fill', 'title' => lang('Saloon')),
    'estate'    => array('icon' => 'bi-car-front', 'title' => lang('Estate')),
    'suv'       => array('icon' => 'bi-truck-front', 'title' => lang('SUV')),
    'van'       => array('icon' => 'bi-truck', 'title' => lang('Van')),
);

// The fuels; electric cars carry their own icon.
$fuels = array(
    'petrol'   => array('icon' => 'bi-fuel-pump', 'title' => lang('Gasoline')),
    'diesel'   => array('icon' => 'bi-fuel-pump-diesel', 'title' => lang('Diesel')),
    'hybrid'   => array('icon' => 'bi-ev-front', 'title' => lang('Hybrid')),
    'electric' => array('icon' => 'bi-ev-station', 'title' => lang('Electric')),
);

// A vehicle's condition: the new and used lists filter on it (?condition=).
$conditions = array(
    'new'  => lang('New car'),
    'used' => lang('Used car'),
    'demo' => lang('Ex-demonstrator'),
);
$transmissions = array('manual' => lang('Manual gearbox'), 'automatic' => lang('Automatic gearbox'));
// The states of a vehicle, set by the staff in the panel.
$vehicle_statuses = array(lang('In stock'), lang('Reserved'), lang('Sold'));

// The sample vehicles: the records the Vehicles form starts with. The
// lists, the vehicle page and the request forms read them; the home shows
// the featured ones. Each names a make, body, fuel, condition, gearbox and
// status of the lists above; 'price' and 'mileage' are the numbers the
// lists sort on, 'price_label' the words the cards show. Model names are
// brand names and stay as they are in every language.
$vehicles = array(
    'aster-city'   => array('make' => 'aster', 'model' => 'City 1.0', 'year' => 2026, 'condition' => 'new', 'body' => 'hatchback',
        'fuel' => 'petrol', 'gearbox' => 'manual', 'mileage' => 10, 'power' => 100, 'colour' => lang('Sea blue'), 'status' => 0,
        'price' => 21900, 'price_label' => '$21,900', 'featured' => true,
        'summary'     => lang('A light five-door for the city, with a full warranty and five years of free servicing.'),
        'description' => lang('The City is small outside and roomy inside: five doors, seats for four adults and a boot for the weekly shop. It parks itself, uses little fuel and comes with our five-year servicing plan.'),
        'features'    => array(lang('Five-year warranty'), lang('Parking camera and sensors'), lang('Phone mirroring on the touch screen'), lang('Lane keeping assist'))),
    'velo-volt'    => array('make' => 'velo', 'model' => 'Volt Long Range', 'year' => 2026, 'condition' => 'new', 'body' => 'suv',
        'fuel' => 'electric', 'gearbox' => 'automatic', 'mileage' => 5, 'power' => 300, 'colour' => lang('Pearl white'), 'status' => 0,
        'price' => 41900, 'price_label' => '$41,900', 'featured' => true,
        'summary'     => lang('An electric family SUV with a range of 520 km and a charge from 10 to 80% in 28 minutes.'),
        'description' => lang('The Volt carries five people and their luggage on one charge from Izmir to Istanbul with a coffee stop. A home charger is fitted free with every new Volt sold this season.'),
        'features'    => array(lang('Range of 520 km'), lang('Fast charging in 28 minutes'), lang('Home charger fitted free'), lang('Heated seats front and back'))),
    'kestrel-gt'   => array('make' => 'kestrel', 'model' => 'GT Hybrid', 'year' => 2025, 'condition' => 'demo', 'body' => 'saloon',
        'fuel' => 'hybrid', 'gearbox' => 'automatic', 'mileage' => 3200, 'power' => 220, 'colour' => lang('Graphite grey'), 'status' => 0,
        'price' => 36750, 'price_label' => '$36,750', 'featured' => true,
        'summary'     => lang('Our own test drive car, six months old, with the rest of the new car warranty.'),
        'description' => lang('This GT was our showroom test drive car: serviced by us, never smoked in and kept in the garage. It has every option of the top trim and costs less than a new one.'),
        'features'    => array(lang('Rest of the new car warranty'), lang('Leather seats and sunroof'), lang('Adaptive cruise control'), lang('Head-up display'))),
    'norda-tour'   => array('make' => 'norda', 'model' => 'Tour 2.0 TD', 'year' => 2022, 'condition' => 'used', 'body' => 'estate',
        'fuel' => 'diesel', 'gearbox' => 'automatic', 'mileage' => 48000, 'power' => 150, 'colour' => lang('Forest green'), 'status' => 0,
        'price' => 24500, 'price_label' => '$24,500', 'featured' => false,
        'summary'     => lang('A one-owner family estate with a full service history and a tow bar.'),
        'description' => lang('One owner from new, every service done on time and a boot of 640 litres. We checked it on 120 points, changed the tyres and give it a twelve-month warranty.'),
        'features'    => array(lang('One owner, full service history'), lang('Tow bar'), lang('New tyres'), lang('Twelve-month warranty'))),
    'norda-trail'  => array('make' => 'norda', 'model' => 'Trail AWD', 'year' => 2021, 'condition' => 'used', 'body' => 'suv',
        'fuel' => 'hybrid', 'gearbox' => 'automatic', 'mileage' => 72500, 'power' => 180, 'colour' => lang('Sand beige'), 'status' => 1,
        'price' => 22400, 'price_label' => '$22,400', 'featured' => false,
        'summary'     => lang('A four-wheel-drive hybrid SUV, checked on 120 points, with seven seats.'),
        'description' => lang('Seven seats, four-wheel drive and the economy of a hybrid. The battery was tested at 94% of its first capacity, and the car comes with a twelve-month warranty.'),
        'features'    => array(lang('Seven seats'), lang('Four-wheel drive'), lang('Battery tested at 94%'), lang('Twelve-month warranty'))),
    'aster-cargo'  => array('make' => 'aster', 'model' => 'Cargo L2', 'year' => 2023, 'condition' => 'used', 'body' => 'van',
        'fuel' => 'diesel', 'gearbox' => 'manual', 'mileage' => 61000, 'power' => 110, 'colour' => lang('White'), 'status' => 0,
        'price' => 27900, 'price_label' => '$27,900', 'featured' => false,
        'summary'     => lang('A long-wheelbase van with shelving in the back, ready for work.'),
        'description' => lang('A tidy work van with a load floor of 3.2 m, shelving and a roof rack. Serviced every 20,000 km and sold with a six-month warranty.'),
        'features'    => array(lang('Shelving and roof rack'), lang('Load length of 3.2 m'), lang('Three seats in front'), lang('Six-month warranty'))),
);
// A vehicle's title as the lists and the request forms name it.
$vehicle_title = function ($key) use ($vehicles, $makes) {
    $v = $vehicles[$key];
    return $makes[$v['make']]['title'] . ' ' . $v['model'];
};

// The service centre's work, in the order the service page lists it.
$services = array(
    'servicing' => array('icon' => 'bi-wrench-adjustable', 'title' => lang('Servicing'),
        'text' => lang('Yearly and mileage services for every make, with genuine parts and the stamp in your book.')),
    'tyres'     => array('icon' => 'bi-life-preserver', 'title' => lang('Tyres and brakes'),
        'text' => lang('Tyres, wheel alignment and brakes, fitted while you wait in the lounge.')),
    'bodywork'  => array('icon' => 'bi-brush', 'title' => lang('Bodywork'),
        'text' => lang('Dents, scratches and windscreens repaired, with a courtesy car for longer jobs.')),
    'ev'        => array('icon' => 'bi-lightning-charge', 'title' => lang('Electric and hybrid care'),
        'text' => lang('Battery health checks and software updates by technicians trained on high voltage.')),
);

// The ways to pay of the finance page; the finance request's Way to Pay
// list starts from ?plan=. 'title' is the answer's own words.
$finance_plans = array(
    'hp'   => array('title' => lang('Hire purchase'), 'featured' => false,
        'text'   => lang('Pay a deposit, then fixed monthly payments; the car is yours after the last one.'),
        'points' => array(lang('Terms of 24 to 60 months'), lang('Fixed monthly payments'), lang('Yours after the last payment'))),
    'pcp'  => array('title' => lang('Personal contract purchase'), 'featured' => true,
        'text'   => lang('Lower monthly payments and three choices at the end: keep, return or change the car.'),
        'points' => array(lang('Lower monthly payments'), lang('Keep, return or change at the end'), lang('A mileage limit you choose'))),
    'cash' => array('title' => lang('Cash or bank transfer'), 'featured' => false,
        'text'   => lang('Pay in full, with your trade-in taken off the price, and drive away the same week.'),
        'points' => array(lang('Trade-in taken off the price'), lang('No interest and no contract'), lang('Ready the same week'))),
);

// The people a visitor meets: sales, the workshop and finance.
$team = array(
    array('name' => lang('Murat Yıldız'), 'role' => lang('Sales manager'),
        'bio' => lang('Has sold cars by the coast road for twenty years and still test drives every used car himself.')),
    array('name' => lang('Elif Arslan'), 'role' => lang('Service manager'),
        'bio' => lang('Runs the workshop and tells you what a car needs, and what it does not.')),
    array('name' => lang('Can Öztürk'), 'role' => lang('Finance adviser'),
        'bio' => lang('Explains every monthly figure before you sign, and finds the plan that fits.')),
);

// The dealer in four figures (the home and about pages).
$figures = array(
    array('value' => '28', 'label' => lang('Years on the coast road')),
    array('value' => '120', 'label' => lang('Cars in stock')),
    array('value' => '120', 'label' => lang('Points checked on every used car')),
    array('value' => '4.8', 'label' => lang('Average customer rating')),
);

// What customers say, each about one of the sample vehicles.
$testimonials = array(
    array('vehicle' => 'norda-tour', 'name' => lang('Selin Aydın'), 'role' => lang('Bought a used Norda Tour'),
        'quote' => lang('They showed me the service history line by line and fitted new tyres before I collected it. No pressure at all.')),
    array('vehicle' => 'velo-volt', 'name' => lang('Burak Şahin'), 'role' => lang('Drives a new Velo Volt'),
        'quote' => lang('My first electric car. They fitted the charger at home the day before I picked it up and showed me every button.')),
    array('vehicle' => 'aster-city', 'name' => lang('Zeynep Demir'), 'role' => lang('Traded in for an Aster City'),
        'quote' => lang('The trade-in offer came in writing the next day and was fair. The whole change took one visit.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the customer chose.
$make_titles = array();
foreach ($makes as $m) $make_titles[] = $m['title'];
$body_titles = array();
foreach ($bodies as $b) $body_titles[] = $b['title'];
$fuel_titles = array();
foreach ($fuels as $f) $fuel_titles[] = $f['title'];
$condition_titles = array_values($conditions);
$transmission_titles = array_values($transmissions);
$plan_choices = array(lang('Not sure yet'));
foreach ($finance_plans as $p) $plan_choices[] = $p['title'];
// The times of day, inside the showroom hours ($hours).
$time_windows = array(
    lang('Morning (09:00–12:00)'),
    lang('Afternoon (12:00–16:00)'),
    lang('Evening (16:00–19:00)'),
);
$contact_choices = array(
    array(lang('Phone'), lang('Phone')),
    array(lang('E-mail'), lang('E-mail')),
    array(lang('WhatsApp'), lang('WhatsApp')),
);
// The trade-in: the car's state and its papers, as the owner judges them.
$tradein_conditions = array(
    array(lang('Like new'), lang('Like new')),
    array(lang('Good'), lang('Good')),
    array(lang('Some wear'), lang('Some wear')),
    array(lang('Needs repairs'), lang('Needs repairs')),
);
$service_history_choices = array(lang('Full service history'), lang('Part service history'), lang('No service history'));
// The finance request asks for bands, never an ID or a bank number: the
// adviser asks for the documents in person.
$deposit_choices = array(lang('No deposit'), lang('Up to 10%'), lang('10% to 25%'), lang('Over 25%'));
$term_choices = array(lang('24 months'), lang('36 months'), lang('48 months'), lang('60 months'));
$income_choices = array(lang('Under $2,000 a month'), lang('$2,000 to $4,000 a month'), lang('$4,000 to $6,000 a month'),
                        lang('Over $6,000 a month'), lang('I would rather discuss it'));
$employment_choices = array(lang('Employed'), lang('Self-employed'), lang('Retired'), lang('Other'));
// The states of each request, set by the staff in the panel.
$test_drive_statuses = array(lang('Request received'), lang('Confirmed'), lang('Completed'), lang('Cancelled'));
$tradein_statuses = array(lang('Request received'), lang('Inspection booked'), lang('Offer sent'), lang('Offer accepted'), lang('Closed'));
$finance_statuses = array(lang('Request received'), lang('Documents needed'), lang('Approved'), lang('Not approved'), lang('Completed'));


// The query string of a link that opens a list with a filter set
// (?make=, ?body=, ?fuel=, ?condition=) or a request form with a choice
// made (?vehicle=, ?plan=): the form's fields start with these values
// (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
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
        $para($eyebrow, 'pdl-eyebrow text-uppercase fw-semibold text-primary mb-1'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'dl-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the way to book a test drive. A shared
// component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-calendar-check', 'text-primary'),
        $span(lang('Book a test drive online; we confirm it within one working day.'), '', lang('Text')),
        $link(lang('Book a Test Drive'), '{{page:test_drive}}', 'link-primary fw-semibold'),
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

// The header: the dealer's name, the menu, the way to value a car and the
// way to book a test drive. The links to the current page are marked
// active on the server (smart active state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('vehicles', lang('All Cars')),
        array('new_cars', lang('New Cars')),
        array('used_cars', lang('Used Cars')),
        array('finance', lang('Finance')),
        array('service', lang('Service')),
        array('about', lang('About')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $el('span', 'd-inline-flex p-2 rounded-2 bg-primary text-white lh-1', lang('Icon Box'), array($icon('bi-car-front-fill', 'fs-5'))),
                    $span('{{site_name}}', 'pdl-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#dl-nav'),
                    $attr('aria-controls', 'dl-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $link(lang('Value My Car'), '{{page:tradein}}', 'btn btn-outline-primary text-nowrap'),
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-check'),
                            $span(lang('Book a Test Drive'), '', lang('Link Text')),
                        ), array('href' => '{{page:test_drive}}')),
                    )),
                ), array('id' => 'dl-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The opening hours, as a definition list: the day, then the showroom's
// hours. The workshop keeps the same days and closes on Sunday.
$hours = function ($class = 'row small mb-0') use ($el) {
    $children = array();
    foreach (array(
        array(lang('Monday–Friday'), '09:00–19:00'),
        array(lang('Saturday'), '10:00–16:00'),
        array(lang('Sunday'), lang('Showroom only, 11:00–15:00')),
    ) as $d) {
        $children[] = $el('dt', 'col-6 fw-normal text-body-secondary', lang('Day'), array(), array('text' => $d[0]));
        $children[] = $el('dd', 'col-6 mb-2', lang('Time'), array(), array('text' => $d[1]));
    }
    return $el('dl', $class, lang('List'), $children);
};

// The footer, on a dark band: the dealer in a sentence and the profiles,
// the stock by body style and fuel, the requests a buyer makes, the
// dealer's pages and how to reach the showroom; at the bottom the year,
// the site's name and the line that figures are given in good faith.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $hours, $bodies, $fuels, $dealer, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $car_links = array($link(lang('All Cars'), '{{page:vehicles}}', $plain));
    foreach ($bodies as $b) $car_links[] = $link($b['title'], '{{page:vehicles}}?' . $query(array('body' => $b['title'])), $plain);
    foreach ($fuels as $f) $car_links[] = $link($f['title'], '{{page:vehicles}}?' . $query(array('fuel' => $f['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Dealer'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-bold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-car-front-fill', 'text-primary'),
                        $span('{{site_name}}', 'pdl-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($dealer['bio'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                        $social('bi-whatsapp', 'WhatsApp'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Car Links'), array(
                    $heading('h2', lang('Cars'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($car_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Buyers'), array(
                    $heading('h2', lang('For Buyers'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book a Test Drive'), '{{page:test_drive}}', $plain),
                        $link(lang('Value My Car'), '{{page:tradein}}', $plain),
                        $link(lang('Apply for Finance'), '{{page:finance_apply}}', $plain),
                        $link(lang('My Test Drives'), '{{page:my_test_drives}}', $plain),
                        $link(lang('My Trade-ins'), '{{page:my_tradeins}}', $plain),
                        $link(lang('My Finance Requests'), '{{page:my_finance}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Dealer'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Service'), '{{page:service}}', $plain),
                        $link(lang('Finance'), '{{page:finance}}', $plain),
                        $link(lang('About'), '{{page:about}}', $plain),
                        $link(lang('Visit Us'), '{{page:visit}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Showroom News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Visit Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $span($dealer['address'], '', lang('Text')), lang('Address')),
                    $contact_line('bi-telephone', $link($dealer['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $dealer['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($dealer['email'], 'mailto:' . $dealer['email'], $plain . ' text-break'), lang('Email')),
                    $hours('row small mt-3 mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Prices, mileage and equipment are given in good faith; please confirm them with us before you buy.'), 'small text-body-secondary mb-0 text-md-end'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The test drive band: a full-width band in the palette's colour with the
// ways to try a car and to value the one you drive now. Shared, so every
// page that ends with it carries the same offer.
$test_drive_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Found the one?'), 'pdl-display h2 fw-bold mb-2'),
                    $para(lang('Book a test drive at a time that suits you, and bring your car: we value it while you drive.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Test Drive'), '{{page:test_drive}}', 'btn btn-secondary btn-lg px-4'),
                        $link(lang('Value My Car'), '{{page:tradein}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A vehicle's facts in one line: year, mileage, fuel and gearbox. $bound
// reads them from the record (the cards of the lists and the vehicle
// page); else the sample's own values are written.
$vehicle_facts = function ($sample_key, $bound = true) use ($el, $icon, $span, $vehicles, $fuels, $transmissions) {
    $s = $vehicles[$sample_key];
    $fact = function ($icon_name, $value, $unit, $name, $token) use ($el, $icon, $span, $bound) {
        $children = array(
            $icon($icon_name, 'text-primary'),
            $span($value, 'fw-semibold', lang('Value'), $bound ? array('_bindings' => array('text' => $token)) : array()),
        );
        if ($unit !== '') $children[] = $span($unit, 'text-body-secondary', lang('Unit'));
        return $el('li', 'd-flex align-items-center gap-1 pg-hide-if-empty', $name, $children);
    };
    return $el('ul', 'list-unstyled d-flex flex-wrap gap-3 small mb-0', lang('Facts'), array(
        $fact('bi-calendar3', (string)$s['year'], '', lang('Year'), 'year'),
        $fact('bi-speedometer2', number_format($s['mileage']), lang('km'), lang('Mileage'), 'mileage'),
        $fact($fuels[$s['fuel']]['icon'], $fuels[$s['fuel']]['title'], '', lang('Fuel'), 'fuel'),
        $fact('bi-gear', $transmissions[$s['gearbox']], '', lang('Gearbox'), 'transmission'),
    ));
};

// A vehicle's card of the lists (the home, the stock, new and used lists):
// the first photo, the condition and status, the price, the title linking
// the vehicle's page, the make and body style and the facts. Every value is
// bound to the Vehicles form's field of the same name; the sample fills
// the canvas.
$vehicle_card = function ($sample_key = 'aster-city') use ($el, $para, $span, $link, $image, $col, $bind, $vehicle_facts, $vehicles, $vehicle_title, $makes, $bodies, $conditions, $vehicle_statuses, $photo) {
    $s = $vehicles[$sample_key];
    $title = $vehicle_title($sample_key);
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pdl-lift', lang('Vehicle Card'), array(
            $el('div', 'position-relative', lang('Picture'), array(
                $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo($sample_key, 800, 600), $title, 'card-img-top object-fit-cover',
                        array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'))),
                )),
                $el('div', 'position-absolute top-0 start-0 d-flex gap-2 p-3', lang('Badges'), array(
                    $span($conditions[$s['condition']], 'badge text-bg-primary', lang('Vehicle Condition'), $bind('condition')),
                    $span($vehicle_statuses[$s['status']], 'badge text-bg-light', lang('Status'), $bind('status')),
                )),
            )),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para($s['price_label'], 'pdl-price h5 fw-bold text-primary mb-1', $bind('price_label')),
                $el('h3', 'h6 card-title fw-semibold position-static mb-1', lang('Vehicle Title'), array(
                    $link($title, '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'small text-body-secondary mb-3', lang('Make and Body'), array(
                    $span($makes[$s['make']]['title'], '', lang('Make'), $bind('make')),
                    $span(' · ', '', lang('Separator')),
                    $span($bodies[$s['body']]['title'], '', lang('Body Style'), $bind('body')),
                )),
                $el('div', 'mt-auto pt-3 border-top', lang('Facts'), array($vehicle_facts($sample_key))),
            )),
        )),
    ), '6', '4');
};

// A body style's tile (the home's "browse by body style"): the icon and
// the name, opening the stock list filtered on it.
$body_tile = function ($key) use ($el, $span, $icon, $col, $bodies, $query) {
    $b = $bodies[$key];
    return $col(array(
        $el('a', 'card h-100 border-0 shadow-sm text-center text-decoration-none link-body-emphasis py-4 pdl-lift', lang('Body Style'), array(
            $icon($b['icon'], 'fs-1 text-primary'),
            $span($b['title'], 'd-block fw-semibold mt-2', lang('Name')),
        ), array('href' => '{{page:vehicles}}?' . $query(array('body' => $b['title'])))),
    ), '', '', '4', '6');
};

// A way to pay's card (the finance page and the home): the name, a line,
// what it gives and a link that opens the finance request with it chosen.
$finance_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $check_list, $finance_plans, $query) {
    $p = $finance_plans[$key];
    $children = array();
    if ($p['featured']) $children[] = $span(lang('Most chosen'), 'badge text-bg-primary align-self-start mb-3', lang('Badge'));
    $children[] = $heading('h3', $p['title'], 'h5 fw-bold mb-2');
    $children[] = $para($p['text'], 'small text-body-secondary mb-3');
    $children[] = $check_list($p['points'], 'list-unstyled small mb-4');
    $children[] = $link(lang('Ask about this plan'), '{{page:finance_apply}}?' . $query(array('plan' => $p['title'])),
        'btn ' . ($p['featured'] ? 'btn-primary' : 'btn-outline-primary') . ' mt-auto');
    return $col(array(
        $el('article', 'card h-100 ' . ($p['featured'] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Finance Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), $children),
        )),
    ), '6', '4');
};

// What a customer says: the quote, the stars, the person and the car.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Customer Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
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
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary', array(),
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
        $el('button', 'btn btn-primary px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-outline-secondary', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
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
                $item('vehicle_new', lang('New Vehicle')),
                $item('staff_test_drives', lang('Test Drives')),
                $item('staff_tradeins', lang('Trade-ins')),
                $item('staff_finance', lang('Finance Requests')),
                $item('blog_new', lang('New Article')),
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
    return $agree($id, 'consent', lang('I would like to receive new arrivals, offers and Showroom News by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// request may carry documents (a service book, a registration) and
// pictures, so the default accepts PDFs, office files and pictures; one
// that takes only pictures is a collection's image kind (a vehicle's
// photos, a trade-in's).
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
                    $link(lang('Five things to check before you buy a used car'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
        return $link($text, '#', 'btn btn-sm btn-outline-primary', array($attr('rel', 'noopener nofollow')),
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
            $el('button', 'btn btn-sm btn-primary', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};

// ── The dealership blocks ───────────────────────────────────────────────
// The structure of the Car Dealership blocks
// (assets/js/style_designer_blocks_dealership.js) and of CTA: Sticky Bar
// (style_designer_blocks_cta.js), with the dealer's own data: the same
// nodes and classes, so the palette's rules (shipped in the User Styles)
// draw them and the operator edits them as the blocks they are.

// A node with an interaction (props._fx, played by pg_fx; reduced motion
// keeps it still).
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};

// The three figures each showroom panel shows at its foot: the label, the
// number and its unit. Power and mileage come from the sample vehicle.
$panel_figures = array(
    'velo-volt'  => array(
        array(lang('Range'), '520', 'km'),
        array(lang('Charge 10–80%'), '28', lang('min')),
        array(lang('Power'), (string)$vehicles['velo-volt']['power'], lang('hp')),
    ),
    'kestrel-gt' => array(
        array(lang('Power'), (string)$vehicles['kestrel-gt']['power'], lang('hp')),
        array(lang('Mileage'), number_format($vehicles['kestrel-gt']['mileage']), 'km'),
        array(lang('Warranty'), '24', lang('months')),
    ),
    'norda-tour' => array(
        array(lang('Boot'), '640', 'L'),
        array(lang('Mileage'), number_format($vehicles['norda-tour']['mileage']), 'km'),
        array(lang('Warranty'), '12', lang('months')),
    ),
);

// Showroom Panel: one sample vehicle a window tall, its photo edge to edge
// (in the style attribute, where Background edits it), the condition and
// body, the name as $tag, the cash price, a test drive with the car chosen
// and the stock of its body style, and its three figures at the foot. The
// head fades and the figures rise on view, or on load for the page's h1.
$showroom_panel = function ($key, $tag = 'h2') use ($el, $heading, $para, $link, $container, $fx, $attr, $searchable, $query, $photo, $vehicles, $vehicle_title, $bodies, $conditions, $panel_figures) {
    $v = $vehicles[$key];
    $title = $vehicle_title($key);
    $trigger = $tag === 'h1' ? 'load' : 'view';
    $figures = array();
    foreach ($panel_figures[$key] as $f) {
        $figures[] = $el('div', 'pg-dl-figure', $f[0], array(
            $heading('dt', $f[0], ''),
            $el('dd', '', lang('Value'), array(
                $heading('span', $f[1], ''),
                $heading('span', ' ' . $f[2], 'fs-6 fw-normal'),
            )),
        ));
    }
    $style = 'background-image: url(' . $photo('panel-' . $key, 1920, 1080) . '); background-size: cover; background-position: center;';
    return $el('section', 'pg-dl-panel d-flex overflow-hidden', $title, array(
        $el('div', 'pg-dl-veil d-flex flex-column justify-content-between w-100 py-5 text-body text-center', lang('Overlay'), array(
            $container(array(
                $fx($el('div', 'col-lg-8 mx-auto', lang('Panel Head'), array(
                    $para($conditions[$v['condition']] . ' · ' . $bodies[$v['body']]['title'], 'pg-dl-kicker small text-uppercase fw-semibold mb-2'),
                    $heading($tag, $title, 'pg-dl-type display-3 mb-2'),
                    $el('p', 'lead mb-4', lang('Price'), array(
                        $heading('span', lang('Cash price'), 'text-body-secondary me-2'),
                        $heading('span', $v['price_label'], 'fw-medium'),
                    )),
                    $el('div', 'd-flex flex-wrap justify-content-center gap-2', lang('Buttons'), array(
                        $link(lang('Book a Test Drive'), '{{page:test_drive}}?' . $query(array('vehicle' => $title)), 'btn btn-primary btn-lg px-5'),
                        $link(lang('More Like This'), '{{page:vehicles}}?' . $query(array('body' => $bodies[$v['body']]['title'])), 'btn btn-secondary btn-lg px-5'),
                    )),
                )), array('effect' => 'fade', 'trigger' => $trigger, 'duration' => 900)),
            ), 'pt-lg-4'),
            $container(array(
                $fx($el('dl', 'pg-dl-figures col-lg-8 mx-auto', lang('Figures'), $figures),
                    array('effect' => 'slide-up', 'trigger' => $trigger, 'stagger' => 120)),
            ), 'pt-5'),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),
    ), array_merge($searchable('car-' . $key), array('_attrs' => array($attr('style', $style)))));
};

// Stock Finder: the way into the stock by condition, by body style (a row
// of large icons between two hairlines) and by make. The header fades and
// the body styles rise on view.
$stock_finder = function () use ($el, $heading, $para, $link, $icon, $container, $fx, $searchable, $query, $makes, $bodies) {
    $shapes = array();
    foreach ($bodies as $b) {
        $shapes[] = $el('li', '', $b['title'], array(
            $el('a', 'pg-dl-shape', $b['title'], array(
                $icon($b['icon']),
                $heading('span', $b['title'], 'fw-medium'),
            ), array('href' => '{{page:vehicles}}?' . $query(array('body' => $b['title'])))),
        ));
    }
    $make_links = array($heading('span', lang('Makes'), 'small text-body-secondary'));
    foreach ($makes as $m) {
        $make_links[] = $link($m['title'], '{{page:vehicles}}?' . $query(array('make' => $m['title'])), 'link-body-emphasis fw-medium');
    }
    return $el('section', 'py-5 my-lg-4', lang('Stock Finder'), array(
        $container(array(
            $fx($el('div', 'text-center col-lg-8 mx-auto mb-5', lang('Section Header'), array(
                $para(lang('The stock'), 'pg-dl-kicker small text-uppercase fw-semibold text-primary mb-2'),
                $heading('h2', lang('Find yours in the stock'), 'pg-dl-type display-5 mb-3'),
                $para(lang('Every car we have, new and used, with its photos, its history and its price.'), 'lead text-body-secondary mb-4'),
                $el('div', 'd-flex flex-wrap justify-content-center gap-2', lang('Condition'), array(
                    $link(lang('New Cars'), '{{page:new_cars}}', 'btn btn-primary rounded-pill px-4'),
                    $link(lang('Used Cars'), '{{page:used_cars}}', 'btn btn-outline-secondary rounded-pill px-4'),
                    $link(lang('All Cars'), '{{page:vehicles}}', 'btn btn-link link-body-emphasis'),
                )),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700)),
            $fx($el('ul', 'pg-dl-shapes', lang('Body Styles'), $shapes),
                array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 80)),
            $el('div', 'd-flex flex-wrap justify-content-center align-items-center gap-3 gap-md-4 mt-4', lang('Makes'), $make_links),
        )),
    ), $searchable('stock', lang('new cars, used cars, body style, make, filter')));
};

// Three Ways In: test drive, trade-in and finance as tall picture tiles
// side by side, each with its kind, a heading, a line and the request's
// action. The tiles rise one after another on view.
$three_ways = function () use ($el, $heading, $para, $link, $container, $fx, $attr, $searchable, $photo) {
    $way = function ($seed, $kind, $title, $text, $action, $href, $variant) use ($el, $heading, $para, $link, $attr, $photo) {
        return $el('article', 'pg-dl-way d-flex', $kind, array(
            $el('div', 'd-flex flex-column justify-content-end align-items-start w-100 p-4 p-lg-5 text-body pg-shade-bottom', lang('Overlay'), array(
                $para($kind, 'pg-dl-kicker small text-uppercase fw-semibold mb-2'),
                $heading('h3', $title, 'pg-dl-type h2 mb-2'),
                $para($text, 'mb-4'),
                $link($action, $href, 'btn ' . $variant . ' px-4'),
            ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),
        ), array('_attrs' => array($attr('style', 'background-image: url(' . $photo('way-' . $seed, 1200, 1500) . '); background-size: cover; background-position: center;'))));
    };
    return $el('section', 'py-5', lang('Three Ways In'), array(
        $container(array(
            $fx($el('div', 'text-center col-lg-8 mx-auto mb-5', lang('Section Header'), array(
                $heading('h2', lang('Before you sign'), 'pg-dl-type display-5 mb-3'),
                $para(lang('Drive the car, know what yours is worth and see the monthly figure, all before you decide.'), 'lead text-body-secondary mb-0'),
            )), array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700)),
        )),
        $fx($el('div', 'pg-dl-ways', lang('Ways'), array(
            $way('drive', lang('Test drive'), lang('Drive it on your own roads'),
                lang('Choose a day and a time of day; we confirm within one working day.'), lang('Book a Test Drive'), '{{page:test_drive}}', 'btn-primary'),
            $way('tradein', lang('Trade-in'), lang('Your car, valued while you drive'),
                lang('Bring the car you drive now; the offer comes in writing.'), lang('Value My Car'), '{{page:tradein}}', 'btn-secondary'),
            $way('finance', lang('Finance'), lang('The monthly figure before you sign'),
                lang('Hire purchase, a contract with three choices at the end, or cash: each figure explained.'), lang('Ways to Pay'), '{{page:finance}}', 'btn-secondary'),
        )), array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 150)),
    ), $searchable('ways', lang('test drive, trade-in, part exchange, valuation, finance, monthly payment')));
};

// CTA: Sticky Bar: a slim band that stays at the top of the window while
// the visitor scrolls the element it sits in, with a title, a line and two
// actions.
$sticky_bar = function ($title, $line, $primary, $secondary) use ($el, $heading, $para, $link, $container, $fx) {
    return $fx($el('section', 'pg-sticky-bar border-bottom py-2', lang('Sticky Bar'), array(
        $container(array(
            $el('div', 'd-flex flex-wrap align-items-center gap-2 gap-md-3', lang('Bar'), array(
                $heading('h2', $title, 'h6 mb-0 me-auto'),
                $para($line, 'small text-body-secondary mb-0 d-none d-md-block'),
                $el('div', 'd-flex gap-2', lang('Buttons'), array(
                    $link($primary[0], $primary[1], 'btn btn-primary btn-sm px-3'),
                    $link($secondary[0], $secondary[1], 'btn btn-outline-secondary btn-sm px-3'),
                )),
            )),
        )),
    )), array('effect' => 'sticky'));
};

