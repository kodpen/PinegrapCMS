<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Private School", part 1 of its files: the node builders,
 * the school's own data (the programmes with their ages and hours, the
 * classes, a day at school, the approach, the campus, the staff, the
 * figures, what parents say, the sample events and the answers of the
 * forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, admissions band, programme, staff, parent and event
 * cards, programme and event facts, page shells, section headers, form
 * fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-school.php puts them together.
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
// every opening until the school puts its own photographs in its place.
// The sample shows rooms, gardens and things children make, never a
// child's face.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-school-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ffedd5/9a3412?text=' . rawurlencode($initials !== '' ? $initials : 'S');
};

// ── The school: its programmes, its days and its people ─────────────────

// The school itself. The about page tells its story; the footer, the
// admissions band and the contact lines use the address, phone and office
// hours.
$school = array(
    'tagline' => lang('A small school where every child is known by name, from the first day of nursery to the last day of primary'),
    'story'   => lang('Two teachers opened the school in a house with a garden and eight children. It has grown into a nursery, a kindergarten and a primary school, and it still keeps its first promise: small groups, teachers who know every child well and a day with as much time outdoors as indoors.'),
    'address' => lang('Linden Street 12, Garden District'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'office@example.com',
    'hours'   => lang('School office: Monday to Friday, 8:00 to 17:00'),
    'licence' => lang('A licensed private school, inspected by the education authority'),
);

// The programmes. Each has its own page (programme_<key>); the application
// opens with the programme chosen (?programme=) in the programme's own
// words. The fee is never a figure: fees change every year, so the page
// asks the family to call or apply.
$programmes = array(
    'nursery' => array('icon' => 'bi-balloon-heart', 'title' => lang('Nursery'),
        'ages'    => lang('Ages 2 to 3'), 'group' => lang('Groups of up to 10 children with two teachers'),
        'hours'   => lang('Half days or full days, 8:00 to 17:00'),
        'summary' => lang('A gentle first step away from home: play, songs, stories and a lot of time in the garden.'),
        'text'    => lang('Our nursery is a calm, homely place where the youngest children learn to play together, to talk about what they feel and to look after themselves. Each child has a key teacher who settles them in with the family and knows their day.'),
        'points'  => array(lang('A key teacher for every child'), lang('A settling-in plan made with the family'), lang('Rest time in a quiet room'), lang('A daily note home about the day'))),
    'kindergarten' => array('icon' => 'bi-palette', 'title' => lang('Kindergarten'),
        'ages'    => lang('Ages 3 to 6'), 'group' => lang('Groups of up to 16 children with two teachers'),
        'hours'   => lang('Full days, 8:30 to 16:30'),
        'summary' => lang('Learning through play, projects and questions, with a year to get ready for school.'),
        'text'    => lang('In kindergarten children follow their questions through projects: they build, paint, count, measure, grow plants and tell stories. In the last year they get ready for school with early reading, numbers and the confidence to try.'),
        'points'  => array(lang('Project-based learning through play'), lang('Early reading and numbers'), lang('English every day'), lang('A school-readiness year'))),
    'primary' => array('icon' => 'bi-book', 'title' => lang('Primary School'),
        'ages'    => lang('Ages 6 to 10'), 'group' => lang('Classes of up to 20 pupils'),
        'hours'   => lang('Full days, 8:30 to 15:30, clubs until 17:30'),
        'summary' => lang('The national curriculum taught in small classes, with languages, science and the arts.'),
        'text'    => lang('Our primary school follows the national curriculum in small classes, so every pupil is heard and helped. Reading, mathematics and science sit beside music, art, sport and a second language, and homework stays short.'),
        'points'  => array(lang('The national curriculum in small classes'), lang('Two foreign languages'), lang('A science lab and a library'), lang('Short homework and regular reports'))),
    'clubs' => array('icon' => 'bi-puzzle', 'title' => lang('After-School Clubs'),
        'ages'    => lang('Ages 4 to 10'), 'group' => lang('Groups of up to 12 children'),
        'hours'   => lang('Weekdays, 15:30 to 17:30'),
        'summary' => lang('Chess, robotics, music, drama, swimming and sport after the school day.'),
        'text'    => lang('Our clubs fill the end of the day with something a child loves. They are open to our own pupils and to children from other schools, and they change each term so there is always something new to try.'),
        'points'  => array(lang('Chess and robotics'), lang('Music, choir and drama'), lang('Swimming and team sports'), lang('Homework club with a teacher'))),
    'summer' => array('icon' => 'bi-sun', 'title' => lang('Summer School'),
        'ages'    => lang('Ages 3 to 10'), 'group' => lang('Groups by age, up to 14 children'),
        'hours'   => lang('Weekly sessions in the summer holiday, 9:00 to 16:00'),
        'summary' => lang('Weeks of nature, art, science and play when the school year is over.'),
        'text'    => lang('Summer school runs in weekly themes: nature explorers, little scientists, art studio and sports week. Families book as many weeks as they like, and children from other schools are welcome.'),
        'points'  => array(lang('A new theme every week'), lang('Water play and garden days'), lang('Trips close to the school'), lang('Open to children from other schools'))),
);
// The fee line of every programme page.
$fee_text = lang('Fees are set each school year. Call the office or send an application and we will send you this year\'s fees.');

// The class groups (the day page and the programme pages): a name, the
// programme and the ages. Fixed data: the groups change little.
$classes = array(
    array('name' => lang('Acorns'), 'programme' => 'nursery', 'ages' => lang('Ages 2 to 3')),
    array('name' => lang('Saplings'), 'programme' => 'kindergarten', 'ages' => lang('Ages 3 to 4')),
    array('name' => lang('Oaks'), 'programme' => 'kindergarten', 'ages' => lang('Ages 4 to 5')),
    array('name' => lang('Pines'), 'programme' => 'kindergarten', 'ages' => lang('Ages 5 to 6')),
    array('name' => lang('Years 1 and 2'), 'programme' => 'primary', 'ages' => lang('Ages 6 to 8')),
    array('name' => lang('Years 3 and 4'), 'programme' => 'primary', 'ages' => lang('Ages 8 to 10')),
);

// A day in kindergarten, hour by hour (the day page and the home).
$day = array(
    array('time' => '8:30', 'title' => lang('Arrival and free play'), 'text' => lang('Children arrive, say goodbye at the door and choose where to play.')),
    array('time' => '9:15', 'title' => lang('Morning circle'), 'text' => lang('We sing, talk about the day and share news from home.')),
    array('time' => '9:45', 'title' => lang('Project time'), 'text' => lang('Small groups build, paint, count and explore the week\'s project.')),
    array('time' => '10:45', 'title' => lang('Garden and outdoor play'), 'text' => lang('Every day, in every season: boots and raincoats wait by the door.')),
    array('time' => '12:00', 'title' => lang('Lunch together'), 'text' => lang('A warm lunch cooked in our own kitchen, eaten with the teachers.')),
    array('time' => '13:00', 'title' => lang('Rest and quiet stories'), 'text' => lang('The youngest sleep; the older children listen to stories or read.')),
    array('time' => '14:00', 'title' => lang('Music, movement or English'), 'text' => lang('A specialist teacher leads a different lesson each afternoon.')),
    array('time' => '16:30', 'title' => lang('Home time'), 'text' => lang('Families collect their children and hear about the day.')),
);

// What the school believes about learning (the approach page and the home).
$approach = array(
    array('icon' => 'bi-people', 'title' => lang('Small groups'),
        'text' => lang('Few children per teacher, so every child is seen, heard and helped.')),
    array('icon' => 'bi-tree', 'title' => lang('Learning outdoors'),
        'text' => lang('A garden, a vegetable patch and a forest corner used every day.')),
    array('icon' => 'bi-lightbulb', 'title' => lang('Curiosity first'),
        'text' => lang('Projects start from the children\'s own questions and grow with them.')),
    array('icon' => 'bi-chat-heart', 'title' => lang('Families as partners'),
        'text' => lang('A daily word at the door, regular meetings and an open door for parents.')),
);

// The campus (the campus page): the rooms and grounds a family sees on a visit.
$facilities = array(
    array('icon' => 'bi-flower1', 'title' => lang('Garden and vegetable patch'),
        'text' => lang('Children plant, water and harvest, and cook what they grow.')),
    array('icon' => 'bi-easel', 'title' => lang('Art studio'),
        'text' => lang('Paint, clay and wood, with room to leave a work unfinished until tomorrow.')),
    array('icon' => 'bi-book-half', 'title' => lang('Library'),
        'text' => lang('Picture books and readers in two languages, borrowed home every week.')),
    array('icon' => 'bi-eyedropper', 'title' => lang('Science lab'),
        'text' => lang('Simple experiments for the primary classes, with real tools.')),
    array('icon' => 'bi-music-note-beamed', 'title' => lang('Music room'),
        'text' => lang('Instruments for every child and a stage for the school shows.')),
    array('icon' => 'bi-egg-fried', 'title' => lang('Our own kitchen'),
        'text' => lang('Fresh meals cooked every day, with menus shared with the families.')),
);

// The school's people (the staff page and the about page). Fixed data,
// not a collection: a school's team changes once a year.
$staff = array(
    array('name' => lang('Ayşe Kaya'), 'role' => lang('Head of School'),
        'bio' => lang('A primary teacher for twenty years before she opened the school, and still teaches a reading group every week.')),
    array('name' => lang('Mehmet Çelik'), 'role' => lang('Head of Primary'),
        'bio' => lang('Leads the primary teachers and the curriculum, and coaches the chess club.')),
    array('name' => lang('Sarah Mitchell'), 'role' => lang('Head of Early Years'),
        'bio' => lang('Runs the nursery and kindergarten, and plans every new child\'s settling-in.')),
    array('name' => lang('Elif Yıldız'), 'role' => lang('Kindergarten Teacher'),
        'bio' => lang('Teaches the Oaks class and leads the garden projects.')),
    array('name' => lang('Can Öztürk'), 'role' => lang('Music Teacher'),
        'bio' => lang('Teaches music to every class and conducts the school choir.')),
    array('name' => lang('Leyla Aksoy'), 'role' => lang('School Nurse'),
        'bio' => lang('Looks after health, allergies and first aid, and talks with families about care plans.')),
);

// The accreditations of the school (the about page and the home).
$accreditations = array(
    array('icon' => 'bi-patch-check', 'title' => lang('Licensed school'), 'text' => lang('Registered with the education authority')),
    array('icon' => 'bi-heart-pulse', 'title' => lang('First aid trained'), 'text' => lang('Every teacher, renewed every year')),
    array('icon' => 'bi-shield-check', 'title' => lang('Safeguarding'), 'text' => lang('Checked staff and a written child protection policy')),
    array('icon' => 'bi-translate', 'title' => lang('Bilingual'), 'text' => lang('English taught every day from kindergarten')),
);

// Why families choose the school (the home and the about page).
$why_us = array(
    array('icon' => 'bi-person-heart', 'title' => lang('Teachers who stay'),
        'text' => lang('Most of our teachers have been with us for years, so children grow up with familiar faces.')),
    array('icon' => 'bi-sun', 'title' => lang('Long days, done well'),
        'text' => lang('Early arrival, lunch from our own kitchen and clubs until the evening.')),
    array('icon' => 'bi-chat-dots', 'title' => lang('Families kept informed'),
        'text' => lang('A note every day for the youngest and a report every term for all.')),
    array('icon' => 'bi-shield-check', 'title' => lang('A safe place'),
        'text' => lang('A secure entrance, checked staff and a nurse on site every day.')),
);

// The school in four figures (the home and the about page).
$figures = array(
    array('value' => '1:8', 'label' => lang('Teachers to children in the early years')),
    array('value' => '240', 'label' => lang('Children from two to ten')),
    array('value' => '35', 'label' => lang('Teachers and staff')),
    array('value' => '3,000 m²', 'label' => lang('Of garden and play areas')),
);

// What parents say, each about one programme. A parent is named, never
// the child.
$testimonials = array(
    array('programme' => 'nursery', 'name' => lang('Zeynep Arslan'), 'role' => lang('Parent in the Nursery'),
        'quote' => lang('Our daughter cried for two mornings and then ran in. Her key teacher told us everything about her day.')),
    array('programme' => 'kindergarten', 'name' => lang('James Carter'), 'role' => lang('Parent in the Kindergarten'),
        'quote' => lang('He comes home with questions every day, and the teachers take every one of them seriously.')),
    array('programme' => 'primary', 'name' => lang('Burak Demir'), 'role' => lang('Parent in the Primary School'),
        'quote' => lang('Small classes made all the difference: our son is known, helped and happy to go to school.')),
);

// The sample events: the records the Events form starts with. The date is
// words without a year, so the sample never reads as an old event; the
// school writes the real date in its own record. Each event belongs to a
// programme or to the whole school (the list's filter, ?programme=).
$events = array(
    'open_morning' => array('title' => lang('Open Morning for New Families'), 'programme' => 'all',
        'when' => lang('A Saturday morning each month, 10:00 to 12:00'), 'place' => lang('The main hall and the classrooms'),
        'summary' => lang('Meet the teachers, see the classrooms in use and ask everything about admissions.'),
        'details' => array(lang('A short talk by the Head of School'), lang('A tour of the classrooms and the garden'), lang('Time with the teachers of each programme'), lang('Activities for the children who come with you')),
        'featured' => true),
    'spring_fair' => array('title' => lang('Spring Fair'), 'programme' => 'all',
        'when' => lang('A Saturday in spring, 11:00 to 15:00'), 'place' => lang('The school garden'),
        'summary' => lang('Games, music, plants grown by the children and food from the families of every country in our school.'),
        'details' => array(lang('Stalls run by the parents\' association'), lang('Plants and crafts made by the children'), lang('Music from the school choir'), lang('All money raised goes to the library')),
        'featured' => true),
    'nature_week' => array('title' => lang('Nature Week'), 'programme' => 'kindergarten',
        'when' => lang('A week in late spring'), 'place' => lang('The garden and the forest nearby'),
        'summary' => lang('A week outdoors: bug hunts, den building, planting and a picnic with the families on the last day.'),
        'details' => array(lang('A guided walk in the forest nearby'), lang('Planting the vegetable patch'), lang('Den building and outdoor stories'), lang('A family picnic on Friday')),
        'featured' => true),
    'science_fair' => array('title' => lang('Primary Science Fair'), 'programme' => 'primary',
        'when' => lang('An afternoon in the spring term, 14:00 to 16:00'), 'place' => lang('The science lab and the main hall'),
        'summary' => lang('Our primary pupils show their experiments and explain them to their families.'),
        'details' => array(lang('Experiments by every primary class'), lang('Questions from a visiting scientist'), lang('A prize for the most curious question'), lang('Families welcome from 14:00')),
        'featured' => false),
    'settling_meeting' => array('title' => lang('Settling-In Meeting for Nursery Families'), 'programme' => 'nursery',
        'when' => lang('Two weeks before the school year starts, 17:30'), 'place' => lang('The nursery rooms'),
        'summary' => lang('New nursery families meet the key teachers and plan the first weeks together.'),
        'details' => array(lang('How the first weeks will run'), lang('What to bring on the first day'), lang('Meals, rest and allergies'), lang('Time with your child\'s key teacher')),
        'featured' => false),
    'winter_concert' => array('title' => lang('Winter Concert'), 'programme' => 'all',
        'when' => lang('The last week of the autumn term, 18:00'), 'place' => lang('The main hall'),
        'summary' => lang('Every class sings, plays or acts on stage for the families before the winter holiday.'),
        'details' => array(lang('The school choir and the club bands'), lang('A short play by the primary pupils'), lang('Songs from the kindergarten classes'), lang('Warm drinks with the parents\' association')),
        'featured' => false),
);
// The programme of an event that is for every family.
$whole_school = lang('Whole School');
// An event's details as the value of its Details field: one line per item.
$details_text = function ($key) use ($events) {
    return implode("\n", $events[$key]['details']);
};
// The words of an event's programme, the value of its Programme field.
$event_programme = function ($key) use ($events, $programmes, $whole_school) {
    $p = $events[$key]['programme'];
    return $p === 'all' ? $whole_school : $programmes[$p]['title'];
};

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the family chose.
$programme_titles = array();
foreach ($programmes as $p) $programme_titles[] = $p['title'];
// The Events form's Programme list and the event list's filter.
$event_programme_titles = array_merge(array($whole_school), $programme_titles);
$application_programme_choices = array_merge($programme_titles, array(lang('Not sure yet')));
$start_choices = array(lang('At the start of the school year'), lang('In the middle of the school year'), lang('As soon as a place is free'), lang('Not decided yet'));
$relation_choices = array(lang('Mother'), lang('Father'), lang('Guardian'), lang('Other'));
// The states of an application, set by the school in the panel.
$application_statuses = array(lang('Application received'), lang('Visit booked'), lang('Place offered'), lang('Waiting list'), lang('Not this time'));
$visit_times = array(lang('A weekday morning'), lang('A weekday afternoon'), lang('An open morning on Saturday'));
$visitor_counts = array(lang('One adult'), lang('Two adults'), lang('Adults and the child'));
// The states of a visit request, set by the school in the panel.
$visit_statuses = array(lang('Request received'), lang('Visit booked'), lang('Visit done'));
$event_titles = array();
foreach ($events as $e) $event_titles[] = $e['title'];
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The query string of a link that opens a list with a filter set
// (?programme=) or a form with a choice made (?programme=): the form's
// fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the admissions band above the footer unless the
// page is a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('admissions_band');
    $children[] = $shared('footer');
    return $root($children);
};

