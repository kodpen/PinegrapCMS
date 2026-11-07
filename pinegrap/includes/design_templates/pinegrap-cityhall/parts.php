<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "City Hall", part 1 of its files: the node builders, the
 * city's own data (the service areas with their services, the departments,
 * the mayor, the council and its committees, the neighbourhoods, the
 * service points with their hours, the useful numbers, the figures, the
 * sample notices and events and the answers of the forms' pick lists) and
 * the parts every page repeats (account bar, notice strip, header, footer,
 * request band, service-area, service, department, council, notice, event
 * and service-point cards, the hours table, page shells, section headers,
 * form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-cityhall.php puts them together.
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

// A sample picture with a fixed seed, so the same picture comes back on
// every opening until the city hall puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-cityhall-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/dbeafe/1e3a8a?text=' . rawurlencode($initials !== '' ? $initials : 'C');
};

// ── The city: its services, its people and its places ───────────────────

// The city hall itself. The city page tells its story; the footer, the
// request band and the contact lines use the address, phone and hours.
$city = array(
    'tagline' => lang('Your city hall: services, notices and the council\'s work, open to every resident'),
    'story'   => lang('The town grew around a river crossing and a weekly market. Today it is a city of green neighbourhoods, a busy harbour and an old town that is still lived in. The city hall looks after its streets, parks, water, culture and social support, and answers to the residents through the elected council.'),
    'address' => lang('Republic Square 1, Old Town'),
    'phone'   => '+90 555 000 00 00',
    'line'    => '+90 555 000 01 53',
    'email'   => 'info@example.com',
    'hours'   => lang('City hall: Monday to Friday, 8:30 to 17:30'),
    'line_hours' => lang('Citizen line: every day, around the clock'),
);

