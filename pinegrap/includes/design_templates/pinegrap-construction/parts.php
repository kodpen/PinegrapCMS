<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Construction Firm", part 1 of its files: the node
 * builders, the firm's own data (the services, the sectors, the sample
 * projects with their facts and scope, how a project runs, the team, safety
 * and quality, why clients choose the firm, its figures, what clients say,
 * the job openings and the answers of the forms' pick lists) and the parts
 * every page repeats (account bar, header, footer, quote band, project,
 * service, team, client and job cards, project facts, page shells,
 * section headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-construction.php puts them together.
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
// every opening until the firm puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-construction-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/fef3c7/92400e?text=' . rawurlencode($initials !== '' ? $initials : 'C');
};

// ── The firm: its services, its projects and its people ─────────────────

// The firm itself. The about page tells its story; the footer, the quote
// band and the contact lines use the address, phone and office hours.
$firm = array(
    'tagline' => lang('Homes, workplaces and public buildings, built on time, on budget and safely'),
    'story'   => lang('Two site engineers started the firm with one crew and a borrowed crane. Today our own teams build homes, offices, schools and roads, and we still run every project the same way: one project manager from the first site visit to the handover, a fixed price agreed before work starts and no surprises on the way.'),
    'address' => lang('Industrial Avenue 42, Building C'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'office@example.com',
    'hours'   => lang('Monday to Friday, 8:00 to 18:00'),
    'licence' => lang('A licensed and fully insured contractor; every building we hand over carries a ten-year structural warranty'),
);

// The services. Each has its own page (service_<key>); the quote request
// opens with the service chosen (?service=) in the service's own words.
$services = array(
    'residential' => array('icon' => 'bi-house-door', 'title' => lang('Residential Construction'),
        'summary' => lang('Detached houses, villas and apartment blocks, from the foundations to the keys.'),
        'text'    => lang('We build new homes for families and developers: one house on its own plot or a block of fifty flats. Our engineers check the design for cost before a single trench is dug, and a fixed price covers everything we agree to build.'),
        'points'  => array(lang('Detached houses and villas'), lang('Apartment blocks and housing estates'), lang('Earthquake-resistant structures'), lang('Energy-efficient building envelopes'))),
    'commercial'  => array('icon' => 'bi-building', 'title' => lang('Commercial Buildings'),
        'summary' => lang('Offices, shops, hotels and warehouses built around how the business will use them.'),
        'text'    => lang('A commercial building earns its keep from the day it opens, so we plan the work to hand it over on the agreed day. We build in phases where a business must keep trading next door.'),
        'points'  => array(lang('Office buildings'), lang('Retail units and shopping centres'), lang('Hotels and restaurants'), lang('Warehouses and logistics centres'))),
    'renovation'  => array('icon' => 'bi-hammer', 'title' => lang('Renovation and Restoration'),
        'summary' => lang('Extensions, refurbishments and the careful repair of old and listed buildings.'),
        'text'    => lang('We renovate homes and buildings while people live and work in them: dust screens, safe routes and a timetable agreed with you. Our restoration team repairs historic stone and timber with the methods they were built with.'),
        'points'  => array(lang('Extensions and loft conversions'), lang('Full interior refurbishment'), lang('Seismic strengthening'), lang('Restoration of historic buildings'))),
    'civil'       => array('icon' => 'bi-signpost-split', 'title' => lang('Civil Works'),
        'summary' => lang('Roads, bridges, retaining walls and the services under them.'),
        'text'    => lang('Our civil engineering crews build the roads, car parks, drainage and retaining walls that a site needs, and work for municipalities on public roads and bridges.'),
        'points'  => array(lang('Roads and car parks'), lang('Bridges and retaining walls'), lang('Drainage and utilities'), lang('Earthworks and site preparation'))),
    'design_build' => array('icon' => 'bi-rulers', 'title' => lang('Design and Build'),
        'summary' => lang('One contract for the design and the building, with one team answering for both.'),
        'text'    => lang('Our architects and engineers design the building and our crews build it, so the drawings are made for the site and the budget from the start. You sign one contract and talk to one project manager.'),
        'points'  => array(lang('Architectural and structural design'), lang('Permits and approvals'), lang('A fixed price for design and construction'), lang('One point of contact'))),
    'management'  => array('icon' => 'bi-clipboard-check', 'title' => lang('Project Management'),
        'summary' => lang('We plan, run and supervise a project for you, whoever builds it.'),
        'text'    => lang('For owners who have their own designers or contractors, we manage the project on their behalf: the programme, the costs, the quality checks on site and the handover documents.'),
        'points'  => array(lang('Programme and cost control'), lang('Tendering and contractor selection'), lang('Site supervision and quality checks'), lang('Handover and warranty follow-up'))),
);

// The sectors the firm builds for, the project list's first filter.
$sectors = array(
    'housing'        => array('icon' => 'bi-houses', 'title' => lang('Housing'),
        'text' => lang('Family homes, apartment blocks and housing estates.')),
    'workplace'      => array('icon' => 'bi-briefcase', 'title' => lang('Offices and Retail'),
        'text' => lang('Offices, shops, hotels and mixed-use buildings.')),
    'public'         => array('icon' => 'bi-mortarboard', 'title' => lang('Education and Health'),
        'text' => lang('Schools, sports halls, clinics and care homes.')),
    'industrial'     => array('icon' => 'bi-gear-wide-connected', 'title' => lang('Industrial'),
        'text' => lang('Factories, warehouses and logistics centres.')),
    'infrastructure' => array('icon' => 'bi-bezier2', 'title' => lang('Infrastructure'),
        'text' => lang('Roads, bridges, drainage and public spaces.')),
);

// The sample projects: the records the Projects form starts with. Size and
// duration are words; the status is "Completed" or "In progress", never a
// year. The scope has one entry per line of the Scope field.
$projects = array(
    'harbour_view' => array('name' => lang('Harbour View Residences'), 'sector' => 'housing', 'service' => 'residential',
        'location' => lang('Seaside District'), 'size' => lang('48 apartments, 6,200 m²'), 'duration' => lang('22 months'),
        'status' => 'completed', 'client' => lang('Harbour View Developments'),
        'summary' => lang('Two eight-storey apartment blocks over a shared car park, with a garden on the podium roof.'),
        'scope'   => array(lang('Piled foundations and a two-level basement car park'), lang('Reinforced concrete frame designed for seismic zone one'), lang('Facade with external insulation and triple glazing'), lang('Landscaped podium garden and children\'s play area')),
        'quote'   => lang('The blocks were handed over a week early, and the snag list was the shortest we have ever signed.'),
        'featured' => true),
    'meridian_offices' => array('name' => lang('Meridian Office Campus'), 'sector' => 'workplace', 'service' => 'design_build',
        'location' => lang('Business Park North'), 'size' => lang('14,500 m² over three buildings'), 'duration' => lang('26 months'),
        'status' => 'completed', 'client' => lang('Meridian Holdings'),
        'summary' => lang('A design-and-build office campus with a shared courtyard, a staff restaurant and a green roof.'),
        'scope'   => array(lang('Architectural and structural design'), lang('Steel and concrete frames for three buildings'), lang('Green roof and rainwater harvesting'), lang('Fit-out of the restaurant and reception')),
        'quote'   => lang('One contract and one project manager made a large campus feel simple.'),
        'featured' => true),
    'oak_school' => array('name' => lang('Oak Park Primary School'), 'sector' => 'public', 'service' => 'commercial',
        'location' => lang('Oak Park'), 'size' => lang('24 classrooms and a sports hall'), 'duration' => lang('15 months'),
        'status' => 'completed', 'client' => lang('The Municipality Education Office'),
        'summary' => lang('A new primary school built in phases beside the old one, which stayed open until the move.'),
        'scope'   => array(lang('Phased work beside an open school'), lang('Classroom wings and a sports hall'), lang('Safe routes and fenced site for the pupils'), lang('Demolition of the old building after the move')),
        'quote'   => lang('Not one school day was lost, and the children watched their new school grow from the playground.'),
        'featured' => true),
    'mill_lofts' => array('name' => lang('Old Mill Lofts'), 'sector' => 'housing', 'service' => 'renovation',
        'location' => lang('Riverside Old Town'), 'size' => lang('18 loft apartments in a stone mill'), 'duration' => lang('14 months'),
        'status' => 'completed', 'client' => lang('Riverside Heritage Trust'),
        'summary' => lang('A listed stone mill restored and converted into loft apartments, keeping its timber roof and iron windows.'),
        'scope'   => array(lang('Seismic strengthening of the stone walls'), lang('Repair of the original timber roof'), lang('New floors, stairs and lift inside the shell'), lang('Restoration of the iron windows')),
        'quote'   => lang('They treated the old mill with respect and still delivered modern homes.'),
        'featured' => false),
    'logistics_hub' => array('name' => lang('Eastgate Logistics Hub'), 'sector' => 'industrial', 'service' => 'commercial',
        'location' => lang('Eastgate Industrial Zone'), 'size' => lang('32,000 m² warehouse with offices'), 'duration' => lang('11 months'),
        'status' => 'in_progress', 'client' => lang('Eastgate Logistics'),
        'summary' => lang('A large distribution warehouse with forty loading docks, offices and a truck yard.'),
        'scope'   => array(lang('Earthworks and a reinforced truck yard'), lang('Precast concrete and steel portal frame'), lang('Forty loading docks with levellers'), lang('Two-storey office block and staff facilities')),
        'quote'   => lang('Weekly reports with photos let us follow the site from our head office.'),
        'featured' => false),
    'valley_bridge' => array('name' => lang('Valley Road and Bridge'), 'sector' => 'infrastructure', 'service' => 'civil',
        'location' => lang('North Valley'), 'size' => lang('3.2 km of road and a 60 m bridge'), 'duration' => lang('18 months'),
        'status' => 'in_progress', 'client' => lang('The Regional Roads Directorate'),
        'summary' => lang('A new valley road with a concrete bridge over the river, built while the old road stays open.'),
        'scope'   => array(lang('Earthworks and retaining walls'), lang('A sixty-metre post-tensioned concrete bridge'), lang('Drainage, lighting and safety barriers'), lang('Traffic management on the old road')),
        'quote'   => lang('The traffic plan kept the valley moving through every stage of the work.'),
        'featured' => false),
);

// The words of a project's status, the value of its Status field.
$project_statuses = array('completed' => lang('Completed'), 'in_progress' => lang('In progress'));
// A project's scope as the value of its Scope field: one line per item.
$scope_text = function ($key) use ($projects) {
    return implode("\n", $projects[$key]['scope']);
};

// How a project runs, from the first call to the warranty (the process
// page and the home).
$process = array(
    array('title' => lang('Site visit'), 'text' => lang('We visit the site, listen to what you need and look at the ground, the access and the neighbours.')),
    array('title' => lang('Fixed-price quote'), 'text' => lang('Within ten working days you receive a detailed quote with a fixed price and a programme.')),
    array('title' => lang('Design and permits'), 'text' => lang('Our engineers complete the drawings and we apply for the permits the work needs.')),
    array('title' => lang('Construction'), 'text' => lang('One project manager runs the site and sends you a progress report with photos every week.')),
    array('title' => lang('Handover and warranty'), 'text' => lang('We walk the building with you, fix the snag list and stay at your side through the warranty years.')),
);

// The firm's people (the team page and the about page). Fixed data, not a
// collection: the leaders of a contractor change rarely.
$team = array(
    array('name' => lang('Murat Demir'), 'role' => lang('Founder and Managing Director'),
        'bio' => lang('A civil engineer who has run building sites for thirty years and still visits one every week.')),
    array('name' => lang('Selin Aydın'), 'role' => lang('Head of Design'),
        'bio' => lang('An architect who leads our design-and-build team and the permits office.')),
    array('name' => lang('Kerem Şahin'), 'role' => lang('Construction Director'),
        'bio' => lang('Runs the project managers and the site teams, and answers for every programme.')),
    array('name' => lang('Deniz Koç'), 'role' => lang('Health and Safety Manager'),
        'bio' => lang('Plans the safety of every site and trains every new worker before the first shift.')),
    array('name' => lang('Emma Clarke'), 'role' => lang('Commercial Manager'),
        'bio' => lang('Prepares the quotes and keeps every project on its agreed budget.')),
    array('name' => lang('Ali Yılmaz'), 'role' => lang('Restoration Specialist'),
        'bio' => lang('A master mason who leads the work on historic stone and timber buildings.')),
);

// Safety and quality: how the firm works on site, and the certificates it
// holds (the safety page, the about page and the home).
$safety_points = array(
    array('icon' => 'bi-cone-striped', 'title' => lang('A safety plan for every site'),
        'text' => lang('Written before work starts and checked every morning by the site manager.')),
    array('icon' => 'bi-person-check', 'title' => lang('Trained crews'),
        'text' => lang('Every worker is trained and certified for the work they do, and inducted on each new site.')),
    array('icon' => 'bi-search', 'title' => lang('Quality checks at every stage'),
        'text' => lang('Concrete, steel and finishes are inspected and recorded before the next stage covers them.')),
    array('icon' => 'bi-recycle', 'title' => lang('Less waste'),
        'text' => lang('Waste is sorted on site and most of it is recycled or reused.')),
);
$accreditations = array(
    array('icon' => 'bi-award', 'title' => lang('ISO 9001'), 'text' => lang('Quality management')),
    array('icon' => 'bi-shield-check', 'title' => lang('ISO 45001'), 'text' => lang('Occupational health and safety')),
    array('icon' => 'bi-tree', 'title' => lang('ISO 14001'), 'text' => lang('Environmental management')),
    array('icon' => 'bi-patch-check', 'title' => lang('Licensed contractor'), 'text' => lang('Registered for public works')),
);

// Why clients choose the firm (the home and the about page).
$why_us = array(
    array('icon' => 'bi-cash-coin', 'title' => lang('A fixed price'),
        'text' => lang('The price we quote is the price you pay for the work we agreed.')),
    array('icon' => 'bi-calendar-check', 'title' => lang('Handed over on time'),
        'text' => lang('A programme agreed before work starts, and weekly reports to keep it.')),
    array('icon' => 'bi-person-badge', 'title' => lang('One project manager'),
        'text' => lang('One person who knows your project from the site visit to the warranty.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Safe sites'),
        'text' => lang('Certified safety management on every site we run.')),
);

// The firm in four figures (the home and the about page).
$figures = array(
    array('value' => '350+', 'label' => lang('Projects handed over')),
    array('value' => '120', 'label' => lang('Engineers and craftspeople')),
    array('value' => '30', 'label' => lang('Years of building')),
    array('value' => '0', 'label' => lang('Lost-time accidents last year')),
);

// What clients say, each about one of the sample projects.
$testimonials = array(
    array('project' => 'harbour_view', 'name' => lang('Canan Arslan'), 'role' => lang('Client of Harbour View Residences'),
        'quote' => lang('The blocks were handed over a week early, and the snag list was the shortest we have ever signed.')),
    array('project' => 'oak_school', 'name' => lang('David Brooks'), 'role' => lang('Client of Oak Park Primary School'),
        'quote' => lang('Not one school day was lost, and the children watched their new school grow from the playground.')),
    array('project' => 'mill_lofts', 'name' => lang('Zeynep Öztürk'), 'role' => lang('Client of Old Mill Lofts'),
        'quote' => lang('They treated the old mill with respect and still delivered modern homes.')),
);

// The sample job openings: the records the Jobs form starts with. Each job
// page opens the application with the job chosen (?job=).
$jobs = array(
    'site_engineer' => array('title' => lang('Site Engineer'), 'department' => 'site', 'type' => 'full_time',
        'location' => lang('Sites across the region'),
        'summary' => lang('Set out the works, check quality and keep the programme on one of our building sites.'),
        'duties'  => array(lang('Set out the works from the drawings'), lang('Check and record the quality of each stage'), lang('Plan the week\'s work with the site manager'), lang('Report progress to the project manager'))),
    'project_manager' => array('title' => lang('Project Manager'), 'department' => 'projects', 'type' => 'full_time',
        'location' => lang('Head office and sites'),
        'summary' => lang('Run a project from the site visit to the handover, with its budget, programme and client.'),
        'duties'  => array(lang('Lead the project team and the subcontractors'), lang('Keep the programme and the budget'), lang('Be the client\'s one point of contact'), lang('Hand over the building and its documents'))),
    'carpenter' => array('title' => lang('Formwork Carpenter'), 'department' => 'site', 'type' => 'full_time',
        'location' => lang('Sites across the region'),
        'summary' => lang('Build the formwork for foundations, walls and slabs with one of our concrete crews.'),
        'duties'  => array(lang('Build and strike formwork safely'), lang('Read the drawings and set out the work'), lang('Work with the steel fixers and concrete crew'), lang('Look after the tools and the materials'))),
    'apprentice' => array('title' => lang('Construction Apprentice'), 'department' => 'site', 'type' => 'apprenticeship',
        'location' => lang('Sites across the region'),
        'summary' => lang('Learn a trade on site with our craftspeople while you study at a vocational school.'),
        'duties'  => array(lang('Work alongside an experienced craftsperson'), lang('Attend school on the agreed days'), lang('Follow the site\'s safety rules'), lang('Keep a record of what you learn'))),
);
$departments = array('site' => lang('Site Teams'), 'projects' => lang('Project Management'), 'office' => lang('Head Office'));
$job_types = array('full_time' => lang('Full-time'), 'part_time' => lang('Part-time'), 'apprenticeship' => lang('Apprenticeship'));
// A job's duties as the value of its Duties field: one line per duty.
$duties_text = function ($key) use ($jobs) {
    return implode("\n", $jobs[$key]['duties']);
};

// A price written as the site writes money: the figure for the site's base
// currency, USD's figure for a currency not listed, with the base
// currency's symbol (the quote request's budget bands).
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the client chose.
$service_titles = array();
foreach ($services as $s) $service_titles[] = $s['title'];
$sector_titles = array();
foreach ($sectors as $s) $sector_titles[] = $s['title'];
$project_names = array();
foreach ($projects as $p) $project_names[] = $p['name'];
$status_titles = array_values($project_statuses);
$quote_service_choices = array_merge($service_titles, array(lang('Not sure yet')));
$project_kinds = array(lang('New building'), lang('Extension'), lang('Renovation'), lang('Fit-out'), lang('Repair Work'), lang('Something else'));
$budget_low = $money(array('USD' => 50000, 'EUR' => 46000, 'GBP' => 40000, 'TRY' => 1800000));
$budget_mid = $money(array('USD' => 250000, 'EUR' => 230000, 'GBP' => 200000, 'TRY' => 9000000));
$budget_high = $money(array('USD' => 1000000, 'EUR' => 920000, 'GBP' => 800000, 'TRY' => 36000000));
$budget_choices = array(
    sprintf(lang('Up to %s'), $budget_low),
    sprintf(lang('%1$s to %2$s'), $budget_low, $budget_mid),
    sprintf(lang('%1$s to %2$s'), $budget_mid, $budget_high),
    sprintf(lang('More than %s'), $budget_high),
    lang('Not sure yet'),
);
$start_choices = array(lang('As soon as possible'), lang('Within three months'), lang('Within six months'), lang('Later than six months'), lang('Not decided yet'));
// The states of a quote request, set by the firm in the panel.
$quote_statuses = array(lang('Request received'), lang('Site visit booked'), lang('Quote sent'), lang('Accepted'), lang('Declined'));
$department_titles = array_values($departments);
$job_type_titles = array_values($job_types);
$job_titles = array();
foreach ($jobs as $j) $job_titles[] = $j['title'];
$application_job_choices = array_merge($job_titles, array(lang('Open application')));
// The states of a job application, set by the firm in the panel.
$application_statuses = array(lang('Application received'), lang('Interview'), lang('Offer made'), lang('Not this time'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);


// The query string of a link that opens a list with a filter set
// (?sector=, ?service=) or a form with a choice made (?service=, ?job=):
// the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the quote band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('quote_band');
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
            $heading('h1', $title, 'pcn-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pcn-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pcn-display h3 mb-0'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'cn-faq') use ($el, $para, $attr, $searchable) {
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


// The firm's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pcn-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The Construction blocks ─────────────────────────────────────────────

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

// The barrier stripe of the boards and the notice: decoration only.
$cn_stripe = function () use ($el, $attr) {
    return $el('div', 'pg-cn-stripe', lang('Stripe'), array(), array('_attrs' => array($attr('aria-hidden', 'true'))));
};

// The Construction: Site Board Hero block (pg-cn-board, pg-cn-stripe): a
// site photograph in seven columns and the firm's board in five, laid over
// the photograph's edge from lg up; the board carries the eyebrow, the
// page's only h1, a line, $buttons and four facts ($facts: label => value).
// The photograph fades and the board rises on load; the photograph is the
// first thing the page shows, so it is not lazy.
$board_hero = function ($eyebrow, $title, $lead, $buttons, $src, $alt, $facts) use ($el, $heading, $para, $image, $container, $row, $col, $fx, $cn_stripe) {
    $dl = array();
    foreach ($facts as $label => $value) {
        $dl[] = $el('div', '', $label, array(
            $el('dt', '', lang('Label'), array(), array('text' => $label)),
            $el('dd', '', lang('Value'), array(), array('text' => $value)),
        ));
    }
    $picture = $col(array(
        $el('div', 'ratio ratio-4x3 overflow-hidden bg-body-tertiary', lang('Picture'), array(
            $image($src, $alt, 'object-fit-cover'),
        )),
    ), '', '7');
    $board = $col(array(
        $el('div', 'pg-cn-board', lang('Board'), array(
            $cn_stripe(),
            $el('div', 'p-4 p-lg-5', lang('Board Body'), array(
                $para($eyebrow, 'pcn-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                $heading('h1', $title, 'pcn-display display-5 mb-3'),
                $para($lead, 'text-body-secondary mb-4'),
                $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Buttons'), $buttons),
                $el('dl', 'border-top pt-4', lang('Board Facts'), $dl),
            )),
        )),
    ), '', '5');
    return $el('section', 'pcn-hero py-4 py-lg-5 overflow-hidden', lang('Hero'), array(
        $container(array(
            $row(array(
                $fx($picture, array('effect' => 'fade', 'trigger' => 'load', 'duration' => 1000)),
                $fx($board, array('effect' => 'slide-up', 'trigger' => 'load', 'duration' => 700, 'delay' => 200)),
            ), '', '', 'g-0 align-items-center'),
        )),
    ));
};

// The stages of the projects on site now, by project key: the share done
// (25, 50 or 75: a Bootstrap width class draws the bar) and each stage's state ('done', 'now' or 'next'), name and line.
$site_stages = array(
    'logistics_hub' => array('done' => 50, 'stages' => array(
        array('done', lang('Groundworks'), lang('The site levelled, the foundations and the truck yard\'s base laid.')),
        array('done', lang('Structural frame'), lang('The precast concrete and steel portal frame standing.')),
        array('now', lang('Roof and cladding'), lang('The roof sheets going on and the walls closing the warehouse in.')),
        array('next', lang('Docks and offices'), lang('The loading docks fitted, the office block finished inside.')),
        array('next', lang('Handover'), lang('Checked with the client, the keys handed over, the site cleared.')),
    )),
    'valley_bridge' => array('done' => 25, 'stages' => array(
        array('done', lang('Earthworks'), lang('The road\'s line cut and the retaining walls built.')),
        array('now', lang('Bridge deck'), lang('The bridge\'s piers standing and its deck cast span by span.')),
        array('next', lang('Drainage and surfacing'), lang('The drains laid and the road surfaced.')),
        array('next', lang('Lighting and barriers'), lang('The street lights and the safety barriers put up.')),
        array('next', lang('Handover'), lang('The road opened to traffic and the old road returned.')),
    )),
);

// The Construction: Project Progress block (pg-cn-stages, pg-cn-dot,
// pg-cn-done, pg-cn-now): one project on site in five columns (its name,
// summary, the share done as a progress bar, a site photograph) beside its
// stages on a line down the left in seven. The project fades in and the
// stages rise one after another on view. A search section under its
// anchor $id.
$site_progress = function ($key, $id, $class = 'py-5') use ($el, $heading, $para, $link, $span, $image, $icon, $container, $row, $col, $attr, $fx, $rise, $query, $projects, $sectors, $site_stages, $searchable, $photo) {
    $p = $projects[$key];
    $s = $site_stages[$key];
    $marks = array(
        'done' => array('icon' => 'bi-check-lg', 'word' => lang('Done'), 'badge' => 'text-bg-primary'),
        'now'  => array('icon' => 'bi-cone-striped', 'word' => lang('On site now'), 'badge' => 'text-bg-warning'),
        'next' => array('icon' => 'bi-circle', 'word' => lang('Coming next'), 'badge' => 'text-bg-secondary'),
    );
    $items = array();
    foreach ($s['stages'] as $st) {
        $m = $marks[$st[0]];
        // A stage to come takes the list's plain look: only done and now
        // have a rule of their own.
        $items[] = $el('li', $st[0] === 'next' ? '' : 'pg-cn-' . $st[0], $st[1], array(
            $el('div', 'pg-cn-dot', lang('Stage Mark'), array($icon($m['icon'])), array('_attrs' => array($attr('aria-hidden', 'true')))),
            $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-1', lang('Stage Head'), array(
                $heading('h3', $st[1], 'h6 fw-semibold mb-0'),
                $span($m['word'], 'badge rounded-0 ' . $m['badge'], lang('State')),
            )),
            $para($st[2], 'small text-body-secondary mb-0'),
        ));
    }
    $share = (string)(int)$s['done'];
    $about = $col(array(
        $para(lang('On site now'), 'pcn-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $p['name'], 'pcn-display h2 mb-3'),
        $para($p['summary'], 'text-body-secondary mb-4'),
        $el('div', 'd-flex justify-content-between small fw-semibold mb-2', lang('Progress Head'), array(
            $span(lang('Work done'), '', lang('Text')),
            $span($share . '%', '', lang('Share')),
        )),
        $el('div', 'progress rounded-0 mb-4', lang('Progress Bar'), array(
            $el('div', 'progress-bar w-' . $share, lang('Progress')),
        ), array('_attrs' => array(
            $attr('role', 'progressbar'), $attr('aria-label', lang('Work done')),
            $attr('aria-valuenow', $share), $attr('aria-valuemin', '0'), $attr('aria-valuemax', '100'),
        ))),
        $el('div', 'ratio ratio-16x9 overflow-hidden bg-body-tertiary', lang('Picture'), array(
            $image($photo('on-site-' . $key, 1200, 675), lang('The latest photograph from the site.'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
        )),
        $link(lang('More projects in this sector'), '{{page:projects}}?' . $query(array('sector' => $sectors[$p['sector']]['title'])), 'd-inline-block small fw-semibold link-primary mt-3'),
    ), '', '5');
    $stages = $col(array(
        $rise($el('ol', 'pg-cn-stages list-unstyled mb-0', lang('Stages'), $items), 100),
    ), '', '7');
    return $el('section', $class, lang('Project Progress'), array(
        $container(array(
            $row(array(
                $fx($about, array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800)),
                $stages,
            ), '4', '', 'g-lg-5'),
        )),
    ), $searchable($id, lang('on site, progress, stage, under construction, current project')));
};

// The Construction: Neighbour Notice block (pg-cn-notice, pg-cn-stripe,
// pg-cn-hours): the notice the firm puts up for the street on a tinted
// band, a framed sheet under the barrier stripe with the working hours,
// deliveries, noisy work and how long, and the call to the firm. The sheet
// rises on view. A search section under its anchor $id.
$street_notice = function ($id, $class = 'py-5 bg-body-tertiary') use ($el, $heading, $para, $link, $icon, $container, $fx, $cn_stripe, $searchable, $firm) {
    $fact = function ($label, $value, $cls) use ($el) {
        return $el('div', 'col-sm-6', $label, array(
            $el('dt', 'small text-uppercase text-body-secondary', lang('Label'), array(), array('text' => $label)),
            $el('dd', $cls, lang('Value'), array(), array('text' => $value)),
        ));
    };
    $sheet = $el('div', 'pg-cn-notice', lang('Notice'), array(
        $cn_stripe(),
        $el('div', 'p-4 p-lg-5', lang('Notice Body'), array(
            $el('div', 'd-flex align-items-center gap-3 mb-3', lang('Notice Head'), array(
                $icon('bi-cone-striped', 'fs-2 text-primary'),
                $heading('h2', lang('Building work on your street'), 'pcn-display h3 mb-0'),
            )),
            $para(lang('Every site we run puts up this notice for its neighbours: what we build, when we work and whom to call. Thank you for your patience while we work.'), 'mb-4'),
            $el('dl', 'row g-3 mb-4', lang('Notice Facts'), array(
                $fact(lang('Working hours'), lang('Monday to Friday, 8:00 to 18:00; Saturday, 8:00 to 13:00'), 'pg-cn-hours mb-0'),
                $fact(lang('Deliveries'), lang('Between 9:30 and 15:00, away from the school run'), 'pg-cn-hours mb-0'),
                $fact(lang('Noisy work'), lang('Kept to weekday mornings, never on Sundays'), 'mb-0'),
                $fact(lang('How long'), lang('Written on each site\'s notice, with the date of the handover'), 'mb-0'),
            )),
            $el('div', 'd-flex flex-wrap align-items-center gap-3 border-top pt-4', lang('Notice Contact'), array(
                $para(lang('A question or a problem? Call the site manager.'), 'fw-semibold mb-0 me-auto'),
                $link(lang('Call the Site'), 'tel:' . preg_replace('/[^0-9+]/', '', $firm['phone']), 'btn btn-primary rounded-0 px-4'),
            )),
        )),
    ));
    return $el('section', $class, lang('Neighbour Notice'), array(
        $container(array(
            $el('div', 'col-lg-9 col-xl-8 mx-auto', lang('Notice Column'), array(
                $fx($sheet, array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700)),
            )),
        )),
    ), $searchable($id, lang('neighbours, noise, working hours, deliveries, dust, complaint')));
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the free site visit and the way to ask for
// it. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-clipboard-check', 'text-primary'),
        $span(lang('Free site visit and a fixed-price quote within ten working days'), '', lang('Text')),
        $link(lang('Request a Quote'), '{{page:quote}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The firm
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

// The header: the firm's name, the menu and the way to ask for a quote.
// The links to the current page are marked active on the server (smart
// active state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('services', lang('Our Services')),
        array('projects', lang('Projects')),
        array('sectors', lang('Sectors')),
        array('about', lang('About Us')),
        array('careers', lang('Careers')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-bricks', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pcn-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#cn-nav'),
                    $attr('aria-controls', 'cn-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-clipboard-check'),
                            $span(lang('Request a Quote'), '', lang('Link Text')),
                        ), array('href' => '{{page:quote}}')),
                    )),
                ), array('id' => 'cn-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the firm in a sentence, how to reach it and
// its profiles, the services, the projects by sector, the client pages and
// the firm's pages; at the bottom the year, the site's name and the
// licence.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $services, $sectors, $firm, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $service_links = array();
    foreach ($services as $key => $s) $service_links[] = $link($s['title'], '{{page:service_' . $key . '}}', $plain);
    $sector_links = array($link(lang('All Projects'), '{{page:projects}}', $plain));
    foreach ($sectors as $s) $sector_links[] = $link($s['title'], '{{page:projects}}?' . $query(array('sector' => $s['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Firm'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-bricks', 'text-primary'),
                        $span('{{site_name}}', 'pcn-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($firm['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($firm['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($firm['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $firm['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($firm['email'], 'mailto:' . $firm['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($firm['hours'], '', lang('Text')), lang('Office Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-linkedin', 'LinkedIn'),
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Service Links'), array(
                    $heading('h2', lang('Our Services'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($service_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Project Links'), array(
                    $heading('h2', lang('Projects'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($sector_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Clients'), array(
                    $heading('h2', lang('For Clients'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Request a Quote'), '{{page:quote}}', $plain),
                        $link(lang('My Quote Requests'), '{{page:my_quotes}}', $plain),
                        $link(lang('Our Process'), '{{page:process}}', $plain),
                        $link(lang('Safety and Quality'), '{{page:safety}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Firm'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Our Team'), '{{page:team}}', $plain),
                        $link(lang('Careers'), '{{page:careers}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($firm['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The quote band: a full-width band in the palette's colour with the way to
// ask for a quote and the phone number. Shared, so every page that ends
// with it carries the same call.
$quote_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $firm) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Planning a building project?'), 'pcn-display h2 mb-2'),
                    $para(lang('Tell us what you want to build. We visit the site for free and send you a fixed-price quote within ten working days.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Request a Quote'), '{{page:quote}}', 'btn btn-secondary btn-lg px-4'),
                        $link($firm['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $firm['phone']), 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The facts of a project in one list: sector, service, location, size,
// duration and status, each bound to the Projects form's field of the
// same name.
$project_facts = function ($sample_key = 'harbour_view', $class = 'd-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $projects, $sectors, $services, $project_statuses) {
    $p = $projects[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', 'list-unstyled ' . $class, lang('Project Facts'), array(
        $fact('bi-geo-alt', $p['location'], 'location', lang('Location')),
        $fact($sectors[$p['sector']]['icon'], $sectors[$p['sector']]['title'], 'sector', lang('Sector')),
        $fact($services[$p['service']]['icon'], $services[$p['service']]['title'], 'service', lang('Service Type')),
        $fact('bi-aspect-ratio', $p['size'], 'size', lang('Project Size')),
        $fact('bi-hourglass-split', $p['duration'], 'duration', lang('Duration')),
        $fact('bi-flag', $project_statuses[$p['status']], 'status', lang('Status')),
    ));
};

// A project's card of the lists (the home, the project list and a
// service's page): the photo, the sector, the name linking the project
// page, the location, the summary and the status. Every value is bound to
// the Projects form's field of the same name; the sample fills the canvas.
$project_card = function ($sample_key = 'harbour_view') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $projects, $sectors, $project_statuses, $photo, $attr) {
    $p = $projects[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden pcn-lift', lang('Project Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('project-' . $sample_key, 800, 600), $p['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($sectors[$p['sector']]['title'], 'pcn-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Sector'), $bind('sector')),
                $el('h3', 'pcn-display h5 position-static mb-1', lang('Project Name'), array(
                    $link($p['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-center gap-1 small fw-semibold mb-2', lang('Location'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($p['location'], '', lang('Location'), $bind('location')),
                )),
                $para($p['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $span($project_statuses[$p['status']], 'badge text-bg-secondary align-self-start mt-auto', lang('Status'), $bind('status')),
            )),
        )),
    ), '6', '4');
};

// A service's card (the home and the services page): the icon, the title
// linking the service's page, the summary and a link to its page.
$service_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $services) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 pcn-lift', lang('Service Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'align-self-start mb-3'),
                $el('h3', 'pcn-display h5 position-static mb-2', lang('Service Name'), array(
                    $link($s['title'], '{{page:service_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($s['summary'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// A member of the team (the team page and the about page): the portrait,
// the name, the role and a sentence.
$team_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $team) {
    $t = $team[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 text-center p-4', lang('Team Member'), array(
            $image($portrait($t['name'], 240), $t['name'], 'rounded-circle mx-auto mb-3', array('_attrs' => array($attr('width', '120'), $attr('height', '120'), $attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'pcn-display h5 mb-1'),
            $para($t['role'], 'small fw-semibold text-primary mb-2'),
            $para($t['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// What a client says: the quote, the stars, the client and the project.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-3 p-4 mb-0', lang('Client Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pcn-quote mb-0'))),
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

// A job opening's card (the careers page): the department, the title
// linking the job's page, the type, the location and the summary, each
// bound to the Jobs form's field of the same name.
$job_card = function ($sample_key = 'site_engineer') use ($el, $para, $span, $link, $icon, $bind, $jobs, $departments, $job_types) {
    $j = $jobs[$sample_key];
    return $el('article', 'card border-0 shadow-sm rounded-3 mb-3 pcn-lift', lang('Job Card'), array(
        $el('div', 'card-body d-flex flex-column flex-md-row align-items-md-center gap-3 p-4', lang('Card Body'), array(
            $el('div', 'flex-grow-1', lang('Job'), array(
                $span($departments[$j['department']], 'pcn-eyebrow small text-uppercase fw-semibold text-primary', lang('Department'), $bind('department')),
                $el('h3', 'pcn-display h5 position-static my-1', lang('Job Title'), array(
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
                $item('project_new', lang('New Project')),
                $item('staff_quotes', lang('Quote Requests')),
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
    return $agree($id, 'consent', lang('I would like to receive the firm\'s news and project stories by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// quote request takes plans, drawings and photos of the site, an
// application a CV,
// so the default accepts PDFs, office files, text and pictures; one that
// takes only pictures is a collection's image kind (a project's photos).
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
                    $link(lang('What to check before you build an extension'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

