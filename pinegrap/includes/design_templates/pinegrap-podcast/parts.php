<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Podcast Network", part 1 of its files: the node
 * builders and the interactions the pages put on them, the network's own
 * data (the hosts, the categories, the sample podcasts, the sample episodes
 * dated back from the day the template opens with their chapters and
 * guests, the newest one's guest and a part of its transcript, the apps
 * that play them, the audience,
 * the sponsorship packages, the supporters' tiers and the answers of the
 * forms' pick lists) and the parts every page repeats (account bar,
 * header, footer, subscribe band, podcast, episode, host, tier and
 * sponsor cards, the chapter list, the player, page shells, section
 * headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-podcast.php puts them together.
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
// An interaction on a node (Interactions in the editor): the operator can
// change or remove it there, and the platform keeps the node still for a
// visitor who prefers reduced motion.
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// A row whose items rise one after another as it scrolls into view.
$rise = function ($node, $stagger = 100) use ($fx) {
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
// every opening until the network puts its own artwork in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-podcast-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: initials on the palette's colour. The initials follow
// the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/431407/ffedd5?text=' . rawurlencode($initials !== '' ? $initials : 'P');
};

// ── The network: hosts, podcasts, episodes, sponsors and supporters ─────

// The network itself. The about page tells its story; the footer, the
// sponsorship page and the contact page use the addresses.
$network = array(
    'tagline'     => lang('Independent podcasts about the way we live, work and listen, made by a small studio'),
    'story'       => lang('The network began with two friends, one microphone and a kitchen table. Today it is a small studio making four podcasts with a dozen hosts and producers, paid for by listeners who support it and by sponsors who fit the shows.'),
    'based'       => lang('Recorded in Istanbul, heard everywhere'),
    'hello'       => 'hello@example.com',
    'sponsorship' => 'sponsors@example.com',
    'press'       => 'press@example.com',
    'feed'        => lang('Every podcast has its own feed: follow it in any podcast app'),
);

// The hosts (the about page, the hosts page and the podcast cards). Page
// content, not a collection: a host changes rarely, and their page is
// edited in the designer like any other page.
$hosts = array(
    'selin' => array('name' => lang('Selin Arda'), 'role' => lang('Host of Kitchen Table Talks'),
        'bio' => lang('A former radio producer who asks the second question everyone else forgets.')),
    'omar'  => array('name' => lang('Omar Haddad'), 'role' => lang('Host of Signal and Noise'),
        'bio' => lang('Builds software by day and explains it to his grandmother by night.')),
    'lena'  => array('name' => lang('Lena Fischer'), 'role' => lang('Host of Small Ledger'),
        'bio' => lang('Ran a bakery for ten years before she started asking other owners how they do it.')),
    'baris' => array('name' => lang('Barış Tekin'), 'role' => lang('Host of Night Stories'),
        'bio' => lang('Collects true stories told after midnight and reads them in a very calm voice.')),
);

// The podcast categories, the podcasts list's filter. Each answer's value
// is its own words, so a filter link (?category=) reads as it shows.
$categories = array(
    'culture'  => array('icon' => 'bi-chat-quote', 'title' => lang('Culture and Conversation')),
    'tech'     => array('icon' => 'bi-cpu', 'title' => lang('Technology')),
    'business' => array('icon' => 'bi-briefcase', 'title' => lang('Small Business')),
    'stories'  => array('icon' => 'bi-moon-stars', 'title' => lang('Stories')),
);

// The sample podcasts: the records the Podcasts form starts with. 'host'
// names a host above; 'schedule' says in words when a new episode comes.
$podcasts = array(
    'kitchen' => array('title' => lang('Kitchen Table Talks'), 'category' => 'culture', 'host' => 'selin',
        'schedule' => lang('New episodes every Tuesday'),
        'summary'  => lang('Long conversations with people worth listening to, recorded at a kitchen table with tea.'),
        'featured' => true),
    'signal'  => array('title' => lang('Signal and Noise'), 'category' => 'tech', 'host' => 'omar',
        'schedule' => lang('New episodes every other Thursday'),
        'summary'  => lang('Technology explained without the hype: what it does, who it helps and what it costs.'),
        'featured' => true),
    'ledger'  => array('title' => lang('Small Ledger'), 'category' => 'business', 'host' => 'lena',
        'schedule' => lang('New episodes every Monday'),
        'summary'  => lang('Owners of small businesses on the numbers, the mistakes and the good days.'),
        'featured' => true),
    'night'   => array('title' => lang('Night Stories'), 'category' => 'stories', 'host' => 'baris',
        'schedule' => lang('New episodes on the first Friday of the month'),
        'summary'  => lang('True stories told by the people who lived them, for listening in the dark.'),
        'featured' => false),
);

// The day an episode came out, counted back from the day the template is
// opened so the newest episode is always recent: $days days before today.
// Each episode is a record of the Episodes form, edited or deleted like
// any other.
$aired_on = function ($days) {
    $day = new DateTime('today');
    if ($days > 0) $day->modify('-' . (int)$days . ' days');
    return $day->format('Y-m-d');
};

// The sample episodes, newest first: the podcast, the season and the
// number in it, the length in minutes, the guests (words, empty for none)
// and the chapters (where each starts, minutes:seconds, and its title).
// The newest also has its guest in full (the home's episode hero) and a
// part of its transcript (the listening page): the chapter it is from, by
// its number, and each turn's start, speaker (host or guest) and words.
$episodes = array(
    'kitchen-12' => array('podcast' => 'kitchen', 'season' => 2, 'number' => 12, 'days' => 2, 'minutes' => 58,
        'title' => lang('A Chef Who Cooks Only for Neighbours'), 'guests' => lang('Derya Aksoy, chef'),
        'summary' => lang('Derya closed her restaurant and opened her kitchen to the street. We talk about cooking for people you know by name.'),
        'chapters' => array(array('0:00', lang('Hello and welcome')), array('4:10', lang('Closing the restaurant')), array('21:45', lang('The first neighbour dinner')), array('47:30', lang('What she cooks at home'))),
        'guest' => array('name' => lang('Derya Aksoy'), 'role' => lang('Chef, cooks for her neighbours'),
            'quote' => lang('I know every plate\'s name before I cook it.')),
        'script' => array('chapter' => 1, 'turns' => array(
            array('4:12', 'host', lang('The restaurant was full most nights. Why close it?')),
            array('4:18', 'guest', lang('Because I stopped knowing who I was cooking for. Two hundred plates a night, and not one name.')),
            array('4:31', 'host', lang('And now?')),
            array('4:33', 'guest', lang('Now it is twelve people on a Thursday. I know who leaves the onions and whose daughter has just started school.')),
            array('4:52', 'host', lang('Does it pay the rent?')),
            array('4:55', 'guest', lang('Just. Ask me again next spring.')),
        )),
        'featured' => true),
    'signal-8' => array('podcast' => 'signal', 'season' => 1, 'number' => 8, 'days' => 5, 'minutes' => 42,
        'title' => lang('What Happens When You Press Send'), 'guests' => lang('Mert Uçar, network engineer'),
        'summary' => lang('One e-mail, followed from the keyboard to the inbox on the other side of the world.'),
        'chapters' => array(array('0:00', lang('Hello and welcome')), array('3:20', lang('Leaving the laptop')), array('18:05', lang('Under the sea')), array('35:40', lang('Arriving'))),
        'featured' => true),
    'ledger-20' => array('podcast' => 'ledger', 'season' => 3, 'number' => 20, 'days' => 8, 'minutes' => 36,
        'title' => lang('Pricing Without Fear'), 'guests' => lang('Ayla Demir, bookshop owner'),
        'summary' => lang('Ayla raised her prices twice in one year and kept every regular. Here is how she did the sums.'),
        'chapters' => array(array('0:00', lang('Hello and welcome')), array('2:50', lang('The first price rise')), array('16:30', lang('What the regulars said')), array('29:15', lang('Her rules for next time'))),
        'featured' => true),
    'night-6' => array('podcast' => 'night', 'season' => 1, 'number' => 6, 'days' => 13, 'minutes' => 31,
        'title' => lang('The Last Ferry of the Night'), 'guests' => '',
        'summary' => lang('A ferry captain remembers the night the fog came down and nobody wanted to go home.'),
        'chapters' => array(array('0:00', lang('The harbour')), array('9:40', lang('The fog')), array('24:10', lang('Morning'))),
        'featured' => false),
    'kitchen-11' => array('podcast' => 'kitchen', 'season' => 2, 'number' => 11, 'days' => 9, 'minutes' => 63,
        'title' => lang('Learning a Language at Seventy'), 'guests' => lang('Nurhan Kaya, retired teacher'),
        'summary' => lang('Nurhan started Japanese the week she retired. She tells us what gets easier with age and what does not.'),
        'chapters' => array(array('0:00', lang('Hello and welcome')), array('6:00', lang('The first lesson')), array('30:20', lang('A trip to Osaka')), array('55:10', lang('Advice for beginners'))),
        'featured' => false),
    'signal-7' => array('podcast' => 'signal', 'season' => 1, 'number' => 7, 'days' => 19, 'minutes' => 39,
        'title' => lang('Passwords, Explained to My Grandmother'), 'guests' => '',
        'summary' => lang('Why a long sentence beats a clever word, and what a password manager actually keeps.'),
        'chapters' => array(array('0:00', lang('Hello and welcome')), array('5:30', lang('Long beats clever')), array('22:10', lang('Password managers'))),
        'featured' => false),
    'ledger-19' => array('podcast' => 'ledger', 'season' => 3, 'number' => 19, 'days' => 15, 'minutes' => 44,
        'title' => lang('Hiring the First Person'), 'guests' => lang('Emre Yalın, café owner'),
        'summary' => lang('The day a one-person business becomes two: the job advert, the interview and the first month.'),
        'chapters' => array(array('0:00', lang('Hello and welcome')), array('4:45', lang('Writing the advert')), array('20:30', lang('The interview')), array('37:00', lang('The first month'))),
        'featured' => false),
    'kitchen-10' => array('podcast' => 'kitchen', 'season' => 1, 'number' => 10, 'days' => 16, 'minutes' => 52,
        'title' => lang('The Sound of a City at Dawn'), 'guests' => lang('Can Öztürk, sound recordist'),
        'summary' => lang('A sound recordist who gets up at four to catch the city before the traffic.'),
        'chapters' => array(array('0:00', lang('Hello and welcome')), array('8:20', lang('Four in the morning')), array('33:50', lang('His favourite recording'))),
        'featured' => false),
);

// An episode's place in its podcast in words, "Season 2, Episode 12".
$episode_number_text = function ($key) use ($episodes) {
    return sprintf(lang('Season %1$d, Episode %2$d'), $episodes[$key]['season'], $episodes[$key]['number']);
};
// An episode's length, "58 min".
$length_text = function ($key) use ($episodes) {
    return sprintf(lang('%d min'), $episodes[$key]['minutes']);
};
// A time in an episode, minutes:seconds, as a <time> whose datetime is its
// duration from the start ("4:10" is PT4M10S); the operator edits its
// words like a link's.
$at = function ($time, $class = '', $name = '') use ($el, $attr) {
    list($m, $s) = array_map('intval', explode(':', $time));
    return $el('time', $class, $name !== '' ? $name : lang('Start'), array(), array('text' => $time,
        '_attrs' => array($attr('datetime', 'PT' . $m . 'M' . $s . 'S'))));
};
// An episode's chapters as the value of its Chapters field: one line per
// chapter, where it starts and its title.
$chapters_text = function ($key) use ($episodes) {
    $lines = array();
    foreach ($episodes[$key]['chapters'] as $c) $lines[] = sprintf(lang('%1$s — %2$s'), $c[0], $c[1]);
    return implode("\n", $lines);
};

// The podcast apps the episodes play in (the listen page, the footer and
// a podcast's page). The addresses are placeholders the network replaces
// with its own podcasts' pages there.
$apps = array(
    array('icon' => 'bi-apple', 'name' => 'Apple Podcasts'),
    array('icon' => 'bi-spotify', 'name' => 'Spotify'),
    array('icon' => 'bi-youtube', 'name' => 'YouTube'),
    array('icon' => 'bi-rss', 'name' => lang('RSS Feed')),
);

// The network in four figures (the home and the sponsorship page). Round
// sample numbers the network replaces with its own.
$audience = array(
    array('value' => '4', 'label' => lang('Podcasts on the network')),
    array('value' => '180K', 'label' => lang('Monthly listens')),
    array('value' => '68%', 'label' => lang('Listeners aged 25 to 44')),
    array('value' => '1,200', 'label' => lang('Listeners who support us')),
);

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};

