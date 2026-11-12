<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Pharmacy", part 1 of its files: the node builders, the
 * pharmacy's own data (the opening hours and the duty nights, the services
 * and their prices, the health checks, the pharmacists and their days, the
 * product ranges, the health topics, how a prescription request works, the
 * pharmacy in figures, what customers say and the answers of the forms'
 * pick lists) and the parts every page repeats (account bar with the
 * on-duty line, header, footer, booking band, service, check, pharmacist,
 * range and customer cards, page shells, section headers, form fields, the
 * comment thread, the staff bar and the blog parts).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-pharmacy.php puts them together.
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
// every opening until the pharmacy puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-pharmacy-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language; a title such as "Dr." is left out.
$portrait = function ($name, $size = 480) {
    $initials = '';
    $words = array_values(array_filter(preg_split('/\s+/u', trim($name)), function ($w) { return substr($w, -1) !== '.'; }));
    foreach (array_slice($words, 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ccfbf1/115e59?text=' . rawurlencode($initials !== '' ? $initials : 'P');
};



// ── The pharmacy: its hours, services, checks, pharmacists and ranges ───

// The pharmacy itself. The about page tells its story; the footer, the
// booking band and the contact lines use the address, the phones and the
// opening hours. The emergency number is the public one: a pharmacy is
// never the place to wait in an emergency.
$pharmacy = array(
    'tagline'   => lang('Your neighbourhood pharmacy: prescriptions ready when you are, vaccinations and health checks without a long wait, and advice you can trust'),
    'story'     => lang('The pharmacy has served the same street for three generations. It started as one counter and a shelf of remedies; today it has a quiet consultation room, a vaccination room and a small team of pharmacists who know their customers by name and take the time to explain every medicine.'),
    'address'   => lang('Linden Street 8, Market Square'),
    'region'    => lang('Step-free entrance, a bench by the door and parking for a few minutes in front'),
    'phone'     => '+90 555 000 00 20',
    'delivery'  => '+90 555 000 00 21',
    'emergency' => '112',
    'email'     => 'hello@example.com',
);

// The days of the week the pharmacists work, in order; a pharmacist's days
// are keys of this list (a test holds them to it).
$days = array(
    'mon' => lang('Monday'),
    'tue' => lang('Tuesday'),
    'wed' => lang('Wednesday'),
    'thu' => lang('Thursday'),
    'fri' => lang('Friday'),
    'sat' => lang('Saturday'),
);
// The opening hours in three lines, the way the footer and the contact
// page show them; on Sunday the pharmacy opens only on its duty days.
$opening_hours = array(
    array('days' => lang('Monday to Friday'), 'hours' => '08:30–19:30'),
    array('days' => lang('Saturday'), 'hours' => '09:00–17:00'),
    array('days' => lang('Sunday'), 'hours' => lang('Closed, except on duty days')),
);

// What being on duty means, in three short answers (the on-duty page and
// the home): the pharmacy's own duty nights, where to go when it is
// closed, and what an emergency calls for. No date is given: the rota is
// the chamber's and changes every month.
$duty_facts = array(
    'ours'      => array('icon' => 'bi-moon-stars', 'title' => lang('Our duty nights'),
        'text' => lang('When the rota puts us on duty, we stay open through the night and the following holiday. Ring the night bell at the side window and a pharmacist serves you there.')),
    'closed'    => array('icon' => 'bi-signpost-split', 'title' => lang('Closed right now?'),
        'text' => lang('The pharmacy on duty tonight is named on the notice in our window and on the chamber of pharmacists\' list of on-duty pharmacies.')),
    'emergency' => array('icon' => 'bi-telephone-plus', 'title' => lang('An emergency'),
        'text' => lang('Chest pain, breathing difficulty, a serious injury or a poisoning cannot wait for a pharmacy. Call 112 at once.')),
);
// What to bring at night, so the duty pharmacist can serve you quickly.
$duty_tips = array(
    lang('Your prescription or its number'),
    lang('Your identity card'),
    lang('The box of the medicine you take, if you have it'),
    lang('A list of your other medicines'),
);

// The services. 'from' is the price a service starts at per currency, read
// with $money, or null for a free service; 'action' is where its card
// sends the customer: the prescription request (with the service chosen),
// online booking or a question to the pharmacist. Each has its own page
// (service_<key>).
$services = array(
    'prescriptions'   => array('icon' => 'bi-prescription2', 'title' => lang('Prescriptions'), 'action' => 'prescription', 'from' => null,
        'summary' => lang('Send us your prescription number and collect your medicines without waiting, or have them delivered.'),
        'text'    => lang('Tell us what your doctor prescribed and when you would like to collect it. We check the stock, order what is missing and send you a message when everything is ready. The original prescription is shown at the counter, and every medicine comes with a word on how to take it.'),
        'points'  => array(lang('New and repeat prescriptions'), lang('A message when your medicines are ready'), lang('Missing medicines ordered the same day'), lang('Advice on how to take every medicine'))),
    'vaccinations'    => array('icon' => 'bi-shield-plus', 'title' => lang('Vaccinations'), 'action' => 'book_online',
        'from'    => array('USD' => 20, 'EUR' => 18, 'GBP' => 16, 'TRY' => 650),
        'summary' => lang('Seasonal flu and other adult vaccinations in our private vaccination room, by appointment or on a quiet afternoon.'),
        'text'    => lang('A trained pharmacist asks a few questions about your health, gives the vaccine in a private room and keeps you with us for a short while afterwards. You leave with a record of the vaccination for your doctor.'),
        'points'  => array(lang('Seasonal flu vaccination'), lang('Other adult vaccinations on request'), lang('A private vaccination room'), lang('A written record for your doctor'))),
    'health_checks'   => array('icon' => 'bi-heart-pulse', 'title' => lang('Health Checks'), 'action' => 'book_online',
        'from'    => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 150),
        'summary' => lang('Blood pressure, blood sugar, cholesterol and weight, measured in a few minutes in our consultation room.'),
        'text'    => lang('A quick check tells you more than a long worry. We measure, explain what the figures mean and write them down for you. When a figure needs a doctor\'s eye, we tell you so and help you take the next step.'),
        'points'  => array(lang('Results in a few minutes'), lang('A private consultation room'), lang('Your figures written down to take home'), lang('Advice on the next step'))),
    'medicine_review' => array('icon' => 'bi-clipboard2-pulse', 'title' => lang('Medicine Reviews'), 'action' => 'ask', 'topic' => 'medicines', 'from' => null,
        'summary' => lang('Taking several medicines? A pharmacist goes through them with you, free of charge.'),
        'text'    => lang('Bring all your medicines, vitamins and remedies in a bag. A pharmacist checks that they work well together, that the doses are clear and that nothing is taken twice, and writes you a simple daily plan.'),
        'points'  => array(lang('All your medicines checked together'), lang('Side effects and interactions explained'), lang('A simple daily plan'), lang('A note for your doctor if needed'))),
    'travel_health'   => array('icon' => 'bi-airplane', 'title' => lang('Travel Health'), 'action' => 'book_online',
        'from'    => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 500),
        'summary' => lang('Advice before a trip, a travel first-aid kit and the medicines and vaccinations your destination asks for.'),
        'text'    => lang('Tell us where you are going and for how long. We go through the vaccinations and medicines your destination calls for, put together a first-aid kit for the journey and tell you how to carry your own medicines abroad.'),
        'points'  => array(lang('Advice for your destination'), lang('A travel first-aid kit'), lang('Medicines for motion sickness and the sun'), lang('Carrying your medicines abroad'))),
    'delivery'        => array('icon' => 'bi-bicycle', 'title' => lang('Home Delivery'), 'action' => 'prescription', 'from' => null,
        'summary' => lang('Medicines brought to your door in the neighbourhood, for anyone who cannot easily come in.'),
        'text'    => lang('Older customers, new parents and anyone who is unwell can have their medicines brought home. We call before we come, a pharmacist explains the medicines on the phone and the original prescription is collected at the door.'),
        'points'  => array(lang('Delivery in the neighbourhood'), lang('A call before we come'), lang('Advice on the phone'), lang('Free for repeat prescriptions'))),
);