// The service areas. Each has its own page (service_<key>) with its
// services; "Make a request" on that page opens the request form with the
// area chosen (?topic=) in the area's own words. Each area names the
// department that runs it.
$service_areas = array(
    'building' => array('icon' => 'bi-buildings', 'title' => lang('Building and Zoning'), 'department' => 'planning',
        'summary'  => lang('Building permits, zoning plans, occupancy certificates and plan copies.'),
        'text'     => lang('Before you build, extend or change the use of a building, the city checks the plan against the zoning rules. Here you find what each permit needs and where to hand in your file.'),
        'services' => array(
            array('title' => lang('Building permit'), 'text' => lang('Permission to build a new building or to extend one.'),
                'docs' => lang('Title deed, architectural project, static report and the applicant\'s ID')),
            array('title' => lang('Occupancy certificate'), 'text' => lang('The certificate that a finished building matches its permit and may be lived in.'),
                'docs' => lang('Building permit, site supervisor\'s report and the utilities\' approvals')),
            array('title' => lang('Zoning status certificate'), 'text' => lang('A written statement of what the zoning plan allows on a plot.'),
                'docs' => lang('Plot number and the applicant\'s ID')),
        )),
    'roads' => array('icon' => 'bi-sign-turn-right', 'title' => lang('Roads and Transport'), 'department' => 'works',
        'summary'  => lang('Road and pavement repairs, street lights, parking and road works permits.'),
        'text'     => lang('The city keeps its streets, pavements, bridges and street lights in repair. Tell us about a pothole or a dark street, or ask for a permit before you dig up a road.'),
        'services' => array(
            array('title' => lang('Road and pavement repair'), 'text' => lang('A pothole, a broken kerb or a loose paving stone, repaired by our road teams.'),
                'docs' => lang('No documents: tell us the street and what you saw')),
            array('title' => lang('Street lighting'), 'text' => lang('A street light that is out, flickering or on in daylight.'),
                'docs' => lang('No documents: the street and the number on the pole help')),
            array('title' => lang('Road works permit'), 'text' => lang('Permission to dig up a road or a pavement for a connection or a repair.'),
                'docs' => lang('Site plan, the works schedule and the contractor\'s details')),
        )),
    'waste' => array('icon' => 'bi-recycle', 'title' => lang('Waste and Recycling'), 'department' => 'cleaning',
        'summary'  => lang('Rubbish collection, recycling, bulky waste and street cleaning.'),
        'text'     => lang('Household rubbish is collected every day in the city centre and every other day in the outer neighbourhoods. Recycling bins stand in every street, and bulky items are collected on request.'),
        'services' => array(
            array('title' => lang('Bulky waste collection'), 'text' => lang('Old furniture and large household items collected from your door.'),
                'docs' => lang('No documents: the address and a list of the items')),
            array('title' => lang('Recycling bins'), 'text' => lang('A new or extra recycling bin for your building or street.'),
                'docs' => lang('No documents: the address and the number of flats')),
            array('title' => lang('Street cleaning'), 'text' => lang('A street, square or green area that needs cleaning.'),
                'docs' => lang('No documents: the place and a photo if you have one')),
        )),
    'social' => array('icon' => 'bi-people', 'title' => lang('Social Support'), 'department' => 'social',
        'summary'  => lang('Help for families in need, older and disabled residents, and home care.'),
        'text'     => lang('The city supports residents who need a hand: food and fuel help for families in need, home care for older residents and transport for disabled residents. Every request is answered in confidence.'),
        'services' => array(
            array('title' => lang('Family support'), 'text' => lang('Food, fuel and school supplies for families in need.'),
                'docs' => lang('ID of the family members and a statement of income')),
            array('title' => lang('Home care for older residents'), 'text' => lang('Cleaning, shopping and a weekly visit for residents who live alone.'),
                'docs' => lang('The resident\'s ID and a doctor\'s report if there is one')),
            array('title' => lang('Accessible transport'), 'text' => lang('A wheelchair-accessible vehicle for hospital visits.'),
                'docs' => lang('The resident\'s ID and the disability report')),
        )),
    'culture' => array('icon' => 'bi-music-note-beamed', 'title' => lang('Culture and Sport'), 'department' => 'culture',
        'summary'  => lang('Courses, the cultural centre, libraries, sports halls and festivals.'),
        'text'     => lang('The cultural centre, the city library and the sports halls are open to every resident. Courses run each term, from languages and crafts to music and swimming.'),
        'services' => array(
            array('title' => lang('City courses'), 'text' => lang('Language, art, music and craft courses for adults and children.'),
                'docs' => lang('The participant\'s ID; children come with a parent')),
            array('title' => lang('Sports halls and pitches'), 'text' => lang('Book a hall or a pitch for a club, a school or a group of friends.'),
                'docs' => lang('The name of the group and the person in charge')),
            array('title' => lang('Hall hire for events'), 'text' => lang('The cultural centre\'s halls for concerts, talks and ceremonies.'),
                'docs' => lang('A short description of the event and the expected guests')),
        )),
    'parks' => array('icon' => 'bi-tree', 'title' => lang('Parks and Environment'), 'department' => 'parks',
        'summary'  => lang('Parks, playgrounds, trees, stray animals and noise.'),
        'text'     => lang('The city plants and looks after its parks, playgrounds and street trees, cares for stray animals with the local shelter and checks noise and pollution complaints.'),
        'services' => array(
            array('title' => lang('Tree pruning and planting'), 'text' => lang('A tree that needs pruning, a fallen branch or a request for a new tree.'),
                'docs' => lang('No documents: the place and a photo if you have one')),
            array('title' => lang('Playground repair'), 'text' => lang('A broken swing, slide or bench in a park.'),
                'docs' => lang('No documents: the park and what is broken')),
            array('title' => lang('Stray animal care'), 'text' => lang('An injured or sick stray animal, taken to the shelter\'s vet.'),
                'docs' => lang('No documents: the place and the animal')),
        )),
    'civil' => array('icon' => 'bi-journal-check', 'title' => lang('Licences and Ceremonies'), 'department' => 'licensing',
        'summary'  => lang('Business licences, market stalls, civil weddings and funeral services.'),
        'text'     => lang('Open a shop, take a market stall, marry at the wedding hall or arrange a funeral: the city\'s licensing and ceremony offices help with the papers and the dates.'),
        'services' => array(
            array('title' => lang('Business licence'), 'text' => lang('The licence to open a shop, an office or a workshop.'),
                'docs' => lang('Lease or title deed, tax registration and the owner\'s ID')),
            array('title' => lang('Civil wedding'), 'text' => lang('A wedding ceremony at the city\'s wedding hall.'),
                'docs' => lang('The couple\'s IDs, health reports and photos')),
            array('title' => lang('Funeral services'), 'text' => lang('A hearse, a place in the city cemetery and help with the papers.'),
                'docs' => lang('The death certificate and the applicant\'s ID')),
        )),
);
// The service area of a request that fits no area.
$other_topic = lang('Something else');