// The sponsorship packages (the sponsorship page): what a sponsor gets and
// the price from which it starts, per episode or per season. Words and a
// price, not products: a sponsorship is agreed after an enquiry. 'when' is
// how long and where it plays, 'at' where the placements drawing marks it.
$packages = array(
    'preroll' => array('title' => lang('Opening Mention'),
        'when'  => lang('30 seconds, before the episode starts'), 'at' => lang('Before the episode starts'),
        'text'  => lang('A thirty-second mention read by the host before the episode starts.'),
        'per'   => lang('per episode'),
        'price' => array('USD' => 250, 'EUR' => 230, 'GBP' => 200, 'TRY' => 9000),
        'perks' => array(lang('Read by the host in their own words'), lang('Link in the episode notes'), lang('Report of listens after thirty days'))),
    'midroll' => array('title' => lang('Mid-episode Story'),
        'when'  => lang('90 seconds, about halfway'), 'at' => lang('About halfway'),
        'text'  => lang('A ninety-second story in the middle of the episode, when listeners are most attentive.'),
        'per'   => lang('per episode'),
        'price' => array('USD' => 600, 'EUR' => 550, 'GBP' => 480, 'TRY' => 21000),
        'perks' => array(lang('Written with you, read by the host'), lang('Link and code in the episode notes'), lang('Report of listens after thirty days'))),
    'season'  => array('title' => lang('Season Partner'),
        'when'  => lang('Named in every episode'), 'at' => lang('In every episode of the season'),
        'text'  => lang('The only sponsor of a whole season of one podcast, named in every episode.'),
        'per'   => lang('per season'),
        'price' => array('USD' => 6000, 'EUR' => 5500, 'GBP' => 4800, 'TRY' => 210000),
        'perks' => array(lang('A mention in every episode of the season'), lang('Your name on the podcast\'s page'), lang('One episode made with you'), lang('Report of listens every month'))),
);

