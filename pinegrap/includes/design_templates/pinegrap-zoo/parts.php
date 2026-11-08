<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Zoo", part 1 of its files: the node builders, the zoo's
 * own data (the zoo and its opening hours, the habitats and animal classes,
 * the conservation status levels, the sample animals, today's feeding times
 * and keeper talks, tickets, memberships and adoptions with their prices,
 * school and group visits, the sample events counted from the day the
 * template opens, the keepers, the figures and the answers of the forms'
 * pick lists) and the small helpers the pages share (page shell, page
 * header, section header, habitat pin, steps, check lists, questions).
 * cards.php and the page files of the same folder are included after it
 * in the same scope and build their trees with these variables;
 * includes/design_templates/pinegrap-zoo.php puts them together.
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
// An entrance the operator can change or remove in the Interactions
// section: the node's children rise one after another as it scrolls into
// view. The platform keeps them still under reduced motion.
$rise = function ($node, $stagger = 80) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => (int)$stagger);
    return $node;
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
// every opening until the zoo puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-zoo-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ecfccb/365314?text=' . rawurlencode($initials !== '' ? $initials : 'Z');
};

// ── The zoo: its hours, its habitats and its animals ────────────────────

// The zoo itself. The about page tells its story; the footer, the visit
// band and the tickets page use the address, phone and hours.
$zoo = array(
    'tagline' => lang('A zoo and aquarium where every visit helps animals in the wild'),
    'story'   => lang('The zoo began as a small garden of rescued animals at the edge of the town. Today twenty habitats and an aquarium share the old park, the keepers run breeding programmes for endangered species, and part of every ticket goes to the field teams that protect them in the wild.'),
    'address' => lang('Park Gate, Riverside Road'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'info@example.com',
    'hours'   => lang('Open every day of the year, 09:00 to 18:00'),
    'season'  => lang('Closes at 16:30 in the winter months'),
    'size'    => lang('Twenty habitats and an aquarium on one loop of two and a half kilometres'),
    'licence' => lang('A member of the national association of zoos and aquariums'),
);

// The opening hours, line by line (the tickets page, the home and the
// footer say them in a line).
$opening_hours = array(
    array('days' => lang('Zoo, every day'), 'hours' => '09:00 - 18:00'),
    array('days' => lang('Children\'s Farm'), 'hours' => '10:00 - 17:00'),
    array('days' => lang('Aquarium'), 'hours' => '09:30 - 17:30'),
    array('days' => lang('Café and shop'), 'hours' => '09:30 - 17:45'),
);

// The habitats of the park. The animal list filters on them (?habitat=) in
// their own words; 'pin' is the theme colour of their numbers on the map.
$habitats = array(
    'savanna'    => array('icon' => 'bi-sun', 'pin' => 'text-bg-warning', 'title' => lang('Savanna'),
        'summary' => lang('Giraffes, zebras and lions on the open grassland by the river.')),
    'rainforest' => array('icon' => 'bi-tree', 'pin' => 'text-bg-success', 'title' => lang('Rainforest'),
        'summary' => lang('A warm, green house of orangutans, tree frogs and free-flying birds.')),
    'polar'      => array('icon' => 'bi-snow', 'pin' => 'text-bg-info', 'title' => lang('Polar Coast'),
        'summary' => lang('Penguins, seals and puffins on a rocky shore with cold, clear pools.')),
    'aquarium'   => array('icon' => 'bi-water', 'pin' => 'text-bg-primary', 'title' => lang('Aquarium'),
        'summary' => lang('A coral reef, a shark tunnel and the jellyfish gallery under one roof.')),
    'australia'  => array('icon' => 'bi-compass', 'pin' => 'text-bg-danger', 'title' => lang('Australia Walk'),
        'summary' => lang('A walk-through bush where kangaroos and kookaburras share the path.')),
    'farm'       => array('icon' => 'bi-house-heart', 'pin' => 'text-bg-secondary', 'title' => lang('Children\'s Farm'),
        'summary' => lang('Goats, sheep and chickens to meet up close, with keepers to answer every question.')),
);

// The classes of animal. The animal list filters on them (?class=).
$animal_classes = array(
    'mammal'       => lang('Mammal'),
    'bird'         => lang('Bird'),
    'reptile'      => lang('Reptile'),
    'amphibian'    => lang('Amphibian'),
    'fish'         => lang('Fish'),
    'invertebrate' => lang('Invertebrate'),
);

// The seven levels of the red list, from safe to gone: the Conservation
// Status field's answers. The code is international and stays as it is.
$conservation = array(
    'lc' => array('code' => 'LC', 'title' => lang('Least Concern')),
    'nt' => array('code' => 'NT', 'title' => lang('Near Threatened')),
    'vu' => array('code' => 'VU', 'title' => lang('Vulnerable')),
    'en' => array('code' => 'EN', 'title' => lang('Endangered')),
    'cr' => array('code' => 'CR', 'title' => lang('Critically Endangered')),
    'ew' => array('code' => 'EW', 'title' => lang('Extinct in the Wild')),
    'ex' => array('code' => 'EX', 'title' => lang('Extinct')),
);

// The sample animals: the records the Animals form starts with. 'number'
// is the habitat's number on the map and its sign; diet and lifespan are
// words. 'adopt' marks the animals that can be adopted.
$animals = array(
    'giraffe'   => array('title' => lang('Reticulated Giraffe'), 'species' => 'Giraffa reticulata', 'habitat' => 'savanna', 'class' => 'mammal', 'status' => 'en',
        'number' => '3', 'diet' => lang('Leaves and twigs'), 'lifespan' => lang('About 25 years'),
        'summary' => lang('Our herd of five, led by Amani, the tallest of them, who was born here.'),
        'details' => array(lang('Fed from the high platform at 11:15'), lang('Part of the European breeding programme'), lang('A tongue half a metre long'), lang('Sleeps less than two hours a day')),
        'featured' => true, 'adopt' => true),
    'lion'      => array('title' => lang('African Lion'), 'species' => 'Panthera leo', 'habitat' => 'savanna', 'class' => 'mammal', 'status' => 'vu',
        'number' => '1', 'diet' => lang('Meat'), 'lifespan' => lang('About 15 years'),
        'summary' => lang('A pride of four that rests on the warm rocks by the viewing window.'),
        'details' => array(lang('Most awake in the late afternoon'), lang('Fed three times a week'), lang('Heard across the whole park'), lang('Born in another zoo of the programme')),
        'featured' => false, 'adopt' => true),
    'orangutan' => array('title' => lang('Sumatran Orangutan'), 'species' => 'Pongo abelii', 'habitat' => 'rainforest', 'class' => 'mammal', 'status' => 'cr',
        'number' => '9', 'diet' => lang('Fruit, leaves and insects'), 'lifespan' => lang('About 45 years'),
        'summary' => lang('A family of three that moves through the ropes high above the path.'),
        'details' => array(lang('Keeper talk at 14:30'), lang('Builds a new nest every evening'), lang('Puzzle feeders keep them busy'), lang('Part of the European breeding programme')),
        'featured' => true, 'adopt' => true),
    'frog'      => array('title' => lang('Golden Poison Frog'), 'species' => 'Phyllobates terribilis', 'habitat' => 'rainforest', 'class' => 'amphibian', 'status' => 'en',
        'number' => '8', 'diet' => lang('Small insects'), 'lifespan' => lang('About 10 years'),
        'summary' => lang('Bright yellow and harmless here: their poison comes from food they only find in the wild.'),
        'details' => array(lang('Bred behind the scenes'), lang('Kept warm and humid all year'), lang('Easiest to see in the morning'), lang('Smaller than a thumb')),
        'featured' => false, 'adopt' => false),
    'penguin'   => array('title' => lang('Humboldt Penguin'), 'species' => 'Spheniscus humboldti', 'habitat' => 'polar', 'class' => 'bird', 'status' => 'vu',
        'number' => '7', 'diet' => lang('Fish'), 'lifespan' => lang('About 20 years'),
        'summary' => lang('A noisy colony of twenty-two that dives past the underwater window.'),
        'details' => array(lang('Fed at 10:30 every morning'), lang('Each one wears a coloured wing band'), lang('Chicks hatch in the spring'), lang('Swims up to 30 km an hour')),
        'featured' => true, 'adopt' => true),
    'turtle'    => array('title' => lang('Green Sea Turtle'), 'species' => 'Chelonia mydas', 'habitat' => 'aquarium', 'class' => 'reptile', 'status' => 'en',
        'number' => '11', 'diet' => lang('Sea grass and lettuce'), 'lifespan' => lang('Over 70 years'),
        'summary' => lang('Rescued from a fishing net and too used to people to go back to the sea.'),
        'details' => array(lang('Glides over the coral reef tank'), lang('Checked by the vet every month'), lang('Breathes at the surface every few minutes'), lang('The oldest animal of the aquarium')),
        'featured' => false, 'adopt' => true),
    'shark'     => array('title' => lang('Blacktip Reef Shark'), 'species' => 'Carcharhinus melanopterus', 'habitat' => 'aquarium', 'class' => 'fish', 'status' => 'vu',
        'number' => '12', 'diet' => lang('Fish and squid'), 'lifespan' => lang('About 25 years'),
        'summary' => lang('Five sharks that circle over the heads of visitors in the tunnel.'),
        'details' => array(lang('Fed by divers at 13:00'), lang('Each one is fed by hand'), lang('Born in an aquarium'), lang('Shy, and harmless to divers')),
        'featured' => false, 'adopt' => false),
    'jellyfish' => array('title' => lang('Moon Jellyfish'), 'species' => 'Aurelia aurita', 'habitat' => 'aquarium', 'class' => 'invertebrate', 'status' => 'lc',
        'number' => '13', 'diet' => lang('Plankton'), 'lifespan' => lang('About one year'),
        'summary' => lang('Hundreds drift in the dark gallery under slowly changing light.'),
        'details' => array(lang('Grown in our own laboratory'), lang('No brain, no heart, no bones'), lang('Fed twice a day'), lang('A favourite of the youngest visitors')),
        'featured' => false, 'adopt' => false),
    'kangaroo'  => array('title' => lang('Red Kangaroo'), 'species' => 'Osphranter rufus', 'habitat' => 'australia', 'class' => 'mammal', 'status' => 'lc',
        'number' => '16', 'diet' => lang('Grass'), 'lifespan' => lang('About 20 years'),
        'summary' => lang('A mob of eight that rests beside the path of the walk-through bush.'),
        'details' => array(lang('Stay on the path, they will come close'), lang('Joeys look out of the pouch in spring'), lang('Jumps up to eight metres'), lang('Most awake early and late')),
        'featured' => false, 'adopt' => false),
    'kookaburra' => array('title' => lang('Laughing Kookaburra'), 'species' => 'Dacelo novaeguineae', 'habitat' => 'australia', 'class' => 'bird', 'status' => 'lc',
        'number' => '17', 'diet' => lang('Insects, worms and small reptiles'), 'lifespan' => lang('About 15 years'),
        'summary' => lang('Its laugh opens the morning on the Australia Walk.'),
        'details' => array(lang('Loudest just after opening'), lang('A pair that has raised four chicks'), lang('Sits still for a long time'), lang('A kingfisher that does not fish')),
        'featured' => false, 'adopt' => false),
    'goat'      => array('title' => lang('Pygmy Goat'), 'species' => 'Capra hircus', 'habitat' => 'farm', 'class' => 'mammal', 'status' => 'lc',
        'number' => '20', 'diet' => lang('Hay and vegetables'), 'lifespan' => lang('About 12 years'),
        'summary' => lang('Six friendly goats that children can brush with the farm keepers.'),
        'details' => array(lang('Brushing time at 15:00'), lang('Wash your hands after the visit'), lang('Climbs everything'), lang('Each one has its own name tag')),
        'featured' => false, 'adopt' => false),
);

// Today's schedule: the feeding times and keeper talks of an ordinary day,
// the same every day of a season. 'animal' is a key of $animals; the place
// says the habitat's number and zone.
$schedule_kinds = array(
    'feed' => array('icon' => 'bi-basket2-fill', 'title' => lang('Feeding')),
    'talk' => array('icon' => 'bi-mic-fill', 'title' => lang('Keeper talk')),
);
$schedule = array(
    array('time' => '10:30', 'kind' => 'feed', 'title' => lang('Penguin feeding'), 'animal' => 'penguin'),
    array('time' => '11:15', 'kind' => 'talk', 'title' => lang('Meet the giraffes'), 'animal' => 'giraffe'),
    array('time' => '13:00', 'kind' => 'feed', 'title' => lang('Shark feeding'), 'animal' => 'shark'),
    array('time' => '14:30', 'kind' => 'talk', 'title' => lang('Orangutan talk'), 'animal' => 'orangutan'),
    array('time' => '15:00', 'kind' => 'talk', 'title' => lang('Goat brushing at the farm'), 'animal' => 'goat'),
    array('time' => '16:00', 'kind' => 'feed', 'title' => lang('Lion feeding'), 'animal' => 'lion'),
);

// ── Tickets, memberships and adoptions with their prices ────────────────

// The tickets for a day. With the shop each is a product of the Tickets
// group; without it the tickets page lists them with their prices. One
// table of prices feeds both, so the page and the cart show one figure.
$tickets = array(
    'adult'  => array('title' => lang('Adult Ticket'), 'who' => lang('Ages 16 to 64')),
    'child'  => array('title' => lang('Child Ticket'), 'who' => lang('Ages 3 to 15; under 3 free')),
    'senior' => array('title' => lang('Senior Ticket'), 'who' => lang('Ages 65 and over')),
    'family' => array('title' => lang('Family Ticket'), 'who' => lang('Two adults and up to three children')),
);
$ticket_prices = array(
    'adult'  => array('USD' => 28, 'EUR' => 26, 'GBP' => 22, 'TRY' => 950),
    'child'  => array('USD' => 18, 'EUR' => 16, 'GBP' => 14, 'TRY' => 600),
    'senior' => array('USD' => 22, 'EUR' => 20, 'GBP' => 17, 'TRY' => 750),
    'family' => array('USD' => 85, 'EUR' => 78, 'GBP' => 66, 'TRY' => 2900),
);

// The memberships: a year of visits. With the shop each is a product of
// the Memberships group; the memberships page lists them.
$memberships = array(
    'single' => array('title' => lang('Single Membership'), 'who' => lang('One adult'),
        'benefits' => array(lang('Free entry every day for a year'), lang('Early entry on summer weekends'), lang('10% off in the café and shop'), lang('The members\' newsletter'))),
    'couple' => array('title' => lang('Couple Membership'), 'who' => lang('Two adults at the same address'),
        'benefits' => array(lang('Free entry every day for a year'), lang('Early entry on summer weekends'), lang('10% off in the café and shop'), lang('Two guest tickets a year'))),
    'family' => array('title' => lang('Family Membership'), 'who' => lang('Two adults and their children under 16'),
        'benefits' => array(lang('Free entry every day for a year'), lang('A members\' night tour each summer'), lang('15% off in the café and shop'), lang('Two guest tickets a year'))),
);
$membership_prices = array(
    'single' => array('USD' => 95, 'EUR' => 88, 'GBP' => 75, 'TRY' => 3200),
    'couple' => array('USD' => 170, 'EUR' => 158, 'GBP' => 135, 'TRY' => 5800),
    'family' => array('USD' => 240, 'EUR' => 222, 'GBP' => 190, 'TRY' => 8200),
);

// The levels of an adoption, for one animal for a year. With the shop
// each adoptable animal is a product per level; the adopt page lists the
// levels once and the animals beside them.
$adoption_levels = array(
    'friend'   => array('title' => lang('Friend Level'), 'benefits' => array(lang('An adoption certificate by e-mail'), lang('A photo and the animal\'s story'), lang('News from its keepers twice a year'))),
    'keeper'   => array('title' => lang('Keeper Level'), 'benefits' => array(lang('Everything in the Friend Level'), lang('Your name on the habitat\'s board'), lang('Two tickets for a day at the zoo'))),
    'guardian' => array('title' => lang('Guardian Level'), 'benefits' => array(lang('Everything in the Keeper Level'), lang('Meet the animal with its keeper'), lang('An invitation to the adopters\' evening'))),
);
$adoption_prices = array(
    'friend'   => array('USD' => 40, 'EUR' => 37, 'GBP' => 32, 'TRY' => 1350),
    'keeper'   => array('USD' => 90, 'EUR' => 83, 'GBP' => 72, 'TRY' => 3100),
    'guardian' => array('USD' => 250, 'EUR' => 230, 'GBP' => 200, 'TRY' => 8500),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A membership's and an adoption's "… a year" words.
$membership_price_text = function ($key) use ($membership_prices, $money) {
    return sprintf(lang('%s a year'), $money($membership_prices[$key]));
};
$adoption_price_text = function ($key) use ($adoption_prices, $money) {
    return sprintf(lang('%s a year'), $money($adoption_prices[$key]));
};

// ── School and group visits, and events ─────────────────────────────────

// The kinds of visit a school or a group asks for. A card opens the visit
// request with its kind chosen (?visit=); 'from' is said as words.
$visits = array(
    'school'   => array('icon' => 'bi-backpack', 'title' => lang('School Trip'), 'who' => lang('Classes from nursery to high school'),
        'length'  => lang('A day, with a workshop of an hour'),
        'summary' => lang('A workshop with an education keeper that follows the curriculum, then the rest of the day in the park.')),
    'tour'     => array('icon' => 'bi-people', 'title' => lang('Guided Group Tour'), 'who' => lang('Groups of ten to thirty'),
        'length'  => lang('Two hours with a guide'),
        'summary' => lang('A walk behind the scenes of two habitats and the aquarium, with the questions you always wanted to ask.')),
    'keeper'   => array('icon' => 'bi-person-badge', 'title' => lang('Keeper for a Day'), 'who' => lang('Ages 16 and over, up to four people'),
        'length'  => lang('From 08:30 to 15:00'),
        'summary' => lang('Prepare food, clean a habitat and feed the animals beside the keepers who care for them.')),
    'birthday' => array('icon' => 'bi-balloon', 'title' => lang('Birthday at the Zoo'), 'who' => lang('Children aged 5 to 12, up to fifteen'),
        'length'  => lang('Two hours in the farm room'),
        'summary' => lang('Games, a visit to the farm, a meeting with a small animal and cake in the farm room.')),
);
$visit_prices = array(
    'school'   => array('USD' => 9, 'EUR' => 8, 'GBP' => 7, 'TRY' => 300),
    'tour'     => array('USD' => 15, 'EUR' => 14, 'GBP' => 12, 'TRY' => 500),
    'keeper'   => array('USD' => 180, 'EUR' => 165, 'GBP' => 142, 'TRY' => 6100),
    'birthday' => array('USD' => 220, 'EUR' => 202, 'GBP' => 175, 'TRY' => 7500),
);
// A visit's "From … " words.
$visit_price_text = function ($key) use ($visit_prices, $money) {
    return sprintf(lang('From %s'), $money($visit_prices[$key]));
};

// The age groups of a visit: the visit request's list (?age=).
$age_groups = array(
    'little'  => lang('Ages 3 to 6'),
    'primary' => lang('Ages 7 to 11'),
    'teens'   => lang('Ages 12 to 17'),
    'adults'  => lang('Adults'),
    'mixed'   => lang('Mixed ages'),
);

// The kinds of event. The event cards say it.
$event_kinds = array(
    'night'    => lang('Night Tour'),
    'family'   => lang('Family Day'),
    'talk'     => lang('Talk'),
    'workshop' => lang('Workshop'),
);

// The sample events: the records the Events form starts with. Each falls
// on the first such ISO weekday after today, plus 'weeks' weeks
// ($event_on), so every event is still to come when the template opens.
$events = array(
    'night_walk'  => array('title' => lang('Zoo by Torchlight'), 'kind' => 'night', 'weekday' => 5, 'weeks' => 0, 'time' => '19:30',
        'place' => lang('Main gate'), 'price' => lang('With an evening ticket'),
        'summary' => lang('A walk with a keeper after the gates close, when the lions wake up and the bats come out.'),
        'featured' => true),
    'penguin_day' => array('title' => lang('Penguin Day'), 'kind' => 'family', 'weekday' => 6, 'weeks' => 1, 'time' => '10:00',
        'place' => lang('Polar Coast'), 'price' => lang('Free with a day ticket'),
        'summary' => lang('Crafts, a quiz and an extra feeding for the whole family, in aid of the penguin project.'),
        'featured' => true),
    'reef_talk'   => array('title' => lang('Saving the Reef'), 'kind' => 'talk', 'weekday' => 4, 'weeks' => 1, 'time' => '18:30',
        'place' => lang('Aquarium lecture room'), 'price' => lang('Free, booking advised'),
        'summary' => lang('Our marine biologists on growing coral in the aquarium and planting it back on the reef.'),
        'featured' => true),
    'photo_class' => array('title' => lang('Animal Photography Workshop'), 'kind' => 'workshop', 'weekday' => 7, 'weeks' => 2, 'time' => '08:30',
        'place' => lang('Education centre'), 'price' => lang('Entry included'),
        'summary' => lang('An hour before opening with a wildlife photographer, then the park while it is quiet.'),
        'featured' => false),
    'bug_day'     => array('title' => lang('Bug Hunt Day'), 'kind' => 'family', 'weekday' => 6, 'weeks' => 3, 'time' => '11:00',
        'place' => lang('Children\'s Farm'), 'price' => lang('Free with a day ticket'),
        'summary' => lang('Magnifying glasses, a trail through the meadow and the insect house open to small hands.'),
        'featured' => false),
);
// The date of an event as Y-m-d: the first day strictly after today with
// that ISO weekday, plus $weeks weeks.
$event_on = function ($weekday, $weeks = 0) {
    $ts = strtotime('tomorrow');
    while ((int)date('N', $ts) !== (int)$weekday) $ts = strtotime('+1 day', $ts);
    return date('Y-m-d', strtotime('+' . (int)$weeks . ' week', $ts));
};
// An animal's details as the value of its Details field: one line per item.
$details_text = function ($list) {
    return implode("\n", $list);
};

// The zoo's people (the about page). Fixed data, not a collection.
$people = array(
    array('name' => lang('Defne Yıldız'), 'role' => lang('Zoo Director'),
        'bio' => lang('A vet who came for one summer and stayed to turn the old garden into a conservation zoo.')),
    array('name' => lang('Samuel Okoro'), 'role' => lang('Head Keeper, Savanna'),
        'bio' => lang('Has cared for the giraffe herd since Amani was born and gives the morning talk.')),
    array('name' => lang('Ayla Demir'), 'role' => lang('Curator of the Aquarium'),
        'bio' => lang('Grows the coral of the reef tank and leads the reef project with the marine lab.')),
    array('name' => lang('Jonas Weber'), 'role' => lang('Head of Education'),
        'bio' => lang('Plans the school workshops and answers every visit request.')),
);

// The zoo in four figures (the about page).
$figures = array(
    array('value' => '20', 'label' => lang('Habitats and an aquarium')),
    array('value' => '120', 'label' => lang('Species in our care')),
    array('value' => '14', 'label' => lang('Breeding programmes for endangered species')),
    array('value' => '35,000', 'label' => lang('School children a year')),
);

// ── The answers of the forms' pick lists ────────────────────────────────
// Each answer's value is its own words, so the e-mails and the lists read
// as the visitor chose.
$habitat_titles = array();
foreach ($habitats as $h) $habitat_titles[] = $h['title'];
$class_titles = array_values($animal_classes);
$status_titles = array();
foreach ($conservation as $c) $status_titles[] = $c['title'];
$age_titles = array_values($age_groups);
$visit_titles = array();
foreach ($visits as $v) $visit_titles[] = $v['title'];
// The visit request's visit: each kind by title, or something else.
$visit_choices = array_merge($visit_titles, array(lang('Something else')));
$group_kinds = array(lang('School or nursery'), lang('Club or association'), lang('Company'), lang('Family or friends'), lang('Care home or day centre'));
$visitor_counts = array(lang('Up to 10'), lang('11 to 20'), lang('21 to 30'), lang('31 to 60'), lang('More than 60'));
$workshop_choices = array(
    array(lang('Yes, with a workshop'), lang('Yes, with a workshop')),
    array(lang('No, the park only'), lang('No, the park only')),
);
$event_kind_titles = array_values($event_kinds);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a visit request, set by the zoo in the panel.
$visit_statuses = array(lang('Request received'), lang('Confirmed'), lang('Waiting list'), lang('Cancelled'));

// The words of a sample record's pick list answers.
$animal_habitat = function ($key) use ($animals, $habitats) {
    return $habitats[$animals[$key]['habitat']]['title'];
};
$animal_class = function ($key) use ($animals, $animal_classes) {
    return $animal_classes[$animals[$key]['class']];
};
$animal_status = function ($key) use ($animals, $conservation) {
    return $conservation[$animals[$key]['status']]['title'];
};
// The place of an animal as the schedule and the map say it.
$animal_place = function ($key) use ($animals, $habitats) {
    return sprintf(lang('Habitat %1$s · %2$s'), $animals[$key]['number'], $habitats[$animals[$key]['habitat']]['title']);
};

// The query string of a link that opens a list with a filter set
// (?habitat=, ?class=) or a form with a choice made (?visit=, ?group=,
// ?age=): the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// ── Page helpers ────────────────────────────────────────────────────────

// A page: the account bar, the header, the page's own content and the
// shared footer, with the visit band (hours, map, tickets) above the
// footer unless the page is a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('visit_band');
    $children[] = $shared('footer');
    return $root($children);
};

// The top of the inner pages: where the page sits, its title and one
// sentence on a soft green ground, and whatever the page adds under them.
$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'pzo-hero py-4 py-lg-5 bg-success-subtle', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'pzo-display display-5 mb-2'),
            $para($lead, 'lead mb-0'),
        ), $more)),
    ));
};

