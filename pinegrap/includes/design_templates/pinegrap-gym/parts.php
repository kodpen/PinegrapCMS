<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Gym", part 1 of its files: the node builders, the gym's
 * own data (the days and opening hours, what a class trains, the levels,
 * the studios, the group classes, the trainers, the week's timetable, the
 * membership plans and their prices, the facilities, what members say and
 * the answers of the forms' pick lists) and the parts every page repeats
 * (account bar, header, footer, join band, class, trainer, plan and member
 * cards, the timetable's days, page shells, section headers, form fields,
 * the comment thread, the staff bar and the blog parts).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-gym.php puts them together.
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
// every opening until the gym puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-gym-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/fde8e8/7f1d1d?text=' . rawurlencode($initials !== '' ? $initials : 'G');
};


// ── The gym: its classes, its week, its trainers and its plans ─────────

// The gym itself. The about page tells its story; the footer, the join
// band and the contact lines use the address, phone and opening hours.
$gym = array(
    'tagline' => lang('A neighbourhood gym with group classes every day, coaches who know your name and room to train your way'),
    'story'   => lang('Two coaches opened the gym in an old print works to make training feel less like a chore and more like a team. Today it has a weights floor, three studios and a timetable of classes from early morning to late evening, and the same welcome for a first visit as for the hundredth.'),
    'address' => lang('Foundry Street 8, Riverside'),
    'region'  => lang('Five minutes from the river station, with bike racks at the door'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'hello@example.com',
);

// The days of the week, in the order the timetable lists them, with the
// gym's opening hours on each. A class starts no earlier than the opening
// and ends before the closing (a test holds the timetable to these).
$days = array(
    'mon' => array('title' => lang('Monday'), 'open' => '06:00', 'close' => '23:00'),
    'tue' => array('title' => lang('Tuesday'), 'open' => '06:00', 'close' => '23:00'),
    'wed' => array('title' => lang('Wednesday'), 'open' => '06:00', 'close' => '23:00'),
    'thu' => array('title' => lang('Thursday'), 'open' => '06:00', 'close' => '23:00'),
    'fri' => array('title' => lang('Friday'), 'open' => '06:00', 'close' => '23:00'),
    'sat' => array('title' => lang('Saturday'), 'open' => '08:00', 'close' => '20:00'),
    'sun' => array('title' => lang('Sunday'), 'open' => '09:00', 'close' => '18:00'),
);
// The opening hours in three lines, the way the footer and the contact
// page show them.
$opening_hours = array(
    array('days' => lang('Monday to Friday'), 'hours' => '06:00–23:00'),
    array('days' => lang('Saturday'), 'hours' => '08:00–20:00'),
    array('days' => lang('Sunday'), 'hours' => '09:00–18:00'),
);

// What a class trains, the trainer list's filter (?focus=) and the class
// list's grouping. Each answer's value is its own words, so a filter link
// reads as it shows.
$focuses = array(
    'strength' => array('icon' => 'bi-trophy', 'title' => lang('Strength Training'),
        'text' => lang('Barbells, kettlebells and your own body weight, coached so every lift is safe and a little heavier each month.')),
    'cardio'   => array('icon' => 'bi-heart-pulse', 'title' => lang('Cardio and Conditioning'),
        'text' => lang('Intervals, cycling and boxing rounds that leave you out of breath and coming back for more.')),
    'mind'     => array('icon' => 'bi-flower3', 'title' => lang('Mind and Body'),
        'text' => lang('Yoga and Pilates for mobility, balance and a strong middle, at a calmer pace.')),
);

// The levels a class is open to.
$levels = array(
    'all'          => lang('Every level'),
    'beginner'     => lang('Good for beginners'),
    'intermediate' => lang('Some experience'),
    'advanced'     => lang('Experienced members'),
);

// The studios a class is held in.
$studios = array(
    'main'  => lang('Main Studio'),
    'cycle' => lang('Cycle Studio'),
    'calm'  => lang('Calm Studio'),
    'floor' => lang('Weights Floor'),
);

// The group classes. 'minutes' is how long a class lasts, 'intensity' how
// hard it is from 1 to 5; the timetable, the class list and the booking
// request read them.
$classes = array(
    'hiit'      => array('name' => lang('HIIT Burn'), 'focus' => 'cardio', 'level' => 'all', 'minutes' => 45, 'intensity' => 5, 'icon' => 'bi-fire',
        'summary' => lang('Short bursts of hard work and short rests: forty-five minutes that raise your heart rate and your fitness.')),
    'cycle'     => array('name' => lang('Rhythm Cycle'), 'focus' => 'cardio', 'level' => 'all', 'minutes' => 45, 'intensity' => 4, 'icon' => 'bi-bicycle',
        'summary' => lang('Indoor cycling to the beat in the dark studio, with climbs, sprints and a playlist that carries you through.')),
    'boxing'    => array('name' => lang('Boxing Fit'), 'focus' => 'cardio', 'level' => 'beginner', 'minutes' => 50, 'intensity' => 4, 'icon' => 'bi-lightning',
        'summary' => lang('Pad work, footwork and rounds on the bags: the boxer\'s training without the sparring.')),
    'barbell'   => array('name' => lang('Barbell Strength'), 'focus' => 'strength', 'level' => 'intermediate', 'minutes' => 60, 'intensity' => 4, 'icon' => 'bi-trophy',
        'summary' => lang('Squat, press and deadlift in small groups, with a coach watching every rep and a plan that grows with you.')),
    'kettlebell' => array('name' => lang('Kettlebell Circuit'), 'focus' => 'strength', 'level' => 'all', 'minutes' => 45, 'intensity' => 3, 'icon' => 'bi-arrow-repeat',
        'summary' => lang('Swings, carries and presses in a circuit that builds strength and stamina at the same time.')),
    'yoga'      => array('name' => lang('Flow Yoga'), 'focus' => 'mind', 'level' => 'all', 'minutes' => 60, 'intensity' => 2, 'icon' => 'bi-flower3',
        'summary' => lang('Moving from pose to pose with the breath, for mobility, balance and a quieter head.')),
    'pilates'   => array('name' => lang('Mat Pilates'), 'focus' => 'mind', 'level' => 'beginner', 'minutes' => 45, 'intensity' => 2, 'icon' => 'bi-circle',
        'summary' => lang('Slow, precise work for a strong middle and a straighter back, on the mat with small equipment.')),
);

// The trainers: the records the Trainers form starts with. The trainer
// list, the trainer page and the home read them; 'classes' are the classes
// each one teaches in the timetable, 'years' how long they have coached.
$trainers = array(
    'deniz'  => array('name' => lang('Deniz Kaya'), 'focus' => 'strength', 'years' => 9, 'classes' => array('barbell', 'kettlebell'),
        'role'    => lang('Head Coach'),
        'summary' => lang('A former weightlifter who teaches the big lifts with patience and a very good eye for form.'),
        'bio'     => lang('Deniz competed in weightlifting for ten years before opening the gym with Selin. She runs the strength classes and writes the programmes on the weights floor, and believes that anyone can learn to lift well with good coaching.')),
    'selin'  => array('name' => lang('Selin Aydın'), 'focus' => 'mind', 'years' => 11, 'classes' => array('yoga', 'pilates'),
        'role'    => lang('Yoga and Pilates Teacher'),
        'summary' => lang('Calm, precise and funny: her classes are where members find the mobility they lost at a desk.'),
        'bio'     => lang('Selin trained as a physiotherapist before she began teaching yoga and Pilates. Her classes are slow and exact, and she gives every member a way to make a pose work for their own body.')),
    'marcus' => array('name' => lang('Marcus Reed'), 'focus' => 'cardio', 'years' => 6, 'classes' => array('hiit', 'boxing'),
        'role'    => lang('Conditioning Coach'),
        'summary' => lang('Loud music, louder encouragement: Marcus makes the hardest forty-five minutes of the week feel like a party.'),
        'bio'     => lang('Marcus boxed as an amateur and coached football teams before he came to the gym. He leads the HIIT and boxing classes and builds every session so a beginner and a regular can work side by side.')),
    'ayse'   => array('name' => lang('Ayşe Demir'), 'focus' => 'cardio', 'years' => 5, 'classes' => array('cycle', 'hiit'),
        'role'    => lang('Cycle Instructor'),
        'summary' => lang('Ayşe builds every ride around a playlist, and nobody leaves her studio with dry hair.'),
        'bio'     => lang('Ayşe raced on the road for years and now teaches the cycle classes, mixing climbs and sprints to the beat. Outside the studio she plans the members\' weekend rides along the river.')),
);

// The week of group classes: day, start, class, trainer and studio. The
// timetable page draws it day by day; each class opens the booking request
// with the class, the day and the time chosen.
$timetable = array(
    array('day' => 'mon', 'time' => '07:00', 'class' => 'hiit', 'trainer' => 'marcus', 'studio' => 'main'),
    array('day' => 'mon', 'time' => '12:30', 'class' => 'pilates', 'trainer' => 'selin', 'studio' => 'calm'),
    array('day' => 'mon', 'time' => '18:30', 'class' => 'barbell', 'trainer' => 'deniz', 'studio' => 'floor'),
    array('day' => 'mon', 'time' => '19:30', 'class' => 'cycle', 'trainer' => 'ayse', 'studio' => 'cycle'),
    array('day' => 'tue', 'time' => '07:00', 'class' => 'cycle', 'trainer' => 'ayse', 'studio' => 'cycle'),
    array('day' => 'tue', 'time' => '18:00', 'class' => 'boxing', 'trainer' => 'marcus', 'studio' => 'main'),
    array('day' => 'tue', 'time' => '19:30', 'class' => 'yoga', 'trainer' => 'selin', 'studio' => 'calm'),
    array('day' => 'wed', 'time' => '07:00', 'class' => 'kettlebell', 'trainer' => 'deniz', 'studio' => 'floor'),
    array('day' => 'wed', 'time' => '12:30', 'class' => 'hiit', 'trainer' => 'ayse', 'studio' => 'main'),
    array('day' => 'wed', 'time' => '18:30', 'class' => 'pilates', 'trainer' => 'selin', 'studio' => 'calm'),
    array('day' => 'wed', 'time' => '19:30', 'class' => 'boxing', 'trainer' => 'marcus', 'studio' => 'main'),
    array('day' => 'thu', 'time' => '07:00', 'class' => 'yoga', 'trainer' => 'selin', 'studio' => 'calm'),
    array('day' => 'thu', 'time' => '18:00', 'class' => 'barbell', 'trainer' => 'deniz', 'studio' => 'floor'),
    array('day' => 'thu', 'time' => '19:30', 'class' => 'cycle', 'trainer' => 'ayse', 'studio' => 'cycle'),
    array('day' => 'fri', 'time' => '07:00', 'class' => 'hiit', 'trainer' => 'marcus', 'studio' => 'main'),
    array('day' => 'fri', 'time' => '18:00', 'class' => 'kettlebell', 'trainer' => 'deniz', 'studio' => 'floor'),
    array('day' => 'sat', 'time' => '09:00', 'class' => 'cycle', 'trainer' => 'ayse', 'studio' => 'cycle'),
    array('day' => 'sat', 'time' => '10:30', 'class' => 'barbell', 'trainer' => 'deniz', 'studio' => 'floor'),
    array('day' => 'sat', 'time' => '12:00', 'class' => 'boxing', 'trainer' => 'marcus', 'studio' => 'main'),
    array('day' => 'sun', 'time' => '10:00', 'class' => 'yoga', 'trainer' => 'selin', 'studio' => 'calm'),
    array('day' => 'sun', 'time' => '11:30', 'class' => 'pilates', 'trainer' => 'selin', 'studio' => 'calm'),
);

// The membership plans, with the price of a month per currency. The plans
// page and the home show them; with the shop each is a product that
// renews the buyer's membership for 'days'.
$plans = array(
    'offpeak'   => array('sku' => 'GYM-OFFPEAK', 'icon' => 'bi-sun', 'title' => lang('Off-Peak'), 'days' => 30, 'featured' => false,
        'price'  => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1250),
        'text'   => lang('For those who train while the city works: the gym and the classes on weekdays until 16:00 and all weekend.'),
        'points' => array(lang('Weights floor and studios at off-peak times'), lang('Group classes at off-peak times'), lang('A welcome session with a coach'))),
    'unlimited' => array('sku' => 'GYM-UNLIMITED', 'icon' => 'bi-infinity', 'title' => lang('Unlimited'), 'days' => 30, 'featured' => true,
        'price'  => array('USD' => 55, 'EUR' => 50, 'GBP' => 44, 'TRY' => 1950),
        'text'   => lang('Every hour the gym is open and every class on the timetable, as often as you like.'),
        'points' => array(lang('Weights floor and studios at any time'), lang('Every group class'), lang('A welcome session with a coach'), lang('The members\' workout library'))),
    'premium'   => array('sku' => 'GYM-PREMIUM', 'icon' => 'bi-gem', 'title' => lang('Unlimited Plus'), 'days' => 30, 'featured' => false,
        'price'  => array('USD' => 95, 'EUR' => 88, 'GBP' => 76, 'TRY' => 3400),
        'text'   => lang('Everything in Unlimited, with two personal training sessions a month and a programme written for you.'),
        'points' => array(lang('Everything in Unlimited'), lang('Two personal training sessions a month'), lang('A programme written for you'), lang('A towel at every visit'))),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A plan therefore shows one amount wherever it appears.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A plan's "… a month" words.
$plan_price = function ($key) use ($plans, $money) {
    return sprintf(lang('%s a month'), $money($plans[$key]['price']));
};

// What the gym has beside the classes (the facilities page and the home).
$facilities = array(
    'floor'   => array('icon' => 'bi-trophy', 'title' => lang('Weights Floor'),
        'text' => lang('Ten lifting platforms, racks, dumbbells up to 50 kg and a turf lane for sleds and carries.')),
    'studios' => array('icon' => 'bi-music-note-beamed', 'title' => lang('Three Studios'),
        'text' => lang('A main studio for HIIT and boxing, a dark cycle studio with forty bikes and a calm studio for yoga and Pilates.')),
    'cardio'  => array('icon' => 'bi-activity', 'title' => lang('Cardio Zone'),
        'text' => lang('Rowers, air bikes, ski machines and treadmills, with a view of the river from the first floor.')),
    'changing' => array('icon' => 'bi-droplet', 'title' => lang('Changing Rooms'),
        'text' => lang('Showers with hot water at every hour, lockers with your own code, hair dryers and towels for hire.')),
    'recovery' => array('icon' => 'bi-thermometer-sun', 'title' => lang('Sauna and Recovery'),
        'text' => lang('A sauna, foam rollers and a quiet corner for stretching after the hard work.')),
    'cafe'    => array('icon' => 'bi-cup-straw', 'title' => lang('Juice Bar'),
        'text' => lang('Smoothies, coffee and protein snacks at the front desk, for before and after training.')),
);

// The gym in four figures (the home and about pages).
$figures = array(
    array('value' => '20+', 'label' => lang('Classes every week')),
    array('value' => '4', 'label' => lang('Coaches on the team')),
    array('value' => '3', 'label' => lang('Studios and a weights floor')),
    array('value' => '17', 'label' => lang('Hours open on weekdays')),
);

// What members say, each about one of the classes.
$testimonials = array(
    array('class' => 'barbell', 'name' => lang('Ece Yılmaz'), 'role' => lang('Member, comes to Barbell Strength'),
        'quote' => lang('I had never touched a barbell. Six months later I deadlift my own body weight, and the coaches cheered louder than I did.')),
    array('class' => 'cycle', 'name' => lang('Tom Fletcher'), 'role' => lang('Member, comes to Rhythm Cycle'),
        'quote' => lang('The seven o\'clock ride is the best start to my day. The music, the dark room and the people keep me coming back.')),
    array('class' => 'yoga', 'name' => lang('Zeynep Arslan'), 'role' => lang('Member, comes to Flow Yoga'),
        'quote' => lang('My back stopped aching after a month of Selin\'s classes. It is the hour of the week that belongs to me.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the member chose.
$focus_titles = array();
foreach ($focuses as $f) $focus_titles[] = $f['title'];
$class_names = array();
foreach ($classes as $c) $class_names[] = $c['name'];
$day_titles = array();
foreach ($days as $d) $day_titles[] = $d['title'];
// Every start time on the timetable, in order: the booking request's Time.
$time_choices = array();
foreach ($timetable as $s) $time_choices[$s['time']] = $s['time'];
ksort($time_choices);
$time_choices = array_values($time_choices);
$trainer_names = array();
foreach ($trainers as $t) $trainer_names[] = $t['name'];
$plan_choices = array();
foreach ($plans as $p) $plan_choices[] = $p['title'];
// The join request offers a free trial pass beside the plans.
$join_choices = array_merge(array(lang('Free trial pass')), $plan_choices);
$goal_choices = array(lang('Get stronger'), lang('Lose weight'), lang('Get fitter'), lang('Move better and feel less stiff'), lang('Train for an event'), lang('Something else'));
$experience_choices = array(
    array(lang('New to training'), lang('New to training')),
    array(lang('Train now and then'), lang('Train now and then')),
    array(lang('Train every week'), lang('Train every week')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of a class booking request, set by the front desk in the panel.
$booking_statuses = array(lang('Request received'), lang('Place confirmed'), lang('Class is full'), lang('Attended'), lang('Cancelled'));
// The states of a join request, set by the front desk in the panel.
$join_statuses = array(lang('Request received'), lang('Trial visit booked'), lang('Membership started'), lang('Declined'));

// The query string of a link that opens a list with a filter set
// (?focus=) or a form with a choice made (?class=, ?day=, ?time=, ?plan=):
// the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the join band above the footer unless the page is
// a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('join_band');
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
            $heading('h1', $title, 'pgy-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pgy-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pgy-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the facilities are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what members say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'gy-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the free trial pass and the way to ask for
// it. A shared component.
$notice = function () use ($el, $icon, $span, $link, $query) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-ticket-perforated', 'text-primary'),
        $span(lang('Your first visit is on us: a free trial pass for any class.'), '', lang('Text')),
        $link(lang('Get a pass'), '{{page:join}}?' . $query(array('plan' => lang('Free trial pass'))), 'link-primary fw-semibold'),
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

// The header: the gym's name, the menu and the way to book a class. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page. The memberships the shop sells
// are linked from the shop pages only, so the header stands on a site
// without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('classes', lang('Group Classes')),
        array('timetable', lang('Class Timetable')),
        array('trainers', lang('Our Trainers')),
        array('plans', lang('Membership Plans')),
        array('book_online', lang('Personal Training')),
        array('facilities', lang('The Gym Floor')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-lightning-charge-fill', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pgy-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#gy-nav'),
                    $attr('aria-controls', 'gy-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-plus'),
                            $span(lang('Book a Class'), '', lang('Link Text')),
                        ), array('href' => '{{page:class_booking}}')),
                    )),
                ), array('id' => 'gy-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the gym in a sentence and its profiles, the
// training by focus, the members' pages, the gym's pages and how to reach
// it with the opening hours; at the bottom the year and the site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $focuses, $gym, $opening_hours, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $train_links = array(
        $link(lang('Group Classes'), '{{page:classes}}', $plain),
        $link(lang('Class Timetable'), '{{page:timetable}}', $plain),
        $link(lang('Our Trainers'), '{{page:trainers}}', $plain),
    );
    foreach ($focuses as $f) $train_links[] = $link($f['title'], '{{page:trainers}}?' . $query(array('focus' => $f['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };
    $hours = array();
    foreach ($opening_hours as $h) {
        $hours[] = $el('span', 'd-block', lang('Opening Hours'), array(
            $span($h['days'] . ': ', '', lang('Days')),
            $span($h['hours'], 'pgy-price', lang('Hours')),
        ));
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Gym'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-lightning-charge-fill', 'text-primary'),
                        $span('{{site_name}}', 'pgy-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($gym['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-youtube', 'YouTube'),
                        $social('bi-tiktok', 'TikTok'),
                        $social('bi-strava', 'Strava'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Training Links'), array(
                    $heading('h2', lang('Train With Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($train_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Members'), array(
                    $heading('h2', lang('For Members'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book a Class'), '{{page:class_booking}}', $plain),
                        $link(lang('My Class Bookings'), '{{page:my_bookings}}', $plain),
                        $link(lang('Join the Gym'), '{{page:join}}', $plain),
                        $link(lang('Membership Plans'), '{{page:plans}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Gym'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('The Gym Floor'), '{{page:facilities}}', $plain),
                        $link(lang('Personal Training'), '{{page:book_online}}', $plain),
                        $link(lang('Training Notes'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Visit Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($gym['address'], '{{page:location}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($gym['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $gym['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($gym['email'], 'mailto:' . $gym['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-clock', $el('div', '', lang('Opening Hours'), $hours), lang('Opening Hours')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Train hard, rest well, come back stronger.'), 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The join band: a full-width band in the palette's colour with the free
// trial pass and the plans. Shared, so every page that ends with it carries
// the same offer.
$join_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $query) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Your first class is on us'), 'pgy-display h2 mb-2'),
                    $para(lang('Ask for a free trial pass, try any class on the timetable and meet the coaches before you choose a plan.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Get a Free Pass'), '{{page:join}}?' . $query(array('plan' => lang('Free trial pass'))), 'btn btn-light btn-lg px-4'),
                        $link(lang('See the Plans'), '{{page:plans}}', 'btn btn-outline-light btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// How long a class lasts, who it is for and how hard it is, in one line.
$class_facts = function ($key, $class = 'd-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $classes, $levels) {
    $c = $classes[$key];
    $fact = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array($icon($icon_name, 'text-primary'), $span($text, '', lang('Value'))));
    };
    return $el('ul', 'list-unstyled ' . $class, lang('Class Facts'), array(
        $fact('bi-stopwatch', sprintf(lang('%d min'), $c['minutes']), lang('Length')),
        $fact('bi-person-check', $levels[$c['level']], lang('Level')),
        $fact('bi-speedometer2', sprintf(lang('Intensity %d of 5'), $c['intensity']), lang('Intensity')),
    ));
};

// A class's card (the class list and the home): the icon, the focus, the
// name, a line, the facts and a link that opens the booking request with
// the class chosen.
$class_card = function ($key) use ($el, $heading, $para, $span, $link, $col, $icon_tile, $class_facts, $classes, $focuses, $query) {
    $c = $classes[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm pgy-lift', lang('Class Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($c['icon'], 'align-self-start mb-3'),
                $span($focuses[$c['focus']]['title'], 'pgy-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Focus')),
                $heading('h3', $c['name'], 'pgy-display h4 mb-2'),
                $para($c['summary'], 'small text-body-secondary mb-3'),
                $class_facts($key, 'd-flex flex-wrap gap-3 small text-body-secondary mb-4'),
                $link(lang('Book this class'), '{{page:class_booking}}?' . $query(array('class' => $c['name'])), 'btn btn-outline-primary mt-auto'),
            )),
        )),
    ), '6', '4');
};

// A trainer's card of the lists (the home and the trainer list): the
// photo, the focus, the name linking the trainer page, the role, the
// summary and the years of coaching. Every value is bound to the Trainers
// form's field of the same name; the sample fills the canvas.
$trainer_card = function ($sample_key = 'deniz') use ($el, $para, $span, $link, $image, $col, $icon, $bind, $trainers, $focuses, $portrait, $attr) {
    $t = $trainers[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pgy-lift', lang('Trainer Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($portrait($t['name'], 600), $t['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($focuses[$t['focus']]['title'], 'pgy-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Focus'), $bind('focus')),
                $el('h3', 'pgy-display h4 position-static mb-1', lang('Trainer Name'), array(
                    $link($t['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($t['role'], 'small fw-semibold mb-2', $bind('role')),
                $para($t['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('p', 'd-flex align-items-center gap-1 small text-body-secondary mt-auto mb-0', lang('Years of Coaching'), array(
                    $icon('bi-award', 'text-primary'),
                    $span((string)$t['years'], 'pgy-price', lang('Value'), $bind('years')),
                    $span(lang('years of coaching'), '', lang('Unit')),
                )),
            )),
        )),
    ), '6', '3');
};

// A plan's card (the plans page and the home): the name, the price of a
// month, a line, what it holds and a link that opens the join request
// with the plan chosen. The plan most members choose is marked.
$plan_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $check_list, $icon_tile, $plans, $plan_price, $query) {
    $p = $plans[$key];
    $top = array($icon_tile($p['icon']));
    if ($p['featured']) $top[] = $span(lang('Most chosen'), 'badge text-bg-primary', lang('Badge'));
    return $col(array(
        $el('article', 'card h-100 shadow-sm ' . ($p['featured'] ? 'border-primary border-2' : 'border-0'), lang('Plan Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-start justify-content-between gap-2 mb-3', lang('Plan Heading'), $top),
                $heading('h3', $p['title'], 'pgy-display h4 mb-1'),
                $para($plan_price($key), 'pgy-price h5 fw-bold mb-2'),
                $para($p['text'], 'small text-body-secondary mb-3'),
                $check_list($p['points'], 'list-unstyled small mb-4'),
                $link(lang('Join with this plan'), '{{page:join}}?' . $query(array('plan' => $p['title'])),
                    ($p['featured'] ? 'btn btn-primary' : 'btn btn-outline-primary') . ' mt-auto'),
            )),
        )),
    ), '', '4');
};

// What a member says: the quote, the stars, the member and the class.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Member Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Member'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// One class of the timetable: the start, the class, the trainer and the
// studio, and a link that opens the booking request with the class, the
// day and the time chosen.
$session_item = function ($s) use ($el, $span, $link, $classes, $trainers, $studios, $days, $query) {
    $c = $classes[$s['class']];
    return $el('li', 'list-group-item d-flex align-items-start gap-3 py-3', lang('Timetable Class'), array(
        $span($s['time'], 'pgy-price fw-bold text-primary', lang('Start Time')),
        $el('div', 'flex-grow-1', lang('Class Details'), array(
            $span($c['name'], 'd-block fw-semibold', lang('Class Name')),
            $span(sprintf(lang('%1$s · %2$s · %3$d min'), $trainers[$s['trainer']]['name'], $studios[$s['studio']], $c['minutes']),
                'd-block small text-body-secondary', lang('Trainer and Studio')),
        )),
        $link(lang('Book'), '{{page:class_booking}}?' . $query(array(
            'class' => $c['name'], 'day' => $days[$s['day']]['title'], 'time' => $s['time'],
        )), 'btn btn-sm btn-outline-primary text-nowrap'),
    ));
};

// A day of the timetable: its name and its classes in the order they start.
$timetable_day = function ($day) use ($el, $heading, $timetable, $days, $session_item) {
    $sessions = array();
    foreach ($timetable as $s) {
        if ($s['day'] === $day) $sessions[$s['time']] = $session_item($s);
    }
    ksort($sessions);
    return $el('section', 'card h-100 border-0 shadow-sm', $days[$day]['title'], array(
        $el('div', 'card-header bg-body border-0 pt-3', lang('Day Header'), array(
            $heading('h3', $days[$day]['title'], 'pgy-display h5 mb-0'),
        )),
        $el('ul', 'list-group list-group-flush', lang('Day Classes'), array_values($sessions)),
    ), array('id' => 'gy-day-' . $day));
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
                $item('staff_bookings', lang('Class Booking Requests')),
                $item('staff_joins', lang('Join Requests')),
                $item('trainer_new', lang('New Trainer')),
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
    return $agree($id, 'consent', lang('I would like to receive the gym\'s news, class changes and Training Notes posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A join
// request may carry a doctor's note or a programme, so the default accepts
// PDFs, office files, text and pictures; one that takes only pictures is a
// collection's image kind (a trainer's photo).
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
                    $link(lang('Five warm-up moves to do before every class'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