// What past sponsors say (the sponsorship page). The firms are made up.
$sponsor_quotes = array(
    array('podcast' => 'ledger', 'source' => lang('Harbour Accounting'),
        'quote' => lang('The host read our mention as if she used us herself. She did, it turned out.')),
    array('podcast' => 'signal', 'source' => lang('Northwind Hosting'),
        'quote' => lang('Fewer listeners than the big shows, and more of them became customers.')),
);

// The supporters' tiers: a membership the shop sells (with the shop),
// renewed every month, opening the supporters' area with its bonus
// episodes. The SKUs name the template, so the products are its own.
$tiers = array(
    'listener' => array('sku' => 'PODCAST-SUPPORT-LISTENER', 'icon' => 'bi-headphones', 'title' => lang('Listener'),
        'text'  => lang('Keeps the microphones on.'),
        'price' => array('USD' => 4, 'EUR' => 4, 'GBP' => 3, 'TRY' => 120),
        'perks' => array(lang('Every episode without sponsor mentions'), lang('The supporters\' newsletter'))),
    'friend'   => array('sku' => 'PODCAST-SUPPORT-FRIEND', 'icon' => 'bi-heart', 'title' => lang('Friend of the Network'),
        'text'  => lang('Pays for an episode a year.'),
        'price' => array('USD' => 8, 'EUR' => 8, 'GBP' => 7, 'TRY' => 250),
        'perks' => array(lang('Everything a Listener gets'), lang('Bonus episodes every month'), lang('Questions for the guests before we record'))),
    'patron'   => array('sku' => 'PODCAST-SUPPORT-PATRON', 'icon' => 'bi-star', 'title' => lang('Patron'),
        'text'  => lang('Keeps the studio running.'),
        'price' => array('USD' => 20, 'EUR' => 19, 'GBP' => 16, 'TRY' => 650),
        'perks' => array(lang('Everything a Friend gets'), lang('Your name in the episode notes'), lang('A seat at the live recording each season'))),
);
// A membership given as a gift (with the shop): one year of the Friend
// tier, as a gift card would.
$gift_memberships = array(
    'gift' => array('sku' => 'PODCAST-SUPPORT-GIFT', 'icon' => 'bi-gift', 'title' => lang('A Year of Support as a Gift'),
        'text'  => lang('Twelve months as a Friend of the Network, for someone who listens.'),
        'price' => array('USD' => 80, 'EUR' => 80, 'GBP' => 70, 'TRY' => 2500)),
);
// The days a tier's membership runs before it is renewed.
$membership_days = 30;

