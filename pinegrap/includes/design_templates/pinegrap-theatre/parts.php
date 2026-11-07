<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Theatre", part 1 of its files: the node builders, the
 * theatre's own data (box office hours, the stages, the genres, the sample
 * productions of the season and their performances counted from today, the
 * access performances, ticket bands and friends tiers with their prices,
 * the youth theatre groups and school workshops, the spaces for hire,
 * facilities, the way there, the people, the figures, what audiences say
 * and the answers of the forms' pick lists) and the parts every page
 * repeats (account bar, header, footer, box office band, the box office
 * hours, production, genre, performance, ticket, friends, group,
 * workshop, space, access, staff and audience cards, page shells, section
 * headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-theatre.php puts them together.
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
// every opening until the theatre puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-theatre-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/fbe9eb/9f1239?text=' . rawurlencode($initials !== '' ? $initials : 'T');
};

// ── The theatre: its box office, its season, its stages and its people ──

// The theatre itself. The about page tells its story; the footer, the box
// office band and the visit page use the address, phone and hours.
$theatre = array(
    'tagline'  => lang('New plays, musicals, dance and family shows on two stages in the heart of the city'),
    'story'    => lang('The theatre opened in an old corn exchange, when a company of actors turned its hall into a stage. Today it makes its own productions, welcomes touring companies on two stages, runs a youth theatre for hundreds of young people and keeps a part of every house for tickets anyone can afford.'),
    'address'  => lang('Theatre Square 5, Old Town'),
    'phone'    => '+90 555 000 00 00',
    'email'    => 'boxoffice@example.com',
    'hours'    => lang('Box office open Monday to Saturday, 10:00 until the curtain goes up'),
    'doors'    => lang('Doors open 45 minutes before each performance'),
    'late'     => lang('Latecomers are seated at a suitable break'),
    'licence'  => lang('A producing theatre, run by a registered charity'),
);

// The box office hours, day by day (the visit page; the footer and the
// account bar say them in a line).
$box_office_hours = array(
    array('days' => lang('Monday to Friday'), 'hours' => lang('10:00 until the curtain goes up')),
    array('days' => lang('Saturday'), 'hours' => lang('10:00 until the curtain goes up')),
    array('days' => lang('Sunday'), 'hours' => lang('Two hours before a performance')),
    array('days' => lang('On the phone'), 'hours' => '10:00 - 18:00'),
);

// The stages: a production's Stage field and the hire page's first spaces.
$stages = array(
    'main'   => array('title' => lang('The Main House'), 'seats' => lang('650 seats on three levels')),
    'studio' => array('title' => lang('The Studio'), 'seats' => lang('120 seats around the stage')),
);

// The genres of a production. The season filters on them (?genre=) in
// their own words.
$genres = array(
    'drama'   => array('icon' => 'bi-mask', 'title' => lang('Drama'),
        'summary' => lang('New writing and the great plays, told by our own company.')),
    'musical' => array('icon' => 'bi-music-note-beamed', 'title' => lang('Musicals'),
        'summary' => lang('Big songs, a live band and stories that send you home humming.')),
    'comedy'  => array('icon' => 'bi-emoji-laughing', 'title' => lang('Comedy'),
        'summary' => lang('Farce, sharp wit and stand-up nights in the Studio.')),
    'dance'   => array('icon' => 'bi-person-arms-up', 'title' => lang('Dance'),
        'summary' => lang('Contemporary and classical companies from home and abroad.')),
    'opera'   => array('icon' => 'bi-mic', 'title' => lang('Opera'),
        'summary' => lang('Touring opera with a full orchestra, sung with surtitles.')),
    'family'  => array('icon' => 'bi-balloon', 'title' => lang('Family Shows'),
        'summary' => lang('Shows for children and the grown-ups who bring them.')),
);

// The states of a production: the Status field's answers and the season's
// filter (?status=).
$production_status_titles = array(
    'now'  => lang('Now Playing'),
    'soon' => lang('Coming to the Stage'),
    'past' => lang('Past Seasons'),
);