// The departments (the departments page and the service-area pages): the
// head, where to find them and when. Fixed data: they change little.
$departments = array(
    'planning'  => array('icon' => 'bi-rulers', 'title' => lang('Zoning and Urban Planning'), 'head' => lang('Selin Aydın'),
        'floor' => lang('Main building, 3rd floor'), 'phone' => '+90 555 000 02 10', 'text' => lang('Zoning plans, building permits and occupancy certificates.')),
    'works'     => array('icon' => 'bi-cone-striped', 'title' => lang('Technical Works'), 'head' => lang('Murat Koç'),
        'floor' => lang('Works depot, Station Road'), 'phone' => '+90 555 000 02 20', 'text' => lang('Roads, pavements, bridges, street lights and road works permits.')),
    'cleaning'  => array('icon' => 'bi-trash3', 'title' => lang('Cleaning Services'), 'head' => lang('Hakan Yılmaz'),
        'floor' => lang('Works depot, Station Road'), 'phone' => '+90 555 000 02 30', 'text' => lang('Rubbish collection, recycling and street cleaning.')),
    'social'    => array('icon' => 'bi-heart', 'title' => lang('Social Services'), 'head' => lang('Derya Şahin'),
        'floor' => lang('Main building, ground floor'), 'phone' => '+90 555 000 02 40', 'text' => lang('Family support, home care and accessible transport.')),
    'culture'   => array('icon' => 'bi-palette', 'title' => lang('Culture and Social Affairs'), 'head' => lang('Emre Polat'),
        'floor' => lang('Cultural centre, Harbour Street'), 'phone' => '+90 555 000 02 50', 'text' => lang('Courses, the cultural centre, libraries, sport and festivals.')),
    'parks'     => array('icon' => 'bi-flower2', 'title' => lang('Parks and Gardens'), 'head' => lang('Nur Erdem'),
        'floor' => lang('Park office, City Park'), 'phone' => '+90 555 000 02 60', 'text' => lang('Parks, playgrounds, street trees and animal care.')),
    'licensing' => array('icon' => 'bi-patch-check', 'title' => lang('Licensing and Inspection'), 'head' => lang('Okan Tekin'),
        'floor' => lang('Main building, 1st floor'), 'phone' => '+90 555 000 02 70', 'text' => lang('Business licences, markets, inspections, weddings and funerals.')),
    'finance'   => array('icon' => 'bi-calculator', 'title' => lang('Financial Services'), 'head' => lang('Gül Özkan'),
        'floor' => lang('Main building, 2nd floor'), 'phone' => '+90 555 000 02 80', 'text' => lang('Local taxes, fees, the budget and tenders.')),
);

// The mayor (the mayor page and the home).
$mayor = array(
    'name'    => lang('Deniz Karaca'),
    'role'    => lang('Mayor'),
    'message' => lang('A city hall is only as good as its answers. Write to us, come to the council, tell us what works and what does not: every request is read and answered.'),
    'bio'     => lang('An engineer who worked on the city\'s water network for fifteen years before being elected to the council, and then as mayor.'),
);

// The council's committees and members (the council page). Members are
// named by their role and committee, never by a party.
$committees = array(
    'planning' => array('title' => lang('Planning and Zoning Committee'), 'text' => lang('Reviews zoning plan changes before the council votes on them.')),
    'budget'   => array('title' => lang('Budget Committee'), 'text' => lang('Examines the yearly budget and the accounts.')),
    'social'   => array('title' => lang('Social Affairs Committee'), 'text' => lang('Follows social support, health and the needs of older and disabled residents.')),
    'culture'  => array('title' => lang('Culture and Youth Committee'), 'text' => lang('Plans festivals, courses and the city\'s work with young people.')),
);
$council = array(
    array('name' => lang('Aylin Doğan'), 'role' => lang('Deputy Mayor'), 'committee' => 'budget'),
    array('name' => lang('Kerem Aslan'), 'role' => lang('Deputy Mayor'), 'committee' => 'planning'),
    array('name' => lang('Seda Güneş'), 'role' => lang('Council Member'), 'committee' => 'social'),
    array('name' => lang('Tolga Kurt'), 'role' => lang('Council Member'), 'committee' => 'planning'),
    array('name' => lang('Ebru Yavuz'), 'role' => lang('Council Member'), 'committee' => 'culture'),
    array('name' => lang('Ali Bulut'), 'role' => lang('Council Member'), 'committee' => 'budget'),
    array('name' => lang('Pınar Acar'), 'role' => lang('Council Member'), 'committee' => 'social'),
    array('name' => lang('Onur Çetin'), 'role' => lang('Council Member'), 'committee' => 'culture'),
);
// When the council meets, in words: the meeting's own notice gives its date.
$council_meetings = lang('The council meets on the first Tuesday of each month at 14:00 in the council hall. Meetings are open to the public, and the agenda is published as a notice a week before.');

// The neighbourhoods (the city page and the request form's pick list).
$neighbourhoods = array(lang('Old Town'), lang('Riverside'), lang('Harbour'), lang('Hillside'), lang('Station'), lang('Orchard'), lang('Meadow'), lang('Linden'));