// The bonus episodes of the supporters' area: pages in the supporters'
// folder, each with its own player.
$bonus_episodes = array(
    'outtakes' => array('podcast' => 'kitchen', 'minutes' => 24, 'title' => lang('Outtakes from the Kitchen Table'),
        'summary' => lang('The best moments that did not fit the episodes of the season.')),
    'askus'    => array('podcast' => 'signal', 'minutes' => 33, 'title' => lang('You Asked: Supporters\' Questions'),
        'summary' => lang('Omar answers the questions supporters sent in this month.')),
    'live'     => array('podcast' => 'night', 'minutes' => 47, 'title' => lang('Night Stories, Live on Stage'),
        'summary' => lang('The live recording in full, with the stories that never reached the feed.')),
);

// The answers of the forms' pick lists. Each answer's value is its own
// words, so the e-mails and the lists read as the visitor chose.
$category_titles = array();
foreach ($categories as $c) $category_titles[] = $c['title'];
$podcast_titles = array();
foreach ($podcasts as $p) $podcast_titles[] = $p['title'];
$package_titles = array();
foreach ($packages as $p) $package_titles[] = $p['title'];
// What a sponsor wants from the sponsorship.
$sponsor_goals = array(lang('People hearing our name'), lang('Visits and sales'), lang('A new product launch'), lang('Supporting the podcast'), lang('Something else'));
// The budget bands in the shop's currency, as the package prices are written.
$budget_low = $money(array('USD' => 1000, 'EUR' => 920, 'GBP' => 800, 'TRY' => 35000));
$budget_high = $money(array('USD' => 5000, 'EUR' => 4600, 'GBP' => 4000, 'TRY' => 175000));
$budget_choices = array(
    sprintf(lang('Up to %s'), $budget_low),
    sprintf(lang('%1$s to %2$s'), $budget_low, $budget_high),
    sprintf(lang('More than %s'), $budget_high),
    lang('To be agreed'),
);
// The states of a sponsorship enquiry and of a guest pitch, set by the
// network's staff in the panel.
$enquiry_statuses = array(lang('Enquiry received'), lang('Proposal sent'), lang('Confirmed'), lang('Declined'));
$pitch_statuses = array(lang('Pitch received'), lang('Shortlisted'), lang('Invited'), lang('Not this time'));
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
);

// The query string of a link that opens a list with a filter set
// (?category=, ?podcast=) or a form with a choice made (?package=, ?podcast=):
// the form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content and the
// shared footer, with the subscribe band above the footer unless the page is a
// form or an account page.
$page = function ($main, $band = true) use ($root, $shared, $el) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($band) $children[] = $shared('subscribe_band');
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
            $heading('h1', $title, 'ppd-display display-6 mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'ppd-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'ppd-display h3 mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the why-us points are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what the listeners say.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'pd-faq') use ($el, $para, $attr, $searchable) {
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

// The line in the account bar: a new episode is out and where to hear it.
// A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex flex-wrap align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-broadcast', 'text-primary'),
        $span(lang('New episodes this week on every podcast'), '', lang('Text')),
        $link(lang('Listen now'), '{{page:episodes}}', 'link-primary fw-semibold'),
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
            $el('div', 'd-flex flex-wrap align-items-center justify-content-end column-gap-1 column-gap-sm-2 row-gap-1 ms-auto', lang('Account Links'), array(
                $lang_switcher('text-nowrap'), $widget('login_region'), $widget('cart_link'),
            )),
        )),
    ));
};

