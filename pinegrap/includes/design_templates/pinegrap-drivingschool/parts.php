<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Driving School", part 1 of its files: the node
 * builders, the school's own data (the licence classes, the course
 * formats, the sample courses with their prices, the instructors, the
 * theory test topics, the practical test day, the branches, why learners
 * choose the school, its figures, the sample pass stories and the answers
 * of the forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, enrol band, licence plate, licence, course, instructor,
 * story and post cards, course facts, page shells, section headers, form
 * fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-drivingschool.php puts them together.
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
// every opening until the school puts its own photos in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-drivingschool-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/451a03/fef3c7?text=' . rawurlencode($initials !== '' ? $initials : 'D');
};

// ── The school: its licences, courses and people ────────────────────────

// The school itself. The about page tells its story; the footer, the
// enrol band and the contact lines use the address, phone and hours.
$school = array(
    'tagline' => lang('Patient instructors, dual-control cars and a plan that gets you to your test ready'),
    'story'   => lang('Two instructors started the school with one dual-control car and a whiteboard in a borrowed room. Today our instructors teach cars, motorcycles, light trucks and trailers from two branches, and every learner still starts the same way: an assessment drive, a written lesson plan and one instructor who stays with them until the test.'),
    'address' => lang('Station Road 24, First Floor'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'lessons@example.com',
    'hours'   => lang('Monday to Saturday, 8:00 to 20:00'),
    'note'    => lang('An approved driving school: theory classes, practical lessons and test preparation'),
);

// The licence classes. Each has its own page (licence_<key>); the course
// list filters on its title (?licence=) and the lesson request opens with
// it chosen.
$licences = array(
    'car' => array('code' => 'B', 'icon' => 'bi-car-front', 'title' => lang('Car'),
        'summary' => lang('The licence most learners start with: cars and small vans with a manual gearbox.'),
        'text'    => lang('A class B licence lets you drive a car or a small van with up to eight passenger seats, manual or automatic. Most learners need between twenty and thirty lessons, and we teach town, country and motorway driving before the test.'),
        'age'     => lang('From 18'), 'lessons' => lang('Twenty to thirty lessons'),
        'points'  => array(lang('Town, country and motorway driving'), lang('Parking and manoeuvres'), lang('Night driving and bad weather'), lang('A mock test before the real one'))),
    'automatic' => array('code' => 'B', 'icon' => 'bi-speedometer2', 'title' => lang('Automatic Car'),
        'summary' => lang('The same car licence learned in an automatic, for a quicker start and fewer lessons.'),
        'text'    => lang('Without a clutch to master you can give the road all your attention, so many learners pass with fewer lessons. A licence passed in an automatic lets you drive automatic cars only.'),
        'age'     => lang('From 18'), 'lessons' => lang('Fifteen to twenty-five lessons'),
        'points'  => array(lang('No clutch and no gear changes'), lang('Hybrid and electric cars'), lang('Town and motorway driving'), lang('A mock test before the real one'))),
    'motorcycle' => array('code' => 'A2', 'icon' => 'bi-bicycle', 'title' => lang('Motorcycle'),
        'summary' => lang('Medium motorcycles, with the off-road skills and the road ride the test asks for.'),
        'text'    => lang('We start on our closed training ground with the balance, braking and slow riding skills, then ride out on the road with an instructor on a second motorcycle and a radio in your helmet.'),
        'age'     => lang('From 20'), 'lessons' => lang('Ten to fifteen lessons'),
        'points'  => array(lang('Helmet, jacket and gloves lent for lessons'), lang('Closed training ground'), lang('Radio contact with your instructor'), lang('Road riding in traffic'))),
    'light_truck' => array('code' => 'C1', 'icon' => 'bi-truck', 'title' => lang('Light Truck'),
        'summary' => lang('Vehicles up to seven and a half tonnes, for delivery and trade drivers.'),
        'text'    => lang('For drivers who already hold a car licence and need a larger vehicle for work. We cover the vehicle checks, the extra space a truck needs and loading safely, on our own light truck.'),
        'age'     => lang('From 18, with a car licence'), 'lessons' => lang('Eight to twelve lessons'),
        'points'  => array(lang('Vehicle safety checks'), lang('Reversing and coupling bays'), lang('Loads and weights'), lang('Driving in town with a larger vehicle'))),
    'trailer' => array('code' => 'BE', 'icon' => 'bi-truck-flatbed', 'title' => lang('Car with Trailer'),
        'summary' => lang('Tow a heavier trailer or a caravan behind your car, legally and calmly.'),
        'text'    => lang('Hitching, reversing a trailer round a corner and the checks before every trip: we teach them on our own car and trailer, so you take the test in the vehicle you practised with.'),
        'age'     => lang('From 18, with a car licence'), 'lessons' => lang('Four to eight lessons'),
        'points'  => array(lang('Coupling and uncoupling'), lang('Reversing a trailer'), lang('Checks before every trip'), lang('Towing on the open road'))),
);

// How a course is taught: the course list's second filter.
$formats = array(
    'standard'   => lang('Standard Course'),
    'intensive'  => lang('Intensive Course'),
    'refresher'  => lang('Refresher Lessons'),
    'theory'     => lang('Theory Only'),
    'test_prep'  => lang('Test Preparation'),
);
$gearboxes = array('manual' => lang('Manual'), 'automatic' => lang('Automatic'));

// A price written as the site writes money: the figure for the site's base
// currency, USD's figure for a currency not listed, with the base
// currency's symbol (the course prices). The school takes the payment at
// its desk; nothing is sold online.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// The sample courses: the records the Courses form starts with. Lessons
// and hours are words, the price is words in the site's currency; what is
// included is one item a line.
$courses = array(
    'car_standard' => array('name' => lang('Car Licence, Standard'), 'licence' => 'car', 'format' => 'standard', 'gearbox' => 'manual',
        'price' => array('USD' => 1200, 'EUR' => 1100, 'GBP' => 950, 'TRY' => 42000),
        'lessons' => lang('25 lessons'), 'hours' => lang('About ten weeks'),
        'summary' => lang('Two lessons a week with the same instructor, the theory classes and a mock test, at your own pace.'),
        'included' => array(lang('Assessment drive'), lang('25 practical lessons of one hour'), lang('Theory classes and the practice app'), lang('A mock test'), lang('The car for the test')),
        'featured' => true),
    'car_intensive' => array('name' => lang('Car Licence, Intensive'), 'licence' => 'car', 'format' => 'intensive', 'gearbox' => 'manual',
        'price' => array('USD' => 1450, 'EUR' => 1330, 'GBP' => 1150, 'TRY' => 51000),
        'lessons' => lang('30 hours of driving'), 'hours' => lang('Three weeks'),
        'summary' => lang('Two hours of driving every weekday for three weeks, for learners who have already passed the theory test.'),
        'included' => array(lang('Assessment drive'), lang('30 hours of driving'), lang('A mock test'), lang('The car for the test')),
        'featured' => true),
    'automatic_standard' => array('name' => lang('Automatic Car, Standard'), 'licence' => 'automatic', 'format' => 'standard', 'gearbox' => 'automatic',
        'price' => array('USD' => 1050, 'EUR' => 960, 'GBP' => 830, 'TRY' => 37000),
        'lessons' => lang('20 lessons'), 'hours' => lang('About eight weeks'),
        'summary' => lang('Learn in a hybrid automatic: fewer lessons, the theory classes and a mock test.'),
        'included' => array(lang('Assessment drive'), lang('20 practical lessons of one hour'), lang('Theory classes and the practice app'), lang('A mock test'), lang('The car for the test')),
        'featured' => true),
    'motorcycle_standard' => array('name' => lang('Motorcycle Licence'), 'licence' => 'motorcycle', 'format' => 'standard', 'gearbox' => 'manual',
        'price' => array('USD' => 900, 'EUR' => 830, 'GBP' => 720, 'TRY' => 32000),
        'lessons' => lang('12 lessons'), 'hours' => lang('About six weeks'),
        'summary' => lang('Training ground skills first, then road rides with an instructor and a radio in your helmet.'),
        'included' => array(lang('Training ground sessions'), lang('12 road lessons'), lang('Helmet, jacket and gloves for lessons'), lang('The motorcycle for the test')),
        'featured' => false),
    'refresher' => array('name' => lang('Confidence Refresher'), 'licence' => 'car', 'format' => 'refresher', 'gearbox' => 'manual',
        'price' => array('USD' => 300, 'EUR' => 280, 'GBP' => 240, 'TRY' => 10500),
        'lessons' => lang('5 lessons'), 'hours' => lang('Two weeks'),
        'summary' => lang('For licence holders who have not driven for years or feel unsure on the motorway.'),
        'included' => array(lang('A talk about what worries you'), lang('5 lessons on the roads you choose'), lang('Motorway and parking practice')),
        'featured' => false),
    'trailer_prep' => array('name' => lang('Trailer Test Preparation'), 'licence' => 'trailer', 'format' => 'test_prep', 'gearbox' => 'manual',
        'price' => array('USD' => 550, 'EUR' => 500, 'GBP' => 430, 'TRY' => 19500),
        'lessons' => lang('6 lessons'), 'hours' => lang('Two weeks'),
        'summary' => lang('Coupling, reversing and towing on our own car and trailer, ending with the test in the same vehicle.'),
        'included' => array(lang('6 lessons of ninety minutes'), lang('Coupling and reversing practice'), lang('The car and trailer for the test')),
        'featured' => false),
);
// A course's price as the value of its Price field, and what it includes
// as the value of its Included field: one item a line.
$price_text = function ($key) use ($courses, $money) {
    return $money($courses[$key]['price']);
};
$included_text = function ($key) use ($courses) {
    return implode("\n", $courses[$key]['included']);
};

// The instructors (the instructors page and the about page). Fixed data,
// not a collection: the team changes rarely. The lesson request offers
// each by name (?instructor=).
$instructors = array(
    array('name' => lang('Selin Arslan'), 'role' => lang('Founder and Senior Instructor'), 'licences' => array('car', 'automatic'),
        'bio' => lang('Has taught nervous learners for many years and still takes every learner\'s assessment drive in person.')),
    array('name' => lang('Mark Ellis'), 'role' => lang('Motorcycle Instructor'), 'licences' => array('motorcycle'),
        'bio' => lang('Rides in all weathers and teaches the slow riding skills most learners fear the most.')),
    array('name' => lang('Deniz Yılmaz'), 'role' => lang('Car and Automatic Instructor'), 'licences' => array('car', 'automatic'),
        'bio' => lang('Patient on the first lesson and strict on the mock test, so the real test holds no surprises.')),
    array('name' => lang('Ana Costa'), 'role' => lang('Theory Teacher'), 'licences' => array('car'),
        'bio' => lang('Runs the theory classes and turns the highway code into questions learners remember.')),
    array('name' => lang('Emre Koç'), 'role' => lang('Truck and Trailer Instructor'), 'licences' => array('light_truck', 'trailer'),
        'bio' => lang('Drove trucks for years before teaching, and can reverse a trailer into any bay.')),
);

// The topics of the theory test (the theory page and the home), each with
// the road sign that leads its card: a warning triangle, a speed-limit
// circle with its figure, or an information square.
$theory_topics = array(
    array('icon' => 'bi-sign-stop', 'sign' => 'warn', 'sign_icon' => 'bi-exclamation-lg', 'title' => lang('Road signs and markings'), 'text' => lang('What each sign and line asks of you, and which one wins when they disagree.')),
    array('icon' => 'bi-signpost-split', 'sign' => 'ban', 'mark' => '50', 'title' => lang('Rules of the road'), 'text' => lang('Right of way, speed limits, junctions, roundabouts and overtaking.')),
    array('icon' => 'bi-eye', 'sign' => 'warn', 'sign_icon' => 'bi-eye', 'title' => lang('Hazard awareness'), 'text' => lang('Seeing danger early: children, cyclists, parked cars and the weather.')),
    array('icon' => 'bi-heart-pulse', 'sign' => 'info', 'sign_icon' => 'bi-heart-pulse', 'title' => lang('First aid'), 'text' => lang('What to do at the scene of an accident until help arrives.')),
    array('icon' => 'bi-gear', 'sign' => 'info', 'sign_icon' => 'bi-gear', 'title' => lang('The vehicle'), 'text' => lang('The checks before a journey, tyres, lights, fluids and warning lamps.')),
    array('icon' => 'bi-tree', 'sign' => 'info', 'sign_icon' => 'bi-tree', 'title' => lang('Eco-safe driving'), 'text' => lang('Driving smoothly to save fuel and keep everyone around you safe.')),
);

// The practical test day, in order, with the time each part takes (the
// practical test page and the licence pages).
$test_day = array(
    array('time' => lang('1 hour before'), 'title' => lang('A warm-up lesson'), 'text' => lang('Your instructor picks you up an hour before the test for a last drive round the test routes.')),
    array('time' => lang('5 min'), 'title' => lang('Eyesight and vehicle questions'), 'text' => lang('The examiner checks your eyesight and asks how you would check the car before a trip.')),
    array('time' => lang('40 min'), 'title' => lang('The drive'), 'text' => lang('About forty minutes on town and country roads, with a manoeuvre and a stretch of independent driving.')),
    array('time' => lang('5 min'), 'title' => lang('The result'), 'text' => lang('The examiner tells you the result and goes through the faults, with your instructor beside you if you wish.')),
);

// The school's branches, the place a learner prefers to start from.
$branches = array(
    'station' => array('title' => lang('Station Road Branch'), 'address' => lang('Station Road 24, First Floor'),
        'text' => lang('The main office, the theory classroom and the car park where lessons start.')),
    'harbour' => array('title' => lang('Harbour Branch'), 'address' => lang('Harbour Street 3'),
        'text' => lang('A small office near the training ground, where motorcycle and trailer lessons start.')),
);

// Why learners choose the school (the home and the about page).
$why_us = array(
    array('icon' => 'bi-person-check', 'title' => lang('One instructor'),
        'text' => lang('The same instructor from your first lesson to your test day.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Dual-control cars'),
        'text' => lang('Modern cars with a second brake for the instructor, cleaned before every lesson.')),
    array('icon' => 'bi-house-door', 'title' => lang('Pick-up from home'),
        'text' => lang('We pick you up from home, school or work within our area.')),
    array('icon' => 'bi-cash-coin', 'title' => lang('A clear price'),
        'text' => lang('The course price includes the theory classes, the mock test and the car for the test.')),
);

// The school in four figures (the home and the about page).
$figures = array(
    array('value' => '3,000+', 'label' => lang('Learners taught')),
    array('value' => '87%', 'label' => lang('Pass at the first or second try')),
    array('value' => '9', 'label' => lang('Dual-control vehicles')),
    array('value' => '5', 'label' => lang('Licence classes')),
);

// The sample pass stories: the records the Pass Stories form starts with.
// The learner gives a first name only; the instructor is one of the team.
$stories = array(
    'zeynep' => array('name' => lang('Zeynep'), 'licence' => 'car', 'try' => 0, 'instructor' => 0, 'lessons' => lang('26 lessons'),
        'quote' => lang('I was scared of roundabouts on my first lesson. On my test day I took four of them without thinking twice.'), 'featured' => true),
    'daniel' => array('name' => lang('Daniel'), 'licence' => 'automatic', 'try' => 0, 'instructor' => 2, 'lessons' => lang('18 lessons'),
        'quote' => lang('Learning in an automatic let me think about the road instead of the pedals. I passed first time.'), 'featured' => true),
    'aylin' => array('name' => lang('Aylin'), 'licence' => 'motorcycle', 'try' => 1, 'instructor' => 1, 'lessons' => lang('14 lessons'),
        'quote' => lang('I failed the slow ride the first time. Two more sessions on the training ground and I passed with no faults.'), 'featured' => true),
    'tom' => array('name' => lang('Tom'), 'licence' => 'trailer', 'try' => 0, 'instructor' => 4, 'lessons' => lang('6 lessons'),
        'quote' => lang('Now I tow our caravan to the coast every summer, and I can reverse it onto the pitch.'), 'featured' => false),
    'merve' => array('name' => lang('Merve'), 'licence' => 'car', 'try' => 1, 'instructor' => 0, 'lessons' => lang('32 lessons'),
        'quote' => lang('My nerves got me on the first test. The mock tests helped me stay calm, and the second time I passed.'), 'featured' => false),
);
// The answers of the Pass Stories form's try list.
$try_choices = array(lang('First try'), lang('Second try'), lang('Third try or later'));

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the learner chose.
$licence_titles = array();
foreach ($licences as $l) $licence_titles[] = $l['title'];
$format_titles = array_values($formats);
$gearbox_titles = array_values($gearboxes);
$course_names = array();
foreach ($courses as $c) $course_names[] = $c['name'];
$enrolment_course_choices = array_merge($course_names, array(lang('Not sure yet')));
$instructor_names = array();
foreach ($instructors as $t) $instructor_names[] = $t['name'];
$instructor_choices = array_merge(array(lang('Any instructor')), $instructor_names);
$branch_titles = array();
foreach ($branches as $b) $branch_titles[] = $b['title'];
// When a learner would like the lessons, and how long one lesson runs.
$lesson_time_choices = array(lang('Weekday mornings'), lang('Weekday afternoons'), lang('Weekday evenings'), lang('Weekends'), lang('Any time'));
$lesson_lengths = array(lang('One hour'), lang('Ninety minutes'), lang('Two hours'));
// Where the learner stands with the theory test.
$theory_choices = array(lang('Not taken yet'), lang('Test booked'), lang('Passed'));
// The states of an enrolment and of a lesson request, set by the school in
// the panel.
$enrolment_statuses = array(lang('Enrolment received'), lang('Call booked'), lang('Enrolled'), lang('Waiting list'), lang('Declined'));
$lesson_statuses = array(lang('Request received'), lang('Confirmed'), lang('Time not available'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The query string of a link that opens a list with a filter set
// (?licence=, ?format=) or a form with a choice made (?course=,
// ?licence=, ?instructor=): the form's fields start with these values
// (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the enrol band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('enrol_band');
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
            $heading('h1', $title, 'pdr-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pdr-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pdr-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the services and why-us points are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what learners say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'drv-faq') use ($el, $para, $attr, $searchable) {
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

// The school's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pdr-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
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
                $item('course_new', lang('New Course')),
                $item('staff_enrolments', lang('Enrolments')),
                $item('staff_lesson_requests', lang('Lesson Requests')),
                $item('story_new', lang('New Pass Story')),
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
    return $agree($id, 'consent', lang('I would like to receive the school\'s news, test tips and course offers by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// enrolment takes a copy of a provisional licence or a medical
// certificate, so the default accepts PDFs, office files, text and
// pictures; one that takes only pictures is a collection's image kind (a
// course's photo, a learner's photo).
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
                    $link(lang('Five habits that help you pass first time'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the assessment drive and the way to ask
// for it. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-car-front', 'text-primary'),
        $span(lang('Your first lesson is an assessment drive with a written lesson plan'), '', lang('Text')),
        $link(lang('Book a Lesson'), '{{page:lesson_request}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The school
// sells nothing online, so the bar carries no cart.
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

// The header: the school's name, the menu and the way to enrol. The links
// to the current page are marked active on the server (smart active
// state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('licences', lang('Licences')),
        array('courses', lang('Courses and Prices')),
        array('theory', lang('Theory Test')),
        array('instructors', lang('Instructors')),
        array('stories', lang('Pass Stories')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-car-front', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pdr-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#drv-nav'),
                    $attr('aria-controls', 'drv-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-pencil-square'),
                            $span(lang('Enrol Now'), '', lang('Link Text')),
                        ), array('href' => '{{page:enrol}}')),
                    )),
                ), array('id' => 'drv-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the school in a sentence, how to reach it
// and its profiles, the licence classes, the courses by licence, the
// learners' pages and the school's pages; at the bottom the year, the
// site's name and the school's note.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $licences, $school, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $licence_links = array();
    foreach ($licences as $key => $l) $licence_links[] = $link($l['title'], '{{page:licence_' . $key . '}}', $plain);
    $course_links = array($link(lang('All Courses'), '{{page:courses}}', $plain));
    foreach ($licences as $l) $course_links[] = $link($l['title'], '{{page:courses}}?' . $query(array('licence' => $l['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Driving School'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-car-front', 'text-primary'),
                        $span('{{site_name}}', 'pdr-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($school['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($school['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($school['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $school['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($school['email'], 'mailto:' . $school['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($school['hours'], '', lang('Text')), lang('Office Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                        $social('bi-tiktok', 'TikTok'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Licence Links'), array(
                    $heading('h2', lang('Licences'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($licence_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Course Links'), array(
                    $heading('h2', lang('Courses'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($course_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Learners'), array(
                    $heading('h2', lang('For Learners'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Enrol Now'), '{{page:enrol}}', $plain),
                        $link(lang('My Enrolments'), '{{page:my_enrolments}}', $plain),
                        $link(lang('Book a Lesson'), '{{page:lesson_request}}', $plain),
                        $link(lang('Book Online'), '{{page:book_online}}', $plain),
                        $link(lang('Practical Test'), '{{page:practical_test}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Driving School'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Instructors'), '{{page:instructors}}', $plain),
                        $link(lang('Pass Stories'), '{{page:stories}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($school['note'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The enrol band: a full-width dark band with the way to enrol and the
// phone number. Shared, so every page that ends with it carries the same
// call.
$enrol_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $school, $attr) {
    return $el('section', 'pdr-hero py-5 bg-body text-body', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Ready to get behind the wheel?'), 'pdr-display h2 mb-2'),
                    $para(lang('Choose a course or ask for a first lesson. We call you back within a day to set up your assessment drive.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Enrol Now'), '{{page:enrol}}', 'btn btn-primary btn-lg px-4'),
                        $link($school['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $school['phone']), 'btn btn-secondary btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// A licence class's letter as a number-plate badge.
$plate = function ($code, $class = '', $extra = array()) use ($el) {
    return $el('span', trim('pdr-plate d-inline-flex align-items-center justify-content-center fw-bold ' . $class), lang('Licence Class'), array(), array_merge(array('text' => $code), $extra));
};

// The facts of a course in one list: licence, format, gearbox, lessons
// and how long it takes, each bound to the Courses form's field of the
// same name.
$course_facts = function ($sample_key = 'car_standard', $class = 'd-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $courses, $licences, $formats, $gearboxes) {
    $c = $courses[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', 'list-unstyled ' . $class, lang('Course Facts'), array(
        $fact($licences[$c['licence']]['icon'], $licences[$c['licence']]['title'], 'licence', lang('Licence Class')),
        $fact('bi-journal-text', $formats[$c['format']], 'format', lang('Format')),
        $fact('bi-gear-wide', $gearboxes[$c['gearbox']], 'gearbox', lang('Gearbox')),
        $fact('bi-car-front-fill', $c['lessons'], 'lessons', lang('Lessons')),
        $fact('bi-calendar-week', $c['hours'], 'duration', lang('Duration')),
    ));
};

// What a course includes: the Included field as written, one item a line;
// the field's value keeps its lines on the page.
$included_list = function ($sample_key = 'car_standard') use ($el, $heading, $bind, $included_text) {
    return $el('div', '', lang('What Is Included'), array(
        $heading('h2', lang('What is included'), 'pdr-display h5 mb-3'),
        $el('div', 'pg-field-value small text-body-secondary lh-lg', lang('Value'), array(), array_merge(
            array('text' => $included_text($sample_key)), $bind('included'))),
    ));
};

// A course's card of the lists (the home, the course list and a licence's
// page): the photo, the format, the name linking the course page, the
// price, the lessons, the summary and the licence. Every value is bound
// to the Courses form's field of the same name; the sample fills the
// canvas.
$course_card = function ($sample_key = 'car_standard') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $courses, $licences, $formats, $photo, $attr, $price_text) {
    $c = $courses[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden pdr-lift', lang('Course Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('course-' . $sample_key, 800, 450), $c['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($formats[$c['format']], 'pdr-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Format'), $bind('format')),
                $el('h3', 'pdr-display h5 position-static mb-1', lang('Course Name'), array(
                    $link($c['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex flex-wrap align-items-center gap-3 small fw-semibold mb-2', lang('Price and Lessons'), array(
                    $span($price_text($sample_key), 'pdr-stat fs-5 text-primary', lang('Price'), $bind('price')),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Lessons'), array(
                        $icon('bi-car-front-fill', 'text-primary'),
                        $span($c['lessons'], '', lang('Lessons'), $bind('lessons')),
                    )),
                )),
                $para($c['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $span($licences[$c['licence']]['title'], 'pdr-badge badge text-bg-secondary align-self-start mt-auto', lang('Licence Class'), $bind('licence')),
            )),
        )),
    ), '6', '4');
};

// An instructor (the instructors page and the about page): the portrait,
// the name, the role, the licence classes taught and a sentence, with the
// way to ask for a lesson with them (?instructor=).
$instructor_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $link, $portrait, $plate, $instructors, $licences, $query) {
    $t = $instructors[$n];
    $plates = array();
    foreach ($t['licences'] as $key) $plates[] = $plate($licences[$key]['code'], 'small', array('_attrs' => array($attr('title', $licences[$key]['title']))));
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 text-center p-4', lang('Instructor'), array(
            $image($portrait($t['name'], 240), $t['name'], 'rounded-circle mx-auto mb-3', array('_attrs' => array($attr('width', '120'), $attr('height', '120'), $attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'pdr-display h5 mb-1'),
            $para($t['role'], 'small fw-semibold text-primary mb-2'),
            $el('div', 'd-flex justify-content-center gap-2 mb-2', lang('Licence Classes'), $plates),
            $para($t['bio'], 'small text-body-secondary mb-3'),
            $link(lang('Ask for a lesson'), '{{page:lesson_request}}?' . $query(array('instructor' => $t['name'])), 'link-primary fw-semibold small mt-auto'),
        )),
    ), '6', '4');
};

// A pass story's card of the lists (the home and the stories page): the
// photo, the learner's words, the first name, the licence, the try and
// the instructor, each bound to the Pass Stories form's field of the same
// name.
$story_card = function ($sample_key = 'zeynep') use ($el, $para, $span, $link, $col, $bind, $icon, $optional_image, $photo, $stories, $licences, $try_choices, $instructors) {
    $s = $stories[$sample_key];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden mb-0 pdr-lift', lang('Story Card'), array(
            $optional_image('photo_1', 'name', 'w-100 h-100 object-fit-cover', 'ratio ratio-4x3 bg-body-tertiary',
                $photo('story-' . $sample_key, 800, 600), lang('Photo')),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap gap-2 mb-3', lang('Badges'), array(
                    $span($licences[$s['licence']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Licence Class'), $bind('licence')),
                    $span($try_choices[$s['try']], 'pdr-badge badge rounded-pill text-bg-secondary', lang('Passed At'), $bind('try')),
                )),
                $el('blockquote', 'mb-3', lang('Quote'), array($para($s['quote'], 'pdr-quote mb-0', $bind('words')))),
                $el('figcaption', 'mt-auto', lang('Learner'), array(
                    $el('h3', 'h6 fw-bold position-static mb-1', lang('Name'), array(
                        $link($s['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                            array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                    )),
                    $el('span', 'd-inline-flex align-items-center gap-1 small text-body-secondary', lang('Instructor'), array(
                        $icon('bi-person-badge', 'text-primary'),
                        $span($instructors[$s['instructor']]['name'], '', lang('Instructor'), $bind('instructor')),
                    )),
                )),
            )),
        )),
    ), '6', '4');
};