// The heading over a section: the title with a leaf mark, one sentence
// under it, and a pill link to the rest on the right. No rule and no small
// line above: the zoo's sections are told apart by their grounds.
$section_header = function ($title, $lead = '', $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $text = array($heading('h2', $title, 'pzo-display h2 mb-1'));
    if ($lead !== '') $text[] = $para($lead, 'text-body-secondary mb-0');
    $children = array($el('div', 'd-flex gap-3', lang('Section Title'), array(
        $icon('bi-leaf', 'pzo-mark fs-3 text-success'),
        $el('div', '', lang('Text'), $text),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'btn btn-sm btn-outline-success rounded-pill d-inline-flex align-items-center gap-1 text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-3 mb-4', lang('Section Header'), $children);
};

// A habitat's number as the map and the signs show it: a round pin in the
// zone's colour (the pg-zoo-pin rule of the zoo block family).
$pin = function ($number, $habitat) use ($span, $habitats) {
    return $span((string)$number, 'pg-zoo-pin ' . $habitats[$habitat]['pin'], lang('Habitat Number'));
};

// An icon in a soft round tile, the way the visit kinds and the ways to
// the zoo are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// A numbered step of a short how-to.
$step = function ($n, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'd-flex gap-3 mb-4', lang('Step'), array(
        $span((string)$n, 'badge rounded-pill text-bg-success fs-6 align-self-start', lang('Step Number')),
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
            $icon('bi-check2', 'text-success'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', $class, lang('Check List'), $children);
};

// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answer, $topics = '', $parent = 'zoo-faq') use ($el, $para, $attr, $searchable) {
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