// The sample productions: the records the Productions form starts with.
// The run is words without a date or a year; the theatre writes its own
// run in each record and moves a production from Coming to the Stage to Now
// Playing to Past Seasons. The dates of each performance are records of
// the Performances form.
$productions = array(
    'lighthouse' => array('title' => lang('The Lighthouse Keeper'), 'genre' => 'drama', 'status' => 'now', 'stage' => 'main',
        'run' => lang('Until the end of spring'), 'length' => lang('2 hours 20 minutes, with one interval'), 'age' => lang('Ages 12 and over'),
        'credits' => lang('Written by Elif Tan, directed by Kerem Aksoy'),
        'summary' => lang('On a storm-bound island a keeper and his daughter wait for a ship that never comes. A new play about the people we keep the light on for.'),
        'cast' => array(lang('Can Yıldız as the Keeper'), lang('Ada Kaya as his daughter'), lang('Mert Uçar as the Stranger'), lang('Live music by the Harbour Trio')),
        'featured' => true),
    'honey' => array('title' => lang('Salt and Honey'), 'genre' => 'musical', 'status' => 'now', 'stage' => 'main',
        'run' => lang('All summer long'), 'length' => lang('2 hours 40 minutes, with one interval'), 'age' => lang('Ages 8 and over'),
        'credits' => lang('Music and words by Defne Ay, directed by Laura Grant'),
        'summary' => lang('Two rival bakeries on one street, a wedding cake that must be ready by Saturday and a band that never stops playing.'),
        'cast' => array(lang('A company of eighteen actors'), lang('A live band of seven musicians'), lang('Choreography by Tolga Er'), lang('Designed by Nehir Su')),
        'featured' => true),
    'neighbours' => array('title' => lang('A Comedy of Neighbours'), 'genre' => 'comedy', 'status' => 'now', 'stage' => 'studio',
        'run' => lang('For six weeks only'), 'length' => lang('1 hour 30 minutes, no interval'), 'age' => lang('Ages 14 and over'),
        'credits' => lang('Written by Sam Porter, directed by Elif Tan'),
        'summary' => lang('Paper-thin walls, one shared washing line and a noise complaint that gets badly out of hand.'),
        'cast' => array(lang('Four actors, twelve characters'), lang('Staged in the round'), lang('Sound by Arda Bulut'), lang('Some strong language')),
        'featured' => true),
    'swans' => array('title' => lang('Winter Swans'), 'genre' => 'dance', 'status' => 'soon', 'stage' => 'main',
        'run' => lang('Opens in the autumn'), 'length' => lang('1 hour 50 minutes, with one interval'), 'age' => lang('All ages'),
        'credits' => lang('Choreographed by Mina Ward for a visiting company'),
        'summary' => lang('A new ballet about a frozen lake and the dancers who wake it, with a score played live.'),
        'cast' => array(lang('Twenty-four dancers'), lang('A live orchestra'), lang('Lighting by Ozan Kılıç'), lang('A visiting company on tour')),
        'featured' => false),
    'dragon' => array('title' => lang('The Paper Dragon'), 'genre' => 'family', 'status' => 'soon', 'stage' => 'studio',
        'run' => lang('Through the winter holidays'), 'length' => lang('1 hour, no interval'), 'age' => lang('Ages 3 to 8 and their families'),
        'credits' => lang('Made by the theatre\'s learning team'),
        'summary' => lang('A dragon folded from an old map sets off to find the place drawn on its wings. Puppets, songs and a lot of paper.'),
        'cast' => array(lang('Three puppeteers'), lang('Songs to join in with'), lang('Cushions at the front for little ones'), lang('A relaxed performance every week')),
        'featured' => false),
    'orchard' => array('title' => lang('The Glass Orchard'), 'genre' => 'opera', 'status' => 'past', 'stage' => 'main',
        'run' => lang('Shown last season'), 'length' => lang('2 hours 50 minutes, with two intervals'), 'age' => lang('Ages 12 and over'),
        'credits' => lang('A touring opera company, conducted by Leyla Öz'),
        'summary' => lang('A gardener grows trees of glass for a king who cannot bear anything that breaks. Sung in Italian with surtitles.'),
        'cast' => array(lang('A cast of nine singers'), lang('An orchestra of forty'), lang('Surtitles in two languages'), lang('Seen by over 12,000 people')),
        'featured' => false),
);

// The access performances: the Access field's answers and the list's
// filter (?access=). A standard performance carries none of these marks.
$access_types = array(
    'standard'  => array('icon' => 'bi-ticket-perforated', 'title' => lang('Standard'),
        'text' => lang('Every performance has step-free seats, an assisted listening system and space for assistance dogs.')),
    'relaxed'   => array('icon' => 'bi-emoji-smile', 'title' => lang('Relaxed Performance'),
        'text' => lang('Lights stay up a little, loud sounds are softened and you are free to move and make noise.')),
    'captioned' => array('icon' => 'bi-badge-cc', 'title' => lang('Captioned'),
        'text' => lang('Every word spoken and sung appears on screens beside the stage.')),
    'audio'     => array('icon' => 'bi-headphones', 'title' => lang('Audio Described'),
        'text' => lang('A describer tells you what happens on stage through a headset, with a touch tour before.')),
    'sign'      => array('icon' => 'bi-hand-index', 'title' => lang('Sign Language Interpreted'),
        'text' => lang('An interpreter signs the whole performance from the side of the stage.')),
);

// The state of a performance's tickets: the Ticket State field's answers.
$ticket_states = array(
    'on_sale'  => array('title' => lang('On Sale'), 'badge' => 'text-bg-success'),
    'few_left' => array('title' => lang('Few Left'), 'badge' => 'text-bg-warning'),
    'sold_out' => array('title' => lang('Sold Out'), 'badge' => 'text-bg-secondary'),
);

// The day of a sample performance, counted from the day the template is
// opened so the season is still to come (and the past performances behind
// it): the first day after today with the ISO weekday given (1 = Monday),
// plus $weeks weeks; a negative $weeks counts back. Each performance is a
// record of the Performances form, edited or deleted like any other.
$perf_on = function ($weekday, $weeks = 0) {
    $day = new DateTime('today');
    do {
        $day->modify('+1 day');
    } while ((int)$day->format('N') !== $weekday);
    if ($weeks !== 0) $day->modify(($weeks > 0 ? '+' : '-') . (7 * abs($weeks)) . ' days');
    return $day->format('Y-m-d');
};

