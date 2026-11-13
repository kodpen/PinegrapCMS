<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Public Library", part 1 of its files: the node
 * builders and the interactions the pages put on them, the library's own
 * data (the branches with their opening hours day by day, the Central
 * Library's floors, the services, what a card lends, the rooms and spaces,
 * the staff, the figures, what readers say, the sample titles of the
 * catalogue, the sample events and the answers of the forms' pick lists)
 * and the parts every page repeats
 * (account bar, notice, header, footer, join band, branch, hours, room,
 * title, event, staff and reader cards, page shells, section headers,
 * form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-library.php puts them together.
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
// A time of day the operator edits like a link's words; 'datetime' keeps
// the machine-readable value (the editor's kit.time()).
$time = function ($datetime, $text, $class, $name) use ($el, $attr) {
    return $el('time', $class, $name, array(), array('text' => $text, '_attrs' => array($attr('datetime', $datetime))));
};
// An interaction on a node (Interactions in the editor): the operator can
// change or remove it there, and the platform keeps the node still for a
// visitor who prefers reduced motion.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// A row whose items rise one after another as it scrolls into view.
$rise = function ($node, $stagger = 90) use ($fx) {
    return $fx($node, array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger));
};
// A section heading that fades in on view.
$fade_in = array('effect' => 'fade', 'trigger' => 'view', 'duration' => 700);
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
// every opening until the library puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-library-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/ecfccb/3f6212?text=' . rawurlencode($initials !== '' ? $initials : 'L');
};

// ── The library: its branches, its shelves and its people ───────────────

