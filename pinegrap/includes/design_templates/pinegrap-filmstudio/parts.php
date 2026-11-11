<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Film Studio", part 1 of its files: the node builders,
 * the studio's own data (the services, the industries it films for, the
 * sample projects with their facts and credits, how a production runs,
 * the studio's spaces, the crew, why clients choose the studio, its
 * figures, what clients say, the crew calls and the answers of the forms'
 * pick lists) and the parts every page repeats (account bar, header,
 * footer, enquiry band, the showreel player, project, service, crew,
 * space, client and crew call cards, project facts, credits, page shells,
 * section headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-filmstudio.php puts them together.
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
// A node's interaction (props._fx), changed or removed in the editor's
// Interactions section; pg_fx keeps the node still under reduced motion.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// A row of cards or a list whose items rise into view one after another
// (the stagger moves the children, not the row).
$rise = function ($node, $stagger = 90) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'stagger' => $stagger));
};
// A heading or a band that fades in as it comes into view.
$fade_in = function ($node, $delay = 0) use ($fx) {
    $effect = array('effect' => 'fade', 'trigger' => 'view', 'duration' => 800);
    if ($delay > 0) $effect['delay'] = $delay;
    return $fx($node, $effect);
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

// A sample picture with a fixed seed, so the same still comes back on
// every opening until the studio puts its own frames in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-filmstudio-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/1e1b4b/e0e7ff?text=' . rawurlencode($initials !== '' ? $initials : 'F');
};

// ── The studio: its services, its work and its people ────────────────────