// The sample performances: eight to come, two already played. 'time' is
// when the curtain goes up.
$performances = array(
    'lh_1' => array('production' => 'lighthouse', 'weekday' => 5, 'weeks' => 0, 'time' => '19:30', 'access' => 'standard', 'state' => 'few_left', 'featured' => true),
    'lh_2' => array('production' => 'lighthouse', 'weekday' => 6, 'weeks' => 0, 'time' => '14:30', 'access' => 'captioned', 'state' => 'on_sale', 'featured' => true),
    'lh_3' => array('production' => 'lighthouse', 'weekday' => 3, 'weeks' => 1, 'time' => '19:30', 'access' => 'audio', 'state' => 'on_sale', 'featured' => false),
    'hn_1' => array('production' => 'honey', 'weekday' => 6, 'weeks' => 0, 'time' => '19:30', 'access' => 'standard', 'state' => 'sold_out', 'featured' => true),
    'hn_2' => array('production' => 'honey', 'weekday' => 7, 'weeks' => 1, 'time' => '15:00', 'access' => 'relaxed', 'state' => 'on_sale', 'featured' => false),
    'hn_3' => array('production' => 'honey', 'weekday' => 4, 'weeks' => 2, 'time' => '19:30', 'access' => 'sign', 'state' => 'on_sale', 'featured' => false),
    'nb_1' => array('production' => 'neighbours', 'weekday' => 4, 'weeks' => 0, 'time' => '20:00', 'access' => 'standard', 'state' => 'on_sale', 'featured' => false),
    'nb_2' => array('production' => 'neighbours', 'weekday' => 2, 'weeks' => 1, 'time' => '20:00', 'access' => 'captioned', 'state' => 'few_left', 'featured' => false),
    'lh_past' => array('production' => 'lighthouse', 'weekday' => 5, 'weeks' => -3, 'time' => '19:30', 'access' => 'standard', 'state' => 'sold_out', 'featured' => false),
    'nb_past' => array('production' => 'neighbours', 'weekday' => 6, 'weeks' => -2, 'time' => '20:00', 'access' => 'relaxed', 'state' => 'sold_out', 'featured' => false),
);

// The ticket bands. With the shop each is a product of the Tickets group;
// without it the visit page lists them with their prices. One table of
// prices feeds both, so the visit page and the shop show one figure.
$tickets = array(
    'stalls'     => array('title' => lang('Stalls'), 'who' => lang('Every seat on the ground floor'),
        'view' => lang('Close to the stage, with step-free seats at the end of the rows')),
    'circle'     => array('title' => lang('Dress Circle'), 'who' => lang('The first balcony'),
        'view' => lang('The whole stage in view, the best place to see a dance')),
    'balcony'    => array('title' => lang('Upper Circle'), 'who' => lang('The top balcony'),
        'view' => lang('High above the stage at the lowest price')),
    'concession' => array('title' => lang('Concession'), 'who' => lang('Over 65s, students and job seekers'),
        'view' => lang('Any seat in the stalls or circle, from Monday to Thursday')),
    'young'      => array('title' => lang('Under 26'), 'who' => lang('Anyone aged 16 to 25'),
        'view' => lang('Any seat in the house, at every performance')),
);
$ticket_prices = array(
    'stalls'     => array('USD' => 45, 'EUR' => 40, 'GBP' => 35, 'TRY' => 1100),
    'circle'     => array('USD' => 38, 'EUR' => 34, 'GBP' => 30, 'TRY' => 950),
    'balcony'    => array('USD' => 20, 'EUR' => 18, 'GBP' => 15, 'TRY' => 500),
    'concession' => array('USD' => 25, 'EUR' => 22, 'GBP' => 19, 'TRY' => 600),
    'young'      => array('USD' => 12, 'EUR' => 11, 'GBP' => 10, 'TRY' => 300),
);

