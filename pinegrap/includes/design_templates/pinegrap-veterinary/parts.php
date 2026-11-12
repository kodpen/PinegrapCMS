<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Veterinary", part 1 of its files: the node builders, the
 * practice's own data (the opening hours, the pets it treats, the services
 * and their prices, the vets and their days, the pet health plans and their
 * prices, the signs of an emergency, the first visit's steps, the practice
 * in figures, what owners say and the answers of the forms' pick lists) and
 * the parts every page repeats (account bar with the emergency line,
 * header, footer, booking band, service, pet, vet, plan and owner cards,
 * page shells, section headers, form fields, the comment thread, the staff
 * bar and the blog parts).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-veterinary.php puts them together.
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
// every opening until the practice puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-veterinary-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language; a title such as "Dr." is left out.
$portrait = function ($name, $size = 480) {
    $initials = '';
    $words = array_values(array_filter(preg_split('/\s+/u', trim($name)), function ($w) { return substr($w, -1) !== '.'; }));
    foreach (array_slice($words, 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/d1fae5/065f46?text=' . rawurlencode($initials !== '' ? $initials : 'V');
};


// ── The practice: its pets, its services, its vets and its plans ────────

// The practice itself. The about page tells its story; the footer, the
// booking band and the contact lines use the address, the phones and the
// opening hours. The emergency line answers when the practice is closed.
$practice = array(
    'tagline'   => lang('A friendly neighbourhood vet for dogs, cats, rabbits and birds, with time for every pet and every question'),
    'story'     => lang('Two vets opened the practice in a corner shop with one consulting room and a lot of patience. Today it has three consulting rooms, an operating theatre, its own laboratory and a team that knows most of its patients by name, and every pet still gets the same calm welcome.'),
    'address'   => lang('Chestnut Lane 14, Old Town'),
    'region'    => lang('Parking in front of the door and a quiet waiting room for cats'),
    'phone'     => '+90 555 000 00 10',
    'emergency' => '+90 555 000 00 11',
    'email'     => 'hello@example.com',
);

// The days of the week the vets work, in order; a vet's days are keys of
// this list (a test holds them to it).
$days = array(
    'mon' => lang('Monday'),
    'tue' => lang('Tuesday'),
    'wed' => lang('Wednesday'),
    'thu' => lang('Thursday'),
    'fri' => lang('Friday'),
    'sat' => lang('Saturday'),
);
// The opening hours in three lines, the way the footer and the contact
// page show them; on Sunday only the emergency line answers.
$opening_hours = array(
    array('days' => lang('Monday to Friday'), 'hours' => '08:00–19:00'),
    array('days' => lang('Saturday'), 'hours' => '09:00–15:00'),
    array('days' => lang('Sunday'), 'hours' => lang('Emergencies only')),
);

// The pets the practice treats: the pets page, the vet list's filter
// (?species=) and the articles' filter. Each answer's value is its own
// words, so a filter link reads as it shows.
$species = array(
    'dog'    => array('icon' => 'bi-house-heart', 'title' => lang('Dogs'),
        'text' => lang('From the first puppy visit to the gentle care of an old friend: vaccinations, check-ups, dental care and surgery.')),
    'cat'    => array('icon' => 'bi-moon-stars', 'title' => lang('Cats'),
        'text' => lang('A cat-friendly practice with a quiet waiting area, calm handling and appointments that keep the stress low.')),
    'small'  => array('icon' => 'bi-flower2', 'title' => lang('Rabbits and Small Pets'),
        'text' => lang('Rabbits, guinea pigs, hamsters and ferrets, with teeth checks, diet advice and vets who treat them every week.')),
    'bird'   => array('icon' => 'bi-feather', 'title' => lang('Birds'),
        'text' => lang('Budgies, canaries and parrots: health checks, beak and claw care and advice on cages, diet and company.')),
);

// The services. 'from' is the price a visit starts at per currency, read
// with $money; 'species' are the pets the service is offered for. Each has
// its own page (service_<key>) and opens the appointment request with the
// service chosen.
$services = array(
    'wellness'    => array('icon' => 'bi-clipboard2-heart', 'title' => lang('Wellness Exams'), 'species' => array('dog', 'cat', 'small', 'bird'),
        'from'    => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1500),
        'summary' => lang('A nose-to-tail check once a year, or twice for older pets, to find small problems before they grow.'),
        'text'    => lang('The vet checks the eyes, ears, teeth, heart, lungs, skin, joints and weight, and talks through food, exercise and behaviour with you. You leave with a written summary and a plan for the year ahead.'),
        'points'  => array(lang('A full physical examination'), lang('Weight and body condition score'), lang('Advice on food, exercise and behaviour'), lang('A written summary to take home'))),
    'vaccination' => array('icon' => 'bi-shield-check', 'title' => lang('Vaccinations'), 'species' => array('dog', 'cat', 'small'),
        'from'    => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1150),
        'summary' => lang('First courses for puppies, kittens and rabbits, and the yearly boosters that keep them protected.'),
        'text'    => lang('Every vaccination comes with a health check. The vet chooses the vaccines your pet needs for its life and travels, records them in its passport and tells you when the next one is due.'),
        'points'  => array(lang('Puppy, kitten and rabbit courses'), lang('Yearly boosters with a health check'), lang('Pet passport and travel vaccines'), lang('Microchipping on the same visit'))),
    'dental'      => array('icon' => 'bi-emoji-smile', 'title' => lang('Dental Care'), 'species' => array('dog', 'cat', 'small'),
        'from'    => array('USD' => 60, 'EUR' => 55, 'GBP' => 48, 'TRY' => 1900),
        'summary' => lang('Teeth checks, scale and polish under anaesthetic, extractions and advice on brushing at home.'),
        'text'    => lang('Most pets over three years old have some dental disease, and it hurts long before it shows. We check the mouth at every visit, clean and polish under a light anaesthetic and show you how to keep the teeth clean at home.'),
        'points'  => array(lang('Dental check at every visit'), lang('Scale and polish under anaesthetic'), lang('Dental X-rays and extractions'), lang('Home care advice and products'))),
    'surgery'     => array('icon' => 'bi-bandaid', 'title' => lang('Surgery'), 'species' => array('dog', 'cat', 'small'),
        'from'    => array('USD' => 180, 'EUR' => 165, 'GBP' => 145, 'TRY' => 6000),
        'summary' => lang('Neutering, lump removals and soft tissue surgery in our own theatre, with a nurse beside every patient.'),
        'text'    => lang('Before an operation the vet examines your pet and explains the risks and the costs. A nurse watches the anaesthetic from start to finish, and we call you as soon as your pet wakes up. A check after the operation is included.'),
        'points'  => array(lang('Neutering of dogs, cats and rabbits'), lang('Lump removals and soft tissue surgery'), lang('A nurse watching every anaesthetic'), lang('A check after the operation included'))),
    'diagnostics' => array('icon' => 'bi-activity', 'title' => lang('Diagnostics and Imaging'), 'species' => array('dog', 'cat', 'small', 'bird'),
        'from'    => array('USD' => 70, 'EUR' => 65, 'GBP' => 56, 'TRY' => 2300),
        'summary' => lang('Blood tests in our own laboratory, digital X-rays and ultrasound, with most results on the same day.'),
        'text'    => lang('When something is wrong we want to know why. Our laboratory runs most blood and urine tests within the hour, and the X-ray and ultrasound rooms are next door, so a sick pet rarely has to come back for answers.'),
        'points'  => array(lang('Blood and urine tests in our own laboratory'), lang('Digital X-rays'), lang('Ultrasound scans'), lang('Most results on the same day'))),
    'nutrition'   => array('icon' => 'bi-basket', 'title' => lang('Nutrition and Weight'), 'species' => array('dog', 'cat', 'small', 'bird'),
        'from'    => array('USD' => 30, 'EUR' => 28, 'GBP' => 24, 'TRY' => 950),
        'summary' => lang('Diet advice for every age, weight clinics with our nurses and special diets for pets with a condition.'),
        'text'    => lang('The right food does more for a pet\'s health than any medicine. Our nurses run free weight checks, write a feeding plan with you and follow your pet\'s progress until it reaches a healthy weight.'),
        'points'  => array(lang('Feeding plans for every age'), lang('Free weight checks with a nurse'), lang('Special diets for kidneys, skin and joints'), lang('Advice for rabbits and birds too'))),
);

