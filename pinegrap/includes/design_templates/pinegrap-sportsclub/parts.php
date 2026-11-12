<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Sports Club", part 1 of its files: the node builders,
 * the club's own data (the club and its ground, the sports, the
 * competitions, the teams, the positions, the sample players and matches,
 * the honours, the academy's values, the memberships, season tickets and
 * kit with their prices, the sponsors and sponsorship packages, the club
 * events, the figures, what players, parents and fans say and the answers
 * of the forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, join band, team, player, match, sponsor, package, event
 * and quote cards, page shells, section headers, form fields, the staff
 * bar and the blog parts). The comment thread comes with the first record
 * pages that carry messages (mail.php), the collection list helpers with
 * the first collections (squad.php).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-sportsclub.php puts them together.
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
// every opening until the club puts its own photographs in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-sportsclub-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait or badge: initials on the palette's colour. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/dcfce7/14532d?text=' . rawurlencode($initials !== '' ? $initials : 'C');
};


// ── The club: its teams, its players, its matches and its friends ──────

// The club itself. The club page tells its story; the footer, the join
// band and the contact lines use the address, the phone and the ground.
// Its past reads as words, never as a year.
$club = array(
    'tagline' => lang('A community football and sports club for every age: weekend matches, weeknight training and a clubhouse that is always open for a cup of tea'),
    'story'   => lang('Railway workers founded the club on a borrowed field to give their children somewhere to play. It has grown to a ground of its own, teams for men, women and children of every age, a basketball section and hundreds of members, and it is still run by volunteers who love the game.'),
    'address' => lang('Mill Lane Ground, Mill Lane 1, Riverside'),
    'region'  => lang('Ten minutes\' walk from the river station, with free parking behind the clubhouse'),
    'phone'   => '+90 555 000 00 00',
    'email'   => 'hello@example.com',
);

// The club's home ground: the ground page, the footer and the match pages
// read it. 'capacity' is a word, not a seat map.
$ground = array(
    'name'     => lang('Mill Lane Ground'),
    'capacity' => lang('Room for 1,500 fans, 300 of them under the covered stand'),
    'text'     => lang('A grass pitch under floodlights, a third-generation artificial pitch for training, a sports hall for basketball and a clubhouse with changing rooms, a kitchen and a bar.'),
    'points'   => array(
        lang('Floodlit grass pitch for match days'),
        lang('All-weather pitch for training and youth matches'),
        lang('Sports hall for the basketball section'),
        lang('Clubhouse with a kitchen, a bar and a big screen'),
        lang('Step-free entry and places for wheelchair users'),
    ),
);

// The sports the club plays. A team names one of them.
$sports = array(
    'football'   => array('title' => lang('Football'), 'icon' => 'bi-flag'),
    'basketball' => array('title' => lang('Basketball'), 'icon' => 'bi-dribbble'),
);

// The competitions a match is played in.
$competitions = array(
    'league'       => lang('Regional Amateur League'),
    'cup'          => lang('County Cup'),
    'women_league' => lang('Women\'s Regional League'),
    'youth_league' => lang('Youth Development League'),
    'veterans'     => lang('Veterans\' Cup'),
    'basketball'   => lang('City Basketball League'),
    'friendly'     => lang('Friendly Match'),
);

// The teams: the cards of the teams page and one page each. 'youth' marks
// an academy team (registrations name it); 'group' is the age group or
// level as words, 'training' when it trains, 'competition' where it plays.
$teams = array(
    'first'      => array('title' => lang('Men\'s First Team'), 'sport' => 'football', 'youth' => false, 'icon' => 'bi-trophy',
        'group' => lang('Adults, 18 and over'), 'competition' => 'league', 'coach' => lang('Emre Şahin'),
        'training' => lang('Tuesday and Thursday, 19:30 to 21:00'),
        'summary'  => lang('The club\'s flagship side, playing in the regional amateur league on Saturday afternoons at Mill Lane.')),
    'women'      => array('title' => lang('Women\'s Team'), 'sport' => 'football', 'youth' => false, 'icon' => 'bi-stars',
        'group' => lang('Adults, 16 and over'), 'competition' => 'women_league', 'coach' => lang('Leyla Koç'),
        'training' => lang('Monday and Wednesday, 19:00 to 20:30'),
        'summary'  => lang('A growing squad of players old and new, competing in the women\'s regional league on Sunday afternoons.')),
    'veterans'   => array('title' => lang('Veterans'), 'sport' => 'football', 'youth' => false, 'icon' => 'bi-hourglass-split',
        'group' => lang('Players over 35'), 'competition' => 'veterans', 'coach' => lang('Hakan Yurt'),
        'training' => lang('Friday, 19:00 to 20:30'),
        'summary'  => lang('Former players and late starters who still love a game, with a monthly cup match and a long third half in the clubhouse.')),
    'u16'        => array('title' => lang('Under-16s'), 'sport' => 'football', 'youth' => true, 'icon' => 'bi-lightning',
        'group' => lang('Ages 14 to 16'), 'competition' => 'youth_league', 'coach' => lang('Can Erdem'),
        'training' => lang('Tuesday and Thursday, 17:30 to 19:00'),
        'summary'  => lang('The academy\'s oldest group, preparing young players for adult football with the same coaching as the first team.')),
    'u12'        => array('title' => lang('Under-12s'), 'sport' => 'football', 'youth' => true, 'icon' => 'bi-emoji-smile',
        'group' => lang('Ages 10 to 12'), 'competition' => 'youth_league', 'coach' => lang('Merve Aksoy'),
        'training' => lang('Wednesday, 17:00 to 18:30'),
        'summary'  => lang('Small-sided matches and lots of touches of the ball, where skill and fair play count more than the score.')),
    'u8'         => array('title' => lang('Under-8s'), 'sport' => 'football', 'youth' => true, 'icon' => 'bi-balloon',
        'group' => lang('Ages 5 to 8'), 'competition' => 'friendly', 'coach' => lang('Merve Aksoy'),
        'training' => lang('Saturday, 09:30 to 10:30'),
        'summary'  => lang('A first taste of football through games and play, with festivals instead of league tables.')),
    'basketball' => array('title' => lang('Basketball Section'), 'sport' => 'basketball', 'youth' => false, 'icon' => 'bi-dribbble',
        'group' => lang('Adults and juniors from 12'), 'competition' => 'basketball', 'coach' => lang('Daniel Brooks'),
        'training' => lang('Monday and Thursday, 20:00 to 21:30, in the sports hall'),
        'summary'  => lang('Men\'s and junior basketball in the sports hall, with home games on Sunday evenings in the city league.')),
);