// The health checks, with how long each takes and its price per currency
// (the health checks page and the home). Each is booked online.
$checks = array(
    'blood_pressure' => array('icon' => 'bi-heart-pulse', 'title' => lang('Blood Pressure'), 'minutes' => 5,
        'price' => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 150),
        'text'  => lang('Three readings a few minutes apart, sitting down and rested, with what the figures mean for you.')),
    'blood_sugar'    => array('icon' => 'bi-droplet', 'title' => lang('Blood Sugar'), 'minutes' => 5,
        'price' => array('USD' => 6, 'EUR' => 6, 'GBP' => 5, 'TRY' => 200),
        'text'  => lang('A drop of blood from the fingertip, measured at once; best before breakfast or two hours after a meal.')),
    'cholesterol'    => array('icon' => 'bi-clipboard2-data', 'title' => lang('Cholesterol'), 'minutes' => 10,
        'price' => array('USD' => 12, 'EUR' => 11, 'GBP' => 10, 'TRY' => 400),
        'text'  => lang('Total cholesterol from a fingertip test, with advice on food and exercise that keeps it in check.')),
    'weight'         => array('icon' => 'bi-speedometer2', 'title' => lang('Weight and Body Mass'), 'minutes' => 10,
        'price' => array('USD' => 5, 'EUR' => 5, 'GBP' => 4, 'TRY' => 150),
        'text'  => lang('Weight, height and waist measured in private, with your body mass index and a realistic goal.')),
);