// The friends tiers: a year of priority booking and more. With the shop
// each is a product of the Friends group; the support page lists them.
$tiers = array(
    'friend' => array('title' => lang('Friend'), 'who' => lang('One person'),
        'benefits' => array(lang('Priority booking before tickets go on sale'), lang('No booking fees'), lang('10% off in the bar'), lang('The friends\' newsletter each season'))),
    'best' => array('title' => lang('Best Friends'), 'who' => lang('Two people at the same address'),
        'benefits' => array(lang('Priority booking for both of you'), lang('No booking fees'), lang('Two free interval drinks each season'), lang('Invitations to open rehearsals'))),
    'family' => array('title' => lang('Family Friends'), 'who' => lang('Two adults and their children under 18'),
        'benefits' => array(lang('Priority booking for the whole family'), lang('A free ticket for a child at every family show'), lang('10% off in the bar'), lang('A backstage tour once a year'))),
    'patron' => array('title' => lang('Patron'), 'who' => lang('Two people who give more'),
        'benefits' => array(lang('Everything Best Friends get'), lang('Invitations to first nights'), lang('A meeting with the company after a show'), lang('Your name in every programme'))),
);
$tier_prices = array(
    'friend' => array('USD' => 60, 'EUR' => 55, 'GBP' => 50, 'TRY' => 1500),
    'best'   => array('USD' => 100, 'EUR' => 90, 'GBP' => 80, 'TRY' => 2500),
    'family' => array('USD' => 120, 'EUR' => 110, 'GBP' => 95, 'TRY' => 3000),
    'patron' => array('USD' => 500, 'EUR' => 460, 'GBP' => 400, 'TRY' => 12500),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A ticket band's "… a seat" and a tier's "… a year" words.
$ticket_price_text = function ($key) use ($ticket_prices, $money) {
    return sprintf(lang('%s a seat'), $money($ticket_prices[$key]));
};
$tier_price_text = function ($key) use ($tier_prices, $money) {
    return sprintf(lang('%s a year'), $money($tier_prices[$key]));
};

// The youth theatre groups: weekly classes by age (the learning page and
// the youth theatre place request's Group list).
$youth_groups = array(
    'little' => array('title' => lang('Little Players'), 'ages' => lang('Ages 5 to 8'), 'when' => lang('Saturday mornings in term time'),
        'summary' => lang('Games, stories and songs that build confidence and a love of the stage.')),
    'junior' => array('title' => lang('Junior Company'), 'ages' => lang('Ages 9 to 13'), 'when' => lang('Tuesday afternoons in term time'),
        'summary' => lang('Acting, voice and movement, with a show for families at the end of each term.')),
    'youth'  => array('title' => lang('Youth Company'), 'ages' => lang('Ages 14 to 18'), 'when' => lang('Thursday evenings in term time'),
        'summary' => lang('New plays made with professional directors and performed in the Studio.')),
    'adult'  => array('title' => lang('Evening Drama Class'), 'ages' => lang('Ages 18 and over'), 'when' => lang('Wednesday evenings, ten weeks a term'),
        'summary' => lang('For anyone who always wanted to try; no experience needed.')),
);

// The school workshops (the learning page and the school group request's
// Workshop list), by age.
$workshops = array(
    'tour'    => array('title' => lang('Backstage Tour'), 'ages' => lang('Ages 7 and over'), 'length' => lang('1 hour'),
        'summary' => lang('The stage, the wings, the dressing rooms and the workshop where the sets are built.')),
    'day'     => array('title' => lang('A Play in a Day'), 'ages' => lang('Ages 8 to 11'), 'length' => lang('A school day'),
        'summary' => lang('Pupils make and perform a short play with two of our actors, ready for parents by home time.')),
    'text'    => array('title' => lang('From Page to Stage'), 'ages' => lang('Ages 11 to 16'), 'length' => lang('2 hours'),
        'summary' => lang('A scene from a set text, explored on its feet with a director.')),
    'careers' => array('title' => lang('Careers in Theatre'), 'ages' => lang('Ages 15 to 18'), 'length' => lang('Half a day'),
        'summary' => lang('Stage managers, designers and technicians show what they do and how they got there.')),
);

// The spaces for hire (the hire page and the hire enquiry's Space list).
$spaces = array(
    'main'     => array('icon' => 'bi-easel2', 'title' => lang('The Main House'), 'capacity' => lang('Up to 650 seated'),
        'summary' => lang('A full stage with lighting, sound and crew, for conferences, concerts and award evenings.')),
    'studio'   => array('icon' => 'bi-bounding-box', 'title' => lang('The Studio'), 'capacity' => lang('Up to 120 seated'),
        'summary' => lang('A black box with seats that move, for launches, talks and smaller shows.')),
    'rehearsal' => array('icon' => 'bi-person-workspace', 'title' => lang('The Rehearsal Room'), 'capacity' => lang('Up to 40 people'),
        'summary' => lang('A bright, sprung-floor room for workshops, meetings, auditions and classes.')),
    'foyer'    => array('icon' => 'bi-cup-straw', 'title' => lang('The Foyer Bar'), 'capacity' => lang('Up to 200 standing'),
        'summary' => lang('A drinks reception, a party or a networking evening, with the bar team on hand.')),
);

// What an audience finds in the building (the visit page).
$facilities = array(
    array('icon' => 'bi-cup-straw', 'title' => lang('Bars'),
        'text' => lang('Order your interval drinks before the show and they wait for you at the bar.')),
    array('icon' => 'bi-egg-fried', 'title' => lang('Café'),
        'text' => lang('Light meals from noon until the curtain goes up.')),
    array('icon' => 'bi-archive', 'title' => lang('Cloakroom'),
        'text' => lang('Free for coats and bags; large bags cannot go into the auditorium.')),
    array('icon' => 'bi-universal-access', 'title' => lang('Access Performance'),
        'text' => lang('Step-free entrance, lifts to every level and wheelchair spaces in the stalls and circle.')),
    array('icon' => 'bi-ear', 'title' => lang('Assisted Listening'),
        'text' => lang('Headsets for every performance, free at the cloakroom.')),
    array('icon' => 'bi-balloon-heart', 'title' => lang('Families'),
        'text' => lang('Booster seats, baby changing and a quiet room for little ones.')),
);

// How to get to the theatre (the visit page).
$getting_there = array(
    array('icon' => 'bi-train-front', 'title' => lang('By metro'), 'text' => lang('Theatre Square station, two minutes on foot.')),
    array('icon' => 'bi-bus-front', 'title' => lang('By bus'), 'text' => lang('Lines 8 and 21 stop in front of the theatre.')),
    array('icon' => 'bi-bicycle', 'title' => lang('By bike'), 'text' => lang('Racks beside the stage door, free of charge.')),
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('A public car park behind the square, open until after the last show.')),
);