// The positions a player plays, per sport. Every answer's value is its own
// words, so the squad list's filter reads as it shows.
$positions = array(
    'goalkeeper' => array('title' => lang('Goalkeeper'), 'sport' => 'football'),
    'defender'   => array('title' => lang('Defender'), 'sport' => 'football'),
    'midfielder' => array('title' => lang('Midfielder'), 'sport' => 'football'),
    'forward'    => array('title' => lang('Striker'), 'sport' => 'football'),
    'guard'      => array('title' => lang('Guard'), 'sport' => 'basketball'),
    'wing'       => array('title' => lang('Wing'), 'sport' => 'basketball'),
    'centre'     => array('title' => lang('Centre'), 'sport' => 'basketball'),
);

// The sample players: the records the Players form starts with, all of
// the adult teams. Youth players are never named in the sample; their
// coaches add the squads they want to show.
$players = array(
    'okan'   => array('name' => lang('Okan Yıldız'), 'team' => 'first', 'position' => 'goalkeeper', 'number' => 1, 'featured' => true,
        'summary' => lang('A calm voice behind the defence and the safest pair of hands in the league.'),
        'bio'     => lang('Okan came through the club\'s youth teams and has kept goal for the first team for six seasons. He works as a physiotherapist and looks after the squad\'s sore legs on Monday nights.')),
    'baris'  => array('name' => lang('Barış Çelik'), 'team' => 'first', 'position' => 'defender', 'number' => 5, 'featured' => false,
        'summary' => lang('The captain: first to every header and last to leave the clubhouse.'),
        'bio'     => lang('Barış joined from a neighbouring club and has worn the armband for three seasons. He coaches the Under-12s on Wednesday evenings.')),
    'tom'    => array('name' => lang('Tom Hughes'), 'team' => 'first', 'position' => 'midfielder', 'number' => 8, 'featured' => true,
        'summary' => lang('Runs all afternoon and passes the ball to the right place nearly every time.'),
        'bio'     => lang('Tom moved to the city for work and found the club on his first weekend. He has been the first team\'s player of the season twice.')),
    'kerem'  => array('name' => lang('Kerem Aydın'), 'team' => 'first', 'position' => 'forward', 'number' => 9, 'featured' => true,
        'summary' => lang('The club\'s top scorer, with a left foot the whole league knows about.'),
        'bio'     => lang('Kerem scored on his first-team debut as a seventeen-year-old and has not stopped since. During the week he studies engineering.')),
    'elif'   => array('name' => lang('Elif Güneş'), 'team' => 'women', 'position' => 'midfielder', 'number' => 10, 'featured' => true,
        'summary' => lang('Captain of the women\'s team and the heart of its midfield.'),
        'bio'     => lang('Elif started playing at the club\'s first open session for women and has led the team from its first friendly to the regional league.')),
    'sarah'  => array('name' => lang('Sarah Mitchell'), 'team' => 'women', 'position' => 'defender', 'number' => 4, 'featured' => false,
        'summary' => lang('Strong in the tackle and quick to start an attack from the back.'),
        'bio'     => lang('Sarah played university football abroad before joining the club. She runs the women\'s team\'s social evenings.')),
    'jamal'  => array('name' => lang('Jamal Osei'), 'team' => 'basketball', 'position' => 'guard', 'number' => 7, 'featured' => false,
        'summary' => lang('Sets the pace of every game and rarely misses from the free-throw line.'),
        'bio'     => lang('Jamal helped start the basketball section in the old sports hall and now coaches its junior group on Thursday evenings.')),
);