// The pharmacists: the records the Pharmacists form starts with. The team
// page, a pharmacist's page and the home read them; 'service' is the
// service each one leads (the team page's filter), 'days' the days they
// work, 'services' what they do, 'years' how long they have practised.
$pharmacists = array(
    'selin'  => array('name' => lang('Selin Arslan'), 'service' => 'prescriptions', 'years' => 18, 'days' => array('mon', 'tue', 'wed', 'thu', 'fri'),
        'services' => array('prescriptions', 'medicine_review', 'delivery'),
        'role'     => lang('Owner and Responsible Pharmacist'),
        'summary'  => lang('The third generation behind the counter, who still knows which customer takes which medicine.'),
        'bio'      => lang('Selin took over the pharmacy from her father and modernised it without changing its welcome. She runs the medicine reviews and is the one to ask when several medicines have to work together.')),
    'kerem'  => array('name' => lang('Kerem Yıldız'), 'service' => 'vaccinations', 'years' => 9, 'days' => array('mon', 'wed', 'thu', 'sat'),
        'services' => array('vaccinations', 'travel_health', 'prescriptions'),
        'role'     => lang('Pharmacist, Vaccinations and Travel'),
        'summary'  => lang('Calm hands in the vaccination room and a map in his head of what every destination asks for.'),
        'bio'      => lang('Kerem is trained to give vaccinations and leads the flu season every autumn. Before a trip he is the pharmacist who puts together your first-aid kit and checks what you need for the journey.')),
    'nora'   => array('name' => lang('Nora Lindqvist'), 'service' => 'health_checks', 'years' => 7, 'days' => array('tue', 'thu', 'fri', 'sat'),
        'services' => array('health_checks', 'medicine_review', 'vaccinations'),
        'role'     => lang('Pharmacist, Health Checks'),
        'summary'  => lang('The health checks\' pharmacist, who turns figures into plain words and a plan you can keep.'),
        'bio'      => lang('Nora runs the consultation room: blood pressure, blood sugar, cholesterol and weight. She follows customers who measure every month and knows when a figure needs a doctor.')),
    'baris'  => array('name' => lang('Barış Koç'), 'service' => 'delivery', 'years' => 4, 'days' => array('mon', 'tue', 'wed', 'fri', 'sat'),
        'services' => array('delivery', 'prescriptions', 'health_checks'),
        'role'     => lang('Pharmacist'),
        'summary'  => lang('The youngest of the team, who answers the delivery line and brings the medicines to the door himself.'),
        'bio'      => lang('Barış joined the pharmacy after his studies. He looks after home delivery and the customers who cannot come in, and takes many of the duty nights.')),
);