// The header: the network's name, the menu and the way to become a
// supporter. The links to the current page are marked active on the server
// (smart active state), so one header serves every page. The support page
// stands without the shop (it links the contact page then), so the header
// stands on a site without the shop. The menu opens out from 1200 px: six
// links and the button need about 1,100 px, more than the 960 px container
// between 992 and 1199 px, so there they stay behind the toggler.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('podcasts', lang('Podcasts')),
        array('episodes', lang('Episodes')),
        array('hosts', lang('Hosts')),
        array('listen', lang('How to Listen')),
        array('blog', lang('News')),
        array('sponsor', lang('Sponsorship')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container flex-xl-wrap', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2', lang('Brand'), array(
                    $icon('bi-mic', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'ppd-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#pd-nav'),
                    $attr('aria-controls', 'pd-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav ppd-nav ms-xl-auto me-xl-2 me-xxl-3 column-gap-xxl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 px-xl-3 px-xxl-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-heart'),
                            $span(lang('Support Us'), '', lang('Link Text')),
                        ), array('href' => '{{page:support}}')),
                    )),
                ), array('id' => 'pd-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The podcast apps as a row of round icon links (the footer, the listen
// page and a podcast's page). The addresses are placeholders.
$app_links = function ($class = 'd-flex flex-wrap gap-3') use ($el, $icon, $attr, $apps) {
    $links = array();
    foreach ($apps as $a) {
        $links[] = $el('a', 'link-body-emphasis fs-5', $a['name'], array($icon($a['icon'])), array('href' => '#', '_attrs' => array(
            $attr('aria-label', sprintf(lang('Listen on %s'), $a['name'])),
        )));
    }
    $links[] = $el('a', 'link-body-emphasis fs-5', 'Instagram', array($icon('bi-instagram')), array('href' => '#', '_attrs' => array($attr('aria-label', 'Instagram'))));
    return $el('div', $class, lang('Listening Links'), $links);
};

// The footer, on a dark band: the network in a sentence, the addresses
// for listeners, sponsors and the press, the apps, the podcasts by
// category, the listeners' pages and the network's pages; at the bottom
// the year, the site's name and where it records.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $categories, $network, $query, $attr, $app_links) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $category_links = array($link(lang('All Podcasts'), '{{page:podcasts}}', $plain));
    foreach ($categories as $c) $category_links[] = $link($c['title'], '{{page:podcasts}}?' . $query(array('category' => $c['title'])), $plain);
    $contact_line = function ($icon_name, $label, $address) use ($el, $icon, $span, $link, $plain) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $label, array(
            $icon($icon_name, 'text-primary'),
            $span($label, 'fw-semibold', lang('Label')),
            $link($address, 'mailto:' . $address, $plain . ' text-break'),
        ));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Network'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-mic', 'text-primary'),
                        $span('{{site_name}}', 'ppd-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($network['tagline'], 'small text-body-secondary mb-3'),
                    $el('div', '', lang('Contact Details'), array(
                        $contact_line('bi-envelope', lang('Listeners'), $network['hello']),
                        $contact_line('bi-megaphone', lang('Sponsorship'), $network['sponsorship']),
                        $contact_line('bi-newspaper', lang('Press'), $network['press']),
                    )),
                    $app_links('d-flex flex-wrap gap-3 mt-3'),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Podcast Links'), array(
                    $heading('h2', lang('Podcasts'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($category_links),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Listeners'), array(
                    $heading('h2', lang('For Listeners'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Latest Episodes'), '{{page:episodes}}', $plain),
                        $link(lang('How to Listen'), '{{page:listen}}', $plain),
                        $link(lang('Support Us'), '{{page:support}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Work With Us'), array(
                    $heading('h2', lang('Work With Us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Sponsorship'), '{{page:sponsor}}', $plain),
                        $link(lang('Sponsor an Episode'), '{{page:sponsor_enquiry}}', $plain),
                        $link(lang('Be a Guest'), '{{page:guest_pitch}}', $plain),
                        $link(lang('My Enquiries'), '{{page:my_enquiries}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('The Network'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('About Us'), '{{page:about}}', $plain),
                        $link(lang('Hosts'), '{{page:hosts}}', $plain),
                        $link(lang('News'), '{{page:blog}}', $plain),
                        $link(lang('Contact Us'), '{{page:contact}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para($network['based'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The subscribe band: a full-width band in the palette's colour with the
// way to follow the podcasts and the way to support them. Shared, so every
// page that ends with it carries the same call.
$subscribe_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Never miss an episode'), 'ppd-display display-6 mb-2'),
                    $para(lang('Follow the podcasts in your app, get the newsletter with each new episode, or support the network and hear the bonus episodes.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('How to Listen'), '{{page:listen}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('Support Us'), '{{page:support}}', 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A podcast's card of the lists (the home and the podcasts page): the
// cover, the category, the title linking the podcast's page, the host and
// when new episodes come. Every value is bound to the Podcasts form's
// field of the same name; the sample fills the canvas.
$podcast_card = function ($sample_key = 'kitchen') use ($el, $para, $span, $link, $image, $col, $bind, $podcasts, $categories, $hosts, $photo, $attr) {
    $p = $podcasts[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden ppd-lift', lang('Podcast Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('cover-' . $sample_key, 600, 600), $p['title'], 'w-100 h-100 object-fit-cover',
                    array('_bindings' => array('src' => 'cover', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($categories[$p['category']]['title'], 'ppd-eyebrow small text-uppercase fw-semibold text-primary mb-1', lang('Category'), $bind('category')),
                $el('h3', 'ppd-display h4 position-static mb-1', lang('Podcast Title'), array(
                    $link($p['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(sprintf(lang('Hosted by %s'), $hosts[$p['host']]['name']), 'small fw-semibold mb-2', $bind('hosted_by')),
                $para($p['schedule'], 'small text-body-secondary mt-auto mb-0', $bind('schedule')),
            )),
        )),
    ), '6', '3');
};

// Bindings that print fields of the podcast an episode points at (its
// Podcast field, a reference into the Podcasts form): $props maps an
// element prop to the podcast's field, e.g. array('text' => 'title').
$pd_podcast_ref = function ($props) {
    $bindings = array();
    $params = array();
    foreach ($props as $prop => $show) {
        $bindings[$prop] = '__ref';
        $params[$prop] = array('field' => 'podcast', 'page_id' => '{{tab:podcast_new}}', 'show' => $show);
    }
    return array('_bindings' => $bindings, '_bindParams' => $params);
};

// An episode's row of the lists (the home, the episodes page and a
// podcast's page): the cover and the title of its podcast (read through
// the reference), the day it came out, the season and number, the title
// linking the episode's page, its summary, its length and the way to
// listen. The rest is bound to the Episodes form's field of the same
// name.
$episode_row = function ($sample_key = 'kitchen-12') use ($el, $span, $para, $link, $image, $icon, $bind, $pd_podcast_ref, $episodes, $podcasts, $aired_on, $episode_number_text, $length_text, $photo, $attr) {
    $e = $episodes[$sample_key];
    return $el('article', 'd-flex flex-column flex-md-row gap-3 gap-md-4 py-4 border-bottom', lang('Episode Row'), array(
        $el('span', 'pg-field-value d-block flex-shrink-0 ppd-thumb rounded-3 overflow-hidden bg-body-tertiary', lang('Picture Frame'), array(
            $image($photo('cover-' . $e['podcast'], 240, 240), $podcasts[$e['podcast']]['title'], 'w-100 h-100 object-fit-cover',
                array_merge($pd_podcast_ref(array('src' => 'cover', 'alt' => 'title')), array('width' => '120', 'height' => '120', '_attrs' => array($attr('loading', 'lazy'))))),
        )),
        $el('div', 'flex-grow-1', lang('Episode Details'), array(
            $el('p', 'd-flex flex-wrap gap-2 small text-body-secondary mb-1', lang('Episode Facts'), array(
                $span($aired_on($e['days']), 'ppd-date fw-semibold text-primary', lang('Date'), $bind('aired')),
                $span($podcasts[$e['podcast']]['title'], 'fw-semibold', lang('Podcast'), $pd_podcast_ref(array('text' => 'title'))),
                $span($episode_number_text($sample_key), '', lang('Episode Number'), $bind('episode_number')),
            )),
            $el('h3', 'ppd-display h5 mb-2', lang('Episode Title'), array(
                $link($e['title'], '#', 'link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $para($e['summary'], 'small text-body-secondary mb-2', $bind('summary')),
            $el('div', 'd-flex flex-wrap align-items-center gap-3 small', lang('Listen'), array(
                $link(lang('Listen'), '#', 'btn btn-sm btn-primary rounded-pill px-3', array(), array('_bindings' => array('href' => 'form_item_view'))),
                $el('span', 'd-inline-flex align-items-center gap-1 text-body-secondary', lang('Length'), array(
                    $icon('bi-clock'),
                    $span($length_text($sample_key), '', lang('Value'), $bind('length')),
                )),
            )),
        )),
    ));
};

// The chapter list of an episode's page: the Chapters field, one line per
// chapter, kept in its lines by ppd-chapters.
$chapter_list = function ($sample_key = 'kitchen-12') use ($el, $heading, $chapters_text, $bind) {
    return $el('section', 'pg-hide-if-empty mb-4', lang('Chapters'), array(
        $heading('h2', lang('Chapters'), 'ppd-display h4 mb-3'),
        $el('div', 'pg-field-value ppd-chapters', lang('Chapters'), array(), array_merge(array('text' => $chapters_text($sample_key)), $bind('chapters'))),
    ));
};

// A player for a page's own recording (a bonus episode of the supporters'
// area): the browser's audio player, which loads nothing until it is
// pressed. The network puts the recording's file in place in the designer;
// until then the player stands without a source and the words under it
// say so.
$player = function ($title) use ($el, $para, $icon, $span, $attr) {
    return $el('div', 'ppd-wave p-4 rounded-4 bg-body-tertiary mb-4', lang('Player'), array(
        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-3', lang('Player Title'), array(
            $icon('bi-soundwave', 'text-primary fs-4'),
            $span($title, '', lang('Episode Title')),
        )),
        $el('audio', 'w-100', lang('Audio'), array(
            $para(lang('Your browser cannot play this recording.'), 'small mb-0'),
        ), array('_attrs' => array(
            $attr('controls', ''), $attr('preload', 'none'), $attr('title', $title),
        ))),
    ));
};

// The episode record's own player: the browser's audio player with the
// record's Audio file as its address (a src binding), loading nothing until
// it is pressed. A record without a file has no address, so the box goes
// (pg-hide-if-empty, judged by the player) and the Listen buttons under it
// stay the way to hear the episode.
$episode_player = function ($title) use ($el, $para, $icon, $span, $attr, $bind) {
    return $el('div', 'pg-hide-if-empty ppd-wave p-4 rounded-4 bg-body-tertiary mb-4', lang('Player'), array(
        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-3', lang('Player Title'), array(
            $icon('bi-soundwave', 'text-primary fs-4'),
            $span($title, '', lang('Episode Title'), $bind('title')),
        )),
        $el('audio', 'pg-field-value w-100', lang('Audio'), array(
            $para(lang('Your browser cannot play this recording.'), 'small mb-0'),
        ), array('_bindings' => array('src' => 'audio'), '_attrs' => array(
            $attr('controls', ''), $attr('preload', 'none'),
        ))),
    ));
};

// The way to hear an episode record: its audio file (the Audio field) and
// the address of the episode in a podcast app (the Listening address), each
// shown only when the record has it. The file opens in the browser's own
// player.
$listen_buttons = function () use ($el, $link, $attr) {
    return $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Listen'), array(
        $link(lang('Play the Episode'), '#', 'btn btn-primary rounded-pill px-4', array($attr('rel', 'noopener')),
            array('target' => '_blank', '_bindings' => array('href' => 'audio', 'show_if' => 'audio'))),
        $link(lang('Open in a Podcast App'), '#', 'btn btn-outline-primary rounded-pill px-4', array($attr('rel', 'noopener')),
            array('target' => '_blank', '_bindings' => array('href' => 'listen_url', 'show_if' => 'listen_url'))),
    ));
};

// A host's card (the about and hosts pages): the portrait, the name, the
// podcast they host and a line about them. Page content.
$host_card = function ($key) use ($el, $heading, $para, $image, $col, $attr, $portrait, $hosts) {
    $h = $hosts[$key];
    return $col(array(
        $el('article', 'text-center', lang('Host Card'), array(
            $image($portrait($h['name'], 320), $h['name'], 'rounded-circle mb-3', array('width' => '160', 'height' => '160', '_attrs' => array($attr('loading', 'lazy')))),
            $heading('h3', $h['name'], 'ppd-display h5 mb-1'),
            $para($h['role'], 'small fw-semibold text-primary mb-2'),
            $para($h['bio'], 'small text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// A supporters' tier card: what it costs each month and what it gives. The
// button is the page's own: the shop's product with the shop, the contact
// page without it ($button).
$tier_card = function ($key, $button) use ($el, $heading, $para, $span, $col, $icon_tile, $check_list, $tiers, $money) {
    $t = $tiers[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 p-4 text-center ppd-lift', lang('Tier Card'), array(
            $icon_tile($t['icon'], 'align-self-center mb-3'),
            $heading('h3', $t['title'], 'ppd-display h4 mb-1'),
            $para($t['text'], 'text-body-secondary mb-3'),
            $el('p', 'mb-3', lang('Price'), array(
                $span($money($t['price']), 'ppd-price display-6 fw-bold', lang('Value')),
                $span(lang('a month'), 'small text-body-secondary ms-1', lang('Label')),
            )),
            $check_list($t['perks'], 'list-unstyled small text-start mb-4'),
            $button,
        )),
    ), '', '4');
};

// What a past sponsor says: the quote, the firm and the podcast it
// sponsored.
$sponsor_quote_card = function ($n) use ($el, $para, $span, $col, $sponsor_quotes, $podcasts) {
    $q = $sponsor_quotes[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Sponsor Quote'), array(
            $el('blockquote', 'mb-4', lang('Quote'), array($para($q['quote'], 'ppd-quote mb-0'))),
            $el('figcaption', 'mt-auto', lang('Source'), array(
                $span($q['source'], 'd-block fw-semibold', lang('Name')),
                $span($podcasts[$q['podcast']]['title'], 'd-block small text-body-secondary', lang('Podcast')),
            )),
        )),
    ), '', '6');
};

// One of the network's figures (the home and the sponsorship page).
$figure_tile = function ($n) use ($el, $span, $col, $audience) {
    $f = $audience[$n];
    return $col(array(
        $el('div', 'text-center', lang('Audience Figure'), array(
            $span($f['value'], 'ppd-display d-block display-5 fw-bold text-primary', lang('Value')),
            $span($f['label'], 'd-block small text-body-secondary', lang('Label')),
        )),
    ), '3', '', '', '6');
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
                $item('episode_new', lang('New Episode')),
                $item('podcast_new', lang('New Podcast')),
                $item('staff_enquiries', lang('Sponsorship Enquiries')),
                $item('staff_pitches', lang('Guest Pitches')),
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
    return $agree($id, 'consent', lang('I would like to receive the network\'s new episodes and news by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A
// sponsorship enquiry or a guest pitch takes documents (a brief, a media
// kit) and pictures, so the default accepts PDFs, office files, text and
// pictures; one that takes only pictures is a collection's image kind (a
// podcast's cover); an episode's recording takes audio.
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
                    $link(lang('Behind the microphone: how an episode is made'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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


// ── Collection lists and record pages ───────────────────────────────────

// A filter row of a list's settings: the field, how it compares and the
// value, or where the value comes from (a URL parameter, left out while
// the parameter is not in the address; or today's date).
$pd_filter = function ($field, $operator, $value, $param = '') {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $param !== '' ? 'url parameter' : '');
    if ($param !== '') $row['dynamic_value_attribute'] = $param;
    return $row;
};
// A filter on the record the page shows: the episodes whose Podcast
// field points at the podcast of the page.
$pd_shown_filter = function ($field) {
    return array('field' => $field, 'operator' => 'is equal to', 'value' => '', 'dynamic_value' => 'shown record', 'dynamic_value_attribute' => '');
};
// A date filter against today: the episodes already out (on or before
// today), so one dated ahead waits for its day.
$pd_date_filter = function ($field, $operator) {
    return array('field' => $field, 'operator' => $operator, 'value' => '', 'dynamic_value' => 'current date');
};
// The answers of the Featured list: the first marks a record for the home.
$pd_featured_choices = array(lang('Yes'), lang('No'));

// A list's settings: the form, its record page, the order and the words
// shown when nothing matches.
$pd_list_config = function ($form_page, $detail_page, $order_field, $direction, $empty, $more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:' . $form_page . '}}',
        'detail_page_id'      => '{{tab:' . $detail_page . '}}',
        'order_by_field'      => $order_field,
        'order_by_direction'  => $direction,
        'empty_message'       => $empty,
    ), $more);
};

// The links above a list that narrow it: one per answer of a pick list,
// each opening the list with the filter set.
$pd_filter_bar = function ($page_key, $label, $param, $values) use ($el, $link, $span, $query) {
    $children = array($span($label, 'small fw-semibold text-body-secondary me-1', lang('Label')));
    $children[] = $link(lang('All'), '{{page:' . $page_key . '}}', 'btn btn-sm btn-outline-secondary rounded-pill');
    foreach ($values as $v) {
        $children[] = $link($v, '{{page:' . $page_key . '}}?' . $query(array($param => $v)), 'btn btn-sm btn-outline-secondary rounded-pill');
    }
    return $el('nav', 'd-flex flex-wrap align-items-center gap-2 mb-4', lang('Filters'), $children);
};

// The record pages' back link and Edit button, above the record.
$pd_toolbar = function ($page_key, $text) use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $page_key . '}}')),
        $edit_button(),
    ));
};
// What a record page shows when its record is not there.
$pd_not_found = function ($icon_name, $title, $text, $back_text, $back_page) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_page . '}}', 'btn btn-primary rounded-pill px-4'),
    ), $show_when('record_not_found'));
};
// A long field of a record page under its heading, its lines kept; the
// block leaves when the record has no value.
$pd_long_field = function ($title, $token, $sample, $class = 'mb-4') use ($el, $heading, $bind) {
    return $el('div', 'pg-hide-if-empty ' . $class, $title, array(
        $heading('h2', $title, 'ppd-display h4 mb-2'),
        $el('div', 'pg-field-value lh-lg', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// A link to an address the record holds, in a new window, shown only when
// the record has it (a podcast app, a sponsor's page).
$pd_record_link = function ($text, $token, $class) use ($link, $attr) {
    return $link($text, '#', $class, array($attr('rel', 'noopener')),
        array('target' => '_blank', '_bindings' => array('href' => $token, 'show_if' => $token)));
};
// A staff page holding a collection's form in a card.
$pd_form_page = function ($title, $lead, $widget_key) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return $staff_page(array(
        $page_header($title, $lead),
        $el('section', 'py-5', lang('Content'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 rounded-4 border', lang('Form Card'), array($widget($widget_key))),
                    ), '', '8'),
                ), '', 'center'),
            )),
        )),
    ));
};