// The day of a sample match, counted from the day the template is opened
// so the fixtures are still to come and the results are behind them: the
// first day after today with the ISO weekday given (1 = Monday), plus
// $weeks weeks; a negative $weeks counts back. Each match is a record of
// the Matches form, edited or deleted like any other.
$match_on = function ($weekday, $weeks = 0) {
    $day = new DateTime('today');
    do {
        $day->modify('+1 day');
    } while ((int)$day->format('N') !== $weekday);
    if ($weeks !== 0) $day->modify(($weeks > 0 ? '+' : '-') . (7 * abs($weeks)) . ' days');
    return $day->format('Y-m-d');
};

// The opponents of the sample matches.
$opponents = array(
    'rovers'   => lang('Riverside Rovers'),
    'hillside' => lang('Hillside Athletic'),
    'oldtown'  => lang('Old Town United'),
    'harbour'  => lang('Harbour Sports Club'),
    'eastgate' => lang('Eastgate Wanderers'),
    'northend' => lang('Northend Ladies'),
    'cityhoops' => lang('City Hoops'),
);

// Where a match is played and how it ended. Every answer's value is its
// own words; 'badge' colours the result.
$venues = array(
    'home' => lang('Home Match'),
    'away' => lang('Away Match'),
);
$outcomes = array(
    'win'  => array('title' => lang('Won'), 'badge' => 'text-bg-success'),
    'draw' => array('title' => lang('Drawn'), 'badge' => 'text-bg-secondary'),
    'loss' => array('title' => lang('Lost'), 'badge' => 'text-bg-danger'),
);

// The sample matches: five to come and four already played (a negative
// 'weeks'). A played match has its score as words and its outcome; one to
// come has neither. 'kickoff' is the start time as words.
$matches = array(
    'next_first'  => array('team' => 'first', 'opponent' => 'rovers', 'competition' => 'league', 'venue' => 'home', 'weekday' => 6, 'weeks' => 0,
        'kickoff' => '15:00', 'score' => '', 'outcome' => '', 'featured' => true,
        'report' => lang('The local derby under the lights: the two sides are level on points, and the clubhouse opens at noon.')),
    'next_women'  => array('team' => 'women', 'opponent' => 'northend', 'competition' => 'women_league', 'venue' => 'away', 'weekday' => 7, 'weeks' => 0,
        'kickoff' => '14:00', 'score' => '', 'outcome' => '', 'featured' => true,
        'report' => lang('A long trip to the league leaders: the minibus leaves the clubhouse at eleven.')),
    'next_basket' => array('team' => 'basketball', 'opponent' => 'cityhoops', 'competition' => 'basketball', 'venue' => 'home', 'weekday' => 7, 'weeks' => 0,
        'kickoff' => '19:00', 'score' => '', 'outcome' => '', 'featured' => false,
        'report' => lang('Sunday night basketball in the sports hall, with free entry for members.')),
    'cup_first'   => array('team' => 'first', 'opponent' => 'hillside', 'competition' => 'cup', 'venue' => 'away', 'weekday' => 3, 'weeks' => 1,
        'kickoff' => '19:45', 'score' => '', 'outcome' => '', 'featured' => true,
        'report' => lang('A midweek cup tie away from home: the winners go through to the quarter-finals.')),
    'next_vets'   => array('team' => 'veterans', 'opponent' => 'eastgate', 'competition' => 'veterans', 'venue' => 'home', 'weekday' => 5, 'weeks' => 2,
        'kickoff' => '19:30', 'score' => '', 'outcome' => '', 'featured' => false,
        'report' => lang('The monthly veterans\' cup match, followed by the usual third half in the clubhouse.')),
    'last_first'  => array('team' => 'first', 'opponent' => 'oldtown', 'competition' => 'league', 'venue' => 'away', 'weekday' => 6, 'weeks' => -2,
        'kickoff' => '15:00', 'score' => '2–1', 'outcome' => 'win', 'featured' => true,
        'report' => lang('Kerem scored twice after the break to turn the game around in front of a loud away end.')),
    'last_women'  => array('team' => 'women', 'opponent' => 'harbour', 'competition' => 'women_league', 'venue' => 'home', 'weekday' => 7, 'weeks' => -2,
        'kickoff' => '14:00', 'score' => '1–1', 'outcome' => 'draw', 'featured' => true,
        'report' => lang('A late equaliser from Elif earned a deserved point against one of the league\'s best sides.')),
    'last_basket' => array('team' => 'basketball', 'opponent' => 'cityhoops', 'competition' => 'basketball', 'venue' => 'away', 'weekday' => 7, 'weeks' => -3,
        'kickoff' => '19:00', 'score' => '68–74', 'outcome' => 'loss', 'featured' => false,
        'report' => lang('Close until the last quarter, when the hosts pulled away from the line.')),
    'last_vets'   => array('team' => 'veterans', 'opponent' => 'rovers', 'competition' => 'veterans', 'venue' => 'home', 'weekday' => 5, 'weeks' => -3,
        'kickoff' => '19:30', 'score' => '3–3', 'outcome' => 'draw', 'featured' => false,
        'report' => lang('Six goals, two penalties and no tired legs admitted to afterwards.')),
);
// A match's title: the club against the opponent, home team first.
$match_title = function ($key) use ($matches, $opponents) {
    $m = $matches[$key];
    $us = '{{site_name}}';
    return $m['venue'] === 'home'
        ? sprintf(lang('%1$s vs %2$s'), $us, $opponents[$m['opponent']])
        : sprintf(lang('%1$s vs %2$s'), $opponents[$m['opponent']], $us);
};