// The product ranges on the shelves (the home and the about page; with the
// shop, its categories). Never prescription medicine.
$ranges = array(
    'vitamins'  => array('icon' => 'bi-capsule', 'title' => lang('Vitamins and Supplements'),
        'text' => lang('Vitamin D, iron, omega-3 and the supplements we would take ourselves, with advice on what you need.')),
    'skin'      => array('icon' => 'bi-droplet-half', 'title' => lang('Skin Care and Sun'),
        'text' => lang('Gentle skin care for every skin type and sun protection for the whole family.')),
    'baby'      => array('icon' => 'bi-balloon-heart', 'title' => lang('Mother and Baby'),
        'text' => lang('Baby care, feeding bottles, nappy creams and the first-aid basics for young families.')),
    'first_aid' => array('icon' => 'bi-bandaid', 'title' => lang('First Aid'),
        'text' => lang('Plasters, dressings, thermometers and ready kits for the home, the car and the journey.')),
    'devices'   => array('icon' => 'bi-activity', 'title' => lang('Health Devices'),
        'text' => lang('Blood pressure monitors, blood sugar meters and inhaler spacers, with a pharmacist to show you how.')),
);

// The health topics: the Health Advice list's filter (?topic=) and the
// question's topic. Each answer's value is its own words, so a filter link
// reads as it shows.
$topics = array(
    'medicines' => lang('Medicines'),
    'heart'     => lang('Heart and Blood Pressure'),
    'diabetes'  => lang('Diabetes'),
    'skin'      => lang('Skin and Sun'),
    'children'  => lang('Children\'s Health'),
    'travel'    => lang('Travel Health'),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A service or a check therefore shows one amount wherever it appears.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A service's "From …" words, or "Free of charge".
$service_price = function ($key) use ($services, $money) {
    if ($services[$key]['from'] === null) return lang('Free of charge');
    return sprintf(lang('From %s'), $money($services[$key]['from']));
};
// A check's price.
$check_price = function ($key) use ($checks, $money) {
    return $money($checks[$key]['price']);
};

// A prescription request in four steps (the prescriptions page and the home).
$prescription_steps = array(
    array('title' => lang('Send your request'), 'text' => lang('Give the prescription number or the medicines, and choose to collect them or have them delivered.')),
    array('title' => lang('We prepare it'), 'text' => lang('We check the stock and order what is missing; a pharmacist checks every medicine against your others.')),
    array('title' => lang('A message when it is ready'), 'text' => lang('You see the status in your account and get a message when the bag is waiting for you.')),
    array('title' => lang('Collect with your prescription'), 'text' => lang('Show the original prescription at the counter or at the door, and ask us anything about the medicines.')),
);

// The pharmacy in four figures (the home and about pages).
$figures = array(
    array('value' => '3', 'label' => lang('Generations behind the counter')),
    array('value' => '4', 'label' => lang('Pharmacists on the team')),
    array('value' => '6', 'label' => lang('Days open every week')),
    array('value' => '10', 'label' => lang('Minutes for most health checks')),
);

// What customers say, each about one of the services.
$testimonials = array(
    array('service' => 'medicine_review', 'name' => lang('Gülten Aydın'), 'role' => lang('Customer for many years'),
        'quote' => lang('I was taking nine different tablets and felt tired all the time. Selin went through every one with me and my doctor changed two of them. I feel like myself again.')),
    array('service' => 'vaccinations', 'name' => lang('Thomas Becker'), 'role' => lang('Flu vaccination every autumn'),
        'quote' => lang('I book my flu jab online, walk in at lunchtime and I am back at my desk ten minutes later. No waiting room, no fuss.')),
    array('service' => 'delivery', 'name' => lang('Merve Çelik'), 'role' => lang('Mother of a newborn'),
        'quote' => lang('With a newborn and a fever in the house I could not go out. They brought the medicine the same afternoon and explained everything on the phone.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the customer chose.
$service_titles = array();
foreach ($services as $s) $service_titles[] = $s['title'];
// The prescription request's kind: the services that send it (with the
// card's choice made) and two more.
$request_choices = array();
foreach ($services as $s) if ($s['action'] === 'prescription') $request_choices[] = $s['title'];
$request_choices = array_merge($request_choices, array(lang('A medicine you did not have in stock'), lang('Something else')));
$pharmacist_names = array();
foreach ($pharmacists as $p) $pharmacist_names[] = $p['name'];
// The prescription request's pharmacist: any pharmacist, or one by name.
$pharmacist_choices = array_merge(array(lang('Any pharmacist')), $pharmacist_names);
$day_titles = array_values($days);
$time_choices = array(lang('Morning'), lang('Afternoon'), lang('Evening'));
$topic_titles = array_values($topics);
// The question's topic: the health topics and anything else.
$topic_choices = array_merge($topic_titles, array(lang('Something else')));
$check_titles = array();
foreach ($checks as $c) $check_titles[] = $c['title'];
$collect_choices = array(
    array(lang('Collect in the pharmacy'), lang('Collect in the pharmacy')),
    array(lang('Deliver to my home'), lang('Deliver to my home')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a prescription request, set by the pharmacy in the panel.
$prescription_statuses = array(lang('Request received'), lang('Being prepared'), lang('Ready to collect'), lang('Out for delivery'), lang('Collected'), lang('Cancelled'));
// The states of a question to the pharmacist, set in the panel.
$question_statuses = array(lang('Question received'), lang('Answered'), lang('Closed'));

// The query string of a link that opens a list with a filter set
// (?service=, ?topic=) or a form with a choice made (?service=,
// ?pharmacist=, ?topic=): the form's fields start with these values
// (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the booking band above the footer unless the page is
// a form or an account page.
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
            $heading('h1', $title, 'prx-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'prx-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'prx-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the services are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'rx-faq') use ($el, $para, $attr, $searchable) {
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

// The phone link of a number as the browser dials it.
$tel = function ($number) {
    return 'tel:' . preg_replace('/[^0-9+]/', '', $number);
};

// The line in the account bar: where to go when the pharmacy is closed (the
// on-duty page) and the emergency number. A shared component.
$notice = function () use ($el, $icon, $span, $link, $pharmacy, $tel) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-moon-stars', 'text-primary'),
        $span(lang('Closed right now?'), '', lang('Text')),
        $link(lang('Find the pharmacy on duty'), '{{page:on_duty}}', 'link-primary fw-semibold'),
        $span(lang('Emergency:'), 'ms-sm-2', lang('Text')),
        $link($pharmacy['emergency'], $tel($pharmacy['emergency']), 'link-danger fw-semibold text-nowrap'),
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

// The pharmacy's name with its cross, in the header and the footer.
$brand = function ($class) use ($el, $span, $attr) {
    return $el('a', $class, lang('Brand'), array(
        $el('span', 'prx-cross d-inline-flex align-items-center justify-content-center rounded-3 bg-primary text-white', lang('Pharmacy Cross'), array(
            $el('i', 'bi bi-plus-lg', lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
        )),
        $span('{{site_name}}', 'prx-brand', lang('Name')),
    ), array('href' => '{{page:home}}'));
};

// The header: the pharmacy's name, the menu and the way to send a
// prescription. The links to the current page are marked active on the
// server (smart active state), so one header serves every page. The shop
// is linked from the shop pages only, so the header stands on a site
// without it.
$header = function () use ($el, $link, $icon, $span, $attr, $brand) {
    $items = array();
    foreach (array(
        array('services', lang('Our Services')),
        array('health_checks', lang('Health Checks')),
        array('team', lang('Our Pharmacists')),
        array('on_duty', lang('On Duty')),
        array('blog', lang('Health Advice')),
        array('contact', lang('Contact Us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $brand('navbar-brand d-flex align-items-center gap-2 fs-4'),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#rx-nav'),
                    $attr('aria-controls', 'rx-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-prescription2'),
                            $span(lang('Send a Prescription'), '', lang('Link Text')),
                        ), array('href' => '{{page:prescription}}')),
                    )),
                ), array('id' => 'rx-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the pharmacy in a sentence and its profiles,
// the services, the customers' pages, the pharmacy's pages and how to reach
// it with the opening hours; at the bottom the year and the site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $services, $pharmacy, $opening_hours, $attr, $brand, $tel) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $service_links = array();
    foreach ($services as $key => $s) $service_links[] = $link($s['title'], '{{page:service_' . $key . '}}', $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };
    $labelled = function ($label, $number, $name) use ($el, $span, $link, $plain, $tel) {
        return $el('span', '', $name, array(
            $span($label . ': ', '', lang('Label')),
            $link($number, $tel($number), $plain . ' fw-semibold'),
        ));
    };
    $hours = array();
    foreach ($opening_hours as $h) {
        $hours[] = $el('span', 'd-block', lang('Opening Hours'), array(
            $span($h['days'] . ': ', '', lang('Days')),
            $span($h['hours'], 'prx-price', lang('Hours')),
        ));
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Pharmacy'), array(
                    $brand('d-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain),
                    $para($pharmacy['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-whatsapp', 'WhatsApp'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Service Links'), array(
                    $heading('h2', lang('Our Services'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($service_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Customer Links'), array(
                    $heading('h2', lang('For Customers'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Send a Prescription'), '{{page:prescription}}', $plain),
                        $link(lang('My Prescriptions'), '{{page:my_prescriptions}}', $plain),
                        $link(lang('Book a Vaccination or Check'), '{{page:book_online}}', $plain),
                        $link(lang('Ask a Pharmacist'), '{{page:ask}}', $plain),
                        $link(lang('My Questions'), '{{page:my_questions}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Pharmacy'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Our Pharmacists'), '{{page:team}}', $plain),
                        $link(lang('On Duty'), '{{page:on_duty}}', $plain),
                        $link(lang('Health Advice'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Visit Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($pharmacy['address'], '{{page:location}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($pharmacy['phone'], $tel($pharmacy['phone']), $plain), lang('Phone')),
                    $contact_line('bi-bicycle', $labelled(lang('Delivery'), $pharmacy['delivery'], lang('Delivery Line')), lang('Delivery Line')),
                    $contact_line('bi-telephone-plus', $labelled(lang('Emergency'), $pharmacy['emergency'], lang('Emergency Number')), lang('Emergency Number')),
                    $contact_line('bi-envelope', $link($pharmacy['email'], 'mailto:' . $pharmacy['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-clock', $el('div', '', lang('Opening Hours'), $hours), lang('Opening Hours')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('The information on this site does not replace a visit to your doctor.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The booking band: a full-width band in the palette's colour with the two
// things most customers come for. Shared, so every page that ends with it
// carries the same offer.
$booking_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Your medicines ready when you are'), 'prx-display h2 mb-2'),
                    $para(lang('Send us your prescription and collect it without waiting, or book a vaccination or a health check online.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Send a Prescription'), '{{page:prescription}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('Book Online'), '{{page:book_online}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// Where a service's card sends the customer: the prescription request with
// the service chosen, online booking, or a question on the service's topic.
$service_action = function ($key) use ($link, $services, $topics, $query) {
    $s = $services[$key];
    if ($s['action'] === 'prescription') {
        return $link(lang('Send a request'), '{{page:prescription}}?' . $query(array('service' => $s['title'])), 'btn btn-link');
    }
    if ($s['action'] === 'ask') {
        return $link(lang('Ask a pharmacist'), '{{page:ask}}?' . $query(array('topic' => $topics[$s['topic']])), 'btn btn-link');
    }
    return $link(lang('Book a time'), '{{page:book_online}}', 'btn btn-link');
};

// A service's card (the services page and the home): the icon, the name,
// a line, the price, a link to its page and the service's next step.
$service_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $services, $service_price, $service_action) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 prx-lift', lang('Service Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'align-self-start mb-3'),
                $heading('h3', $s['title'], 'prx-display h4 mb-2'),
                $para($s['summary'], 'small text-body-secondary mb-3'),
                $para($service_price($key), 'prx-price fw-bold mb-4'),
                $el('div', 'd-flex flex-wrap gap-2 mt-auto', lang('Buttons'), array(
                    $link(lang('Read More'), '{{page:service_' . $key . '}}', 'btn btn-outline-primary rounded-pill'),
                    $service_action($key),
                )),
            )),
        )),
    ), '6', '4');
};

// A health check's card (the health checks page and the home): the icon,
// the name, what it measures, how long it takes, the price and a link to
// book it.
$check_card = function ($key) use ($el, $heading, $para, $link, $span, $icon, $col, $icon_tile, $checks, $check_price) {
    $c = $checks[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-primary-subtle rounded-4 prx-lift', lang('Check Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($c['icon'], 'align-self-start bg-body mb-3'),
                $heading('h3', $c['title'], 'prx-display h5 mb-2'),
                $para($c['text'], 'small mb-3'),
                $el('p', 'd-flex flex-wrap align-items-center gap-3 small mb-4', lang('Time and Price'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Duration'), array(
                        $icon('bi-clock'),
                        $span(sprintf(lang('%d minutes'), $c['minutes']), '', lang('Value')),
                    )),
                    $span($check_price($key), 'prx-price fw-bold', lang('Price')),
                )),
                $link(lang('Book this check'), '{{page:book_online}}', 'btn btn-primary rounded-pill mt-auto'),
            )),
        )),
    ), '6', '3');
};

// A pharmacist's card of the lists (the home and the team page): the
// photo, the service they lead, the name linking the pharmacist's page, the
// role, the summary and the years of practice. Every value is bound to the
// Pharmacists form's field of the same name; the sample fills the canvas.
$pharmacist_card = function ($sample_key = 'selin') use ($el, $para, $span, $link, $image, $col, $icon, $bind, $pharmacists, $services, $portrait, $attr) {
    $p = $pharmacists[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden prx-lift', lang('Pharmacist Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($portrait($p['name'], 600), $p['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($services[$p['service']]['title'], 'prx-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Service'), $bind('service')),
                $el('h3', 'prx-display h4 position-static mb-1', lang('Pharmacist Name'), array(
                    $link($p['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($p['role'], 'small fw-semibold mb-2', $bind('role')),
                $para($p['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('p', 'd-flex align-items-center gap-1 small text-body-secondary mt-auto mb-0', lang('Years of Practice'), array(
                    $icon('bi-award', 'text-primary'),
                    $span((string)$p['years'], 'prx-price', lang('Value'), $bind('years')),
                    $span(lang('years in practice'), '', lang('Unit')),
                )),
            )),
        )),
    ), '6', '3');
};

// A product range's card (the home and the about page): the icon, the
// range and a line. It links nothing, so it stands on a site without the
// shop; the shop pages draw their own.
$range_card = function ($key) use ($el, $heading, $para, $col, $icon_tile, $ranges) {
    $r = $ranges[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4', lang('Range Card'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($r['icon'], 'mb-3'),
                $heading('h3', $r['title'], 'prx-display h6 fw-bold mb-2'),
                $para($r['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '', '', '12', 'col-lg');
};

// What a customer says: the quote, the stars and the customer.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Customer Quote'), array(
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

// A pharmacist's working days, as a row of day badges: the days they work
// in the palette's colour, the others plain.
$pharmacist_days = function ($key) use ($el, $span, $days, $pharmacists) {
    $badges = array();
    foreach ($days as $k => $title) {
        $on = in_array($k, $pharmacists[$key]['days'], true);
        $badges[] = $span($title, 'badge rounded-pill ' . ($on ? 'text-bg-primary' : 'bg-body-tertiary text-body-secondary text-decoration-line-through'), lang('Day'));
    }
    return $el('p', 'd-flex flex-wrap gap-1 mb-0', lang('Working Days'), $badges);
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
                $item('staff_prescriptions', lang('Prescription Requests')),
                $item('staff_questions', lang('Questions to the Pharmacist')),
                $item('pharmacist_new', lang('New Pharmacist')),
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
    return $agree($id, 'consent', lang('I would like to receive the pharmacy\'s news, seasonal reminders and Health Advice articles by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// question may carry a photo of a medicine's box or a doctor's letter, so
// the default accepts PDFs, office files, text and pictures; one that takes
// only pictures is a collection's image kind (a pharmacist's photo).
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
                    $link(lang('Five questions to ask about a new medicine'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