// The service points (the service points page and the contact page): where
// residents are served in person, with their hours as words.
$service_points = array(
    'main' => array('icon' => 'bi-bank', 'title' => lang('City Hall'), 'address' => lang('Republic Square 1, Old Town'),
        'phone' => '+90 555 000 00 00', 'text' => lang('Every department, the citizen desk and the council hall.'),
        'hours' => array(array(lang('Monday to Friday'), lang('8:30 to 17:30')), array(lang('Saturday and Sunday'), lang('Closed')))),
    'desk' => array('icon' => 'bi-person-raised-hand', 'title' => lang('Citizen Desk'), 'address' => lang('Republic Square 1, ground floor'),
        'phone' => '+90 555 000 01 53', 'text' => lang('Requests, complaints and every question, answered in one place.'),
        'hours' => array(array(lang('Monday to Friday'), lang('8:00 to 19:00')), array(lang('Saturday'), lang('9:00 to 13:00')), array(lang('Sunday'), lang('Closed')))),
    'harbour' => array('icon' => 'bi-geo-alt', 'title' => lang('Harbour Service Point'), 'address' => lang('Harbour Street 14, Harbour'),
        'phone' => '+90 555 000 03 10', 'text' => lang('Requests, social support and course sign-ups close to home.'),
        'hours' => array(array(lang('Monday to Friday'), lang('9:00 to 17:00')), array(lang('Saturday and Sunday'), lang('Closed')))),
    'hillside' => array('icon' => 'bi-geo-alt', 'title' => lang('Hillside Service Point'), 'address' => lang('Pine Avenue 7, Hillside'),
        'phone' => '+90 555 000 03 20', 'text' => lang('Requests, social support and course sign-ups close to home.'),
        'hours' => array(array(lang('Monday to Friday'), lang('9:00 to 17:00')), array(lang('Saturday and Sunday'), lang('Closed')))),
);

// Numbers worth keeping (the useful numbers page and the footer).
$useful_numbers = array(
    array('icon' => 'bi-telephone-plus', 'title' => lang('Emergency'), 'number' => '112', 'text' => lang('Fire, ambulance and police')),
    array('icon' => 'bi-headset', 'title' => lang('Citizen line'), 'number' => '+90 555 000 01 53', 'text' => lang('Every request and question, around the clock')),
    array('icon' => 'bi-droplet', 'title' => lang('Water faults'), 'number' => '+90 555 000 04 10', 'text' => lang('Burst pipes and no water')),
    array('icon' => 'bi-lightbulb', 'title' => lang('Street lights'), 'number' => '+90 555 000 04 20', 'text' => lang('A dark street or a broken light')),
    array('icon' => 'bi-heart-pulse', 'title' => lang('Funeral services'), 'number' => '+90 555 000 04 30', 'text' => lang('A hearse and the cemetery office')),
    array('icon' => 'bi-bug', 'title' => lang('Animal care'), 'number' => '+90 555 000 04 40', 'text' => lang('Injured or sick stray animals')),
);

// The city in four figures (the home and the city page).
$figures = array(
    array('value' => '186,000', 'label' => lang('Residents')),
    array('value' => '8', 'label' => lang('Neighbourhoods')),
    array('value' => '140', 'label' => lang('Parks and playgrounds')),
    array('value' => '94%', 'label' => lang('Requests answered within a week')),
);

// The sample notices: the records the Notices form starts with. The dates
// are words without a year, so the sample never reads as an old notice;
// the city writes the real date in its own record. Each notice has a type
// (the list's filter, ?type=) and a reference number.
$notice_types = array(
    'notice'   => lang('Public Notice'),
    'tender'   => lang('Tender'),
    'decision' => lang('Council Decision'),
    'vacancy'  => lang('Vacancy'),
    'zoning'   => lang('Zoning Notice'),
);
$notices = array(
    'water_cut' => array('type' => 'notice', 'title' => lang('Planned Water Cut in Riverside'), 'ref' => 'PN-014',
        'published' => lang('Published this week'), 'deadline' => lang('Tuesday, 9:00 to 15:00'), 'department' => 'works',
        'summary' => lang('Water will be off in part of Riverside while a main pipe is renewed.'),
        'details' => array(lang('Streets: River Road, Mill Lane and Bridge Street'), lang('Water tankers will stand in Mill Square'), lang('Keep taps closed until the water is back'), lang('Questions: the citizen line')),
        'featured' => true),
    'park_tender' => array('type' => 'tender', 'title' => lang('Tender: Renewal of the City Park Playground'), 'ref' => 'TN-022',
        'published' => lang('Published two weeks ago'), 'deadline' => lang('Bids close in four weeks, at 10:00'), 'department' => 'finance',
        'summary' => lang('Open tender for the design and build of a new accessible playground in the City Park.'),
        'details' => array(lang('Open procedure, lowest compliant bid'), lang('Tender documents from the Finance office'), lang('Bids by hand to the City Hall, 2nd floor'), lang('Bids are opened in public in the council hall')),
        'featured' => true),
    'council_agenda' => array('type' => 'decision', 'title' => lang('Council Meeting Agenda and Decisions'), 'ref' => 'CD-031',
        'published' => lang('Published after each meeting'), 'deadline' => lang('No deadline'), 'department' => 'clerks',
        'summary' => lang('The agenda of the next council meeting and the decisions of the last one.'),
        'details' => array(lang('Budget changes for the parks programme'), lang('New bus stops on Station Road'), lang('The name of the new library'), lang('Questions from residents')),
        'featured' => true),
    'engineer_vacancy' => array('type' => 'vacancy', 'title' => lang('Vacancy: Civil Engineer'), 'ref' => 'VC-007',
        'published' => lang('Published last week'), 'deadline' => lang('Applications close in three weeks'), 'department' => 'works',
        'summary' => lang('The Technical Works department is hiring a civil engineer for roads and bridges.'),
        'details' => array(lang('A degree in civil engineering'), lang('Three years of experience on site'), lang('A driving licence'), lang('Applications with a CV to the Human Resources office')),
        'featured' => false),
    'harbour_plan' => array('type' => 'zoning', 'title' => lang('Zoning Plan Change: Harbour Quarter'), 'ref' => 'ZN-003',
        'published' => lang('Published this month'), 'deadline' => lang('Objections within thirty days'), 'department' => 'planning',
        'summary' => lang('A change to the zoning plan of the Harbour quarter is open for objections.'),
        'details' => array(lang('A new public square on the old warehouse site'), lang('Lower building heights along the shore'), lang('The plan can be seen in the Planning office'), lang('Objections in writing to the City Hall')),
        'featured' => false),
    'market_days' => array('type' => 'notice', 'title' => lang('New Market Days in Orchard'), 'ref' => 'PN-015',
        'published' => lang('Published this month'), 'deadline' => lang('From next week'), 'department' => 'licensing',
        'summary' => lang('The Orchard street market moves to Wednesday and Saturday mornings.'),
        'details' => array(lang('Wednesday and Saturday, 7:00 to 14:00'), lang('Orchard Square and Plum Street'), lang('Parking on Plum Street is closed on market days'), lang('Stall holders keep their places')),
        'featured' => false),
);
// The departments that publish notices: every department and the council's
// clerks, who publish the agenda and the decisions.
$clerks_title = lang('Council Clerks');