// What the club has won, as words: never a season or a year.
$honours = array(
    array('icon' => 'bi-trophy-fill', 'title' => lang('Regional Amateur League champions'), 'times' => lang('Three times')),
    array('icon' => 'bi-award-fill', 'title' => lang('County Cup winners'), 'times' => lang('Five times')),
    array('icon' => 'bi-star-fill', 'title' => lang('Women\'s Regional League promotion'), 'times' => lang('Twice')),
    array('icon' => 'bi-heart-fill', 'title' => lang('Community Club of the Year'), 'times' => lang('Once, and proud of it')),
);

// What the academy stands for (the academy page and the home).
$academy_points = array(
    'coaches' => array('icon' => 'bi-person-badge', 'title' => lang('Qualified Coaches'),
        'text' => lang('Every group has a qualified, background-checked coach and a volunteer helper.')),
    'play'    => array('icon' => 'bi-emoji-smile', 'title' => lang('Fun First'),
        'text' => lang('Children learn by playing: lots of touches of the ball, small-sided games and every child on the pitch.')),
    'pathway' => array('icon' => 'bi-signpost-split', 'title' => lang('A Path to Adult Football'),
        'text' => lang('From the Under-8s to the first team, with the same way of playing at every age.')),
    'girls'   => array('icon' => 'bi-gender-female', 'title' => lang('Girls Welcome'),
        'text' => lang('Girls play in every age group, and the women\'s team trains the oldest girls once a month.')),
);

// The membership of the club. With the shop each is a product that renews
// the buyer's membership for 'days'; without it, the membership page asks
// the club. Prices are per season, per currency.
$memberships = array(
    'adult'  => array('sku' => 'CLUB-MEMBER-ADULT', 'icon' => 'bi-person', 'title' => lang('Adult Membership'), 'days' => 365, 'featured' => true,
        'price'  => array('USD' => 60, 'EUR' => 55, 'GBP' => 48, 'TRY' => 2200),
        'text'   => lang('Support the club, vote at the annual meeting and get member prices in the clubhouse and the kit shop.'),
        'points' => array(lang('A vote at the annual meeting'), lang('Member prices in the clubhouse'), lang('Ten percent off in the kit shop'), lang('The members\' area of the website'))),
    'junior' => array('sku' => 'CLUB-MEMBER-JUNIOR', 'icon' => 'bi-emoji-smile', 'title' => lang('Junior Membership'), 'days' => 365, 'featured' => false,
        'price'  => array('USD' => 25, 'EUR' => 23, 'GBP' => 20, 'TRY' => 900),
        'text'   => lang('For fans under 16: a membership card, a birthday card from the team and a match-day mascot draw.'),
        'points' => array(lang('A membership card'), lang('A birthday card from the team'), lang('The match-day mascot draw'))),
    'family' => array('sku' => 'CLUB-MEMBER-FAMILY', 'icon' => 'bi-people', 'title' => lang('Family Membership'), 'days' => 365, 'featured' => false,
        'price'  => array('USD' => 120, 'EUR' => 110, 'GBP' => 95, 'TRY' => 4400),
        'text'   => lang('Two adults and their children under 16, with every benefit of the adult and junior memberships.'),
        'points' => array(lang('Two adult votes at the annual meeting'), lang('Junior benefits for every child'), lang('Ten percent off in the kit shop'))),
);

// Season tickets: entry to every home league match of the first team and
// the women's team. Sold in the shop, never shipped.
$season_tickets = array(
    'adult'      => array('sku' => 'CLUB-SEASON-ADULT', 'title' => lang('Adult Season Ticket'), 'featured' => true,
        'price' => array('USD' => 90, 'EUR' => 82, 'GBP' => 72, 'TRY' => 3200),
        'text'  => lang('Every home league match of the first team and the women\'s team, with a seat in the covered stand.')),
    'concession' => array('sku' => 'CLUB-SEASON-CONCESSION', 'title' => lang('Concession Season Ticket'), 'featured' => false,
        'price' => array('USD' => 55, 'EUR' => 50, 'GBP' => 44, 'TRY' => 2150),
        'text'  => lang('For students, fans over 65 and fans with a disability, with a free place for a carer.')),
    'junior'     => array('sku' => 'CLUB-SEASON-JUNIOR', 'title' => lang('Junior Season Ticket'), 'featured' => false,
        'price' => array('USD' => 20, 'EUR' => 18, 'GBP' => 16, 'TRY' => 700),
        'text'  => lang('For fans under 16, who come in with an adult.')),
);