// The vets: the records the Vets form starts with. The vet list, the vet
// page and the home read them; 'species' is the pet each one sees most (the
// list's filter), 'days' the days they consult, 'services' what they do,
// 'years' how long they have practised.
$vets = array(
    'elif'   => array('name' => lang('Dr. Elif Şahin'), 'species' => 'dog', 'years' => 14, 'days' => array('mon', 'tue', 'thu', 'fri'),
        'services' => array('surgery', 'wellness', 'dental'),
        'role'     => lang('Senior Veterinary Surgeon'),
        'summary'  => lang('Our surgeon, calm in the theatre and patient in the consulting room, with a soft spot for old dogs.'),
        'bio'      => lang('Elif opened the practice with Can after ten years in a busy city hospital. She leads the surgery and the dental work, and believes that a pet who is not afraid of the vet is half healed already.')),
    'can'    => array('name' => lang('Dr. Can Öztürk'), 'species' => 'cat', 'years' => 12, 'days' => array('mon', 'wed', 'thu', 'sat'),
        'services' => array('wellness', 'diagnostics', 'vaccination'),
        'role'     => lang('Veterinarian, Feline Medicine'),
        'summary'  => lang('The cats\' favourite: quiet hands, a towel always ready and plenty of time for nervous patients.'),
        'bio'      => lang('Can has a further qualification in feline medicine and made the practice cat friendly, from the separate waiting area to the way cats are handled. He runs the laboratory and the ultrasound room.')),
    'mira'   => array('name' => lang('Dr. Mira Collins'), 'species' => 'small', 'years' => 8, 'days' => array('tue', 'wed', 'fri', 'sat'),
        'services' => array('wellness', 'dental', 'nutrition', 'vaccination'),
        'role'     => lang('Veterinarian, Rabbits and Exotics'),
        'summary'  => lang('Rabbits, guinea pigs, birds and the odd tortoise: Mira knows what makes small patients tick.'),
        'bio'      => lang('Mira treats the practice\'s rabbits, small pets and birds, whose teeth, diet and housing need a vet who knows them well. She also runs the weight clinic with the nurses.')),
    'emre'   => array('name' => lang('Dr. Emre Aksoy'), 'species' => 'dog', 'years' => 5, 'days' => array('mon', 'tue', 'wed', 'thu', 'fri'),
        'services' => array('vaccination', 'wellness', 'diagnostics'),
        'role'     => lang('Veterinarian'),
        'summary'  => lang('The puppy and kitten vet, who answers every first-timer\'s question twice and never minds a third time.'),
        'bio'      => lang('Emre joined the practice after his training and sees most of the young patients: first vaccinations, microchips and the questions every new owner has. He also takes the evening emergency line.')),
);