// The library itself. The about page tells its story; the footer, the
// join band and the contact lines use the address, phone and hours.
$library = array(
    'tagline' => lang('Books, films, music, spaces and help for everyone in town, free to join and free to borrow'),
    'story'   => lang('The library opened in a single reading room above the old market hall, with books given by the townspeople. It has grown into a central library and two neighbourhood branches, lends half a million items a year and still keeps its first promise: everyone is welcome, and borrowing is free.'),
    'address' => lang('Library Square 1, Town Centre'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'ask@example.com',
    'hours'   => lang('Central Library: Monday to Saturday, 9:00 to 20:00; Sunday, 12:00 to 17:00'),
    'licence' => lang('A public library run by the town council, free to join for everyone who lives, works or studies here'),
);

// The branches. Each has its own page (branch_<key>) with its hours day
// by day; the forms' Branch lists and the event list name them in their
// own words. Fixed data: a library's branches change rarely. An hours row
// names its days in words and lists them in 'on' (0 is Monday, 6 Sunday),
// so the visit page's table gives every day of the week its own row.
$branches = array(
    'central' => array('icon' => 'bi-building', 'title' => lang('Central Library'),
        'address' => lang('Library Square 1, Town Centre'), 'phone' => '+90 555 000 00 00',
        'short'   => lang('Open every day, late until 20:00 on weekdays'),
        'hours'   => array(
            array('days' => lang('Monday to Friday'), 'on' => array(0, 1, 2, 3, 4), 'hours' => '9:00 - 20:00'),
            array('days' => lang('Saturday'), 'on' => array(5), 'hours' => '9:00 - 18:00'),
            array('days' => lang('Sunday'), 'on' => array(6), 'hours' => '12:00 - 17:00'),
        ),
        'summary' => lang('Four floors of books, a quiet study floor, the local history room and the community hall.'),
        'text'    => lang('The Central Library holds the largest collection, the local history room and the community hall. Its top floor is kept quiet for study, and the ground floor has the children\'s library, the café corner and the computers.'),
        'points'  => array(lang('A quiet study floor with power at every desk'), lang('The local history room and archive'), lang('Thirty public computers and free Wi-Fi'), lang('A lift to every floor and a hearing loop at the desk'))),
    'riverside' => array('icon' => 'bi-water', 'title' => lang('Riverside Branch'),
        'address' => lang('Mill Lane 8, Riverside'), 'phone' => '+90 555 000 00 01',
        'short'   => lang('Open Tuesday to Saturday'),
        'hours'   => array(
            array('days' => lang('Monday'), 'on' => array(0), 'hours' => lang('Closed')),
            array('days' => lang('Tuesday to Friday'), 'on' => array(1, 2, 3, 4), 'hours' => '10:00 - 18:00'),
            array('days' => lang('Saturday'), 'on' => array(5), 'hours' => '10:00 - 16:00'),
            array('days' => lang('Sunday'), 'on' => array(6), 'hours' => lang('Closed')),
        ),
        'summary' => lang('A bright family library by the river, with a large children\'s corner and a garden for story time.'),
        'text'    => lang('Riverside is our family branch: picture books, early readers and a reading garden where story time is held whenever the weather allows. Parents\' groups meet in its activity room every week.'),
        'points'  => array(lang('A large children\'s corner'), lang('A reading garden for story time'), lang('The activity room for groups'), lang('Step-free entrance and baby changing'))),
    'hillside' => array('icon' => 'bi-tree', 'title' => lang('Hillside Branch'),
        'address' => lang('Station Road 21, Hillside'), 'phone' => '+90 555 000 00 02',
        'short'   => lang('Open Monday to Friday, late on Thursdays'),
        'hours'   => array(
            array('days' => lang('Monday to Wednesday'), 'on' => array(0, 1, 2), 'hours' => '10:00 - 18:00'),
            array('days' => lang('Thursday'), 'on' => array(3), 'hours' => '10:00 - 20:00'),
            array('days' => lang('Friday'), 'on' => array(4), 'hours' => '10:00 - 17:00'),
            array('days' => lang('Saturday and Sunday'), 'on' => array(5, 6), 'hours' => lang('Closed')),
        ),
        'summary' => lang('A small neighbourhood library near the station, with study tables, computers and a meeting room.'),
        'text'    => lang('Hillside is a small, friendly branch close to the station. Students use its study tables after school, and its meeting room hosts the evening reading clubs and digital help sessions.'),
        'points'  => array(lang('Study tables and quiet corners'), lang('Ten public computers and printing'), lang('A meeting room for clubs and groups'), lang('Returns box open around the clock'))),
);

// The days of the week, Monday first (the visit page's table).
$week_days = array(lang('Monday'), lang('Tuesday'), lang('Wednesday'), lang('Thursday'), lang('Friday'), lang('Saturday'), lang('Sunday'));

// A branch's hours as two times ('9:00 - 20:00'), or null on a closed day.
$hours_times = function ($hours) {
    $parts = explode(' - ', $hours);
    return count($parts) === 2 ? $parts : null;
};
// A time of the hours as a <time> value: 9:00 is 09:00.
$clock = function ($time) {
    return str_pad($time, 5, '0', STR_PAD_LEFT);
};

// The Central Library's floors from the top down (its page's floor
// directory): the level, what the floor is, what is on it and who goes
// up. A room named on a floor is one of the branch's rooms to book.
$floors = array(
    'central' => array(
        array('level' => '3', 'name' => lang('Quiet study floor'),
            'text' => lang('Desks with power at every seat, kept quiet for study and reading.'),
            'items' => array(lang('Study desks'), lang('Silent reading room'), lang('Free Wi-Fi')),
            'access' => lang('Everyone, by lift or stairs')),
        array('level' => '2', 'name' => lang('Local History'),
            'text' => lang('The local history room and its archive: maps, newspapers and photographs of the town.'),
            'items' => array(lang('Local history room'), lang('Archive'), lang('Group Study Room')),
            'access' => lang('The archive by appointment with a librarian')),
        array('level' => '1', 'name' => lang('Lending floor'),
            'text' => lang('Novels, non-fiction, large print and films, and the community hall for talks and concerts.'),
            'items' => array(lang('Novels'), lang('Non-fiction'), lang('Large Print'), lang('Community Hall')),
            'access' => lang('Everyone; the hall when it is booked')),
        array('level' => '0', 'name' => lang('Ground floor'),
            'text' => lang('The welcome desk, the children\'s library, the café corner and the public computers.'),
            'items' => array(lang('Welcome desk'), lang('Children\'s library'), lang('Computers'), lang('Café corner')),
            'access' => lang('Everyone; the returns box is by the door')),
    ),
);

// What a card lends (the home's card band): each format with how many
// items at a time, for how long and how often a loan is renewed. Fifteen
// items in all, as the services and the questions say; the eBooks count
// apart, ten at a time, as the digital page says.
$loan_rules = array(
    array('icon' => 'bi-book', 'format' => lang('Books and large print'), 'count' => '15', 'loan' => lang('3 weeks'), 'renew' => lang('Twice')),
    array('icon' => 'bi-film', 'format' => lang('Films'), 'count' => '5', 'loan' => lang('1 week'), 'renew' => lang('Once')),
    array('icon' => 'bi-newspaper', 'format' => lang('Magazines'), 'count' => '5', 'loan' => lang('1 week'), 'renew' => lang('Not renewed')),
    array('icon' => 'bi-tablet', 'format' => lang('eBooks and audiobooks'), 'count' => '10', 'loan' => lang('3 weeks'), 'renew' => lang('Once')),
);

// What the library offers (the home, the digital page and the about page).
$services = array(
    array('icon' => 'bi-book', 'title' => lang('Borrow for free'),
        'text' => lang('Up to fifteen items at a time for three weeks, renewed at the desk or by phone.')),
    array('icon' => 'bi-tablet', 'title' => lang('eBooks and audiobooks'),
        'text' => lang('Thousands of titles to read or hear on your phone with your library card.')),
    array('icon' => 'bi-pc-display', 'title' => lang('Computers and Wi-Fi'),
        'text' => lang('Free computers, free Wi-Fi in every branch and help to get started.')),
    array('icon' => 'bi-lamp', 'title' => lang('Places to study'),
        'text' => lang('Quiet desks, group tables and rooms to book for meetings and study groups.')),
    array('icon' => 'bi-printer', 'title' => lang('Print, copy and scan'),
        'text' => lang('Print from the library computers or your own device, and scan to e-mail.')),
    array('icon' => 'bi-house-heart', 'title' => lang('Home library service'),
        'text' => lang('Books brought to your door each month when you cannot come to us.')),
);

// The library's digital services (the digital page): what a card opens.
$digital = array(
    array('icon' => 'bi-tablet', 'title' => lang('eBooks'),
        'text' => lang('Novels, guides and children\'s books to read on a phone, tablet or e-reader.')),
    array('icon' => 'bi-headphones', 'title' => lang('Audiobooks'),
        'text' => lang('Stories and non-fiction read aloud, to hear on the way to work or at home.')),
    array('icon' => 'bi-newspaper', 'title' => lang('Newspapers and magazines'),
        'text' => lang('Today\'s papers and hundreds of magazines, the whole issue on your screen.')),
    array('icon' => 'bi-mortarboard', 'title' => lang('Online learning'),
        'text' => lang('Language courses, study guides and computer skills, free with your card.')),
);

// The formats of the catalogue: the Format field's answers and the list's
// filter (?format=).
$formats = array(
    'book'       => array('icon' => 'bi-book', 'title' => lang('Books')),
    'large'      => array('icon' => 'bi-zoom-in', 'title' => lang('Large Print')),
    'ebook'      => array('icon' => 'bi-tablet', 'title' => lang('eBooks')),
    'audiobook'  => array('icon' => 'bi-headphones', 'title' => lang('Audiobooks')),
    'film'       => array('icon' => 'bi-film', 'title' => lang('Films')),
    'magazine'   => array('icon' => 'bi-newspaper', 'title' => lang('Magazines')),
);
// Who a title is for: the Audience field's answers and the list's filter
// (?audience=).
$audiences = array(
    'adults'   => lang('Adults'),
    'teens'    => lang('Teens'),
    'children' => lang('Children'),
);
// Where a title is: the Availability field's answers, set by the staff.
$availability_choices = array(lang('On the shelf'), lang('On loan'), lang('On order'), lang('Library use only'));

// The sample titles: the records the Catalogue form starts with. Titles
// and authors are made up, so the sample names no real book. A title the
// staff mark as a new arrival shows on the home.
$catalogue_items = array(
    'harbour' => array('title' => lang('The Harbour Keeper'), 'author' => lang('Selin Aydın'), 'format' => 'book', 'audience' => 'adults',
        'genre' => lang('Novel'), 'shelf' => 'F AYD', 'branch' => 'central', 'availability' => 0,
        'summary' => lang('A lighthouse keeper\'s daughter finds a box of letters that changes what her town believes about one stormy night.'),
        'new_arrival' => true),
    'garden' => array('title' => lang('A Year in the Kitchen Garden'), 'author' => lang('Thomas Reed'), 'format' => 'book', 'audience' => 'adults',
        'genre' => lang('Home and Garden'), 'shelf' => '635 REE', 'branch' => 'riverside', 'availability' => 1,
        'summary' => lang('Month by month, what to sow, grow and cook from a small garden or a balcony.'),
        'new_arrival' => true),
    'stars' => array('title' => lang('The Girl Who Counted Stars'), 'author' => lang('Deniz Koç'), 'format' => 'book', 'audience' => 'children',
        'genre' => lang('Picture Book'), 'shelf' => 'JP KOC', 'branch' => 'riverside', 'availability' => 0,
        'summary' => lang('A curious girl tries to count every star in the sky and learns something about her grandfather on the way.'),
        'new_arrival' => true),
    'signal' => array('title' => lang('Lost Signal'), 'author' => lang('Maya Ellis'), 'format' => 'audiobook', 'audience' => 'teens',
        'genre' => lang('Science Fiction'), 'shelf' => lang('Online'), 'branch' => 'central', 'availability' => 0,
        'summary' => lang('Four friends at a mountain camp hear a voice on an old radio that should not be working.'),
        'new_arrival' => false),
    'river' => array('title' => lang('Our River Through the Ages'), 'author' => lang('Local History Society'), 'format' => 'large', 'audience' => 'adults',
        'genre' => lang('Local History'), 'shelf' => 'LP 942 LOC', 'branch' => 'central', 'availability' => 3,
        'summary' => lang('Mills, floods, bridges and the people who lived beside the river, with old photographs from the archive.'),
        'new_arrival' => false),
    'quiet' => array('title' => lang('The Quiet Coast'), 'author' => lang('Arda Şahin'), 'format' => 'film', 'audience' => 'adults',
        'genre' => lang('Documentary'), 'shelf' => 'DVD 551 SAH', 'branch' => 'hillside', 'availability' => 2,
        'summary' => lang('A year on a stretch of coast where seabirds, fishermen and storms share the same few miles.'),
        'new_arrival' => true),
);

// The kinds of event. The event list filters on them (?kind=).
$event_kinds = array(
    'club'     => array('icon' => 'bi-people', 'title' => lang('Reading Clubs')),
    'talk'     => array('icon' => 'bi-mic', 'title' => lang('Author Talks')),
    'story'    => array('icon' => 'bi-balloon', 'title' => lang('Story Times')),
    'workshop' => array('icon' => 'bi-pencil', 'title' => lang('Workshops')),
    'digital'  => array('icon' => 'bi-laptop', 'title' => lang('Digital Help')),
    'show'     => array('icon' => 'bi-easel', 'title' => lang('Exhibitions')),
);
// Who an event is for: the Age Group field's answers.
$age_groups = array(lang('Everyone'), lang('Adults'), lang('Teens'), lang('Children and families'));

// The sample events: the records the Events form starts with. When is
// words without a date, so the sample never reads as an old event; the
// library writes the real date in its own record.
$events = array(
    'book_club' => array('title' => lang('Evening Book Club'), 'kind' => 'club', 'branch' => 'hillside', 'age' => 0,
        'when' => lang('The last Thursday of each month, 18:30'),
        'summary' => lang('One book a month, a pot of tea and an hour of good talk. New readers are always welcome.'),
        'details' => array(lang('The month\'s book waits for you at the desk'), lang('An hour of talk led by a librarian'), lang('Tea and biscuits'), lang('Come once or come every month')),
        'featured' => true),
    'author_talk' => array('title' => lang('Meet the Author: Writing Your Town'), 'kind' => 'talk', 'branch' => 'central', 'age' => 1,
        'when' => lang('A Saturday in spring, 14:00'),
        'summary' => lang('A local novelist talks about turning the streets we know into fiction, and signs copies afterwards.'),
        'details' => array(lang('A 45-minute talk and readings'), lang('Questions from the audience'), lang('A book signing after the talk'), lang('Step-free access and a hearing loop')),
        'featured' => true),
    'story_time' => array('title' => lang('Saturday Story Time'), 'kind' => 'story', 'branch' => 'riverside', 'age' => 3,
        'when' => lang('Every Saturday, 10:30'),
        'summary' => lang('Stories, songs and a little craft for children under six and the grown-ups who bring them.'),
        'details' => array(lang('Half an hour of stories and songs'), lang('A simple craft to take home'), lang('In the reading garden when it is fine'), lang('No booking needed for families')),
        'featured' => true),
    'teen_writers' => array('title' => lang('Teen Writers\' Workshop'), 'kind' => 'workshop', 'branch' => 'central', 'age' => 2,
        'when' => lang('Wednesday afternoons in term time, 16:00'),
        'summary' => lang('Write stories, poems and comics with a published writer, and share them if you like.'),
        'details' => array(lang('For ages 12 to 17'), lang('Notebooks and pens provided'), lang('A reading for families at the end of term'), lang('Twelve places each term')),
        'featured' => false),
    'digital_help' => array('title' => lang('Digital Help Drop-In'), 'kind' => 'digital', 'branch' => 'hillside', 'age' => 1,
        'when' => lang('Every Tuesday, 10:00 to 12:00'),
        'summary' => lang('Bring your phone, tablet or laptop and a question: e-mail, online forms, video calls or eBooks.'),
        'details' => array(lang('One-to-one help from a trained volunteer'), lang('Library laptops if you have no device'), lang('Help with the library\'s eBooks app'), lang('Booking helps us plan, but you can just come')),
        'featured' => false),
    'old_photos' => array('title' => lang('Our Town in Old Photographs'), 'kind' => 'show', 'branch' => 'central', 'age' => 0,
        'when' => lang('Through the summer, in opening hours'),
        'summary' => lang('Photographs from the local history room show the market, the mills and the river as they used to be.'),
        'details' => array(lang('Sixty photographs from the archive'), lang('Stories collected from older residents'), lang('Bring your own old photographs to scan'), lang('Free, no booking needed')),
        'featured' => false),
);

// The rooms and spaces a group may book (the spaces page and the Room
// list of the booking request, ?room=).
$rooms = array(
    'study' => array('icon' => 'bi-lamp', 'title' => lang('Group Study Room'), 'branch' => 'central',
        'seats' => lang('Up to 6 people'), 'summary' => lang('A quiet room with a large table and a screen for study groups and interviews.'),
        'equipment' => array(lang('A screen to plug a laptop into'), lang('A whiteboard'), lang('Power at the table'))),
    'meeting' => array('icon' => 'bi-people', 'title' => lang('Meeting Room'), 'branch' => 'hillside',
        'seats' => lang('Up to 16 people'), 'summary' => lang('A bright room for community groups, clubs and small training sessions.'),
        'equipment' => array(lang('A projector and a screen'), lang('Tables that can be moved'), lang('A kitchenette for tea'))),
    'hall' => array('icon' => 'bi-easel2', 'title' => lang('Community Hall'), 'branch' => 'central',
        'seats' => lang('Up to 80 people'), 'summary' => lang('Our largest space, for talks, concerts, exhibitions and public meetings.'),
        'equipment' => array(lang('A sound system and two microphones'), lang('A projector and a large screen'), lang('A hearing loop and step-free access'))),
    'activity' => array('icon' => 'bi-palette', 'title' => lang('Activity Room'), 'branch' => 'riverside',
        'seats' => lang('Up to 20 people'), 'summary' => lang('A room with washable floors and low tables for parents\' groups, crafts and children\'s parties.'),
        'equipment' => array(lang('Low tables and chairs for children'), lang('A sink and craft cupboard'), lang('Opens onto the reading garden'))),
);

// The library's people (the about page). Fixed data, not a collection.
$staff = array(
    array('name' => lang('Nurten Yılmaz'), 'role' => lang('Head of Libraries'),
        'bio' => lang('Has run the town\'s libraries for ten years and still works a shift at the desk every week.')),
    array('name' => lang('David Brooks'), 'role' => lang('Children\'s Librarian'),
        'bio' => lang('Leads story time and the summer reading challenge, and chooses the new picture books.')),
    array('name' => lang('Emre Polat'), 'role' => lang('Local History Librarian'),
        'bio' => lang('Looks after the archive and helps readers trace their family and their house.')),
    array('name' => lang('Hannah Lee'), 'role' => lang('Digital Services Librarian'),
        'bio' => lang('Runs the eBooks service and trains the volunteers of the digital help sessions.')),
);

// The library in four figures (the home and the about page).
$figures = array(
    array('value' => '3', 'label' => lang('Libraries in town')),
    array('value' => '180,000', 'label' => lang('Books, films and magazines')),
    array('value' => '24,000', 'label' => lang('Members with a library card')),
    array('value' => '600', 'label' => lang('Events and clubs a year')),
);

// What readers say, each about one branch.
$testimonials = array(
    array('branch' => 'central', 'name' => lang('Zeynep Arslan'), 'role' => lang('Reader at the Central Library'),
        'quote' => lang('I wrote most of my thesis on the quiet floor. The staff found me books I did not know existed.')),
    array('branch' => 'riverside', 'name' => lang('James Carter'), 'role' => lang('Reader at the Riverside Branch'),
        'quote' => lang('Saturday story time is the best half hour of our week, and my son now asks for the library.')),
    array('branch' => 'hillside', 'name' => lang('Burak Demir'), 'role' => lang('Reader at the Hillside Branch'),
        'quote' => lang('The book club got me reading again after years, and I have made good friends there.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the reader chose.
$branch_titles = array();
foreach ($branches as $b) $branch_titles[] = $b['title'];
$format_titles = array();
foreach ($formats as $f) $format_titles[] = $f['title'];
$audience_titles = array_values($audiences);
$event_kind_titles = array();
foreach ($event_kinds as $k) $event_kind_titles[] = $k['title'];
$event_titles = array();
foreach ($events as $e) $event_titles[] = $e['title'];
$room_titles = array();
foreach ($rooms as $r) $room_titles[] = $r['title'];
$place_counts = array('1', '2', '3', '4', '5', '6');
$card_kinds = array(lang('Adult card'), lang('Young reader\'s card (under 16)'), lang('Visitor\'s card'));
$group_sizes = array(lang('Up to 5 people'), lang('6 to 15 people'), lang('16 to 40 people'), lang('More than 40 people'));
$time_choices = array(lang('A weekday morning'), lang('A weekday afternoon'), lang('A weekday evening'), lang('A Saturday'));
// The states of each request, set by the library in the panel.
$booking_statuses = array(lang('Request received'), lang('Place confirmed'), lang('Waiting list'), lang('Cancelled'));
$membership_statuses = array(lang('Request received'), lang('Card ready to collect'), lang('Card issued'), lang('More information needed'));
$reservation_statuses = array(lang('Request received'), lang('Ready to collect'), lang('Picked up'), lang('Not available this time'));
$room_statuses = array(lang('Request received'), lang('Room booked'), lang('Not available this time'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The words of a sample record's pick list answers.
$item_format = function ($key) use ($catalogue_items, $formats) {
    return $formats[$catalogue_items[$key]['format']]['title'];
};
$item_audience = function ($key) use ($catalogue_items, $audiences) {
    return $audiences[$catalogue_items[$key]['audience']];
};
$item_branch = function ($key) use ($catalogue_items, $branches) {
    return $branches[$catalogue_items[$key]['branch']]['title'];
};
$item_availability = function ($key) use ($catalogue_items, $availability_choices) {
    return $availability_choices[$catalogue_items[$key]['availability']];
};
$event_kind = function ($key) use ($events, $event_kinds) {
    return $event_kinds[$events[$key]['kind']]['title'];
};
$event_branch = function ($key) use ($events, $branches) {
    return $branches[$events[$key]['branch']]['title'];
};
$event_age = function ($key) use ($events, $age_groups) {
    return $age_groups[$events[$key]['age']];
};
// An event's details as the value of its Details field: one line per item.
$details_text = function ($key) use ($events) {
    return implode("\n", $events[$key]['details']);
};

// The query string of a link that opens a list with a filter set
// (?format=, ?audience=, ?kind=) or a form with a choice made (?event=,
// ?title=, ?room=): the form's fields start with these values
// (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the join band above the footer unless the
// page is a form or an account page.
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

$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-body-tertiary', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'plb-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'plb-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'plb-display h3 mb-0'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'lib-faq') use ($el, $para, $attr, $searchable) {
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


// The library's figures in a row: the number large, its meaning under it.
$figure_row = function ($class = '') use ($el, $span, $col, $row, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Key Figure'), array(
                $span($f['value'], 'plb-stat d-block display-5 fw-bold text-primary', lang('Value')),
                $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4', '', $class);
};


// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the late opening and the way to the hours.
// A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'mb-0', lang('Notice'), array(
        $icon('bi-clock', 'text-primary me-1'),
        $span(lang('The Central Library is open until 20:00 on weekdays'), '', lang('Text')),
        $link(lang('Opening Hours'), '{{page:visit}}', 'link-primary fw-semibold ms-1'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site. The
// library sells nothing online, so the bar carries no cart.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body-tertiary', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex flex-wrap align-items-center justify-content-end column-gap-1 column-gap-sm-2 row-gap-1 ms-auto', lang('Account Links'), array(
                $lang_switcher('text-nowrap'), $widget('login_region'),
            )),
        )),
    ));
};