// The sample events: the records the Events form starts with. The date is
// words without a year, like the notices. Each event has a category (the
// list's filter, ?category=).
$event_categories = array(
    'culture'  => lang('Culture'),
    'sport'    => lang('Sport'),
    'children' => lang('Children'),
    'market'   => lang('Market Days'),
    'ceremony' => lang('Ceremonies'),
);
$events = array(
    'summer_festival' => array('title' => lang('City Summer Festival'), 'category' => 'culture',
        'when' => lang('A weekend in early summer, from 16:00'), 'place' => lang('Republic Square and the harbour front'),
        'summary' => lang('Three evenings of concerts, street theatre and food from every neighbourhood.'),
        'details' => array(lang('Concerts on the main stage every evening'), lang('Street theatre in the Old Town'), lang('Food stalls run by neighbourhood associations'), lang('Free entry for everyone')),
        'featured' => true),
    'city_run' => array('title' => lang('City Run'), 'category' => 'sport',
        'when' => lang('A Sunday morning in autumn, 9:00'), 'place' => lang('Start and finish at City Park'),
        'summary' => lang('A 5 km and a 10 km run along the river, with a children\'s race before the start.'),
        'details' => array(lang('Sign up at the cultural centre or on the day'), lang('Roads along the river closed until noon'), lang('Water points every two kilometres'), lang('A medal for every runner')),
        'featured' => true),
    'children_week' => array('title' => lang('Children\'s Week'), 'category' => 'children',
        'when' => lang('A week in spring, every afternoon'), 'place' => lang('Parks and libraries in every neighbourhood'),
        'summary' => lang('Puppet shows, storytelling, games and workshops for children in every neighbourhood.'),
        'details' => array(lang('Puppet shows in the parks'), lang('Storytelling in the libraries'), lang('Science and craft workshops'), lang('A parade on the last day')),
        'featured' => true),
    'farmers_market' => array('title' => lang('Farmers\' Market'), 'category' => 'market',
        'when' => lang('Every Saturday morning, 8:00 to 13:00'), 'place' => lang('Orchard Square'),
        'summary' => lang('Fruit, vegetables, cheese and bread from the farms around the city.'),
        'details' => array(lang('Only growers and makers from the region'), lang('Bring your own bag'), lang('Bicycle parking next to the square'), lang('Stall requests to the Licensing office')),
        'featured' => false),
    'film_nights' => array('title' => lang('Open-Air Film Nights'), 'category' => 'culture',
        'when' => lang('Friday evenings in summer, after sunset'), 'place' => lang('The amphitheatre in City Park'),
        'summary' => lang('Family films under the stars, free for everyone.'),
        'details' => array(lang('Bring a blanket or a cushion'), lang('Seats for older and disabled guests'), lang('Films with subtitles'), lang('Cancelled if it rains')),
        'featured' => false),
    'memorial' => array('title' => lang('Founding Day Ceremony'), 'category' => 'ceremony',
        'when' => lang('Founding Day, 10:00'), 'place' => lang('Republic Square'),
        'summary' => lang('The city remembers its founding with a ceremony, music and an open day at the City Hall.'),
        'details' => array(lang('Words from the mayor and the council'), lang('The city band and the children\'s choir'), lang('An open day in the council hall'), lang('Old photographs of the city on show')),
        'featured' => false),
);