// The pet health plans, with the price of a month per currency. The health
// plans page and the home show them; with the shop each is a product that
// pays a year of the plan at once ('months').
$plans = array(
    'young'  => array('sku' => 'VET-PLAN-YOUNG', 'icon' => 'bi-balloon-heart', 'title' => lang('Puppy and Kitten Plan'), 'months' => 12, 'featured' => false,
        'species' => array('dog', 'cat'),
        'price'   => array('USD' => 18, 'EUR' => 16, 'GBP' => 14, 'TRY' => 590),
        'text'    => lang('Everything a young pet needs in its first year, spread over twelve small payments.'),
        'points'  => array(lang('The first vaccination course'), lang('Microchipping'), lang('Neutering at a reduced price'), lang('Flea and worm treatment all year'))),
    'adult'  => array('sku' => 'VET-PLAN-ADULT', 'icon' => 'bi-heart', 'title' => lang('Adult Care Plan'), 'months' => 12, 'featured' => true,
        'species' => array('dog', 'cat'),
        'price'   => array('USD' => 22, 'EUR' => 20, 'GBP' => 17, 'TRY' => 720),
        'text'    => lang('Two wellness exams, the yearly booster and the preventive treatments a grown pet needs.'),
        'points'  => array(lang('Two wellness exams a year'), lang('The yearly booster vaccination'), lang('Flea and worm treatment all year'), lang('Ten per cent off other treatments'))),
    'senior' => array('sku' => 'VET-PLAN-SENIOR', 'icon' => 'bi-sunset', 'title' => lang('Senior Pet Plan'), 'months' => 12, 'featured' => false,
        'species' => array('dog', 'cat'),
        'price'   => array('USD' => 32, 'EUR' => 29, 'GBP' => 25, 'TRY' => 1050),
        'text'    => lang('More checks for older friends: a yearly blood test and a closer look at joints, teeth and heart.'),
        'points'  => array(lang('Two wellness exams a year'), lang('A yearly blood test'), lang('The yearly booster vaccination'), lang('Ten per cent off other treatments'))),
    'small'  => array('sku' => 'VET-PLAN-SMALL', 'icon' => 'bi-flower2', 'title' => lang('Rabbit and Small Pet Plan'), 'months' => 12, 'featured' => false,
        'species' => array('small'),
        'price'   => array('USD' => 12, 'EUR' => 11, 'GBP' => 9, 'TRY' => 390),
        'text'    => lang('Teeth checks, vaccinations and nail trims for rabbits and small pets, all year.'),
        'points'  => array(lang('Two health and teeth checks a year'), lang('Rabbit vaccinations'), lang('Nail trims at every visit'), lang('Ten per cent off other treatments'))),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A plan or a service therefore shows one amount wherever it appears.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A plan's "… a month" words.
$plan_price = function ($key) use ($plans, $money) {
    return sprintf(lang('%s a month'), $money($plans[$key]['price']));
};
// A service's "From …" words.
$service_price = function ($key) use ($services, $money) {
    return sprintf(lang('From %s'), $money($services[$key]['from']));
};

// The signs that cannot wait for an appointment (the emergency page and
// the notice): call the emergency line at once.
$emergency_signs = array(
    lang('Breathing difficulty or pale gums'),
    lang('Heavy bleeding or a deep wound'),
    lang('Collapse, a fit or sudden weakness'),
    lang('Swallowed poison, medicine or a foreign object'),
    lang('Repeated vomiting or a swollen belly'),
    lang('Straining to pass urine, above all in male cats'),
);

// A first visit in four steps (the new clients' page and the home).
$first_visit = array(
    array('title' => lang('Register your pet'), 'text' => lang('Send an appointment request or pick a free time online; we register your pet with the first visit.')),
    array('title' => lang('Bring what you have'), 'text' => lang('The pet passport or vaccination card, any medicines and the name of your previous vet, if there was one.')),
    array('title' => lang('Meet the vet'), 'text' => lang('A full examination and time for your questions. Cats wait in their own quiet area.')),
    array('title' => lang('Leave with a plan'), 'text' => lang('A written summary, the next vaccination date and, if you like, a health plan that spreads the cost.')),
);

// The practice in four figures (the home and about pages).
$figures = array(
    array('value' => '4', 'label' => lang('Vets on the team')),
    array('value' => '3', 'label' => lang('Consulting rooms')),
    array('value' => '24/7', 'label' => lang('Emergency line')),
    array('value' => '1', 'label' => lang('In-house laboratory')),
);

// What owners say, each about one of the services.
$testimonials = array(
    array('service' => 'dental', 'name' => lang('Ayla Korkmaz'), 'role' => lang('Owner of Pamuk, a Persian cat'),
        'quote' => lang('Pamuk stopped eating and nobody knew why. Dr. Öztürk found a bad tooth in five minutes, and a week later she was asking for breakfast again.')),
    array('service' => 'surgery', 'name' => lang('James Whitfield'), 'role' => lang('Owner of Bruno, a Labrador'),
        'quote' => lang('They called me the moment Bruno woke up from his operation and again the next morning. I have never felt so looked after at a vet.')),
    array('service' => 'nutrition', 'name' => lang('Deniz Erdem'), 'role' => lang('Owner of Fıstık, a rabbit'),
        'quote' => lang('Finally a vet who knows rabbits. Dr. Collins changed Fıstık\'s diet and his teeth have been fine ever since.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the owner chose.
$species_titles = array();
foreach ($species as $s) $species_titles[] = $s['title'];
// The appointment request's pet type: the four groups and anything else.
$pet_choices = array_merge($species_titles, array(lang('Another pet')));
$service_titles = array();
foreach ($services as $s) $service_titles[] = $s['title'];
// The appointment request's service: the services and a visit about
// something else (a vet decides what it needs).
$reason_choices = array_merge($service_titles, array(lang('My pet is unwell'), lang('Something else')));
$vet_names = array();
foreach ($vets as $v) $vet_names[] = $v['name'];
// The appointment request's vet: any vet, or one by name.
$vet_choices = array_merge(array(lang('Any vet')), $vet_names);
$day_titles = array_values($days);
$time_choices = array(lang('Morning'), lang('Afternoon'), lang('Evening'));
$plan_choices = array();
foreach ($plans as $p) $plan_choices[] = $p['title'];
$age_choices = array(lang('Under one year'), lang('One to seven years'), lang('Over seven years'), lang('I am not sure'));
$visit_choices = array(
    array(lang('First visit'), lang('First visit')),
    array(lang('Already registered'), lang('Already registered')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of an appointment request, set by the reception in the panel.
$appointment_statuses = array(lang('Request received'), lang('Appointment confirmed'), lang('Visit completed'), lang('Cancelled'));
// The states of a health plan sign-up, set by the reception in the panel.
$plan_join_statuses = array(lang('Request received'), lang('Plan started'), lang('Declined'));

// The query string of a link that opens a list with a filter set
// (?species=) or a form with a choice made (?service=, ?vet=, ?species=,
// ?plan=): the form's fields start with these values (_cf.url_param).
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
            $heading('h1', $title, 'pvt-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pvt-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pvt-display h3 mb-0'),
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

// Five stars, for what owners say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'vt-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the emergency line, which answers day and
// night, and the page that says what to do. A shared component.
$notice = function () use ($el, $icon, $span, $link, $practice) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-telephone-plus', 'text-danger'),
        $span(lang('Emergency? Our line answers day and night:'), '', lang('Text')),
        $link($practice['emergency'], 'tel:' . preg_replace('/[^0-9+]/', '', $practice['emergency']), 'link-danger fw-semibold text-nowrap'),
        $link(lang('What to do'), '{{page:emergency}}', 'link-primary'),
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

// The header: the practice's name, the menu and the way to ask for an
// appointment. The links to the current page are marked active on the
// server (smart active state), so one header serves every page. The shop
// is linked from the shop pages only, so the header stands on a site
// without it.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('services', lang('Our Services')),
        array('pets', lang('Pets')),
        array('vets', lang('Vets')),
        array('health_plans', lang('Health Plans')),
        array('new_clients', lang('New Clients')),
        array('emergency', lang('Emergencies')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-heart-pulse-fill', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pvt-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#vt-nav'),
                    $attr('aria-controls', 'vt-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav pvt-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-plus'),
                            $span(lang('Book an Appointment'), '', lang('Link Text')),
                        ), array('href' => '{{page:appointment}}')),
                    )),
                ), array('id' => 'vt-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the practice in a sentence and its profiles,
// the care by pet, the owners' pages, the practice's pages and how to reach
// it with the opening hours; at the bottom the year and the site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $species, $practice, $opening_hours, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $care_links = array(
        $link(lang('Our Services'), '{{page:services}}', $plain),
        $link(lang('Pets We Treat'), '{{page:pets}}', $plain),
    );
    foreach ($species as $s) $care_links[] = $link($s['title'], '{{page:vets}}?' . $query(array('species' => $s['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };
    $tel = function ($number) {
        return 'tel:' . preg_replace('/[^0-9+]/', '', $number);
    };
    $hours = array();
    foreach ($opening_hours as $h) {
        $hours[] = $el('span', 'd-block', lang('Opening Hours'), array(
            $span($h['days'] . ': ', '', lang('Days')),
            $span($h['hours'], 'pvt-price', lang('Hours')),
        ));
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Practice'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-heart-pulse-fill', 'text-primary'),
                        $span('{{site_name}}', 'pvt-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($practice['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Care Links'), array(
                    $heading('h2', lang('Our Care'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($care_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Pet Owners'), array(
                    $heading('h2', lang('For Pet Owners'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book an Appointment'), '{{page:appointment}}', $plain),
                        $link(lang('Pick a Free Time'), '{{page:book_online}}', $plain),
                        $link(lang('My Appointments'), '{{page:my_appointments}}', $plain),
                        $link(lang('Join a Health Plan'), '{{page:plan_join}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Practice'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Our Vets'), '{{page:vets}}', $plain),
                        $link(lang('New Clients'), '{{page:new_clients}}', $plain),
                        $link(lang('Pet Care Articles'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Visit Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($practice['address'], '{{page:location}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($practice['phone'], $tel($practice['phone']), $plain), lang('Phone')),
                    $contact_line('bi-telephone-plus', $el('span', '', lang('Emergency Line'), array(
                        $span(lang('Emergencies') . ': ', '', lang('Label')),
                        $link($practice['emergency'], $tel($practice['emergency']), $plain . ' fw-semibold'),
                    )), lang('Emergency Line')),
                    $contact_line('bi-envelope', $link($practice['email'], 'mailto:' . $practice['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-clock', $el('div', '', lang('Opening Hours'), $hours), lang('Opening Hours')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Kind care for every pet, from the first visit to the last.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The booking band: a full-width band in the palette's colour with the two
// ways to see a vet. Shared, so every page that ends with it carries the
// same offer.
$booking_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Time for a check-up?'), 'pvt-display h2 mb-2'),
                    $para(lang('Send us an appointment request and we call you back, or pick a free time with your vet now.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('Pick a Free Time'), '{{page:book_online}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The pets a service is for, as small badges.
$species_badges = function ($keys) use ($el, $span, $species) {
    $badges = array();
    foreach ($keys as $k) $badges[] = $span($species[$k]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Badge'));
    return $el('p', 'd-flex flex-wrap gap-1 mb-3', lang('Pets'), $badges);
};

// A service's card (the services page and the home): the icon, the name,
// a line, the pets, the starting price, a link to its page and one that
// opens the appointment request with the service chosen.
$service_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $species_badges, $services, $service_price, $query) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 pvt-lift', lang('Service Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'align-self-start mb-3'),
                $heading('h3', $s['title'], 'pvt-display h4 mb-2'),
                $para($s['summary'], 'small text-body-secondary mb-3'),
                $species_badges($s['species']),
                $para($service_price($key), 'pvt-price fw-bold mb-4'),
                $el('div', 'd-flex flex-wrap gap-2 mt-auto', lang('Buttons'), array(
                    $link(lang('Read More'), '{{page:service_' . $key . '}}', 'btn btn-outline-primary rounded-pill'),
                    $link(lang('Ask for this service'), '{{page:appointment}}?' . $query(array('service' => $s['title'])), 'btn btn-link'),
                )),
            )),
        )),
    ), '6', '4');
};

// A pet's card (the pets page and the home): the icon, the pets, a line,
// a link to the vets who see them most and one that asks for an
// appointment for that pet.
$species_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $species, $query) {
    $s = $species[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-primary-subtle rounded-4 pvt-lift', lang('Pet Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'align-self-start bg-body mb-3'),
                $heading('h3', $s['title'], 'pvt-display h4 mb-2'),
                $para($s['text'], 'small mb-4'),
                $el('div', 'd-flex flex-wrap gap-2 mt-auto', lang('Buttons'), array(
                    $link(lang('Find a vet'), '{{page:vets}}?' . $query(array('species' => $s['title'])), 'btn btn-primary rounded-pill'),
                    $link(lang('Ask for an appointment'), '{{page:appointment}}?' . $query(array('species' => $s['title'])), 'btn btn-link'),
                )),
            )),
        )),
    ), '6', '3');
};

// A vet's card of the lists (the home and the vet list): the photo, the
// pets they see most, the name linking the vet's page, the role, the
// summary and the years of practice. Every value is bound to the Vets
// form's field of the same name; the sample fills the canvas.
$vet_card = function ($sample_key = 'elif') use ($el, $para, $span, $link, $image, $col, $icon, $bind, $vets, $species, $portrait, $attr) {
    $v = $vets[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden pvt-lift', lang('Vet Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($portrait($v['name'], 600), $v['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($species[$v['species']]['title'], 'pvt-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Pets'), $bind('species')),
                $el('h3', 'pvt-display h4 position-static mb-1', lang('Vet Name'), array(
                    $link($v['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($v['role'], 'small fw-semibold mb-2', $bind('role')),
                $para($v['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('p', 'd-flex align-items-center gap-1 small text-body-secondary mt-auto mb-0', lang('Years of Practice'), array(
                    $icon('bi-award', 'text-primary'),
                    $span((string)$v['years'], 'pvt-price', lang('Value'), $bind('years')),
                    $span(lang('years in practice'), '', lang('Unit')),
                )),
            )),
        )),
    ), '6', '3');
};

// A plan's card (the health plans page and the home): the name, the pets,
// the price of a month, a line, what it holds and a link that opens the
// sign-up request with the plan chosen. The plan most owners choose is
// marked.
$plan_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $check_list, $icon_tile, $species_badges, $plans, $plan_price, $query) {
    $p = $plans[$key];
    $top = array($icon_tile($p['icon']));
    if ($p['featured']) $top[] = $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary', lang('Badge'));
    return $col(array(
        $el('article', 'card h-100 shadow-sm rounded-4 ' . ($p['featured'] ? 'border-primary border-2' : 'border-0'), lang('Plan Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-start justify-content-between gap-2 mb-3', lang('Plan Heading'), $top),
                $heading('h3', $p['title'], 'pvt-display h5 mb-2'),
                $species_badges($p['species']),
                $para($plan_price($key), 'pvt-price h5 fw-bold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $check_list($p['points'], 'list-unstyled small mb-4'),
                $link(lang('Join this plan'), '{{page:plan_join}}?' . $query(array('plan' => $p['title'])),
                    ($p['featured'] ? 'btn btn-primary' : 'btn btn-outline-primary') . ' rounded-pill mt-auto'),
            )),
        )),
    ), '6', '3');
};

// What an owner says: the quote, the stars, the owner and the pet.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Owner Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Owner'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('width' => '48', 'height' => '48', '_attrs' => array($attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// A vet's consulting days, as a row of day badges: the days they work in
// the palette's colour, the others plain.
$vet_days = function ($key) use ($el, $span, $days, $vets) {
    $badges = array();
    foreach ($days as $k => $title) {
        $on = in_array($k, $vets[$key]['days'], true);
        $badges[] = $span($title, 'badge rounded-pill ' . ($on ? 'text-bg-primary' : 'bg-body-tertiary text-body-secondary text-decoration-line-through'), lang('Day'));
    }
    return $el('p', 'd-flex flex-wrap gap-1 mb-0', lang('Consulting Days'), $badges);
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
                $item('staff_appointments', lang('Appointment Requests')),
                $item('staff_plan_joins', lang('Health Plan Requests')),
                $item('vet_new', lang('New Vet')),
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
    return $agree($id, 'consent', lang('I would like to receive the practice\'s news, seasonal reminders and Pet Care Articles by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// appointment request may carry earlier test results or a photo of the
// problem, so the default accepts PDFs, office files, text and pictures; one
// that takes only pictures is a collection's image kind (a vet's photo).
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
                    $link(lang('How to brush your dog\'s teeth in five steps'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