// The theatre's people (the about page). Fixed data, not a collection.
$people = array(
    array('name' => lang('Kerem Aksoy'), 'role' => lang('Artistic Director'),
        'bio' => lang('Chooses the season, directs a play each year and leads the company.')),
    array('name' => lang('Laura Grant'), 'role' => lang('Executive Director'),
        'bio' => lang('Runs the theatre and its charity, and keeps a part of every house affordable.')),
    array('name' => lang('Elif Tan'), 'role' => lang('Associate Director'),
        'bio' => lang('Develops new plays with writers and directs in the Studio.')),
    array('name' => lang('Deniz Koç'), 'role' => lang('Head of Learning'),
        'bio' => lang('Runs the youth theatre, the school workshops and the family shows.')),
    array('name' => lang('Ozan Kılıç'), 'role' => lang('Head of Production'),
        'bio' => lang('Leads the crew who build, light and run every show.')),
    array('name' => lang('Selin Aydın'), 'role' => lang('Box Office Manager'),
        'bio' => lang('Looks after the audience, from the first ticket to the last bow.')),
);

// The theatre in four figures (the home and the about page).
$figures = array(
    array('value' => '2', 'label' => lang('Stages under one roof')),
    array('value' => '400', 'label' => lang('Performances a year')),
    array('value' => '180,000', 'label' => lang('Seats sold each season')),
    array('value' => '600', 'label' => lang('Young people in the youth theatre')),
);