// The header: the library's name, the menu and the way to join. The links
// to the current page are marked active on the server (smart active
// state), so one header serves every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $nav = array();
    foreach (array(
        array('catalogue', lang('Catalogue')),
        array('events', lang('Events')),
        array('visit', lang('Visit Us')),
        array('spaces', lang('Rooms and Spaces')),
        array('children', lang('Children and Teens')),
        array('digital', lang('Digital Library')),
        array('blog', lang('News')),
    ) as $n) {
        $nav[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-book', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'plb-brand fs-4 text-truncate', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#lib-nav'),
                    $attr('aria-controls', 'lib-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ms-xl-auto me-xl-3 column-gap-xl-1 py-3 py-xl-0', lang('Nav Menu'), $nav, array('smartActive' => true)),
                    // From 1200 to 1399 px the brand and the seven links fill the
                    // 1140 px container, so the card button stays in the collapsed
                    // menu and comes back to the open bar from 1400 px.
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex d-xl-none d-xxl-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-person-vcard'),
                            $span(lang('Join the Library'), '', lang('Link Text')),
                        ), array('href' => '{{page:join}}')),
                    )),
                ), array('id' => 'lib-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the library in a sentence, how to reach it
// and its profiles, the branches, the catalogue by format, the events by
// kind, the members' pages and the library's pages; at the bottom the
// year, the site's name and who runs the library.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $branches, $format_titles, $event_kind_titles, $library, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $out = array();
        foreach ($links as $l) $out[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $out);
    };
    $branch_links = array();
    foreach ($branches as $key => $b) $branch_links[] = $link($b['title'], '{{page:branch_' . $key . '}}', $plain);
    $branch_links[] = $link(lang('Opening Hours'), '{{page:visit}}', $plain);
    $catalogue_links = array($link(lang('All Titles'), '{{page:catalogue}}', $plain));
    foreach ($format_titles as $title) $catalogue_links[] = $link($title, '{{page:catalogue}}?' . $query(array('format' => $title)), $plain);
    $event_links = array($link(lang('All Events'), '{{page:events}}', $plain));
    foreach ($event_kind_titles as $title) $event_links[] = $link($title, '{{page:events}}?' . $query(array('kind' => $title)), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Library'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-book', 'text-primary'),
                        $span('{{site_name}}', 'plb-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($library['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-geo-alt', $link($library['address'], '{{page:contact}}', $plain), lang('Address')),
                        $contact_line('bi-telephone', $link($library['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $library['phone']), $plain), lang('Phone')),
                        $contact_line('bi-envelope', $link($library['email'], 'mailto:' . $library['email'], $plain . ' text-break'), lang('Email')),
                        $contact_line('bi-clock', $span($library['hours'], '', lang('Text')), lang('Opening Hours')),
                    )),
                    $el('div', 'd-flex gap-3 mt-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Branch Links'), array(
                    $heading('h2', lang('Branches'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($branch_links),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Catalogue Links'), array(
                    $heading('h2', lang('Catalogue'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($catalogue_links),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Event Links'), array(
                    $heading('h2', lang('Events'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($event_links),
                )),
                $el('div', 'col-6 col-md-12 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Members'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Join the Library'), '{{page:join}}', $plain),
                        $link(lang('My Library Card'), '{{page:my_memberships}}', $plain),
                        $link(lang('My Reserved Titles'), '{{page:my_reservations}}', $plain),
                        $link(lang('My Event Places'), '{{page:my_bookings}}', $plain),
                        $link(lang('Book a Room'), '{{page:room_booking}}', $plain),
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($library['licence'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The join band: a full-width band in the palette's colour with the ways
// to join and to find a branch. Shared, so every page that ends with it
// carries the same call.
$join_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Your library card is free'), 'plb-display h2 mb-2'),
                    $para(lang('Ask for a card online and collect it at any branch, or come in with proof of your address and leave with it the same day.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Join the Library'), '{{page:join}}', 'btn btn-secondary btn-lg px-4'),
                        $link(lang('Find a Branch'), '{{page:visit}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A branch's opening hours, day by day, as a list of terms and values (the
// visit page, the branch pages and the home).
$hours_list = function ($key, $class = '') use ($el, $branches) {
    $rows = array();
    foreach ($branches[$key]['hours'] as $h) {
        $rows[] = $el('div', 'd-flex justify-content-between gap-3 py-2 border-bottom', lang('Opening Hours Row'), array(
            $el('dt', 'fw-semibold', lang('Days'), array(), array('text' => $h['days'])),
            $el('dd', 'plb-hours mb-0 text-end', lang('Hours'), array(), array('text' => $h['hours'])),
        ));
    }
    return $el('dl', trim('mb-0 ' . $class), lang('Opening Hours'), $rows);
};

// A branch's card (the home and the visit page): the picture, the name
// linking the branch's page, the address, the hours in a line and the
// phone.
$branch_card = function ($key) use ($el, $para, $span, $link, $image, $col, $icon, $photo, $attr, $branches) {
    $b = $branches[$key];
    $line = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $span($text, '', $name)));
    };
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden plb-lift', lang('Branch Card'), array(
            $el('span', 'd-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('branch-' . $key, 800, 450), $b['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('h3', 'plb-display h5 position-static mb-2', lang('Branch Name'), array(
                    $link($b['title'], '{{page:branch_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($b['summary'], 'small text-body-secondary mb-3'),
                $line('bi-geo-alt', $b['address'], lang('Address')),
                $line('bi-clock', $b['short'], lang('Opening Hours')),
                $line('bi-telephone', $b['phone'], lang('Phone')),
            )),
        )),
    ), '6', '4');
};

// A room's card (the spaces page and the branch pages): the picture, the
// room, its branch and size, what it has and the way to book it with the
// room chosen (?room=).
$room_card = function ($key) use ($el, $heading, $para, $span, $link, $image, $col, $photo, $attr, $check_list, $query, $rooms, $branches) {
    $r = $rooms[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Room Card'), array(
            $el('span', 'd-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('room-' . $key, 800, 450), $r['title'], 'w-100 h-100 object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($branches[$r['branch']]['title'], 'plb-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Branch')),
                $heading('h3', $r['title'], 'plb-display h5 mb-1'),
                $para($r['seats'], 'small fw-semibold mb-2'),
                $para($r['summary'], 'small text-body-secondary mb-3'),
                $check_list($r['equipment'], 'list-unstyled small mb-4'),
                $link(lang('Book This Room'), '{{page:room_booking}}?' . $query(array('room' => $r['title'])), 'btn btn-outline-primary mt-auto align-self-start'),
            )),
        )),
    ), '6', '6');
};

// The availability of a title as a badge, bound to its Availability field.
$availability_badge = function ($text, $class = 'badge rounded-pill bg-body text-body-emphasis border') use ($span, $bind) {
    return $span($text, $class, lang('Availability'), $bind('availability'));
};

// The facts of a title in one list: format, audience, genre, shelf mark
// and branch, each bound to the Catalogue form's field of the same name.
$item_facts = function ($sample_key = 'harbour', $class = 'list-unstyled small mb-0') use ($el, $span, $bind, $catalogue_items, $item_format, $item_audience, $item_branch) {
    $t = $catalogue_items[$sample_key];
    $fact = function ($label, $text, $token) use ($el, $span, $bind) {
        return $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $label, array(
            $span($label, 'fw-semibold', lang('Label')),
            $span($text, 'text-end', lang('Value'), $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Title Facts'), array(
        $fact(lang('Format'), $item_format($sample_key), 'format'),
        $fact(lang('Who it is for'), $item_audience($sample_key), 'audience'),
        $fact(lang('Genre'), $t['genre'], 'genre'),
        $fact(lang('Shelf Mark'), $t['shelf'], 'shelf'),
        $fact(lang('Branch'), $item_branch($sample_key), 'branch'),
    ));
};

// A title's card of the lists (the home's new arrivals and the
// catalogue): the cover, the format, the title linking its page, the
// author and where it is. Every value is bound to the Catalogue form's
// field of the same name; the sample fills the canvas.
$item_card = function ($sample_key = 'harbour') use ($el, $para, $span, $link, $image, $col, $bind, $catalogue_items, $item_format, $item_availability, $availability_badge, $photo, $attr) {
    $t = $catalogue_items[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-transparent plb-lift', lang('Title Card'), array(
            $el('span', 'pg-field-value d-block ratio plb-cover bg-body-tertiary rounded shadow-sm overflow-hidden mb-3', lang('Cover Frame'), array(
                $image($photo('cover-' . $sample_key, 400, 600), $t['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'd-flex flex-column', lang('Card Body'), array(
                $span($item_format($sample_key), 'plb-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Format'), $bind('format')),
                $el('h3', 'plb-display h6 position-static mb-1', lang('Title'), array(
                    $link($t['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($t['author'], 'small text-body-secondary mb-2', $bind('author')),
                $el('div', '', lang('Availability'), array($availability_badge($item_availability($sample_key)))),
            )),
        )),
    ), '4', '2', '', '6');
};

// The facts of an event in one list: kind, branch, when and who it is
// for, each bound to the Events form's field of the same name.
$event_facts = function ($sample_key = 'book_club', $class = 'list-unstyled d-flex flex-wrap gap-3 small text-body-secondary mb-0') use ($el, $icon, $span, $bind, $events, $event_kind, $event_branch, $event_age) {
    $e = $events[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('li', 'd-inline-flex align-items-center gap-1', $name, array(
            $icon($icon_name, 'text-primary'),
            $span($text, '', $name, $bind($token)),
        ));
    };
    return $el('ul', $class, lang('Event Facts'), array(
        $fact('bi-calendar-event', $e['when'], 'when', lang('When')),
        $fact('bi-geo-alt', $event_branch($sample_key), 'branch', lang('Branch')),
        $fact('bi-people', $event_age($sample_key), 'age_group', lang('Age Group')),
        $fact('bi-tag', $event_kind($sample_key), 'kind', lang('Kind')),
    ));
};

// An event's card of the lists (the home and the event list): the photo,
// the kind, the title linking the event page, when, the branch and the
// summary. Every value is bound to the Events form's field of the same
// name; the sample fills the canvas.
$event_card = function ($sample_key = 'book_club') use ($el, $para, $span, $link, $image, $col, $bind, $icon, $events, $event_kind, $event_branch, $photo, $attr) {
    $e = $events[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden plb-lift', lang('Event Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('event-' . $sample_key, 800, 450), $e['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo_1', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_kind($sample_key), 'plb-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Kind'), $bind('kind')),
                $el('h3', 'plb-display h5 position-static mb-2', lang('Event Title'), array(
                    $link($e['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-start gap-1 small fw-semibold mb-1', lang('When'), array(
                    $icon('bi-calendar-event', 'text-primary'),
                    $span($e['when'], '', lang('When'), $bind('when')),
                )),
                $el('p', 'd-flex align-items-start gap-1 small mb-2', lang('Branch'), array(
                    $icon('bi-geo-alt', 'text-primary'),
                    $span($event_branch($sample_key), '', lang('Branch'), $bind('branch')),
                )),
                $para($e['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '4');
};

// A member of staff (the about page): the portrait, the name, the role
// and a sentence.
$staff_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $staff) {
    $t = $staff[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm text-center p-4', lang('Staff Member'), array(
            $image($portrait($t['name'], 240), $t['name'], 'rounded-circle mx-auto mb-3', array('width' => '120', 'height' => '120', '_attrs' => array($attr('loading', 'lazy')))),
            $heading('h3', $t['name'], 'plb-display h5 mb-1'),
            $para($t['role'], 'small fw-semibold text-primary mb-2'),
            $para($t['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// What a reader says: the quote, the reader and the branch.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $icon, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Reader Quote'), array(
            $icon('bi-quote', 'fs-2 text-primary lh-1 mb-2'),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'plb-quote mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Reader Details'), array(
                $image($portrait($t['name'], 96), $t['name'], 'rounded-circle', array('width' => '48', 'height' => '48', '_attrs' => array($attr('loading', 'lazy')))),
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
                $item('staff_memberships', lang('Library Card Requests')),
                $item('staff_reservations', lang('Book Reservation Requests')),
                $item('staff_bookings', lang('Event Places')),
                $item('staff_room_bookings', lang('Room Bookings')),
                $item('item_new', lang('New Title')),
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
    return $agree($id, 'consent', lang('I would like to receive the library\'s news and event invitations by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A card
// request takes a proof of address (a bill, a letter), so the default
// accepts PDFs, office files, text and pictures; one that takes only
// pictures is a collection's image kind (a cover, an event's photo).
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
                    $link(lang('Ten new books for long summer evenings'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