// The kit shop's products: shipped, per currency.
$kit = array(
    'home'     => array('sku' => 'CLUB-KIT-HOME', 'icon' => 'bi-tags', 'title' => lang('Home Shirt'),
        'price' => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1600),
        'text'  => lang('The green and white home shirt the first team wears, in adult and children\'s sizes.')),
    'away'     => array('sku' => 'CLUB-KIT-AWAY', 'icon' => 'bi-tags', 'title' => lang('Away Shirt'),
        'price' => array('USD' => 45, 'EUR' => 42, 'GBP' => 36, 'TRY' => 1600),
        'text'  => lang('The dark away shirt, with the club badge stitched on the chest.')),
    'training' => array('sku' => 'CLUB-KIT-TRAINING', 'icon' => 'bi-wind', 'title' => lang('Training Top'),
        'price' => array('USD' => 35, 'EUR' => 32, 'GBP' => 28, 'TRY' => 1250),
        'text'  => lang('A light quarter-zip top for cold training nights and the touchline.')),
    'scarf'    => array('sku' => 'CLUB-KIT-SCARF', 'icon' => 'bi-snow', 'title' => lang('Club Scarf'),
        'price' => array('USD' => 18, 'EUR' => 16, 'GBP' => 14, 'TRY' => 650),
        'text'  => lang('A knitted scarf in the club colours, for the covered stand in winter.')),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// A membership's or a season ticket's "… a season" words.
$season_price = function (array $prices) use ($money) {
    return sprintf(lang('%s a season'), $money($prices));
};

// The sponsors, by tier: the sponsors page and the footer's partner line.
$sponsor_tiers = array(
    'main'    => lang('Main Partner'),
    'gold'    => lang('Gold Partners'),
    'friends' => lang('Friends of the Club'),
);
$sponsors = array(
    'bakery'   => array('name' => lang('Riverside Bakery'), 'tier' => 'main',
        'text' => lang('Our shirt sponsor, and the reason the half-time pastries are so good.')),
    'builders' => array('name' => lang('Northline Builders'), 'tier' => 'gold',
        'text' => lang('Built the new changing rooms and sponsors the academy\'s kit.')),
    'garage'   => array('name' => lang('Mill Lane Garage'), 'tier' => 'gold',
        'text' => lang('Keeps the club minibus on the road for every away trip.')),
    'pharmacy' => array('name' => lang('Greenway Pharmacy'), 'tier' => 'friends',
        'text' => lang('Fills the first aid kits of every team.')),
    'print'    => array('name' => lang('Harbour Print'), 'tier' => 'friends',
        'text' => lang('Prints the match-day programme and the posters.')),
    'cafe'     => array('name' => lang('Bluebell Café'), 'tier' => 'friends',
        'text' => lang('Sponsors the player of the match award at every home game.')),
);
// The ways a business can support the club: the sponsorship enquiry's
// Package answers. Prices are agreed with the club, never shown.
$sponsor_packages = array(
    'shirt'    => array('icon' => 'bi-tags', 'title' => lang('Shirt Partner'),
        'text' => lang('Your name on the first team\'s shirts for a season, at every match and in every photograph.')),
    'matchday' => array('icon' => 'bi-megaphone', 'title' => lang('Match-Day Partner'),
        'text' => lang('A home match in your name: the programme, announcements and the player of the match award.')),
    'academy'  => array('icon' => 'bi-mortarboard', 'title' => lang('Academy Partner'),
        'text' => lang('Kit, balls and coaching courses for the youth teams, with your name on their training tops.')),
    'board'    => array('icon' => 'bi-easel', 'title' => lang('Pitchside Board'),
        'text' => lang('A board around the main pitch, seen by every fan and in every match video.')),
);

// The kinds of club events: the event list's filter (?kind=).
$event_kinds = array(
    'social'     => lang('Social Evening'),
    'fundraiser' => lang('Fundraiser'),
    'open_day'   => lang('Open Day'),
    'camp'       => lang('Holiday Camp'),
);
// The sample club events: the records the Club Events form starts with.
// 'when' is words, never a date.
$events = array(
    'awards' => array('title' => lang('End of Season Awards Night'), 'kind' => 'social', 'featured' => true,
        'when'  => lang('The Saturday after the last home match, from 19:00'), 'place' => lang('The Clubhouse'),
        'summary' => lang('Players of the season, the coaches\' awards, a buffet and dancing until late. Every member and family is welcome.')),
    'quiz'   => array('title' => lang('Quiz Night for the Academy'), 'kind' => 'fundraiser', 'featured' => true,
        'when'  => lang('First Friday of the month, 20:00'), 'place' => lang('The Clubhouse'),
        'summary' => lang('Teams of up to six, a raffle and a bar, with every penny going to the youth teams\' kit and travel.')),
    'open'   => array('title' => lang('Open Training Day'), 'kind' => 'open_day', 'featured' => true,
        'when'  => lang('Saturday before the season starts, 10:00 to 14:00'), 'place' => lang('Mill Lane Ground'),
        'summary' => lang('Come and try football or basketball with our coaches, meet the teams and see the ground. No need to book.')),
    'camp'   => array('title' => lang('Summer Football Camp'), 'kind' => 'camp', 'featured' => false,
        'when'  => lang('Every weekday in the first week of the school holidays, 09:00 to 15:00'), 'place' => lang('Mill Lane Ground'),
        'summary' => lang('Five days of football, games and a tournament on the last day for children aged 6 to 14.')),
);