// A notice's or an event's details as the value of its Details field: one
// line per item.
$notice_details_text = function ($key) use ($notices) {
    return implode("\n", $notices[$key]['details']);
};
$event_details_text = function ($key) use ($events) {
    return implode("\n", $events[$key]['details']);
};
// The words of a notice's department, the value of its Department field.
$notice_department = function ($key) use ($notices, $departments, $clerks_title) {
    $d = $notices[$key]['department'];
    return $d === 'clerks' ? $clerks_title : $departments[$d]['title'];
};

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the resident chose.
$notice_type_titles = array_values($notice_types);
$event_category_titles = array_values($event_categories);
$department_titles = array();
foreach ($departments as $dep) $department_titles[] = $dep['title'];
$notice_department_titles = array_merge($department_titles, array($clerks_title));
$area_titles = array();
foreach ($service_areas as $a) $area_titles[] = $a['title'];
// The request form's Topic list (?topic= from a service area's page).
$request_topic_choices = array_merge($area_titles, array($other_topic));
$request_kinds = array(lang('Service request'), lang('Complaint'), lang('Suggestion'), lang('Question'));
$neighbourhood_choices = array_merge($neighbourhoods, array(lang('Outside the city')));
// The states of a request, set by the city hall in the panel.
$request_statuses = array(lang('Request received'), lang('Sent to the department'), lang('In progress'), lang('Resolved'), lang('Closed'));
// A meeting with the mayor's office.
$meeting_subjects = array(lang('A neighbourhood matter'), lang('A project or an idea'), lang('An association or a club'), lang('A personal matter'));
$meeting_times = array(lang('A weekday morning'), lang('A weekday afternoon'), lang('Any time'));
// The states of a meeting request, set by the mayor's office in the panel.
$meeting_statuses = array(lang('Request received'), lang('Meeting booked'), lang('Meeting held'), lang('Not possible this time'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The query string of a link that opens a list with a filter set (?type=,
// ?category=) or a form with a choice made (?topic=): the form's fields
// start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the request band above the footer unless the page is
// a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('request_band');
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
            $heading('h1', $title, 'pcy-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pcy-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pcy-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the service areas and the city's points are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'cy-faq') use ($el, $para, $attr, $searchable) {
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


// The city's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pcy-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// A phone number as a link the phone can call.
$tel = function ($number) {
    return 'tel:' . preg_replace('/[^0-9+]/', '', $number);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the citizen line and the way to make a
// request. A shared component, so the city changes it once for the site.
$notice = function () use ($el, $icon, $span, $link, $city, $tel) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-headset', 'text-primary'),
        $span(lang('Citizen line, around the clock:'), '', lang('Text')),
        $link($city['line'], $tel($city['line']), 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component. The city hall sells nothing online, so the bar carries no
// cart.
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

// The header: the city's name, the menu and the way to make a request. The
// links to the current page are marked active on the server (smart active
// state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('services', lang('City Services')),
        array('notices', lang('Public Notices')),
        array('events', lang('Events')),
        array('council', lang('Council')),
        array('departments', lang('City Departments')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-building', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pcy-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#cy-nav'),
                    $attr('aria-controls', 'cy-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-chat-square-text'),
                            $span(lang('Make a Request'), '', lang('Link Text')),
                        ), array('href' => '{{page:request}}')),
                    )),
                ), array('id' => 'cy-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the city hall in a sentence, how to reach it
// and its profiles, the service areas, the notices by type, the residents'
// pages and the city hall's pages; at the bottom the year, the site's name
// and the hours of the citizen line.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $service_areas, $notice_type_titles, $city, $query, $attr, $tel) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $area_links = array($link(lang('All Services'), '{{page:services}}', $plain));
    foreach ($service_areas as $key => $a) $area_links[] = $link($a['title'], '{{page:service_' . $key . '}}', $plain);
    $notice_links = array($link(lang('All Notices'), '{{page:notices}}', $plain));
    foreach ($notice_type_titles as $title) $notice_links[] = $link($title, '{{page:notices}}?' . $query(array('type' => $title)), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the City Hall'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-building', 'text-primary'),
                        $span('{{site_name}}', 'pcy-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($city['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($city['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($city['phone'], $tel($city['phone']), $plain), lang('Phone')),
                        $contact_line('bi-headset', $link($city['line'], $tel($city['line']), $plain), lang('Citizen line')),
                        $contact_line('bi-envelope', $link($city['email'], 'mailto:' . $city['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($city['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-twitter-x', 'X'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Service Links'), array(
                    $heading('h2', lang('City Services'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($area_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Notice Links'), array(
                    $heading('h2', lang('Public Notices'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($notice_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Residents'), array(
                    $heading('h2', lang('For Residents'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Make a Request'), '{{page:request}}', $plain),
                        $link(lang('My Requests'), '{{page:my_requests}}', $plain),
                        $link(lang('Meet the Mayor\'s Office'), '{{page:meet}}', $plain),
                        $link(lang('Service Points'), '{{page:service_points}}', $plain),
                        $link(lang('Useful Numbers'), '{{page:numbers}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('City Hall'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('The Mayor'), '{{page:mayor}}', $plain),
                        $link(lang('Council'), '{{page:council}}', $plain),
                        $link(lang('City Departments'), '{{page:departments}}', $plain),
                        $link(lang('Our City'), '{{page:city}}', $plain),
                        $link(lang('Events'), '{{page:events}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($city['line_hours'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The request band: a full-width band in the palette's colour with the
// ways to make a request and to follow one. Shared, so every page that
// ends with it carries the same call.
$request_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Something to fix, ask or suggest?'), 'pcy-display h2 mb-2'),
                    $para(lang('Send a request in two minutes and follow every answer from your account. The citizen line takes calls around the clock.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Make a Request'), '{{page:request}}', 'btn btn-light btn-lg px-4'),
                        $link(lang('My Requests'), '{{page:my_requests}}', 'btn btn-outline-light btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// ── Cards ───────────────────────────────────────────────────────────────

// A service area's card (the home and the services page): the icon, the
// title linking the area's page and the summary.
$area_card = function ($key) use ($el, $para, $link, $col, $icon_tile, $service_areas) {
    $a = $service_areas[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 p-4 pcy-lift', lang('Service Area Card'), array(
            $icon_tile($a['icon'], 'mb-3 align-self-start'),
            $el('h3', 'pcy-display h5 position-static mb-2', lang('Service Area Name'), array(
                $link($a['title'], '{{page:service_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
            )),
            $para($a['summary'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// A service of an area (the area's page): what it is, the documents to
// bring, and the request form with the area chosen.
$service_card = function ($key, $n) use ($el, $para, $span, $link, $col, $icon, $heading, $service_areas, $query) {
    $a = $service_areas[$key];
    $s = $a['services'][$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3', lang('Service Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $heading('h3', $s['title'], 'pcy-display h5 mb-2'),
                $para($s['text'], 'small text-body-secondary mb-3'),
                $el('p', 'd-flex align-items-start gap-2 small mb-3', lang('Documents'), array(
                    $icon('bi-file-earmark-text', 'text-primary'),
                    $span($s['docs'], '', lang('Documents')),
                )),
                $link(lang('Make a Request'), '{{page:request}}?' . $query(array('topic' => $a['title'])), 'btn btn-sm btn-outline-primary mt-auto align-self-start'),
            )),
        )),
    ), '6', '4');
};

// A department's card (the departments page and the service-area pages):
// the head, where to find them and the phone.
$department_card = function ($key) use ($el, $para, $span, $link, $col, $icon, $heading, $icon_tile, $departments, $tel) {
    $d = $departments[$key];
    $line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('li', 'd-flex align-items-start gap-2 mb-1', $name, array($icon($icon_name, 'text-primary'), $child));
    };
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 p-4', lang('Department Card'), array(
            $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Title'), array(
                $icon_tile($d['icon']),
                $heading('h3', $d['title'], 'pcy-display h5 mb-0'),
            )),
            $para($d['text'], 'small text-body-secondary mb-3'),
            $el('ul', 'list-unstyled small mb-0', lang('Department Details'), array(
                $line('bi-person-badge', $span($d['head'], 'fw-semibold', lang('Head of Department')), lang('Head of Department')),
                $line('bi-door-open', $span($d['floor'], '', lang('Where')), lang('Where')),
                $line('bi-telephone', $link($d['phone'], $tel($d['phone']), 'link-body-emphasis text-decoration-none'), lang('Phone')),
            )),
        )),
    ), '6', '4');
};

// A council member (the council page): the portrait, the name, the role
// and the committee.
$council_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $council, $committees) {
    $m = $council[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 text-center p-4', lang('Council Member'), array(
            $image($portrait($m['name'], 240), $m['name'], 'rounded-circle mx-auto mb-3', array('_attrs' => array($attr('width', '112'), $attr('height', '112'), $attr('loading', 'lazy')))),
            $heading('h3', $m['name'], 'pcy-display h6 mb-1'),
            $para($m['role'], 'small fw-semibold text-primary mb-1'),
            $para($committees[$m['committee']]['title'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3', '', '6');
};

// A service point (the service points page and the contact page): the
// address, the phone and the hours, with a link that opens the address in
// a map (spaces as '+', so a house number never reads as a year after
// '%20').
$hours_table = function ($rows) use ($el, $attr) {
    $trs = array();
    foreach ($rows as $r) {
        $trs[] = $el('tr', '', lang('Table Row'), array(
            $el('th', 'fw-normal text-body-secondary ps-0', lang('Days'), array(), array('text' => $r[0], '_attrs' => array($attr('scope', 'row')))),
            $el('td', 'text-end pe-0', lang('Hours'), array(), array('text' => $r[1])),
        ));
    }
    return $el('table', 'table table-sm small mb-0', lang('Opening Hours'), array($el('tbody', '', lang('Table Body'), $trs)));
};
$point_card = function ($key) use ($el, $para, $link, $col, $heading, $icon_tile, $hours_table, $service_points, $tel, $attr) {
    $p = $service_points[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3', lang('Service Point Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Title'), array(
                    $icon_tile($p['icon']),
                    $heading('h3', $p['title'], 'pcy-display h5 mb-0'),
                )),
                $para($p['text'], 'small text-body-secondary mb-2'),
                $para($p['address'], 'small fw-semibold mb-1'),
                $link($p['phone'], $tel($p['phone']), 'small link-body-emphasis text-decoration-none d-block mb-3'),
                $hours_table($p['hours']),
                $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?' . http_build_query(array('query' => $p['address'])), 'small fw-semibold link-primary mt-3', array($attr('rel', 'noopener')), array('target' => '_blank')),
            )),
        )),
    ), '6', '6', '', '12');
};

// A useful number (the useful numbers page): the service and its number,
// which a phone calls.
$number_card = function ($n) use ($el, $para, $link, $col, $heading, $icon_tile, $useful_numbers, $tel) {
    $u = $useful_numbers[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 p-4', lang('Number Card'), array(
            $el('div', 'd-flex align-items-center gap-3', lang('Title'), array(
                $icon_tile($u['icon']),
                $el('div', '', lang('Text'), array(
                    $heading('h3', $u['title'], 'h6 fw-bold mb-1'),
                    $link($u['number'], $tel($u['number']), 'pcy-stat d-block fs-5 fw-bold link-primary text-decoration-none'),
                    $para($u['text'], 'small text-body-secondary mb-0'),
                )),
            )),
        )),
    ), '6', '4');
};

// The facts of a notice in one list: the reference, the type, the
// department, when it was published and its deadline, each bound to the
// Notices form's field of the same name.
$notice_facts = function ($sample_key = 'park_tender', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $notices, $notice_types, $notice_department) {
    $n = $notices[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Notice Facts'), array(
        $fact('bi-hash', $n['ref'], 'reference', lang('Reference')),
        $fact('bi-tag', $notice_types[$n['type']], 'type', lang('Type')),
        $fact('bi-diagram-3', $notice_department($sample_key), 'department', lang('Department')),
        $fact('bi-calendar', $n['published'], 'published', lang('Published')),
        $fact('bi-hourglass-split', $n['deadline'], 'deadline', lang('Deadline')),
    ));
};

// A notice's card of the lists (the home and the notice list): the type
// and reference, the title linking the notice page, the deadline and the
// summary. Every value is bound to the Notices form's field of the same
// name; the sample fills the canvas.
$notice_card = function ($sample_key = 'park_tender') use ($el, $para, $span, $link, $col, $bind, $icon, $notices, $notice_types) {
    $n = $notices[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 border-start border-4 border-primary shadow-sm rounded-3 pcy-lift', lang('Notice Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Type and Reference'), array(
                    $span($notice_types[$n['type']], 'badge text-bg-primary', lang('Type'), $bind('type')),
                    $span($n['ref'], 'pcy-ref small text-body-secondary', lang('Reference'), $bind('reference')),
                )),
                $el('h3', 'pcy-display h5 position-static mb-2', lang('Notice Title'), array(
                    $link($n['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($n['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('p', 'd-flex align-items-start gap-1 small fw-semibold mt-auto mb-0', lang('Deadline'), array(
                    $icon('bi-hourglass-split', 'text-primary'),
                    $span($n['deadline'], '', lang('Deadline'), $bind('deadline')),
                )),
            )),
        )),
    ), '6', '4');
};