// What audiences say (the home and the support page).
$testimonials = array(
    array('name' => lang('Zeynep Arslan'), 'role' => lang('Friend of the theatre'),
        'quote' => lang('We book the whole season the day priority booking opens. There has not been a dull evening yet.')),
    array('name' => lang('James Carter'), 'role' => lang('Teacher on a school trip'),
        'quote' => lang('The play in a day was the highlight of the year for my class. Parents were in tears at the end.')),
    array('name' => lang('Burak Demir'), 'role' => lang('Audience member'),
        'quote' => lang('The captioned performance meant my father could follow every word. We will be back.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$production_statuses = array_values($production_status_titles);
$genre_titles = array();
foreach ($genres as $g) $genre_titles[] = $g['title'];
$stage_titles = array();
foreach ($stages as $s) $stage_titles[] = $s['title'];
$production_titles = array();
foreach ($productions as $p) $production_titles[] = $p['title'];
$access_titles = array();
foreach ($access_types as $a) $access_titles[] = $a['title'];
$ticket_state_titles = array();
foreach ($ticket_states as $t) $ticket_state_titles[] = $t['title'];
$group_titles = array();
foreach ($youth_groups as $g) $group_titles[] = $g['title'];
$workshop_titles = array();
foreach ($workshops as $w) $workshop_titles[] = $w['title'];
$school_workshop_choices = array_merge($workshop_titles, array(lang('Not sure yet')));
$space_titles = array();
foreach ($spaces as $s) $space_titles[] = $s['title'];
$space_choices = array_merge($space_titles, array(lang('Not sure yet')));
$hire_kinds = array(lang('A conference or talk'), lang('A performance or concert'), lang('A party or reception'), lang('A rehearsal or audition'), lang('Filming or photography'));
$guest_counts = array(lang('Up to 40'), lang('40 to 120'), lang('120 to 300'), lang('More than 300'));
$year_groups = array(lang('Primary school'), lang('Secondary school'), lang('Sixth form or college'));
$visit_day_choices = array(lang('A weekday morning'), lang('A weekday afternoon'), lang('Any weekday'));
// The states of a hire enquiry, a school group request and a youth theatre
// place, set by the theatre in the panel.
$hire_statuses = array(lang('Enquiry received'), lang('Quote sent'), lang('Booked'), lang('Space not available'));
$school_group_statuses = array(lang('Request received'), lang('Visit booked'), lang('Visit done'), lang('Not possible this time'));
$place_statuses = array(lang('Request received'), lang('Place offered'), lang('Waiting list'), lang('Cancelled'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The words of a sample record's pick list answers.
$production_status = function ($key) use ($productions, $production_status_titles) {
    return $production_status_titles[$productions[$key]['status']];
};
$production_genre = function ($key) use ($productions, $genres) {
    return $genres[$productions[$key]['genre']]['title'];
};
$production_stage = function ($key) use ($productions, $stages) {
    return $stages[$productions[$key]['stage']]['title'];
};
$performance_production = function ($key) use ($performances, $productions) {
    return $productions[$performances[$key]['production']]['title'];
};
$performance_access = function ($key) use ($performances, $access_types) {
    return $access_types[$performances[$key]['access']]['title'];
};
$performance_state = function ($key) use ($performances, $ticket_states) {
    return $ticket_states[$performances[$key]['state']]['title'];
};
// A production's cast and creative team as the value of its Cast field:
// one line per item.
$details_text = function ($list) {
    return implode("\n", $list);
};

// The query string of a link that opens a list with a filter set
// (?status=, ?genre=, ?production=, ?access=) or a form with a choice made
// (?space=, ?group=, ?workshop=): the form's fields start with these
// values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the box office band above the footer unless the page
// is a form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('box_office_band');
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
            $heading('h1', $title, 'pth-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pth-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'pth-display h3 mb-0'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'th-faq') use ($el, $para, $attr, $searchable) {
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


// The theatre's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'pth-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};


// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: when the box office is open and the way to
// see what is on. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-ticket-perforated', 'text-primary'),
        $span(lang('Box office open Monday to Saturday from 10:00'), '', lang('Text')),
        $link(lang('What\'s On'), '{{page:whats_on}}', 'link-primary fw-semibold'),
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

// The header: the theatre's name, the menu and the way to the performance
// dates. The links to the current page are marked active on the server
// (smart active state), so one header serves every page. Tickets and
// friends are the shop's and are linked from the shop pages only, so the
// header stands on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('season', lang('The Season')),
        array('whats_on', lang('What\'s On')),
        array('visit', lang('Your Visit')),
        array('learning', lang('Learning')),
        array('hire', lang('Hire a Space')),
        array('support', lang('Support Us')),
        array('blog', lang('News')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-ticket-perforated', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'pth-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#th-nav'),
                    $attr('aria-controls', 'th-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-event'),
                            $span(lang('Find a Performance'), '', lang('Link Text')),
                        ), array('href' => '{{page:whats_on}}')),
                    )),
                ), array('id' => 'th-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the theatre in a sentence, how to reach it
// and its profiles, the season by state, the productions by genre, the
// audience's pages and the theatre's pages; at the bottom the year, the
// site's name and the theatre's standing.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $theatre, $production_statuses, $genre_titles, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $season_links = array($link(lang('The Whole Season'), '{{page:season}}', $plain));
    foreach ($production_statuses as $title) $season_links[] = $link($title, '{{page:season}}?' . $query(array('status' => $title)), $plain);
    $genre_links = array();
    foreach ($genre_titles as $title) $genre_links[] = $link($title, '{{page:season}}?' . $query(array('genre' => $title)), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Theatre'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-ticket-perforated', 'text-primary'),
                        $span('{{site_name}}', 'pth-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($theatre['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($theatre['address'], '{{page:visit}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($theatre['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $theatre['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($theatre['email'], 'mailto:' . $theatre['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($theatre['hours'], '', lang('Text')), lang('Box Office Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Theatre Season Links'), array(
                    $heading('h2', lang('The Season'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($season_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Genre Links'), array(
                    $heading('h2', lang('Genres'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($genre_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Audience Links'), array(
                    $heading('h2', lang('Your Visit'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('What\'s On'), '{{page:whats_on}}', $plain),
                        $link(lang('Plan Your Visit'), '{{page:visit}}', $plain),
                        $link(lang('Access Performances'), '{{page:visit}}#th-access', $plain),
                        $link(lang('Learning'), '{{page:learning}}', $plain),
                        $link(lang('Book a School Workshop'), '{{page:school_group}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Theatre'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Support Us'), '{{page:support}}', $plain),
                        $link(lang('Hire a Space'), '{{page:hire}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($theatre['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The box office band: a full-width band in the palette's colour with the
// ways to find a performance and to plan a visit. Shared, so every page
// that ends with it carries the same call.
$box_office_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $theatre) {
    return $el('section', 'py-5 bg-primary text-white', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Your seat is waiting'), 'pth-display h2 mb-2'),
                    $para($theatre['hours'] . '. ' . $theatre['doors'] . '.', 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Find a Performance'), '{{page:whats_on}}', 'btn btn-light btn-lg px-4'),
                        $link(lang('Plan Your Visit'), '{{page:visit}}', 'btn btn-outline-light btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// The box office hours, day by day, as a list of terms and values (the
// visit page), with the doors and latecomers under them.
$hours_list = function ($class = '') use ($el, $para, $box_office_hours, $theatre) {
    $rows = array();
    foreach ($box_office_hours as $h) {
        $rows[] = $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom', lang('Box Office Hours Row'), array(
            $el('dt', 'fw-semibold', lang('Days'), array(), array('text' => $h['days'])),
            $el('dd', 'pth-price mb-0 text-end', lang('Hours'), array(), array('text' => $h['hours'])),
        ));
    }
    return $el('div', $class, lang('Box Office Hours'), array(
        $el('dl', 'mb-2', lang('Box Office Hours List'), $rows),
        $para($theatre['doors'] . '. ' . $theatre['late'] . '.', 'small text-body-secondary mb-0'),
    ));
};

// The state of a production as a badge, bound to its Status field.
$status_badge = function ($text, $class = 'badge rounded-pill text-bg-primary') use ($span, $bind) {
    return $span($text, $class, lang('Status'), $bind('status'));
};

// The facts of a production in one list: the run, the stage, the running
// time and the age guidance, each bound to the Productions form's field of
// the same name.
$production_facts = function ($sample_key = 'lighthouse', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $productions, $production_stage) {
    $p = $productions[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Production Facts'), array(
        $fact('bi-calendar-range', $p['run'], 'run', lang('Run Dates')),
        $fact('bi-geo-alt', $production_stage($sample_key), 'stage', lang('Theatre Stage')),
        $fact('bi-hourglass-split', $p['length'], 'running_time', lang('Running Time')),
        $fact('bi-people', $p['age'], 'age_guidance', lang('Age Guidance')),
    ));
};

// A production's card of the lists (the home and the season): the photo,
// the genre and state, the title linking the production's page, the run,
// the stage and the summary. Every value is bound to the Productions form's
// field of the same name; the sample fills the canvas.
$production_card = function ($sample_key = 'lighthouse') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $productions, $production_status, $production_genre, $production_stage, $status_badge, $photo, $attr) {
    $p = $productions[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden pth-lift', lang('Production Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('production-' . $sample_key, 800, 600), $p['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('div', 'd-flex flex-wrap align-items-center gap-2 mb-2', lang('Genre and Status'), array(
                    $span($production_genre($sample_key), 'pth-eyebrow small text-uppercase fw-semibold text-primary', lang('Genre'), $bind('genre')),
                    $status_badge($production_status($sample_key)),
                )),
                $el('h3', 'pth-display h4 position-static mb-2', lang('Production Title'), array(
                    $link($p['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-start gap-1 small fw-semibold mb-1', lang('Run Dates'), array(
                    $icon('bi-calendar-range', 'text-primary'),
                    $span($p['run'], '', lang('Run Dates'), $bind('run')),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-2', lang('Theatre Stage'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($production_stage($sample_key), '', lang('Theatre Stage'), $bind('stage')),
                )),
                $para($p['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// A genre's card (the home and the season page): the icon, the title and a
// sentence; it opens the season filtered on that genre.
$genre_card = function ($key) use ($el, $para, $link, $col, $icon, $genres, $query) {
    $g = $genres[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-body-tertiary p-4 pth-lift', lang('Genre Card'), array(
            $icon($g['icon'], 'fs-3 text-primary mb-2'),
            $el('h3', 'pth-display h5 position-static mb-1', lang('Genre Name'), array(
                $link($g['title'], '{{page:season}}?' . $query(array('genre' => $g['title'])), 'stretched-link link-body-emphasis text-decoration-none'),
            )),
            $para($g['summary'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// A performance's row of the lists (the home, What's On and a production's
// page): the date and the time, the production linking the performance's
// page, the stage, the access mark and the ticket state. Bound to the
// Performances form's fields.
$performance_row = function ($sample_key = 'lh_1') use ($el, $span, $link, $icon, $bind, $performances, $productions, $stages, $ticket_states, $perf_on, $performance_production, $performance_access) {
    $s = $performances[$sample_key];
    $state = $ticket_states[$s['state']];
    return $el('article', 'd-flex flex-column flex-md-row align-items-md-center gap-3 py-3 border-bottom position-relative', lang('Performance Row'), array(
        $el('div', 'pth-date flex-shrink-0', lang('Date and Time'), array(
            $span($perf_on($s['weekday'], $s['weeks']), 'd-block fw-bold text-primary', lang('Performance Date'), $bind('perf_date')),
            $span($s['time'], 'd-block small text-body-secondary', lang('Curtain Up'), $bind('perf_time')),
        )),
        $el('div', 'flex-grow-1', lang('Performance Details'), array(
            $el('h3', 'pth-display h5 mb-1', lang('The Production'), array(
                $link($performance_production($sample_key), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'production', 'href' => 'form_item_view'))),
            )),
            $el('p', 'd-flex flex-wrap gap-3 small text-body-secondary mb-0', lang('Performance Facts'), array(
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Theatre Stage'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($stages[$productions[$s['production']]['stage']]['title'], '', lang('Theatre Stage'), $bind('stage')),
                )),
                $el('span', 'd-inline-flex align-items-center gap-1', lang('Access Performance'), array(
                    $icon('bi-universal-access', 'text-primary'),
                    $span($performance_access($sample_key), '', lang('Access Performance'), $bind('access')),
                )),
            )),
        )),
        $el('div', 'flex-shrink-0', lang('Tickets'), array(
            $span($state['title'], 'badge rounded-pill ' . $state['badge'], lang('Ticket State'), $bind('ticket_state')),
        )),
    ));
};

// The facts of a performance in one list (its page): the production, the
// date, the curtain, the stage, the access mark and the ticket state, each
// bound to the Performances form's field of the same name.
$performance_facts = function ($sample_key = 'lh_1', $class = 'list-unstyled small mb-0') use ($el, $span, $bind, $performances, $productions, $stages, $perf_on, $performance_production, $performance_access, $performance_state) {
    $s = $performances[$sample_key];
    $fact = function ($label, $text, $token) use ($el, $span, $bind) {
        return $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $label, array(
            $span($label, 'text-body-secondary', lang('Label')),
            $span($text, 'fw-semibold text-end', $label, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Performance Facts'), array(
        $fact(lang('The Production'), $performance_production($sample_key), 'production'),
        $fact(lang('Date'), $perf_on($s['weekday'], $s['weeks']), 'perf_date'),
        $fact(lang('Curtain Up'), $s['time'], 'perf_time'),
        $fact(lang('Theatre Stage'), $stages[$productions[$s['production']]['stage']]['title'], 'stage'),
        $fact(lang('Access Performance'), $performance_access($sample_key), 'access'),
        $fact(lang('Tickets'), $performance_state($sample_key), 'ticket_state'),
    ));
};

// An access performance (the visit page): what it gives, with the way to
// the performances that carry it (?access=).
$access_card = function ($key) use ($el, $para, $link, $col, $icon, $access_types, $query) {
    $a = $access_types[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-body-tertiary p-4 pth-lift', lang('Access Card'), array(
            $icon($a['icon'], 'fs-3 text-primary mb-2'),
            $el('h3', 'pth-display h5 position-static mb-1', lang('Access Performance'), array(
                $link($a['title'], '{{page:whats_on}}?' . $query(array('access' => $a['title'])), 'stretched-link link-body-emphasis text-decoration-none'),
            )),
            $para($a['text'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// A ticket band (the visit page): who it is for, the price and the view.
// It does not link the shop: the visit page stands without it.
$ticket_card = function ($key) use ($el, $heading, $para, $span, $col, $tickets, $ticket_price_text) {
    $t = $tickets[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Ticket Card'), array(
            $heading('h3', $t['title'], 'pth-display h5 mb-1'),
            $para($t['who'], 'small text-body-secondary mb-3'),
            $span($ticket_price_text($key), 'pth-price d-block h4 fw-bold text-primary mb-2', lang('Price')),
            $para($t['view'], 'small mb-0'),
        )),
    ), '6', '4');
};

// A friends tier (the support page): who it is for, the price a year and
// what it gives.
$tier_card = function ($key) use ($el, $heading, $para, $span, $col, $check_list, $tiers, $tier_price_text) {
    $t = $tiers[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Friends Card'), array(
            $heading('h3', $t['title'], 'pth-display h5 mb-1'),
            $para($t['who'], 'small text-body-secondary mb-3'),
            $span($tier_price_text($key), 'pth-price d-block h4 fw-bold text-primary mb-3', lang('Price')),
            $check_list($t['benefits'], 'list-unstyled small mb-0'),
        )),
    ), '6', '3');
};

// A youth theatre group (the learning page): the ages, the title, when it
// meets and a sentence, with the way to ask for a place (?group=).
$group_card = function ($key) use ($el, $heading, $para, $span, $link, $col, $youth_groups, $query) {
    $g = $youth_groups[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Group Card'), array(
            $span($g['ages'], 'pth-eyebrow d-block small text-uppercase fw-semibold text-primary mb-1', lang('Ages')),
            $heading('h3', $g['title'], 'pth-display h5 mb-1'),
            $para($g['when'], 'small fw-semibold mb-2'),
            $para($g['summary'], 'small text-body-secondary mb-3'),
            $link(lang('Ask for a place'), '{{page:youth_place}}?' . $query(array('group' => $g['title'])), 'btn btn-sm btn-outline-primary mt-auto align-self-start'),
        )),
    ), '6', '3');
};

// A school workshop (the learning page): the ages, the title, the length
// and a sentence, with the way to request it (?workshop=).
$workshop_card = function ($key) use ($el, $heading, $para, $span, $link, $col, $workshops, $query) {
    $w = $workshops[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Workshop Card'), array(
            $span($w['ages'], 'pth-eyebrow d-block small text-uppercase fw-semibold text-primary mb-1', lang('Ages')),
            $heading('h3', $w['title'], 'pth-display h5 mb-1'),
            $para($w['length'], 'small fw-semibold mb-2'),
            $para($w['summary'], 'small text-body-secondary mb-3'),
            $link(lang('Request this workshop'), '{{page:school_group}}?' . $query(array('workshop' => $w['title'])), 'btn btn-sm btn-outline-primary mt-auto align-self-start'),
        )),
    ), '6', '3');
};

// A space for hire (the hire page): the icon, the title, how many it holds
// and a sentence, with the way to enquire about it (?space=).
$space_card = function ($key) use ($el, $heading, $para, $span, $link, $col, $icon, $spaces, $query) {
    $s = $spaces[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm p-4', lang('Space Card'), array(
            $icon($s['icon'], 'fs-3 text-primary mb-2'),
            $heading('h3', $s['title'], 'pth-display h5 mb-1'),
            $span($s['capacity'], 'd-block small fw-semibold mb-2', lang('Capacity')),
            $para($s['summary'], 'small text-body-secondary mb-3'),
            $link(lang('Enquire about this space'), '{{page:hire}}?' . $query(array('space' => $s['title'])) . '#th-hire-form', 'btn btn-sm btn-outline-primary mt-auto align-self-start'),
        )),
    ), '6', '3');
};

// A member of the theatre's team (the about page): the portrait, the name,
// the role and a sentence.
$person_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $people) {
    $t = $people[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm text-center p-4', lang('Staff Member'), array(
            $image($portrait($t['name'], 240), $t['name'], 'rounded-circle mx-auto mb-3', array('_attrs' => array($attr('width', '120'), $attr('height', '120'), $attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'pth-display h5 mb-1'),
            $para($t['role'], 'small fw-semibold text-primary mb-2'),
            $para($t['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// What an audience member says: the quote, the name and who they are.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $icon, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Audience Quote'), array(
            $icon('bi-quote', 'fs-2 text-primary lh-1 mb-2'),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'pth-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Audience Member'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('_attrs' => array($attr('width', '48'), $attr('height', '48'), $attr('loading', 'lazy')))),
                $el('div', '', lang('Name and Role'), array(
                    $span($t['name'], 'd-block fw-semibold', lang('Name')),
                    $span($t['role'], 'd-block small text-body-secondary', lang('Role')),
                )),
            )),
        )),
    ), '', '4');
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
                $item('staff_hire', lang('Hire Enquiries')),
                $item('staff_school_groups', lang('School Groups')),
                $item('staff_youth_places', lang('Youth Theatre Places')),
                $item('production_new', lang('New Production')),
                $item('performance_new', lang('New Performance')),
                $item('blog_new', lang('New Article')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

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
    return $agree($id, 'consent', lang('I would like to receive the theatre\'s news, new seasons and ticket offers by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A hire
// enquiry may carry a running order or a floor plan and a school group
// request a list of access needs, so the default accepts PDFs, office
// files, text and pictures; one that takes only pictures is a collection's
// image kind (a production's photo).
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
                    $link(lang('Behind the curtain on a first night'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

