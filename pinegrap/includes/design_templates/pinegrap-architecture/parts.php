<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Architecture & Interiors Studio", part 1 of its files:
 * the node builders, the studio's own data (the services, the project
 * types and locations, the sample projects with their facts, brief and
 * credits, the stages of work, the team, the awards and the press, its
 * figures, what clients say, the job openings and the answers of the
 * forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, enquiry band, project, service, team, award, press,
 * client and job cards, project facts, credits, gallery, page shells,
 * section headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-architecture.php puts them together.
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

// A node with an interaction (props._fx, played by pg_fx; reduced motion
// is honoured there).
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// A list or a row whose items rise in one after another on view.
$rise = function ($node, $stagger = 80) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger);
    return $node;
};

// A sample picture with a fixed seed, so the same picture comes back on
// every opening until the studio puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-architecture-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e5e7eb/1f2937?text=' . rawurlencode($initials !== '' ? $initials : 'A');
};


// ── The studio: its services, its projects and its people ───────────────

// The studio itself. The studio page tells its story; the footer, the
// enquiry band and the contact lines use the address, phone and hours.
$studio = array(
    'tagline' => lang('Architecture and interiors for homes, workplaces and public places, drawn around light and the people who use them'),
    'story'   => lang('Two architects opened the studio in a former print works with one drawing table each. Today a team of architects, interior designers and landscape architects works on houses, workplaces, hotels and public buildings, and every project still starts the same way: a long conversation, a walk around the site and a sketch on paper.'),
    'address' => lang('Old Print Works, Studio 3, Harbour Lane 14'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'studio@example.com',
    'hours'   => lang('Monday to Friday, 9:30 to 18:30; visits by appointment'),
    'licence' => lang('Registered architects; every project is insured for professional liability'),
);

// The services. Each has its own page (service_<key>); the enquiry and the
// consultation open with the service chosen (?service=) in its own words.
$services = array(
    'architecture'   => array('icon' => 'bi-building', 'title' => lang('Architecture'),
        'summary' => lang('New buildings and extensions, from the first sketch to the last day on site.'),
        'text'    => lang('We design houses, workplaces and public buildings that sit well on their site and age well. We take the design through planning and the technical drawings, and we stay on site with the builder until the keys are handed over.'),
        'points'  => array(lang('Feasibility studies and site analysis'), lang('Concept and planning applications'), lang('Technical drawings and tender documents'), lang('Supervision on site until handover'))),
    'interiors'      => array('icon' => 'bi-lamp', 'title' => lang('Interior Design'),
        'summary' => lang('Interiors for homes, offices, hotels and restaurants, down to the door handles.'),
        'text'    => lang('An interior is where a building is lived in. We plan the spaces, choose the materials, light and colours, and draw the joinery, so that every room works as well as it looks.'),
        'points'  => array(lang('Space planning'), lang('Materials, colour and finishes'), lang('Lighting design'), lang('Joinery and fitted furniture'))),
    'adaptive_reuse' => array('icon' => 'bi-arrow-repeat', 'title' => lang('Adaptive Reuse'),
        'summary' => lang('New lives for old buildings: warehouses, mills and houses with a history.'),
        'text'    => lang('The greenest building is often the one already standing. We survey old buildings with care, keep what gives them character and add what a new use needs, working with conservation officers from the first meeting.'),
        'points'  => array(lang('Measured surveys and condition reports'), lang('Change of use and listed building consent'), lang('Restoration of historic details'), lang('Energy upgrades for old buildings'))),
    'masterplanning' => array('icon' => 'bi-grid-3x3', 'title' => lang('Masterplanning'),
        'summary' => lang('Neighbourhoods, campuses and town centres planned around streets and squares.'),
        'text'    => lang('A masterplan decides how a place will feel for generations. We plan the streets, the open spaces and the mix of uses with the people who live and work there, and draw design codes that keep each building part of the whole.'),
        'points'  => array(lang('Urban analysis and public consultation'), lang('Masterplans and design codes'), lang('Streets, squares and public space'), lang('Phasing and planning strategy'))),
    'landscape'      => array('icon' => 'bi-tree', 'title' => lang('Landscape Architecture'),
        'summary' => lang('Gardens, courtyards, roof terraces and parks designed with the buildings.'),
        'text'    => lang('Our landscape architects work beside the architects from the first sketch, so the garden, the terrace and the building are one design. We plant for the local climate and for little watering.'),
        'points'  => array(lang('Private gardens and courtyards'), lang('Roof gardens and terraces'), lang('Public parks and playgrounds'), lang('Planting plans for a dry climate'))),
    'furniture'      => array('icon' => 'bi-easel', 'title' => lang('Furniture and Objects'),
        'summary' => lang('Bespoke furniture, lighting and objects made with local workshops.'),
        'text'    => lang('When the right piece does not exist, we draw it. Our furniture and lights are made in small numbers by carpenters, metalworkers and glassblowers we have worked with for years.'),
        'points'  => array(lang('Bespoke tables, seating and storage'), lang('Light fittings'), lang('Reception desks and counters'), lang('Small series for hotels and restaurants'))),
);

// The project types, the project list's first filter.
$project_types = array(
    'homes'       => array('icon' => 'bi-house', 'title' => lang('Homes'),
        'text' => lang('Houses, apartments and extensions.')),
    'workplace'   => array('icon' => 'bi-briefcase', 'title' => lang('Workplaces'),
        'text' => lang('Offices, studios and workshops.')),
    'hospitality' => array('icon' => 'bi-cup-hot', 'title' => lang('Hospitality'),
        'text' => lang('Hotels, restaurants and cafés.')),
    'culture'     => array('icon' => 'bi-bank', 'title' => lang('Culture and Education'),
        'text' => lang('Libraries, galleries, schools and community buildings.')),
    'public'      => array('icon' => 'bi-signpost-split', 'title' => lang('Public Space'),
        'text' => lang('Squares, parks, streets and masterplans.')),
);

// The places the studio has worked, the project list's second filter.
$locations = array(
    'istanbul' => lang('Istanbul'),
    'izmir'    => lang('Izmir'),
    'bodrum'   => lang('Bodrum'),
    'london'   => lang('London'),
);

// The stage a project has reached, the value of its Stage field: never a
// year, which would date the sample.
$project_stages = array(
    'completed'   => lang('Completed'),
    'on_site'     => lang('On site'),
    'in_design'   => lang('In design'),
    'competition' => lang('Competition entry'),
);

// The sample projects: the records the Projects form starts with. The size
// is words; the credits name who the studio designed and built it with,
// one "Role: Name" line each in the Credits field.
$projects = array(
    'courtyard_house' => array('name' => lang('Courtyard House'), 'type' => 'homes', 'service' => 'architecture',
        'location' => 'bodrum', 'place' => lang('On a hillside above the bay'), 'size' => lang('A house of 420 m² around a shaded courtyard'),
        'stage' => 'completed', 'client' => lang('A private client'),
        'summary' => lang('A family house built of local stone around a courtyard with an old olive tree, open to the sea breeze and closed to the afternoon sun.'),
        'brief'   => lang('The family asked for a house that would stay cool without air conditioning for most of the summer, keep the olive tree at its heart and let three generations live together and apart.'),
        'credits' => array(array(lang('Structural engineer'), lang('Atlas Engineering')), array(lang('Builder'), lang('Ege Stone Builders')), array(lang('Landscape'), lang('The studio\'s landscape team')), array(lang('Photography'), lang('Deniz Kaya'))),
        'quote'   => lang('We spend the whole day in the courtyard, and the house stays cool even in the hottest weeks.'),
        'featured' => true),
    'print_works' => array('name' => lang('The Print Works Offices'), 'type' => 'workplace', 'service' => 'adaptive_reuse',
        'location' => 'istanbul', 'place' => lang('In an old industrial quarter by the water'), 'size' => lang('3,800 m² over four floors'),
        'stage' => 'completed', 'client' => lang('Kervan Media'),
        'summary' => lang('A former printing works turned into offices for a media company, keeping its brick vaults, steel columns and the old press hall.'),
        'brief'   => lang('A growing company wanted one home for four teams, a hall for events and a building that still told the story of the street.'),
        'credits' => array(array(lang('Structural engineer'), lang('Kubbe Structures')), array(lang('Services engineer'), lang('Akım Building Services')), array(lang('Builder'), lang('Haliç Restoration')), array(lang('Photography'), lang('Mert Yalın'))),
        'quote'   => lang('The old press hall is now where the whole company meets, and visitors always ask about it first.'),
        'featured' => true),
    'harbour_hotel' => array('name' => lang('Harbour Hotel Interiors'), 'type' => 'hospitality', 'service' => 'interiors',
        'location' => 'izmir', 'place' => lang('On the seafront promenade'), 'size' => lang('64 rooms, a restaurant and a roof bar'),
        'stage' => 'completed', 'client' => lang('Liman Hotels'),
        'summary' => lang('Interiors for a seafront hotel in sand-coloured plaster, oak and woven cane, with furniture made by local workshops.'),
        'brief'   => lang('The owners wanted guests to feel the sea from every room and the building to feel made in the city, not ordered from a catalogue.'),
        'credits' => array(array(lang('Lighting design'), lang('Lumen Light Studio')), array(lang('Joinery'), lang('Meşe Woodworks')), array(lang('Builder'), lang('Körfez Fit-out')), array(lang('Photography'), lang('Deniz Kaya'))),
        'quote'   => lang('Guests write about the rooms in their reviews more than about the view.'),
        'featured' => true),
    'garden_library' => array('name' => lang('Garden Library'), 'type' => 'culture', 'service' => 'architecture',
        'location' => 'london', 'place' => lang('In a public park'), 'size' => lang('A library of 1,200 m² with a reading garden'),
        'stage' => 'on_site', 'client' => lang('The Borough Libraries Service'),
        'summary' => lang('A timber library under a planted roof, with reading rooms that open onto a walled garden.'),
        'brief'   => lang('A neighbourhood library to replace a closed one: a quiet place for study, a lively one for children, and a building that gives the park back more than it takes.'),
        'credits' => array(array(lang('Structural engineer'), lang('Timber Frame Partners')), array(lang('Landscape'), lang('The studio\'s landscape team')), array(lang('Cost consultant'), lang('Measure and Co.'))),
        'quote'   => lang('The design workshops brought in people who had never used the old library.'),
        'featured' => false),
    'market_square' => array('name' => lang('Market Square'), 'type' => 'public', 'service' => 'masterplanning',
        'location' => 'izmir', 'place' => lang('In the old town centre'), 'size' => lang('A square of 9,000 m² and five streets'),
        'stage' => 'in_design', 'client' => lang('The Municipality'),
        'summary' => lang('A car park given back to people as a shaded square for a weekly market, with trees, water and benches.'),
        'brief'   => lang('The town asked for a square that would work on market days and on empty winter evenings, and for streets that walkers could cross safely.'),
        'credits' => array(array(lang('Landscape'), lang('The studio\'s landscape team')), array(lang('Traffic engineer'), lang('Yol Mobility')), array(lang('Public consultation'), lang('Forum Participation'))),
        'quote'   => lang('Hundreds of residents drew their own square with the studio, and you can see their ideas in the plan.'),
        'featured' => false),
    'bosphorus_flat' => array('name' => lang('Bosphorus Apartment'), 'type' => 'homes', 'service' => 'interiors',
        'location' => 'istanbul', 'place' => lang('In a building from the turn of the last century'), 'size' => lang('An apartment of 180 m²'),
        'stage' => 'completed', 'client' => lang('A private client'),
        'summary' => lang('An apartment with high ceilings restored and opened up, with a kitchen in oak and terrazzo and the original parquet repaired.'),
        'brief'   => lang('A couple wanted to keep the plaster ceilings and the parquet, cook for friends in a large kitchen and work at home in a quiet room.'),
        'credits' => array(array(lang('Joinery'), lang('Meşe Woodworks')), array(lang('Builder'), lang('Haliç Restoration')), array(lang('Photography'), lang('Mert Yalın'))),
        'quote'   => lang('The apartment feels as old as it is and works like a new one.'),
        'featured' => false),
);

// A project's credits as the value of its Credits field: one line each.
$credits_text = function ($key) use ($projects) {
    $lines = array();
    foreach ($projects[$key]['credits'] as $c) $lines[] = $c[0] . ': ' . $c[1];
    return implode("\n", $lines);
};

// How a project runs, from the first conversation to the years after
// handover (the process page and the home).
$stages = array(
    array('title' => lang('Brief and site'), 'text' => lang('We listen to what you need, walk the site with you and agree the brief, the budget and the fee.')),
    array('title' => lang('Concept design'), 'text' => lang('Sketches, models and a few clear options, until one feels right to you.')),
    array('title' => lang('Developed design and permits'), 'text' => lang('We work the design out with the engineers and apply for the permits it needs.')),
    array('title' => lang('Technical design and tender'), 'text' => lang('Detailed drawings and specifications, and help choosing the right builder.')),
    array('title' => lang('On site'), 'text' => lang('We visit the site every week, answer the builder\'s questions and check the work.')),
    array('title' => lang('Handover and aftercare'), 'text' => lang('We hand over the building with you and come back after the first year to see how it is used.')),
);

// What the studio believes in (the studio page).
$principles = array(
    array('icon' => 'bi-brightness-high', 'title' => lang('Light first'),
        'text' => lang('We draw every room around the light it will have at each hour of the day.')),
    array('icon' => 'bi-hourglass', 'title' => lang('Built to last'),
        'text' => lang('Plain, honest materials that age well and can be repaired.')),
    array('icon' => 'bi-thermometer-sun', 'title' => lang('Low energy'),
        'text' => lang('Shade, thick walls and cross ventilation before machines.')),
    array('icon' => 'bi-chat-square-text', 'title' => lang('Listening before drawing'),
        'text' => lang('The best ideas often come from the people who will use the building.')),
);

// The materials the studio builds with (the material board of the home):
// the sample picture's seed, the name, the finish and where it was used.
$materials = array(
    array('seed' => 'material-stone', 'title' => lang('Local stone'), 'alt' => lang('A wall of rough local stone'),
        'finish' => lang('Split face, laid dry'), 'used' => lang('Walls and garden terraces')),
    array('seed' => 'material-oak', 'title' => lang('Oak'), 'alt' => lang('Oiled oak boards'),
        'finish' => lang('Oiled, wide boards'), 'used' => lang('Floors, stairs and joinery')),
    array('seed' => 'material-lime', 'title' => lang('Lime plaster'), 'alt' => lang('A wall of warm white lime plaster'),
        'finish' => lang('Hand-trowelled, warm white'), 'used' => lang('Walls and ceilings')),
    array('seed' => 'material-brick', 'title' => lang('Handmade brick'), 'alt' => lang('Handmade bricks in a long bond'),
        'finish' => lang('Long bond, pale mortar'), 'used' => lang('Facades and courtyards')),
    array('seed' => 'material-terrazzo', 'title' => lang('Terrazzo'), 'alt' => lang('A polished terrazzo floor'),
        'finish' => lang('Polished, local aggregate'), 'used' => lang('Kitchens and bathrooms')),
    array('seed' => 'material-brass', 'title' => lang('Brass'), 'alt' => lang('Brushed brass handles'),
        'finish' => lang('Brushed, left to darken'), 'used' => lang('Handles, lamps and rails')),
);

// The studio's people (the team page and the studio page). Fixed data, not
// a collection: a small studio's team changes a few times a year.
$team = array(
    array('name' => lang('Leyla Aksoy'), 'role' => lang('Founding Partner, Architect'),
        'bio' => lang('Leads the studio\'s houses and cultural buildings, and still draws the first sketch of every project by hand.')),
    array('name' => lang('Tom Hartley'), 'role' => lang('Founding Partner, Architect'),
        'bio' => lang('Leads the workplace and adaptive reuse projects, and the studio\'s work in London.')),
    array('name' => lang('Elif Demirci'), 'role' => lang('Head of Interiors'),
        'bio' => lang('An interior designer who chooses every material by hand and knows every workshop in town.')),
    array('name' => lang('Can Erdem'), 'role' => lang('Associate, Masterplanning'),
        'bio' => lang('Plans squares and neighbourhoods, and runs the studio\'s public workshops.')),
    array('name' => lang('Nora Lind'), 'role' => lang('Landscape Architect'),
        'bio' => lang('Designs gardens and parks for a dry climate, with plants that need little water.')),
    array('name' => lang('Burak Tan'), 'role' => lang('Studio Manager'),
        'bio' => lang('Runs the studio, the fees and the programmes, and answers most of the enquiries.')),
);

// The awards the studio's projects have won or been shortlisted for. The
// prize is named, never the year: a year would date the sample.
$award_results = array('winner' => lang('Winner'), 'shortlisted' => lang('Shortlisted'), 'commended' => lang('Commended'));
$awards = array(
    array('award' => lang('National Architecture Prize, Houses'), 'result' => 'winner', 'project' => 'courtyard_house'),
    array('award' => lang('Heritage Reuse Award'), 'result' => 'winner', 'project' => 'print_works'),
    array('award' => lang('Hospitality Interior of the Year'), 'result' => 'shortlisted', 'project' => 'harbour_hotel'),
    array('award' => lang('Civic Design Award, Public Space'), 'result' => 'commended', 'project' => 'market_square'),
);

// What the press has written about the studio. The publications are
// samples; the studio puts its own in their place.
$press = array(
    array('outlet' => lang('Architecture Quarterly'), 'kind' => lang('Feature'),
        'title' => lang('A house that breathes: how a courtyard cools a family home')),
    array('outlet' => lang('Interiors Review'), 'kind' => lang('Review'),
        'title' => lang('A seafront hotel made by the city\'s own workshops')),
    array('outlet' => lang('The Design Weekly'), 'kind' => lang('Interview'),
        'title' => lang('Why old factories make the best offices')),
);

// The studio in four figures (the home and the studio page).
$figures = array(
    array('value' => '180+', 'label' => lang('Projects completed')),
    array('value' => '24', 'label' => lang('Architects and designers')),
    array('value' => '15', 'label' => lang('Awards and commendations')),
    array('value' => '6', 'label' => lang('Countries we have built in')),
);

// What clients say, each about one of the sample projects.
$testimonials = array(
    array('project' => 'courtyard_house', 'name' => lang('Ayşe Kılıç'), 'role' => lang('Client of Courtyard House'),
        'quote' => lang('We spend the whole day in the courtyard, and the house stays cool even in the hottest weeks.')),
    array('project' => 'print_works', 'name' => lang('James Porter'), 'role' => lang('Client of The Print Works Offices'),
        'quote' => lang('The old press hall is now where the whole company meets, and visitors always ask about it first.')),
    array('project' => 'harbour_hotel', 'name' => lang('Selim Uçar'), 'role' => lang('Client of Harbour Hotel Interiors'),
        'quote' => lang('Guests write about the rooms in their reviews more than about the view.')),
);

// The sample job openings: the records the Jobs form starts with. Each job
// page opens the application with the job chosen (?job=).
$jobs = array(
    'architect' => array('title' => lang('Project Architect'), 'department' => 'architecture', 'type' => 'full_time',
        'location' => lang('At the studio'),
        'summary' => lang('Lead the design and technical drawings of a house or a workplace, and follow it on site.'),
        'duties'  => array(lang('Develop designs from concept to technical drawings'), lang('Coordinate the engineers and consultants'), lang('Visit the site and answer the builder\'s questions'), lang('Present the work to clients'))),
    'interior_designer' => array('title' => lang('Interior Designer'), 'department' => 'interiors', 'type' => 'full_time',
        'location' => lang('At the studio'),
        'summary' => lang('Plan interiors, choose materials and draw joinery for homes and hotels.'),
        'duties'  => array(lang('Draw plans, elevations and joinery details'), lang('Put together material and colour boards'), lang('Work with our workshops and suppliers'), lang('Check the work on site'))),
    'visualiser' => array('title' => lang('Architectural Visualiser'), 'department' => 'architecture', 'type' => 'part_time',
        'location' => lang('At the studio or remote'),
        'summary' => lang('Make the images and models that help clients see a design before it is built.'),
        'duties'  => array(lang('Build 3D models from the drawings'), lang('Make still images and short films'), lang('Prepare images for competitions and the press'), lang('Look after the studio\'s model library'))),
    'intern' => array('title' => lang('Architecture Intern'), 'department' => 'architecture', 'type' => 'internship',
        'location' => lang('At the studio'),
        'summary' => lang('Join a project team for six months between your studies and learn how a building is designed and built.'),
        'duties'  => array(lang('Make models and drawings with the project team'), lang('Join site visits and client meetings'), lang('Research materials and precedents'), lang('Keep a record of what you learn'))),
);
$departments = array('architecture' => lang('Architecture'), 'interiors' => lang('Interiors'), 'office' => lang('Studio Office'));
$job_types = array('full_time' => lang('Full-time'), 'part_time' => lang('Part-time'), 'internship' => lang('Internship'));
// A job's duties as the value of its Duties field: one line per duty.
$duties_text = function ($key) use ($jobs) {
    return implode("\n", $jobs[$key]['duties']);
};

// A price written as the site writes money: the figure for the site's base
// currency, USD's figure for a currency not listed, with the base
// currency's symbol (the enquiry's budget bands).
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the client chose.
$service_titles = array();
foreach ($services as $s) $service_titles[] = $s['title'];
$type_titles = array();
foreach ($project_types as $t) $type_titles[] = $t['title'];
$location_titles = array_values($locations);
$project_names = array();
foreach ($projects as $p) $project_names[] = $p['name'];
$stage_titles = array_values($project_stages);
$enquiry_service_choices = array_merge($service_titles, array(lang('Not sure yet')));
$project_kinds = array(lang('New building'), lang('Extension'), lang('Renovation'), lang('Interior fit-out'), lang('Change of use'), lang('Garden or landscape'), lang('Something else'));
$site_choices = array(lang('I own the site or building'), lang('I am buying a site or building'), lang('I am still looking'), lang('Something else'));
$budget_low = $money(array('USD' => 100000, 'EUR' => 92000, 'GBP' => 80000, 'TRY' => 3600000));
$budget_mid = $money(array('USD' => 500000, 'EUR' => 460000, 'GBP' => 400000, 'TRY' => 18000000));
$budget_high = $money(array('USD' => 2000000, 'EUR' => 1840000, 'GBP' => 1600000, 'TRY' => 72000000));
$budget_choices = array(
    sprintf(lang('Up to %s'), $budget_low),
    sprintf(lang('%1$s to %2$s'), $budget_low, $budget_mid),
    sprintf(lang('%1$s to %2$s'), $budget_mid, $budget_high),
    sprintf(lang('More than %s'), $budget_high),
    lang('Not sure yet'),
);
$start_choices = array(lang('As soon as possible'), lang('Within six months'), lang('Within a year'), lang('Later than a year'), lang('Not decided yet'));
// The states of a project enquiry, set by the studio in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Call booked'), lang('Proposal sent'), lang('Appointed'), lang('Declined'));
// Where a consultation takes place, and its states.
$consultation_places = array(lang('At the studio'), lang('Video call'), lang('On site'));
$consultation_statuses = array(lang('Request received'), lang('Time proposed'), lang('Confirmed'), lang('Held'), lang('Cancelled'));
$department_titles = array_values($departments);
$job_type_titles = array_values($job_types);
$job_titles = array();
foreach ($jobs as $j) $job_titles[] = $j['title'];
$application_job_choices = array_merge($job_titles, array(lang('Open application')));
// The states of a job application, set by the studio in the panel.
$application_statuses = array(lang('Application received'), lang('Interview'), lang('Offer made'), lang('Not this time'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);


// The query string of a link that opens a list with a filter set
// (?type=, ?location=) or a form with a choice made (?service=, ?job=):
// the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the enquiry band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('enquiry_band');
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
            $heading('h1', $title, 'pai-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pai-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pai-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a thin square frame, the way the services and the principles are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center border border-dark-subtle text-body-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// The Architecture: Plate Hero block (pg-arch-plate): the eyebrow and the
// page's only h1 in eight columns, a line and the two ways on in four,
// aligned to the foot, then one wide photograph captioned like a plate in
// a monograph: its number, the project it shows, a line and a link to it
// pushed to the end from sm up. The words rise and the plate fades on load;
// the photograph is the first thing the page shows, so it is not lazy.
$plate_hero = function ($eyebrow, $title, $lead, $buttons, $src, $alt, $plate)
    use ($el, $heading, $para, $link, $span, $image, $container, $row, $col, $fx) {
    $words = $row(array(
        $col(array(
            $para($eyebrow, 'pai-eyebrow text-uppercase fw-semibold text-body-secondary mb-3'),
            $heading('h1', $title, 'pai-display display-4 mb-0'),
        ), '', '8'),
        $col(array(
            $para($lead, 'text-body-secondary mb-4'),
            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
        ), '', '4'),
    ), '4', '', 'g-lg-5 align-items-end mb-4 mb-lg-5');
    $figure = $el('figure', 'pg-arch-plate mb-0', lang('Plate'), array(
        $el('div', 'ratio ratio-21x9 overflow-hidden bg-body-tertiary', lang('Picture'), array(
            $image($src, $alt, 'object-fit-cover'),
        )),
        $el('figcaption', 'd-flex flex-wrap align-items-baseline gap-2 gap-sm-4 pt-3 mt-3 small', lang('Plate Caption'), array(
            $span($plate['no'], 'pg-arch-no', lang('Number')),
            $span($plate['name'], 'fw-semibold', lang('Project Name')),
            $span($plate['place'], 'text-body-secondary', lang('Setting')),
            $link(lang('See the Project'), $plate['href'], 'ms-sm-auto link-body-emphasis fw-semibold'),
        )),
    ));
    return $el('section', 'pai-hero pt-4 pt-lg-5 pb-5 overflow-hidden', lang('Hero'), array(
        $container(array(
            $fx($words, array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700)),
            $fx($figure, array('effect' => 'fade', 'trigger' => 'load', 'duration' => 1200, 'delay' => 200)),
        )),
    ));
};

// The Architecture: Material Board block (pg-arch-swatch): a header and
// the studio's materials ($materials), each mounted like a sample on a
// board with its finish and where it was used, rising in one after
// another on view. A search section under its anchor $id.
$material_board = function ($id, $class = 'py-5 bg-body-tertiary') use ($el, $heading, $para, $image, $container, $photo, $attr, $materials, $rise, $searchable) {
    $items = array();
    foreach ($materials as $m) {
        $items[] = $el('li', 'col', $m['title'], array(
            $el('div', 'pg-arch-swatch h-100', lang('Sample'), array(
                $el('div', 'ratio ratio-1x1 overflow-hidden bg-body-tertiary mb-3', lang('Picture'), array(
                    $image($photo($m['seed'], 600, 600), $m['alt'], 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                )),
                $heading('h3', $m['title'], 'pai-display h6 fw-semibold mb-1'),
                $para($m['finish'], 'small text-body-secondary mb-2'),
                $para($m['used'], 'small mb-0'),
            )),
        ));
    }
    return $el('section', $class, lang('Materials'), array(
        $container(array(
            $el('div', 'col-lg-8 px-0 mb-5', lang('Section Header'), array(
                $para(lang('Materials'), 'pai-eyebrow text-uppercase fw-semibold text-body-secondary mb-2'),
                $heading('h2', lang('What our buildings are made of'), 'pai-display h2 mb-3'),
                $para(lang('A few honest materials, chosen for the place, the climate and how they age, and used again from one project to the next.'), 'text-body-secondary mb-0'),
            )),
            $rise($el('ul', 'list-unstyled row row-cols-2 row-cols-md-3 row-cols-lg-6 g-3 mb-0', lang('Materials'), $items)),
        )),
    ), $searchable($id, lang('stone, oak, plaster, brick, terrazzo, brass, finishes')));
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'ai-faq') use ($el, $para, $attr, $searchable) {
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


// The studio's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pai-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the free first consultation and the way to
// ask for it. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-chat-square-text', 'text-primary'),
        $span(lang('The first consultation about your project is free'), '', lang('Text')),
        $link(lang('Book a Consultation'), '{{page:consultation}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The
// studio sells nothing online, so the bar carries no cart.
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

// The header: the studio's name, the menu and the way to start a project.
// The links to the current page are marked active on the server (smart
// active state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('projects', lang('Projects')),
        array('services', lang('Services')),
        array('studio', lang('Studio')),
        array('awards', lang('Awards and Press')),
        array('blog', lang('Journal')),
        array('careers', lang('Careers')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-rulers', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pai-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#ai-nav'),
                    $attr('aria-controls', 'ai-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-0 d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $span(lang('Start a Project'), '', lang('Link Text')),
                            $icon('bi-arrow-right'),
                        ), array('href' => '{{page:enquiry}}')),
                    )),
                ), array('id' => 'ai-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the studio in a sentence, how to reach it
// and its profiles, the services, the projects by type and by place, the
// client pages and the studio's pages; at the bottom the year, the site's
// name and the registration line.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $services, $project_types, $locations, $studio, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $service_links = array();
    foreach ($services as $key => $s) $service_links[] = $link($s['title'], '{{page:service_' . $key . '}}', $plain);
    $project_links = array($link(lang('All Projects'), '{{page:projects}}', $plain));
    foreach ($project_types as $t) $project_links[] = $link($t['title'], '{{page:projects}}?' . $query(array('type' => $t['title'])), $plain);
    $place_links = array();
    foreach ($locations as $title) $place_links[] = $link($title, '{{page:projects}}?' . $query(array('location' => $title)), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };
    $column = function ($name, $title, $links) use ($el, $heading, $list) {
        return $el('div', 'col-6 col-md-3 col-lg-2', $name, array(
            $heading('h2', $title, 'pai-eyebrow h6 text-uppercase small fw-bold mb-3'),
            $list($links),
        ));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Studio'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-rulers', 'text-primary'),
                        $span('{{site_name}}', 'pai-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($studio['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($studio['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($studio['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $studio['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($studio['email'], 'mailto:' . $studio['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($studio['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-pinterest', 'Pinterest'),
                        $social('bi-linkedin', 'LinkedIn'),
                        $social('bi-vimeo', 'Vimeo'),
                    )),
                )),
                $column(lang('Service Links'), lang('Services'), $service_links),
                $column(lang('Project Links'), lang('Projects'), array_merge($project_links, $place_links)),
                $column(lang('Clients'), lang('For Clients'), array(
                    $link(lang('Start a Project'), '{{page:enquiry}}', $plain),
                    $link(lang('Book a Consultation'), '{{page:consultation}}', $plain),
                    $link(lang('My Enquiries'), '{{page:my_enquiries}}', $plain),
                    $link(lang('How We Work'), '{{page:process}}', $plain),
                    $link(lang('FAQ'), '{{page:faq}}', $plain),
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                )),
                $column(lang('Footer Links'), lang('Studio'), array(
                    $link(lang('About the Studio'), '{{page:studio}}', $plain),
                    $link(lang('Our Team'), '{{page:team}}', $plain),
                    $link(lang('Awards and Press'), '{{page:awards}}', $plain),
                    $link(lang('Careers'), '{{page:careers}}', $plain),
                    $link(lang('Journal'), '{{page:blog}}', $plain),
                    $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                    $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($studio['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The enquiry band: a full-width band in the palette's colour with the way
// to start a project and to book a consultation. Shared, so every page
// that ends with it carries the same call.
$enquiry_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Thinking about a project?'), 'pai-display h2 mb-2'),
                    $para(lang('Tell us about your site, your plans and your budget. We answer every enquiry within three working days, and the first consultation is free.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-secondary btn-lg rounded-0 px-4'),
                        $link(lang('Book a Consultation'), '{{page:consultation}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The facts of a project in one list, as the Architecture: Project Sheet
// block draws them (pg-arch-facts: each label beside its value on a
// hairline): type, location, place, service, size and stage, each bound to
// the Projects form's field of the same name.
$project_facts = function ($sample_key = 'courtyard_house') use ($el, $span, $bind, $projects, $project_types, $locations, $services, $project_stages) {
    $p = $projects[$sample_key];
    $fact = function ($label, $text, $token, $name) use ($el, $span, $bind) {
        return $el('div', '', $name, array(
            $el('dt', '', lang('Label'), array(), array('text' => $label)),
            $el('dd', '', $name, array($span($text, '', $name, $bind($token)))),
        ));
    };
    return $el('dl', 'pg-arch-facts border-top mb-0', lang('Project Facts'), array(
        $fact(lang('Type'), $project_types[$p['type']]['title'], 'type', lang('Project Type')),
        $fact(lang('Location'), $locations[$p['location']], 'location', lang('Location')),
        $fact(lang('Setting'), $p['place'], 'place', lang('Setting')),
        $fact(lang('Service'), $services[$p['service']]['title'], 'service', lang('Service')),
        $fact(lang('Size'), $p['size'], 'size', lang('Project Size')),
        $fact(lang('Stage'), $project_stages[$p['stage']], 'stage', lang('Stage')),
    ));
};

// The credits of a project: who the studio worked with, one line each, as
// the Credits field holds them.
$credits_block = function ($sample_key = 'courtyard_house') use ($el, $heading, $bind, $credits_text) {
    return $el('div', 'pg-hide-if-empty', lang('Credits'), array(
        $heading('h2', lang('Credits'), 'pai-eyebrow h6 text-uppercase fw-bold mb-3'),
        $el('div', 'pg-field-value small lh-lg', lang('Value'), array(), array_merge(array('text' => $credits_text($sample_key)), $bind('credits'))),
    ));
};

// The gallery of a project page: the first photo wide, the others beside
// one another under it; a photo the record lacks leaves with its frame.
// $rest leaves the first photo out, for a page that shows it in the
// project sheet.
$gallery = function ($sample_key = 'courtyard_house', $rest = false) use ($el, $image, $photo, $attr, $projects) {
    $name = $projects[$sample_key]['name'];
    $frame = function ($token, $seed, $ratio, $col_class) use ($el, $image, $photo, $attr, $name) {
        return $el('div', 'pg-hide-if-empty ' . $col_class, lang('Picture'), array(
            $el('span', 'pg-field-value d-block ratio ' . $ratio . ' bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo($seed, 1600, 1000), $name, 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => $token, 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
        ));
    };
    $frames = $rest ? array() : array($frame('photo_1', 'project-' . $sample_key, 'ratio-21x9', 'col-12'));
    foreach (array(2, 3, 4) as $n) $frames[] = $frame('photo_' . $n, 'project-' . $sample_key . '-' . $n, 'ratio-4x3', 'col-12 col-md-4');
    return $el('div', 'row g-3', lang('Gallery'), $frames);
};

// A project's card of the lists (the home, the project list and a
// service's page): the photo, the type and the location, the name linking
// the project page, the summary and the stage. Every value is bound to the
// Projects form's field of the same name; the sample fills the canvas.
$project_card = function ($sample_key = 'courtyard_house') use ($el, $para, $span, $link, $image, $col, $bind, $projects, $project_types, $locations, $project_stages, $photo, $attr) {
    $p = $projects[$sample_key];
    return $col(array(
        $el('article', 'h-100 d-flex flex-column position-relative pai-lift', lang('Project Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary mb-3', lang('Picture Frame'), array(
                $image($photo('project-' . $sample_key, 800, 600), $p['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('p', 'pai-eyebrow d-flex flex-wrap gap-2 small text-uppercase fw-semibold text-body-secondary mb-1', lang('Type and Location'), array(
                $span($project_types[$p['type']]['title'], '', lang('Project Type'), $bind('type')),
                $span('·', '', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true')))),
                $span($locations[$p['location']], '', lang('Location'), $bind('location')),
            )),
            $el('h3', 'pai-display h5 mb-2', lang('Project Name'), array(
                $link($p['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
            )),
            $para($p['summary'], 'small text-body-secondary mb-3', $bind('summary')),
            $span($project_stages[$p['stage']], 'badge rounded-0 text-bg-primary align-self-start mt-auto', lang('Stage'), $bind('stage')),
        )),
    ), '6', '4');
};

// A service's card (the home and the services page): the icon, the title
// linking the service's page and the summary.
$service_card = function ($key) use ($el, $para, $link, $col, $icon_tile, $services) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'h-100 border-top border-dark-subtle pt-4 position-relative pai-lift', lang('Service Card'), array(
            $icon_tile($s['icon'], 'mb-3'),
            $el('h3', 'pai-display h5 mb-2', lang('Service Name'), array(
                $link($s['title'], '{{page:service_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
            )),
            $para($s['summary'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// A member of the team (the team page and the studio page): the portrait,
// the name, the role and a sentence.
$team_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $team) {
    $t = $team[$n];
    return $col(array(
        $el('article', 'h-100', lang('Team Member'), array(
            $el('span', 'd-block ratio ratio-1x1 bg-body-tertiary mb-3', lang('Picture Frame'), array(
                $image($portrait($t['name']), $t['name'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $heading('h3', $t['name'], 'pai-display h5 mb-1'),
            $para($t['role'], 'pai-eyebrow small text-uppercase fw-semibold text-body-secondary mb-2'),
            $para($t['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// An award (the awards page and the studio page): the result, the prize
// and the project it was given to.
$award_card = function ($n) use ($el, $span, $icon, $awards, $award_results, $projects) {
    $a = $awards[$n];
    return $el('li', 'd-flex flex-column flex-md-row gap-2 gap-md-4 py-3 border-bottom', lang('Award'), array(
        $span($award_results[$a['result']], 'pai-index small text-uppercase fw-semibold text-body-secondary flex-shrink-0', lang('Result')),
        $el('div', 'd-flex align-items-start gap-2 flex-grow-1', lang('Prize'), array(
            $icon('bi-award', 'text-primary'),
            $span($a['award'], 'fw-semibold', lang('Prize')),
        )),
        $span($projects[$a['project']]['name'], 'small text-body-secondary flex-shrink-0', lang('Project Name')),
    ));
};

// A press article (the awards page): the publication, the kind of piece
// and its title.
$press_card = function ($n) use ($el, $para, $span, $col, $press) {
    $p = $press[$n];
    return $col(array(
        $el('article', 'h-100 border-top border-dark-subtle pt-3', lang('Press Article'), array(
            $el('p', 'pai-eyebrow d-flex flex-wrap gap-2 small text-uppercase fw-semibold mb-2', lang('Publication'), array(
                $span($p['outlet'], 'text-body-emphasis', lang('Publication')),
                $span($p['kind'], 'text-body-secondary', lang('Kind')),
            )),
            $para($p['title'], 'pai-quote fs-5 mb-0'),
        )),
    ), '', '4');
};

// What a client says: the quote, the client and the project.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'h-100 d-flex flex-column border-start border-2 border-primary ps-4 mb-0', lang('Client Quote'), array(
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pai-quote fs-5 mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Client'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
};

// A job opening's card (the careers page): the team, the title linking
// the job's page, the type, the location and the summary, each bound to
// the Jobs form's field of the same name.
$job_card = function ($sample_key = 'architect') use ($el, $para, $span, $link, $icon, $bind, $jobs, $departments, $job_types) {
    $j = $jobs[$sample_key];
    return $el('article', 'border-bottom py-4 position-relative', lang('Job Card'), array(
        $el('div', 'd-flex flex-column flex-md-row align-items-md-center gap-3', lang('Card Body'), array(
            $el('div', 'flex-grow-1', lang('Job'), array(
                $span($departments[$j['department']], 'pai-eyebrow small text-uppercase fw-semibold text-body-secondary', lang('Team'), $bind('department')),
                $el('h3', 'pai-display h5 my-1', lang('Job Title'), array(
                    $link($j['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($j['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
            $el('ul', 'list-unstyled d-flex flex-wrap flex-md-column gap-2 gap-md-1 small mb-0 flex-shrink-0', lang('Job Facts'), array(
                $el('li', 'd-inline-flex align-items-center gap-1', lang('Job Type'), array(
                    $icon('bi-briefcase', 'text-primary'),
                    $span($job_types[$j['type']], '', lang('Job Type'), $bind('type')),
                )),
                $el('li', 'd-inline-flex align-items-center gap-1', lang('Location'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($j['location'], '', lang('Location'), $bind('location')),
                )),
            )),
        )),
    ));
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
$submit = function ($text, $class = 'btn btn-primary rounded-0 px-4') use ($el, $attr) {
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
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary rounded-0', array(),
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
        $span(lang('Badge'), 'badge rounded-0 text-bg-secondary', lang('Badge'), array('_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
    );
    if ($w['reply']) {
        $badges[] = $span(lang('Featured'), 'badge rounded-0 text-bg-primary', lang('Featured'), $show_when('comment_is_featured'));
    }
    $card = $el('div', 'card border-0 bg-body-tertiary rounded-0 flex-grow-1', lang('Comment Card'), array(
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
        $el('button', 'btn btn-primary rounded-0 px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-outline-secondary rounded-0', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
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
            $el('form', 'card card-body border-0 bg-body-tertiary rounded-0 p-4', lang('Comment Form'), $fields, array('_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment'))),
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
            $span(lang('Staff'), 'badge rounded-0 text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('project_new', lang('New Project')),
                $item('staff_enquiries', lang('Project Enquiries')),
                $item('staff_consultations', lang('Consultation Requests')),
                $item('job_new', lang('New Job Opening')),
                $item('staff_applications', lang('Job Applications')),
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
    return $agree($id, 'consent', lang('I would like to receive the studio\'s journal and new projects by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// enquiry takes drawings, surveys and photos of the site, an application a
// CV and a portfolio, so the default accepts PDFs, office files, text and
// pictures; one that takes only pictures is a collection's image kind (a
// project's photos).
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
        $el('article', 'card h-100 border-0 rounded-0 overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'pai-eyebrow small text-uppercase fw-semibold text-body-secondary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('What to ask before you buy a plot to build on'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
        return $link($text, '#', 'btn btn-sm btn-outline-primary rounded-0', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    return $el('aside', 'p-3 rounded-0 bg-body-tertiary mb-4', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars', 'text-primary'),
            $span(lang('Summarize this post with AI'), 'small fw-semibold me-1', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-primary rounded-0', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};