// The facts of an event in one list: category, when and where, each bound
// to the Events form's field of the same name.
$event_facts = function ($sample_key = 'summer_festival', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $events, $event_categories) {
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
        $fact('bi-tag', $event_categories[$e['category']], 'category', lang('Category')),
    ));
};

// An event's card of the lists (the home and the event list): the photo,
// the category, the title linking the event page, when, where and the
// summary, each bound to the Events form's field of the same name.
$event_card = function ($sample_key = 'summer_festival') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $events, $event_categories, $photo, $attr) {
    $e = $events[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden pcy-lift', lang('Event Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('event-' . $sample_key, 800, 450), $e['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_categories[$e['category']], 'pcy-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Category'), $bind('category')),
                $el('h3', 'pcy-display h5 position-static mb-2', lang('Event Title'), array(
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
            $span(lang('Staff'), 'badge text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('staff_requests', lang('Citizen Requests')),
                $item('staff_meetings', lang('Meeting Requests')),
                $item('notice_new', lang('New Notice')),
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
    return $agree($id, 'consent', lang('I would like to receive the city hall\'s news, notices and event invitations by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// request takes a photo of the place or a document, and a notice its
// tender papers, so the default accepts PDFs, office files, text and
// pictures; one that takes only pictures is a collection's image kind
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
                    $link(lang('New benches and shade trees for Republic Square'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