$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-body-tertiary', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'psh-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'psh-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'psh-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the approach and why-us points are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'sh-faq') use ($el, $para, $attr, $searchable) {
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
                $span($f['value'], 'psh-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: admissions are open and the way to visit.
// A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-megaphone', 'text-primary'),
        $span(lang('Admissions are open for the next school year'), '', lang('Text')),
        $link(lang('Book a School Visit'), '{{page:visit}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The
// school sells nothing online, so the bar carries no cart.
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

// The header: the school's name, the menu and the way to apply. The links
// to the current page are marked active on the server (smart active
// state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('programmes', lang('Programmes')),
        array('day', lang('A Day at School')),
        array('campus', lang('Our Campus')),
        array('events', lang('Events')),
        array('about', lang('About Us')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-backpack', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'psh-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#sh-nav'),
                    $attr('aria-controls', 'sh-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-pencil-square'),
                            $span(lang('Apply Now'), '', lang('Link Text')),
                        ), array('href' => '{{page:apply}}')),
                    )),
                ), array('id' => 'sh-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the school in a sentence, how to reach it
// and its profiles, the programmes, the events by programme, the parents'
// pages and the school's pages; at the bottom the year, the site's name
// and the licence.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $programmes, $event_programme_titles, $school, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $programme_links = array();
    foreach ($programmes as $key => $p) $programme_links[] = $link($p['title'], '{{page:programme_' . $key . '}}', $plain);
    $event_links = array($link(lang('All Events'), '{{page:events}}', $plain));
    foreach ($event_programme_titles as $title) $event_links[] = $link($title, '{{page:events}}?' . $query(array('programme' => $title)), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the School'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-backpack', 'text-primary'),
                        $span('{{site_name}}', 'psh-brand', lang('Name')),
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
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Programme Links'), array(
                    $heading('h2', lang('Programmes'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($programme_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Event Links'), array(
                    $heading('h2', lang('Events'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($event_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Parents'), array(
                    $heading('h2', lang('For Parents'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Apply Now'), '{{page:apply}}', $plain),
                        $link(lang('Book a School Visit'), '{{page:visit}}', $plain),
                        $link(lang('My Applications'), '{{page:my_applications}}', $plain),
                        $link(lang('Fees'), '{{page:fees}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The School'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Our Approach'), '{{page:approach}}', $plain),
                        $link(lang('Our Teachers'), '{{page:staff_team}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($school['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The admissions band: a full-width band in the palette's colour with the
// ways to apply and to visit. Shared, so every page that ends with it
// carries the same call.
$admissions_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Looking for a place for your child?'), 'psh-display h2 mb-2'),
                    $para(lang('Come and see the school on an ordinary day, or send an application and we will call you within a week.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Apply Now'), '{{page:apply}}', 'btn btn-light btn-lg rounded-pill px-4'),
                        $link(lang('Book a School Visit'), '{{page:visit}}', 'btn btn-outline-light btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The facts of a programme in one list: ages, group size and hours.
$programme_facts = function ($key, $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $programmes) {
    $p = $programmes[$key];
    $fact = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name),
        ));
    };
    return $el('ul', $class, lang('Programme Facts'), array(
        $fact('bi-person-arms-up', $p['ages'], lang('Ages')),
        $fact('bi-people', $p['group'], lang('Group Size')),
        $fact('bi-clock', $p['hours'], lang('Hours')),
    ));
};

// A programme's card (the home and the programmes page): the picture, the
// ages, the title linking the programme's page and the summary.
$programme_card = function ($key) use ($el, $para, $span, $link, $image, $col, $photo, $attr, $programmes) {
    $p = $programmes[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden psh-lift', lang('Programme Card'), array(
            $el('span', 'd-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('programme-' . $key, 800, 600), $p['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($p['ages'], 'psh-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Ages')),
                $el('h3', 'psh-display h5 position-static mb-2', lang('Programme Name'), array(
                    $link($p['title'], '{{page:programme_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($p['summary'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// A member of staff (the staff page and the about page): the portrait, the
// name, the role and a sentence.
$staff_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $staff) {
    $t = $staff[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 text-center p-4', lang('Staff Member'), array(
            $image($portrait($t['name'], 240), $t['name'], 'rounded-circle mx-auto mb-3', array('_attrs' => array($attr('width', '120'), $attr('height', '120'), $attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'psh-display h5 mb-1'),
            $para($t['role'], 'small fw-semibold text-primary mb-2'),
            $para($t['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// What a parent says: the quote, the parent and the programme.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $icon, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Parent Quote'), array(
            $icon('bi-quote', 'fs-2 text-primary lh-1 mb-2'),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'psh-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Parent Details'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// The facts of an event in one list: programme, when and where, each bound
// to the Events form's field of the same name.
$event_facts = function ($sample_key = 'open_morning', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $events, $event_programme) {
    $e = $events[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Event Facts'), array(
        $fact('bi-calendar-event', $e['when'], 'when', lang('When')),
        $fact('bi-geo-alt', $e['place'], 'place', lang('Place')),
        $fact('bi-mortarboard', $event_programme($sample_key), 'programme', lang('Programme')),
    ));
};

// An event's card of the lists (the home and the event list): the photo,
// the programme, the title linking the event page, when, where and the
// summary. Every value is bound to the Events form's field of the same
// name; the sample fills the canvas.
$event_card = function ($sample_key = 'open_morning') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $events, $event_programme, $photo, $attr) {
    $e = $events[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden psh-lift', lang('Event Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('event-' . $sample_key, 800, 450), $e['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_programme($sample_key), 'psh-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Programme'), $bind('programme')),
                $el('h3', 'psh-display h5 position-static mb-2', lang('Event Title'), array(
                    $link($e['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-start gap-1 small fw-semibold mb-1', lang('When'), array(
                    $icon('bi-calendar-event', 'text-primary'),
                    $span($e['when'], '', lang('When'), $bind('when')),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-2', lang('Place'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($e['place'], '', lang('Place'), $bind('place')),
                )),
                $para($e['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
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
                $item('staff_applications', lang('Admission Applications')),
                $item('staff_visits', lang('Visit Requests')),
                $item('event_new', lang('New Event')),
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
    return $agree($id, 'consent', lang('I would like to receive the school\'s news and event invitations by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// application takes the child's documents (a birth certificate, a report
// from the last school), so the default accepts PDFs, office files, text
// and pictures; one that takes only pictures is a collection's image kind
// (an event's photo).
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
                    $link(lang('How we settle new children into the kindergarten'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