// The club in four figures (the home and the club page).
$figures = array(
    array('value' => '7', 'label' => lang('Teams from Under-8s to Veterans')),
    array('value' => '250+', 'label' => lang('Players every week')),
    array('value' => '40', 'label' => lang('Volunteer coaches and helpers')),
    array('value' => '600', 'label' => lang('Members and season ticket holders')),
);

// What players, parents and fans say, each about one of the teams.
$testimonials = array(
    array('team' => 'u12', 'name' => lang('Ece Yılmaz'), 'role' => lang('Parent, Under-12s'),
        'quote' => lang('My daughter was shy when she started. Two seasons later she captains her team and cannot wait for Wednesday training.')),
    array('team' => 'women', 'name' => lang('Zeynep Arslan'), 'role' => lang('Player, Women\'s Team'),
        'quote' => lang('I came to an open session never having kicked a ball. Now I play every Sunday with friends I would never have met.')),
    array('team' => 'first', 'name' => lang('Tom Fletcher'), 'role' => lang('Season ticket holder, Men\'s First Team'),
        'quote' => lang('Saturday at Mill Lane is the best afternoon of the week: real football, good people and the best pastries in the league.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$team_titles = array();
$youth_team_titles = array();
foreach ($teams as $t) {
    $team_titles[] = $t['title'];
    if ($t['youth']) $youth_team_titles[] = $t['title'];
}
$position_titles = array();
foreach ($positions as $p) $position_titles[] = $p['title'];
$competition_titles = array_values($competitions);
$venue_titles = array_values($venues);
$outcome_titles = array();
foreach ($outcomes as $o) $outcome_titles[] = $o['title'];
$event_kind_titles = array_values($event_kinds);
$package_titles = array();
foreach ($sponsor_packages as $p) $package_titles[] = $p['title'];
// The trial request names a team to try for, or leaves it to the coaches.
$trial_team_choices = array_merge($team_titles, array(lang('Not sure yet')));
$experience_choices = array(
    array(lang('New to the sport'), lang('New to the sport')),
    array(lang('Played at school or for fun'), lang('Played at school or for fun')),
    array(lang('Played for a club'), lang('Played for a club')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);
// The states of an academy registration, set by the club in the panel.
$registration_statuses = array(lang('Registration received'), lang('Taster session booked'), lang('Place offered'), lang('Waiting list'), lang('Closed'));
// The states of a trial request, set by the club in the panel.
$trial_statuses = array(lang('Request received'), lang('Trial booked'), lang('Joined the squad'), lang('Not this time'));
// The states of a sponsorship enquiry, set by the club in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('In conversation'), lang('Agreed'), lang('Closed'));

// The query string of a link that opens a list with a filter set
// (?team=, ?kind=) or a form with a choice made (?team=, ?position=,
// ?package=):
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
            $heading('h1', $title, 'psp-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'psp-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'psp-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the teams and the academy's
// values are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what fans say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'sp-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: the trials open for every team and the way
// to ask for one. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-megaphone', 'text-primary'),
        $span(lang('Trials are open for every team: come and train with us.'), '', lang('Text')),
        $link(lang('Ask for a trial'), '{{page:trial}}', 'link-primary fw-semibold'),
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

// The club's badge beside its name: a shield in the palette's colour.
$crest = function ($class = 'fs-4') use ($icon) {
    return $icon('bi-shield-fill', 'psp-crest text-primary ' . $class);
};

// The header: the club's badge and name, the menu and the way to ask for
// a trial. The links to the current page are marked active on the server
// (smart active state), so one header serves every page. The shop's pages
// (kit, season tickets, membership) are linked from the shop pages only,
// so the header stands on a site without the shop.
$header = function () use ($el, $link, $icon, $span, $attr, $crest) {
    $items = array();
    foreach (array(
        array('teams', lang('Our Teams')),
        array('fixtures', lang('Fixtures')),
        array('results', lang('Match Results')),
        array('academy', lang('Youth Academy')),
        array('blog', lang('News')),
        array('events', lang('Events')),
        array('sponsors', lang('Sponsors')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $crest(),
                    $span('{{site_name}}', 'psp-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#sp-nav'),
                    $attr('aria-controls', 'sp-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav psp-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-person-plus'),
                            $span(lang('Join a Team'), '', lang('Link Text')),
                        ), array('href' => '{{page:trial}}')),
                    )),
                ), array('id' => 'sp-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the club in a sentence and its profiles, the
// teams (each opening its own page), the fans' pages, the club's pages and
// how to reach the ground; at the bottom the year and the site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $teams, $club, $ground, $attr, $crest) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $team_links = array();
    foreach ($teams as $key => $t) $team_links[] = $link($t['title'], '{{page:team_' . $key . '}}', $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Club'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $crest(''),
                        $span('{{site_name}}', 'psp-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($club['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                        $social('bi-twitter-x', 'X'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Team Links'), array(
                    $heading('h2', lang('Our Teams'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($team_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Fans'), array(
                    $heading('h2', lang('Match Day'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Fixtures'), '{{page:fixtures}}', $plain),
                        $link(lang('Match Results'), '{{page:results}}', $plain),
                        $link(lang('Squads'), '{{page:squad}}', $plain),
                        $link(lang('Club News'), '{{page:blog}}', $plain),
                        $link(lang('Club Events'), '{{page:events}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Club'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About the Club'), '{{page:club}}', $plain),
                        $link(lang('Youth Academy'), '{{page:academy}}', $plain),
                        $link(lang('Ask for a Trial'), '{{page:trial}}', $plain),
                        $link(lang('Sponsors'), '{{page:sponsors}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Find Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $link($club['address'], '{{page:ground}}', $plain), lang('Address')),
                    $contact_line('bi-telephone', $link($club['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $club['phone']), $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link($club['email'], 'mailto:' . $club['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-chat-dots', $link(lang('Contact Us'), '{{page:contact}}', $plain), lang('Contact Us')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(sprintf(lang('Home matches at %s'), $ground['name']), 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The join band: a full-width band in the palette's colour asking players
// of every age to join. Shared, so every page that ends with it carries
// the same call.
$join_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Play for the club'), 'psp-display h2 mb-2'),
                    $para(lang('Whatever your age or level, there is a team for you. Ask for a trial, or register a child for the youth academy.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Ask for a Trial'), '{{page:trial}}', 'btn btn-secondary btn-lg px-4'),
                        $link(lang('Youth Academy'), '{{page:academy}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// ── Cards ───────────────────────────────────────────────────────────────

// A team's age group, competition and training times, as a short list.
$team_facts = function ($key, $class = 'list-unstyled small text-body-secondary mb-0') use ($el, $icon, $span, $teams, $competitions) {
    $t = $teams[$key];
    $fact = function ($icon_name, $text, $name) use ($el, $icon, $span) {
        return $el('li', 'd-flex align-items-start gap-2 mb-1', $name, array($icon($icon_name, 'text-primary'), $span($text, '', lang('Value'))));
    };
    return $el('ul', $class, lang('Team Facts'), array(
        $fact('bi-people', $t['group'], lang('Age Group')),
        $fact('bi-trophy', $competitions[$t['competition']], lang('Competition')),
        $fact('bi-clock', $t['training'], lang('Training')),
    ));
};

// A team's card (the teams page, the academy and the home): the icon, the
// sport, the name linking the team's page, a line and the facts.
$team_card = function ($key) use ($el, $heading, $para, $span, $link, $col, $icon_tile, $team_facts, $teams, $sports) {
    $t = $teams[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm psp-lift', lang('Team Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($t['icon'], 'align-self-start mb-3'),
                $span($sports[$t['sport']]['title'], 'psp-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Sport')),
                $el('h3', 'psp-display h4 position-static mb-2', lang('Team Name'), array(
                    $link($t['title'], '{{page:team_' . $key . '}}', 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($t['summary'], 'small text-body-secondary mb-3'),
                $team_facts($key, 'list-unstyled small text-body-secondary mt-auto mb-0'),
            )),
        )),
    ), '6', '4');
};

// A player's card of the squad list and the home: the photo, the shirt
// number and the position, the name linking the player's page, the team
// and a line. Every value is bound to the Players form's field of the same
// name; the sample fills the canvas.
$player_card = function ($sample_key = 'kerem') use ($el, $para, $span, $link, $image, $col, $bind, $players, $teams, $positions, $portrait, $attr) {
    $p = $players[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden psp-lift', lang('Player Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($portrait($p['name'], 600), $p['name'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'photo', 'alt' => 'name'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $el('p', 'd-flex align-items-baseline gap-2 mb-1', lang('Number and Position'), array(
                    $span((string)$p['number'], 'psp-score h4 fw-bold text-primary mb-0', lang('Shirt Number'), $bind('shirt_number')),
                    $span($positions[$p['position']]['title'], 'psp-eyebrow small text-uppercase fw-semibold text-primary', lang('Playing Position'), $bind('position')),
                )),
                $el('h3', 'psp-display h4 position-static mb-1', lang('Player Name'), array(
                    $link($p['name'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'name', 'href' => 'form_item_view'))),
                )),
                $para($teams[$p['team']]['title'], 'small fw-semibold mb-2', $bind('team')),
                $para($p['summary'], 'small text-body-secondary mb-0', $bind('summary')),
            )),
        )),
    ), '6', '3');
};

// A match's row of the fixtures and results lists and the home: the date
// and the kick-off, the competition and the team, the two sides linking
// the match page, home or away, and once played the score and the
// outcome (both leave the row while the match has none). Every value is
// bound to the Matches form's field of the same name.
$match_row = function ($sample_key = 'next_first') use ($el, $span, $link, $icon, $bind, $matches, $teams, $competitions, $venues, $outcomes, $match_on, $match_title) {
    $m = $matches[$sample_key];
    $result = array(
        $span($m['score'] !== '' ? $m['score'] : '2–1', 'psp-score h4 fw-bold mb-0 pg-hide-if-empty', lang('Match Score'), $bind('score')),
    );
    $outcome = $m['outcome'] !== '' ? $outcomes[$m['outcome']] : $outcomes['win'];
    $result[] = $span($outcome['title'], 'badge rounded-pill ' . $outcome['badge'] . ' pg-hide-if-empty', lang('Outcome'), $bind('outcome'));
    return $el('article', 'd-flex flex-column flex-md-row align-items-md-center gap-3 py-3 border-bottom position-relative', lang('Match Row'), array(
        $el('div', 'flex-shrink-0 text-md-center', lang('Match Date'), array(
            $span($match_on($m['weekday'], $m['weeks']), 'd-block fw-bold text-primary', lang('Date'), $bind('match_date')),
            $span($m['kickoff'], 'd-block small text-body-secondary', lang('Kick-off'), $bind('kickoff')),
        )),
        $el('div', 'flex-grow-1', lang('Match Details'), array(
            $el('p', 'd-flex flex-wrap gap-2 small text-body-secondary mb-1', lang('Competition and Team'), array(
                $span($competitions[$m['competition']], 'fw-semibold', lang('Competition'), $bind('competition')),
                $span('·', '', lang('Separator')),
                $span($teams[$m['team']]['title'], '', lang('Team Name'), $bind('team')),
            )),
            $el('h3', 'psp-display h5 position-static mb-1', lang('Match Title'), array(
                $link($match_title($sample_key), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $el('p', 'd-inline-flex align-items-center gap-1 small text-body-secondary mb-0', lang('Venue'), array(
                $icon('bi-geo-alt', 'text-primary'),
                $span($venues[$m['venue']], '', lang('Home or Away'), $bind('venue')),
            )),
        )),
        $el('div', 'd-flex align-items-center gap-2 flex-shrink-0', lang('Result'), $result),
    ));
};

// A sponsor's tile (the sponsors page and the home): the sample badge, the
// name, the tier and what the sponsor does for the club.
$sponsor_tile = function ($key) use ($el, $para, $span, $image, $col, $attr, $portrait, $sponsors, $sponsor_tiers) {
    $s = $sponsors[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm text-center p-4', lang('Sponsor'), array(
            $image($portrait($s['name'], 160), $s['name'], 'rounded-circle mx-auto mb-3', array('width' => '80', 'height' => '80', '_attrs' => array($attr('loading', 'lazy')))),
            $span($sponsor_tiers[$s['tier']], 'psp-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Tier')),
            $el('h3', 'h6 fw-bold mb-2', lang('Sponsor Name'), array(), array('text' => $s['name'])),
            $para($s['text'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '4');
};

// A sponsorship package's card (the sponsors page): what it holds and a
// link that opens the enquiry with the package chosen.
$package_card = function ($key) use ($el, $heading, $para, $link, $col, $icon_tile, $sponsor_packages, $query) {
    $p = $sponsor_packages[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm psp-lift', lang('Package Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $icon_tile($p['icon'], 'align-self-start mb-3'),
                $heading('h3', $p['title'], 'psp-display h5 mb-2'),
                $para($p['text'], 'small text-body-secondary mb-4'),
                $link(lang('Ask about this package'), '{{page:sponsor_enquiry}}?' . $query(array('package' => $p['title'])), 'btn btn-outline-primary mt-auto'),
            )),
        )),
    ), '6', '3');
};

// A club event's card (the event list and the home): the kind, the title
// linking the event page, when and where, and a line. Every value is
// bound to the Club Events form's field of the same name.
$event_card = function ($sample_key = 'awards') use ($el, $para, $span, $link, $icon, $col, $bind, $events, $event_kinds) {
    $e = $events[$sample_key];
    $fact = function ($icon_name, $text, $token, $name) use ($el, $icon, $span, $bind) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-1', $name, array($icon($icon_name, 'text-primary'), $span($text, '', lang('Value'), $bind($token))));
    };
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm psp-lift', lang('Event Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_kinds[$e['kind']], 'psp-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Kind'), $bind('kind')),
                $el('h3', 'psp-display h5 position-static mb-2', lang('Event Title'), array(
                    $link($e['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para($e['summary'], 'small text-body-secondary mb-3', $bind('summary')),
                $el('div', 'mt-auto', lang('Event Facts'), array(
                    $fact('bi-calendar-event', $e['when'], 'event_when', lang('When')),
                    $fact('bi-geo-alt', $e['place'], 'place', lang('Place')),
                )),
            )),
        )),
    ), '6', '4');
};

// What a player, a parent or a fan says: the quote, the stars, the person
// and the team.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm p-4 mb-0', lang('Quote Card'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
            $el('figcaption', 'd-flex align-items-center gap-3 mt-auto', lang('Person'), array(
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

// ── Staff, newsletter and blog parts ────────────────────────────────────

// The staff pages' own menu, under the header: one shared component.
$staff_bar = function () use ($el, $link, $span) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center gap-4 py-2', lang('Staff Bar Container'), array(
            $span(lang('Staff'), 'badge rounded-pill text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline column-gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('staff_registrations', lang('Academy Registrations')),
                $item('staff_trials', lang('Trial Requests')),
                $item('staff_sponsors', lang('Sponsorship Enquiries')),
                $item('match_new', lang('New Match')),
                $item('player_new', lang('New Player')),
                $item('event_new', lang('New Club Event')),
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
    return $agree($id, 'consent', lang('I would like to receive the club\'s news, fixtures and match reports by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// registration may carry a medical form or a consent letter, so the
// default accepts PDFs, office files, text and pictures; one that takes
// only pictures is a collection's image kind (a player's photo).
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
                    $link(lang('Five things to bring to your first training session'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