// The studio itself. The about page tells its story; the footer, the
// enquiry band and the contact lines use the address, phone and hours.
$studio = array(
    'tagline' => lang('Commercials, brand films and documentaries, from the first idea to the final cut'),
    'story'   => lang('A director and a cinematographer started the studio in a rented garage with one camera and a borrowed light. Today our own crew writes, shoots, edits and grades films for brands, artists and charities, and we still make every film the same way: one producer from the first call to the delivery, a treatment before a single frame is shot and a price agreed before the cameras roll.'),
    'address' => lang('Harbour Warehouses, Unit 7'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'studio@example.com',
    'hours'   => lang('Monday to Friday, 9:00 to 19:00'),
    'note'    => lang('An independent production company: we write, shoot, edit, grade and mix in house'),
);

// The services. Each has its own page (service_<key>); the enquiry opens
// with the service chosen (?service=) in the service's own words.
$services = array(
    'commercials' => array('icon' => 'bi-badge-tv', 'title' => lang('Commercials'),
        'summary' => lang('Television, cinema and online spots that sell a product in thirty seconds.'),
        'text'    => lang('We write the idea with you, cast it, shoot it and cut every version the media plan needs: the thirty-second spot, the six-second cut-down and the vertical edit for social media.'),
        'points'  => array(lang('Creative idea and script'), lang('Casting and locations'), lang('A cut for every screen and length'), lang('Broadcast-ready delivery'))),
    'brand_films' => array('icon' => 'bi-stars', 'title' => lang('Brand Films'),
        'summary' => lang('Longer films that tell who a company is, what it makes and why it matters.'),
        'text'    => lang('A brand film has room for a story: the people behind a product, the place it is made, the customers who use it. We find that story in the research before we write a word of the script.'),
        'points'  => array(lang('Research and interviews'), lang('Story and treatment'), lang('Filming on location'), lang('Music and sound design'))),
    'music_videos' => array('icon' => 'bi-music-note-beamed', 'title' => lang('Music Videos'),
        'summary' => lang('Videos for artists and labels, from performance films to full narratives.'),
        'text'    => lang('We work with the artist on an idea that fits the song and the budget, then shoot it in the studio or on location and grade it into a look of its own.'),
        'points'  => array(lang('Concept and treatment'), lang('Performance and narrative shoots'), lang('Choreography and art direction'), lang('Colour grading with a look of its own'))),
    'documentary' => array('icon' => 'bi-camera-reels', 'title' => lang('Documentaries'),
        'summary' => lang('Short and feature-length documentaries for broadcasters, festivals and causes.'),
        'text'    => lang('We follow real people for weeks or months, with a small crew that stays out of the way. Our producers handle access, releases and archive footage, and our editors shape the hours of footage into a story.'),
        'points'  => array(lang('Research and access'), lang('Small observational crews'), lang('Archive research and licensing'), lang('Festival and broadcast delivery'))),
    'corporate' => array('icon' => 'bi-people', 'title' => lang('Corporate and Event Films'),
        'summary' => lang('Conferences, launches, training films and the stories of a company for its own people.'),
        'text'    => lang('We film events with several cameras, stream them live when needed and cut a highlight film by the next morning. Training and internal films are scripted with your team and filmed around the working day.'),
        'points'  => array(lang('Multi-camera event filming'), lang('Live streaming'), lang('Next-day highlight films'), lang('Training and internal films'))),
    'post' => array('icon' => 'bi-sliders', 'title' => lang('Post-production'),
        'summary' => lang('Editing, colour grading, sound mixing and motion graphics for footage shot by anyone.'),
        'text'    => lang('Our edit, grading and sound suites also work on films other crews have shot. Bring us the footage and the deadline; we deliver the master in every format the broadcaster or platform asks for.'),
        'points'  => array(lang('Offline and online editing'), lang('Colour grading'), lang('Sound design and mixing'), lang('Motion graphics and titles'))),
);

// The industries the studio films for, the project list's first filter.
$industries = array(
    'automotive' => array('icon' => 'bi-car-front', 'title' => lang('Automotive'),
        'text' => lang('Car launches, dealers and mobility brands.')),
    'fashion'    => array('icon' => 'bi-bag', 'title' => lang('Fashion and Beauty'),
        'text' => lang('Collections, lookbooks and beauty brands.')),
    'food'       => array('icon' => 'bi-cup-hot', 'title' => lang('Food and Drink'),
        'text' => lang('Restaurants, producers and drinks brands.')),
    'music'      => array('icon' => 'bi-vinyl', 'title' => lang('Music and Culture'),
        'text' => lang('Artists, labels, festivals and museums.')),
    'technology' => array('icon' => 'bi-cpu', 'title' => lang('Technology'),
        'text' => lang('Software, devices and the people who build them.')),
    'nonprofit'  => array('icon' => 'bi-heart', 'title' => lang('Non-profit'),
        'text' => lang('Charities, foundations and public campaigns.')),
);

// The sample projects: the records the Projects form starts with. Where it
// was shot and the running time are words; the status is "Released" or "In
// post-production", never a year. The credits have one "Role: Name" line
// each.
$projects = array(
    'night_drive' => array('name' => lang('Night Drive'), 'industry' => 'automotive', 'service' => 'commercials',
        'client' => lang('Meridian Motors'), 'location' => lang('Coast road and city tunnels'), 'runtime' => lang('60 seconds'),
        'status' => 'released',
        'summary' => lang('A launch spot for an electric car, shot in one night on an empty coast road with a camera car and a drone.'),
        'credits' => array(lang('Director: Ece Kaya'), lang('Director of Photography: Tom Hughes'), lang('Producer: Leyla Aksoy'), lang('Editor: Can Yıldız'), lang('Colourist: Mira Novak')),
        'quote'   => lang('They lit a whole coast road for one night and gave us the best launch film we have had.'),
        'featured' => true),
    'thread_and_needle' => array('name' => lang('Thread and Needle'), 'industry' => 'fashion', 'service' => 'brand_films',
        'client' => lang('Atelier Lune'), 'location' => lang('The atelier and the old town'), 'runtime' => lang('3 minutes'),
        'status' => 'released',
        'summary' => lang('A brand film about the tailors of a small fashion house, told through the making of one coat.'),
        'credits' => array(lang('Director: Ece Kaya'), lang('Director of Photography: Tom Hughes'), lang('Producer: Leyla Aksoy'), lang('Editor: Can Yıldız'), lang('Sound Design: Baran Öz')),
        'quote'   => lang('Our tailors had never been on camera, and they forgot the camera was there after the first hour.'),
        'featured' => true),
    'slow_bloom' => array('name' => lang('Slow Bloom'), 'industry' => 'music', 'service' => 'music_videos',
        'client' => lang('Juniper Avenue'), 'location' => lang('Stage One and a greenhouse'), 'runtime' => lang('4 minutes'),
        'status' => 'released',
        'summary' => lang('A music video shot in one continuous take through a greenhouse built on our largest stage.'),
        'credits' => array(lang('Director: Mira Novak'), lang('Director of Photography: Tom Hughes'), lang('Producer: Leyla Aksoy'), lang('Production Designer: Selin Ak'), lang('Colourist: Mira Novak')),
        'quote'   => lang('One take, four minutes, and every flower in the right place: the band still talks about that day.'),
        'featured' => true),
    'harvest_table' => array('name' => lang('The Harvest Table'), 'industry' => 'food', 'service' => 'documentary',
        'client' => lang('Valley Growers Cooperative'), 'location' => lang('Farms across the valley'), 'runtime' => lang('24 minutes'),
        'status' => 'released',
        'summary' => lang('A short documentary following four farming families through one season, from sowing to the harvest feast.'),
        'credits' => array(lang('Director: Ece Kaya'), lang('Camera: Deniz Arslan'), lang('Producer: Leyla Aksoy'), lang('Editor: Can Yıldız'), lang('Music: Baran Öz')),
        'quote'   => lang('The film showed our members\' work better than any report we have written.'),
        'featured' => false),
    'build_day' => array('name' => lang('Build Day'), 'industry' => 'technology', 'service' => 'corporate',
        'client' => lang('Brightline Software'), 'location' => lang('The client\'s offices and conference hall'), 'runtime' => lang('5 minutes'),
        'status' => 'released',
        'summary' => lang('A developer conference filmed with six cameras, streamed live and cut into a highlight film by the next morning.'),
        'credits' => array(lang('Producer: Leyla Aksoy'), lang('Live Director: Deniz Arslan'), lang('Camera: Tom Hughes'), lang('Editor: Can Yıldız'), lang('Motion Graphics: Selin Ak')),
        'quote'   => lang('The highlight film was in our inbox before the first speaker had left the city.'),
        'featured' => false),
    'open_doors' => array('name' => lang('Open Doors'), 'industry' => 'nonprofit', 'service' => 'post',
        'client' => lang('Harbour Shelter Foundation'), 'location' => lang('Footage from the foundation\'s volunteers'), 'runtime' => lang('90 seconds'),
        'status' => 'in_post',
        'summary' => lang('An appeal film cut from footage the foundation\'s volunteers filmed on their phones, graded and mixed in our suites.'),
        'credits' => array(lang('Editor: Can Yıldız'), lang('Colourist: Mira Novak'), lang('Sound Design: Baran Öz'), lang('Producer: Leyla Aksoy')),
        'quote'   => lang('They turned hours of shaky phone footage into a film that made people pick up the phone.'),
        'featured' => false),
);

// The words of a project's status, the value of its Status field.
$project_statuses = array('released' => lang('Released'), 'in_post' => lang('In post-production'));
// A project's credits as the value of its Credits field: one line each.
$credits_text = function ($key) use ($projects) {
    return implode("\n", $projects[$key]['credits']);
};

// How a production runs, from the first call to the delivery (the process
// page and the home).
$process = array(
    array('title' => lang('Brief and treatment'), 'text' => lang('We listen to what the film must do and send a treatment: the idea, the look and a first budget.')),
    array('title' => lang('Pre-production'), 'text' => lang('Script, storyboard, casting, locations and the shooting schedule, agreed with you before the shoot.')),
    array('title' => lang('The shoot'), 'text' => lang('Our crew films on our stages or on location, with the producer at your side on set.')),
    array('title' => lang('The edit'), 'text' => lang('Editing, colour grading, sound and graphics, with two rounds of changes on every cut.')),
    array('title' => lang('Delivery'), 'text' => lang('The master and every version you need, in the formats each screen and platform asks for.')),
);

// The studio's spaces: what the studio page shows and the studio hire
// request offers (?space=).
$spaces = array(
    'stage_one'  => array('icon' => 'bi-camera-video', 'title' => lang('Stage One'),
        'text' => lang('Our large sound stage with a lighting grid, blackout and room for full sets.')),
    'cyclorama'  => array('icon' => 'bi-bounding-box', 'title' => lang('Cyclorama Stage'),
        'text' => lang('A white infinity curve that also turns green for compositing.')),
    'edit_suite' => array('icon' => 'bi-film', 'title' => lang('Edit Suites'),
        'text' => lang('Three quiet edit rooms with calibrated monitors and fast storage.')),
    'grade_mix'  => array('icon' => 'bi-soundwave', 'title' => lang('Grading and Sound Suite'),
        'text' => lang('A grading theatre and a mixing room for the final picture and sound.')),
);

// The studio's people (the crew page and the about page). Fixed data, not
// a collection: the core crew changes rarely.
$crew = array(
    array('name' => lang('Ece Kaya'), 'role' => lang('Founder and Director'),
        'bio' => lang('Directs commercials and documentaries, and still writes the first treatment of every film.')),
    array('name' => lang('Tom Hughes'), 'role' => lang('Director of Photography'),
        'bio' => lang('Lights and shoots our films, from a phone-sized rig to a full camera crew.')),
    array('name' => lang('Leyla Aksoy'), 'role' => lang('Executive Producer'),
        'bio' => lang('Runs every production\'s budget, schedule and crew, and is the client\'s one point of contact.')),
    array('name' => lang('Can Yıldız'), 'role' => lang('Lead Editor'),
        'bio' => lang('Finds the story in the footage and cuts every version the film needs.')),
    array('name' => lang('Mira Novak'), 'role' => lang('Colourist and Director'),
        'bio' => lang('Grades our films into a look of their own and directs music videos.')),
    array('name' => lang('Baran Öz'), 'role' => lang('Sound Designer and Composer'),
        'bio' => lang('Records, designs and mixes the sound, and writes original music when a film needs it.')),
);

// Why clients choose the studio (the home and the about page).
$why_us = array(
    array('icon' => 'bi-lightbulb', 'title' => lang('Ideas first'),
        'text' => lang('Every film starts with a treatment you can read before you commit to a budget.')),
    array('icon' => 'bi-person-badge', 'title' => lang('One producer'),
        'text' => lang('One person who knows your film from the first call to the delivery.')),
    array('icon' => 'bi-house-gear', 'title' => lang('Everything in house'),
        'text' => lang('Stages, kit, edit, grade and sound under one roof, so nothing waits on a supplier.')),
    array('icon' => 'bi-cash-coin', 'title' => lang('A price agreed up front'),
        'text' => lang('The budget we agree is the price you pay for the film we agreed.')),
);

// The studio in four figures (the home and the about page).
$figures = array(
    array('value' => '400+', 'label' => lang('Films delivered')),
    array('value' => '35', 'label' => lang('Brands and artists a year')),
    array('value' => '2', 'label' => lang('Stages under one roof')),
    array('value' => '12', 'label' => lang('Festival selections')),
);

// What clients say, each about one of the sample projects.
$testimonials = array(
    array('project' => 'night_drive', 'name' => lang('Okan Tekin'), 'role' => lang('Client of Night Drive'),
        'quote' => lang('They lit a whole coast road for one night and gave us the best launch film we have had.')),
    array('project' => 'thread_and_needle', 'name' => lang('Claire Dubois'), 'role' => lang('Client of Thread and Needle'),
        'quote' => lang('Our tailors had never been on camera, and they forgot the camera was there after the first hour.')),
    array('project' => 'harvest_table', 'name' => lang('Hasan Polat'), 'role' => lang('Client of The Harvest Table'),
        'quote' => lang('The film showed our members\' work better than any report we have written.')),
);

// The sample crew calls: the records the Jobs form starts with. Each call
// opens the application with the role chosen (?job=).
$jobs = array(
    'production_assistant' => array('title' => lang('Production Assistant'), 'department' => 'production', 'type' => 'full_time',
        'location' => lang('The studio and locations'),
        'summary' => lang('Keep a shoot running: call sheets, kit lists, the crew\'s day and everything in between.'),
        'duties'  => array(lang('Prepare call sheets and schedules'), lang('Book kit, transport and catering'), lang('Look after the cast and crew on set'), lang('Keep the production\'s paperwork in order'))),
    'assistant_editor' => array('title' => lang('Assistant Editor'), 'department' => 'post', 'type' => 'full_time',
        'location' => lang('The studio'),
        'summary' => lang('Prepare the footage, sync the sound and build the timelines our editors cut from.'),
        'duties'  => array(lang('Ingest and back up the footage'), lang('Sync the sound and log the takes'), lang('Prepare exports and deliveries'), lang('Cut social versions of finished films'))),
    'camera_assistant' => array('title' => lang('Camera Assistant'), 'department' => 'camera', 'type' => 'freelance',
        'location' => lang('The studio and locations'),
        'summary' => lang('Prepare, build and look after the camera on shoot days, and pull focus for our camera operators.'),
        'duties'  => array(lang('Prepare and test the camera kit'), lang('Pull focus and change lenses'), lang('Manage the media cards on set'), lang('Keep the camera report'))),
    'runner' => array('title' => lang('Studio Runner'), 'department' => 'production', 'type' => 'internship',
        'location' => lang('The studio'),
        'summary' => lang('Learn how films are made by helping every team of the studio for six months.'),
        'duties'  => array(lang('Help set up the stages and the kit'), lang('Run errands for the crew on shoot days'), lang('Shadow the producers and editors'), lang('Keep the studio ready for clients'))),
);
$departments = array('production' => lang('Production'), 'camera' => lang('Camera and Lighting'), 'post' => lang('Post-production'));
$job_types = array('full_time' => lang('Full-time'), 'freelance' => lang('Freelance'), 'internship' => lang('Internship'));
// A crew call's duties as the value of its Duties field: one line per duty.
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
$industry_titles = array();
foreach ($industries as $s) $industry_titles[] = $s['title'];
$project_names = array();
foreach ($projects as $p) $project_names[] = $p['name'];
$status_titles = array_values($project_statuses);
$space_titles = array();
foreach ($spaces as $s) $space_titles[] = $s['title'];
$enquiry_service_choices = array_merge($service_titles, array(lang('Not sure yet')));
// Where the film will be shown, and how long it runs.
$screen_choices = array(lang('Television and cinema'), lang('Online and social media'), lang('Events and screens'), lang('Inside the company'), lang('Festivals'), lang('Not sure yet'));
$length_choices = array(lang('Under one minute'), lang('One to five minutes'), lang('Five to thirty minutes'), lang('Longer than thirty minutes'), lang('Several short films'));
$budget_low = $money(array('USD' => 10000, 'EUR' => 9200, 'GBP' => 8000, 'TRY' => 360000));
$budget_mid = $money(array('USD' => 50000, 'EUR' => 46000, 'GBP' => 40000, 'TRY' => 1800000));
$budget_high = $money(array('USD' => 150000, 'EUR' => 138000, 'GBP' => 120000, 'TRY' => 5400000));
$budget_choices = array(
    sprintf(lang('Up to %s'), $budget_low),
    sprintf(lang('%1$s to %2$s'), $budget_low, $budget_mid),
    sprintf(lang('%1$s to %2$s'), $budget_mid, $budget_high),
    sprintf(lang('More than %s'), $budget_high),
    lang('Not sure yet'),
);
$delivery_choices = array(lang('Within a month'), lang('Within three months'), lang('Within six months'), lang('Not decided yet'));
// The states of a project enquiry, set by the studio in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Call booked'), lang('Proposal sent'), lang('In production'), lang('Declined'));
// How long a space is hired for, and the states of a hire request.
$hire_lengths = array(lang('Half a day'), lang('A full day'), lang('Two days or more'));
$hire_statuses = array(lang('Request received'), lang('Confirmed'), lang('Not available'));
$department_titles = array_values($departments);
$job_type_titles = array_values($job_types);
$job_titles = array();
foreach ($jobs as $j) $job_titles[] = $j['title'];
$application_job_choices = array_merge($job_titles, array(lang('Open application')));
// The states of an application, set by the studio in the panel.
$application_statuses = array(lang('Application received'), lang('Interview'), lang('Offer made'), lang('Not this time'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The query string of a link that opens a list with a filter set
// (?industry=, ?service=) or a form with a choice made (?service=, ?space=, ?job=):
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
            $heading('h1', $title, 'pfm-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pfm-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pfm-display h3 mb-0'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'fm-faq') use ($el, $para, $attr, $searchable) {
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
                $span($f['value'], 'pfm-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the free first call and the way to ask for
// it. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-camera-reels', 'text-primary'),
        $span(lang('A free first call and a written treatment within a week'), '', lang('Text')),
        $link(lang('Start a Project'), '{{page:enquiry}}', 'link-primary fw-semibold'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The studio
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

// The header: the studio's name, the menu and the way to start a project.
// The links to the current page are marked active on the server (smart
// active state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('projects', lang('Work')),
        array('services', lang('Our Services')),
        array('studio', lang('The Studio')),
        array('crew', lang('The Crew')),
        array('about', lang('About Us')),
        array('blog', lang('Journal')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-film', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pfm-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#fm-nav'),
                    $attr('aria-controls', 'fm-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-camera-reels'),
                            $span(lang('Start a Project'), '', lang('Link Text')),
                        ), array('href' => '{{page:enquiry}}')),
                    )),
                ), array('id' => 'fm-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the studio in a sentence, how to reach it
// and its profiles, the services, the work by industry, the client pages
// and the studio's pages; at the bottom the year, the site's name and the
// studio's note.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $services, $industries, $studio, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $service_links = array();
    foreach ($services as $key => $s) $service_links[] = $link($s['title'], '{{page:service_' . $key . '}}', $plain);
    $industry_links = array($link(lang('All Work'), '{{page:projects}}', $plain));
    foreach ($industries as $s) $industry_links[] = $link($s['title'], '{{page:projects}}?' . $query(array('industry' => $s['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Studio'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-film', 'text-primary'),
                        $span('{{site_name}}', 'pfm-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($studio['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($studio['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($studio['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $studio['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($studio['email'], 'mailto:' . $studio['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($studio['hours'], '', lang('Text')), lang('Office Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-vimeo', 'Vimeo'),
                        $social('bi-youtube', 'YouTube'),
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-linkedin', 'LinkedIn'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Service Links'), array(
                    $heading('h2', lang('Our Services'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($service_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Work Links'), array(
                    $heading('h2', lang('Work'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($industry_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Clients'), array(
                    $heading('h2', lang('For Clients'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Start a Project'), '{{page:enquiry}}', $plain),
                        $link(lang('My Enquiries'), '{{page:my_enquiries}}', $plain),
                        $link(lang('Hire the Studio'), '{{page:studio_hire}}', $plain),
                        $link(lang('How We Work'), '{{page:process}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Studio'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('The Crew'), '{{page:crew}}', $plain),
                        $link(lang('Join the Crew'), '{{page:careers}}', $plain),
                        $link(lang('Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($studio['note'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The enquiry band: a full-width dark band with the way to start a
// project and the phone number. Shared, so every page that ends with it
// carries the same call.
$enquiry_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $studio, $attr) {
    return $el('section', 'pfm-hero py-5 bg-body text-body', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Have a story to tell?'), 'pfm-display h2 mb-2'),
                    $para(lang('Tell us what the film must do. We call you back within a day and send a written treatment within a week.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Start a Project'), '{{page:enquiry}}', 'btn btn-primary btn-lg px-4'),
                        $link($studio['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $studio['phone']), 'btn btn-outline-light btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The showreel: a player with the still as its poster. The studio puts its
// own file in its place (or swaps the player for its video host's embed);
// until then the poster shows.
// $frame is the frame's class: on the home's cinema screen the screen
// rounds and fills it (pg-filmstudio-screen).
$showreel = function ($seed = 'showreel', $title = '', $frame = 'ratio ratio-16x9 rounded-3 overflow-hidden bg-dark') use ($el, $attr, $para, $photo) {
    if ($title === '') $title = lang('Showreel');
    return $el('div', $frame, lang('Video Frame'), array(
        $el('video', 'w-100 h-100 object-fit-cover', lang('Video'), array(
            $para(lang('Your browser cannot play this video.'), 'text-white p-3'),
        ), array('_attrs' => array(
            $attr('controls', ''), $attr('preload', 'none'), $attr('playsinline', ''),
            $attr('poster', $photo($seed, 1280, 720)), $attr('title', $title),
        ))),
    ));
};

// The facts of a project in one list: client, industry, service, where it
// was shot, running time and status, each bound to the Projects form's
// field of the same name.
$project_facts = function ($sample_key = 'night_drive', $class = 'd-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $projects, $industries, $services, $project_statuses) {
    $p = $projects[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', 'list-unstyled ' . $class, lang('Project Facts'), array(
        $fact('bi-building', $p['client'], 'client', lang('Client')),
        $fact($industries[$p['industry']]['icon'], $industries[$p['industry']]['title'], 'industry', lang('Industry')),
        $fact($services[$p['service']]['icon'], $services[$p['service']]['title'], 'service', lang('Service Type')),
        $fact('bi-geo-alt', $p['location'], 'location', lang('Shot In')),
        $fact('bi-stopwatch', $p['runtime'], 'runtime', lang('Running Time')),
        $fact('bi-flag', $project_statuses[$p['status']], 'status', lang('Status')),
    ));
};

// A project's credits: the Credits field as written, one "Role: Name" line
// each; the field's value keeps its lines on the page.
$credits_list = function ($sample_key = 'night_drive') use ($el, $heading, $bind, $credits_text) {
    return $el('div', '', lang('Credits'), array(
        $heading('h2', lang('Credits'), 'pfm-display h5 mb-3'),
        $el('div', 'pg-field-value small text-body-secondary lh-lg', lang('Value'), array(), array_merge(
            array('text' => $credits_text($sample_key)), $bind('credits'))),
    ));
};

// A project's card of the lists (the home, the work list and a service's
// page): the still with a play mark, the industry, the name linking the
// project page, the client, the running time, the summary and the status.
// Every value is bound to the Projects form's field of the same name; the
// sample fills the canvas.
$project_card = function ($sample_key = 'night_drive') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $projects, $industries, $project_statuses, $photo, $attr) {
    $p = $projects[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden pfm-lift', lang('Project Card'), array(
            $el('span', 'd-block position-relative', lang('Still'), array(
                $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($photo('project-' . $sample_key, 800, 450), $p['name'], 'w-100 h-100 object-fit-cover',
                        array('_bindings' => array('src' => 'still_1', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $el('span', 'pfm-play position-absolute top-50 start-50 translate-middle', lang('Play'), array(
                    $icon('bi-play-circle-fill', 'display-5 text-white'),
                )),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($industries[$p['industry']]['title'], 'pfm-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Industry'), $bind('industry')),
                $el('h3', 'h5 position-static mb-1', lang('Project Name'), array(
                    $link($p['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex flex-wrap align-items-center gap-3 small fw-semibold mb-2', lang('Client and Running Time'), array(
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Client'), array(
                        $icon('bi-building', 'text-primary'),
                        $span($p['client'], '', lang('Client'), $bind('client')),
                    )),
                    $el('span', 'd-inline-flex align-items-center gap-1', lang('Running Time'), array(
                        $icon('bi-stopwatch', 'text-primary'),
                        $span($p['runtime'], '', lang('Running Time'), $bind('runtime')),
                    )),
                )),
                $para($p['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $span($project_statuses[$p['status']], 'badge text-bg-dark align-self-start mt-auto', lang('Status'), $bind('status')),
            )),
        )),
    ), '6', '4');
};

// A service's card (the home and the services page): the icon, the title
// linking the service's page and the summary.
$service_card = function ($key) use ($el, $para, $link, $col, $icon_tile, $services) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 pfm-lift', lang('Service Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'align-self-start mb-3'),
                $el('h3', 'pfm-display h5 position-static mb-2', lang('Service Name'), array(
                    $link($s['title'], '{{page:service_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($s['summary'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// A space of the studio (the studio page and the home): the still, the
// icon, the name, a sentence and the way to ask for it (?space=).
$space_card = function ($key) use ($el, $heading, $para, $link, $image, $col, $icon, $attr, $photo, $spaces, $query) {
    $s = $spaces[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 overflow-hidden', lang('Space Card'), array(
            $el('span', 'd-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('space-' . $key, 800, 450), $s['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('h3', 'pfm-display h5 d-flex align-items-center gap-2 mb-2', lang('Space Name'), array(
                    $icon($s['icon'], 'text-primary'),
                    $el('span', '', lang('Name'), array(), array('text' => $s['title'])),
                )),
                $para($s['text'], 'small text-body-secondary mb-3'),
                $link(lang('Ask for this space'), '{{page:studio_hire}}?' . $query(array('space' => $s['title'])), 'link-primary fw-semibold small mt-auto'),
            )),
        )),
    ), '6', '3');
};

// A member of the crew (the crew page and the about page): the portrait,
// the name, the role and a sentence.
$crew_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $crew) {
    $t = $crew[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-3 text-center p-4', lang('Crew Member'), array(
            $image($portrait($t['name'], 240), $t['name'], 'rounded-circle mx-auto mb-3', array('_attrs' => array($attr('width', '120'), $attr('height', '120'), $attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'h5 mb-1'),
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
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pfm-quote mb-0'))),
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

// A crew call's card (the careers page): the department, the title
// linking the call's page, the type, the location and the summary, each
// bound to the Jobs form's field of the same name.
$job_card = function ($sample_key = 'production_assistant') use ($el, $para, $span, $link, $icon, $bind, $jobs, $departments, $job_types) {
    $j = $jobs[$sample_key];
    return $el('article', 'card border-0 shadow-sm rounded-3 mb-3 pfm-lift', lang('Job Card'), array(
        $el('div', 'card-body d-flex flex-column flex-md-row align-items-md-center gap-3 p-4', lang('Card Body'), array(
            $el('div', 'flex-grow-1', lang('Job'), array(
                $span($departments[$j['department']], 'pfm-eyebrow small text-uppercase fw-semibold text-primary', lang('Department'), $bind('department')),
                $el('h3', 'h5 position-static my-1', lang('Job Title'), array(
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
                $item('staff_enquiries', lang('Project Enquiries')),
                $item('staff_studio_hires', lang('Studio Hire Requests')),
                $item('job_new', lang('New Crew Call')),
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
    return $agree($id, 'consent', lang('I would like to receive the studio\'s new films and stories from the set by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. An
// enquiry takes a brief, a script or reference pictures, an application a
// CV, so the default accepts PDFs, office files, text and pictures; one
// that takes only pictures is a collection's image kind (a project's
// stills).
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
                    $link(lang('How we plan a one-night shoot'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

