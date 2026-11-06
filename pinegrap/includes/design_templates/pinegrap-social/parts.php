<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Social Community", part 1 of its files: the node
 * builders, the community itself (its topics, the sample members and their
 * posts, the guidelines, how joining works) and the parts every page
 * repeats (account bar, header with the search, member bar, footer, join
 * band, page shells, section headers, avatar, post, profile and topic
 * cards, tag links, form fields, comment thread, staff bar). The page files
 * of the same folder are included after it in the same scope and build
 * their trees with these variables; includes/design_templates/pinegrap-social.php
 * puts them together.
 *
 * A post is a record of the Posts collection sent by a signed-in member, a
 * profile a record of the Profiles collection the member sends once and
 * edits; comments are the post page's Comments widget. Every sample is
 * written here once: the cards and the collections' samples all read it.
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

// A sample photo with a fixed seed, so the same picture comes back on every
// opening until the members put their own in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-social-' . $seed . '/' . (int)$width . '/' . (int)$height;
};

// ── The community: topics, members, posts ───────────────────────────────

// The topics, in the order of the menu and the topic tiles. A post and a
// profile name one of them by its title, which is what the explore page's
// ?topic= filter and the people list's filter read.
$topics = array(
    'photo'    => array('title' => lang('Photography'), 'icon' => 'bi-camera',
        'text'   => lang('Light, streets and faces: pictures taken for the joy of it.'),
        'topics' => lang('photos, camera, street, portrait, film, light')),
    'food'     => array('title' => lang('Food and Cooking'), 'icon' => 'bi-egg-fried',
        'text'   => lang('What is on the table tonight, and how it got there.'),
        'topics' => lang('recipes, baking, breakfast, coffee, dinner, kitchen')),
    'travel'   => array('title' => lang('Travel'), 'icon' => 'bi-airplane',
        'text'   => lang('Near and far: the places members walked, rode and slept in.'),
        'topics' => lang('trips, cities, beaches, maps, holidays, trains')),
    'plants'   => array('title' => lang('Plants and Gardens'), 'icon' => 'bi-flower1',
        'text'   => lang('Balconies, allotments and the one plant that will not die.'),
        'topics' => lang('houseplants, gardening, flowers, balcony, seeds, allotment')),
    'pets'     => array('title' => lang('Pets'), 'icon' => 'bi-heart',
        'text'   => lang('Cats on keyboards, dogs on walks, and every other friend.'),
        'topics' => lang('cats, dogs, animals, puppies, kittens, walks')),
    'outdoors' => array('title' => lang('Outdoors'), 'icon' => 'bi-tree',
        'text'   => lang('Trails, waves and summits, and the view at the top.'),
        'topics' => lang('hiking, cycling, climbing, surfing, camping, nature')),
);

// The sample members. Each is a sample record of the Profiles collection
// ('name' is its display name); 'handle' is a sample user name for the
// sample records that ask for one, 'topic' a key of the list above. The
// people are fictional.
$members = array(
    'ayla'  => array('handle' => 'ayla.lens', 'name' => lang('Ayla Demir'), 'city' => lang('Izmir'), 'topic' => 'photo',
        'bio' => lang('Street pictures on an old film camera. Mornings by the harbour.')),
    'tom'   => array('handle' => 'tom_bakes', 'name' => lang('Tom Fletcher'), 'city' => lang('Leeds'), 'topic' => 'food',
        'bio' => lang('Weekend baker. Sourdough most Saturdays, cake when it fails.')),
    'mira'  => array('handle' => 'mira.roams', 'name' => lang('Mira Kovač'), 'city' => lang('Zagreb'), 'topic' => 'travel',
        'bio' => lang('Slow trips by train and ferry, with a notebook in every bag.')),
    'jonas' => array('handle' => 'jonas.green', 'name' => lang('Jonas Lind'), 'city' => lang('Gothenburg'), 'topic' => 'plants',
        'bio' => lang('Forty houseplants in a two-room flat, and room for more.')),
    'sofia' => array('handle' => 'sofia_and_pip', 'name' => lang('Sofia Reyes'), 'city' => lang('Seville'), 'topic' => 'pets',
        'bio' => lang('Pip is a rescue greyhound. I mostly hold the lead.')),
    'kai'   => array('handle' => 'kai.trails', 'name' => lang('Kai Moreno'), 'city' => lang('Denver'), 'topic' => 'outdoors',
        'bio' => lang('Trail runs before work, mountains every other weekend.')),
    'lena'  => array('handle' => 'lena.frames', 'name' => lang('Lena Hoffmann'), 'city' => lang('Leipzig'), 'topic' => 'photo',
        'bio' => lang('Window light, still lifes and the odd self-portrait.')),
    'emre'  => array('handle' => 'emre.cooks', 'name' => lang('Emre Yıldız'), 'city' => lang('Ankara'), 'topic' => 'food',
        'bio' => lang('Home cooking from my grandmother\'s notebook, one page a week.')),
);

// The sample posts, two for each member, newest first. 'tags' are the
// words of the post's tags field (the tag page reads ?tag= and lists the
// posts whose tags contain it), 'alt' the picture described for a screen
// reader, 'comments' the count the sample card shows.
$social_posts = array(
    'ayla' => array(
        array('caption' => lang('Fishermen mending nets before the sun was up.'), 'alt' => lang('Two fishermen mending a net on a quay at dawn'),
            'place' => lang('Izmir'), 'tags' => array('harbour', 'film', 'morning'), 'comments' => 12),
        array('caption' => lang('The ferry at blue hour, from the last bench on the pier.'), 'alt' => lang('A ferry crossing a bay under a deep blue sky'),
            'place' => lang('Izmir'), 'tags' => array('harbour', 'bluehour'), 'comments' => 7),
    ),
    'tom' => array(
        array('caption' => lang('Third loaf this month and the crumb is finally open.'), 'alt' => lang('A sliced sourdough loaf on a wooden board'),
            'place' => lang('Leeds'), 'tags' => array('sourdough', 'baking'), 'comments' => 21),
        array('caption' => lang('Lemon cake for the neighbours. One slice survived the photo.'), 'alt' => lang('A lemon cake with one slice cut on a plate'),
            'place' => lang('Leeds'), 'tags' => array('baking', 'cake'), 'comments' => 9),
    ),
    'mira' => array(
        array('caption' => lang('Eleven hours on the night train, worth every minute of this view.'), 'alt' => lang('Mountains seen through a train window in the early light'),
            'place' => lang('Ljubljana'), 'tags' => array('train', 'slowtravel'), 'comments' => 15),
        array('caption' => lang('An island with one café, one bakery and a hundred cats.'), 'alt' => lang('Stone houses by a small harbour on an island'),
            'place' => lang('Vis'), 'tags' => array('islands', 'slowtravel'), 'comments' => 6),
    ),
    'jonas' => array(
        array('caption' => lang('The monstera gave us a new leaf. The whole flat celebrated.'), 'alt' => lang('A monstera plant with a new unfurling leaf by a window'),
            'place' => lang('Gothenburg'), 'tags' => array('houseplants', 'monstera'), 'comments' => 18),
        array('caption' => lang('Seedlings on the windowsill: tomatoes, basil and hope.'), 'alt' => lang('Small seedlings in pots lined up on a windowsill'),
            'place' => lang('Gothenburg'), 'tags' => array('seeds', 'balcony'), 'comments' => 4),
    ),
    'sofia' => array(
        array('caption' => lang('Pip found the only patch of sun in the whole park.'), 'alt' => lang('A grey greyhound lying in a patch of sunlight on grass'),
            'place' => lang('Seville'), 'tags' => array('greyhound', 'dogs'), 'comments' => 26),
        array('caption' => lang('Rainy day plans: blanket, sofa, no walk.'), 'alt' => lang('A greyhound curled up under a blanket on a sofa'),
            'place' => lang('Seville'), 'tags' => array('greyhound', 'rainyday'), 'comments' => 11),
    ),
    'kai' => array(
        array('caption' => lang('Above the clouds by seven, back at my desk by ten.'), 'alt' => lang('A trail runner on a ridge above a sea of clouds'),
            'place' => lang('Colorado'), 'tags' => array('trailrunning', 'mountains'), 'comments' => 14),
        array('caption' => lang('Camp for the night, and the quietest lake I know.'), 'alt' => lang('A small tent by a still mountain lake at dusk'),
            'place' => lang('Colorado'), 'tags' => array('camping', 'mountains'), 'comments' => 8),
    ),
    'lena' => array(
        array('caption' => lang('Pears and a jug in the kitchen window, nothing more.'), 'alt' => lang('Two pears and a white jug on a table in window light'),
            'place' => lang('Leipzig'), 'tags' => array('stilllife', 'windowlight'), 'comments' => 10),
        array('caption' => lang('Self-portrait with the camera I saved for all winter.'), 'alt' => lang('A woman holding a camera in front of her face by a window'),
            'place' => lang('Leipzig'), 'tags' => array('selfportrait', 'film'), 'comments' => 5),
    ),
    'emre' => array(
        array('caption' => lang('Grandmother\'s stuffed peppers, page twelve of her notebook.'), 'alt' => lang('Stuffed peppers in a copper pan on a stove'),
            'place' => lang('Ankara'), 'tags' => array('homecooking', 'recipes'), 'comments' => 19),
        array('caption' => lang('Sunday breakfast for six, and no one left hungry.'), 'alt' => lang('A breakfast table with bread, cheese, olives and tea'),
            'place' => lang('Ankara'), 'tags' => array('breakfast', 'homecooking'), 'comments' => 13),
    ),
);

// The community guidelines, in the order the guidelines page and the
// new-post form tell them: sample rules the site owner changes to its own.
$community_rules = array(
    array('icon' => 'bi-camera', 'title' => lang('Share what is yours'), 'text' => lang('Post photos you took or have the right to share, and say who made them when it was not you.')),
    array('icon' => 'bi-chat-heart', 'title' => lang('Be kind in the comments'), 'text' => lang('Disagree with an idea, never attack a person. No insults, threats or hate.')),
    array('icon' => 'bi-shield-check', 'title' => lang('Keep people safe'), 'text' => lang('No private details of others, and ask before you post a picture of someone.')),
    array('icon' => 'bi-eye', 'title' => lang('Describe your pictures'), 'text' => lang('A line of alt text lets a member who cannot see the photo enjoy it too.')),
    array('icon' => 'bi-flag', 'title' => lang('Report, do not argue'), 'text' => lang('If a post breaks these rules, report it: a moderator reads every report.')),
);

// How joining works, in the order the home page and the help page tell it.
$social_steps = array(
    array(lang('Create your account'), lang('Sign up with your e-mail address; it takes a minute.')),
    array(lang('Set up your profile'), lang('Add a photo, a few words about you and the topic you love most.')),
    array(lang('Share your first post'), lang('Upload a photo, write a caption and add a few tags.')),
    array(lang('Join the conversation'), lang('Comment on posts, save the ones you love and find people near you.')),
);

// The address of a tag's page, the explore list filtered to a topic, and
// the people list searched for a sample member's name: what a tag, a topic
// tile and a name open. The member page filters ?member= on the author
// name of a record, which a fictional sample member never is, so a static
// card opens the people list, where the member's sample profile record
// links the member page with its real author name.
$tag_url = function ($tag) {
    return '{{page:tag}}?tag=' . rawurlencode($tag);
};
$topic_url = function ($key) use ($topics) {
    return '{{page:explore}}?topic=' . rawurlencode($topics[$key]['title']);
};
$member_url = function ($key) use ($members) {
    return '{{page:people}}?query=' . rawurlencode($members[$key]['name']);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: what the community is. A shared component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-camera', 'text-primary'),
        $span(lang('Share your photos, find your people.'), '', lang('Text')),
        $link(lang('Community Guidelines'), '{{page:guidelines}}', 'link-primary fw-semibold text-nowrap'),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once.
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

// The search box of the header: the site search, which reads ?query= and
// finds posts, people and pages alike.
$search_box = function ($id, $class = '') use ($el, $attr, $icon) {
    return $el('form', $class, lang('Search Form'), array(
        $el('div', 'input-group', lang('Search Box'), array(
            $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Search the community'), '_attrs' => array($attr('for', $id)))),
            $el('input', 'form-control rounded-start-pill', lang('Field'), array(), array('id' => $id, '_attrs' => array(
                $attr('type', 'search'), $attr('name', 'query'), $attr('placeholder', lang('People, tags, places…')),
            ))),
            $el('button', 'btn btn-primary rounded-end-pill px-3', lang('Search Button'), array($icon('bi-search')), array('_attrs' => array(
                $attr('type', 'submit'), $attr('aria-label', lang('Search')),
            ))),
        )),
    ), array('_attrs' => array($attr('action', '{{page:search}}'), $attr('method', 'get'), $attr('role', 'search'))));
};

// The header: the community's name, the search, the menu and the New post
// button. The links to the current page are marked active on the server
// (smart active state), which is what lets one header serve every page.
$header = function () use ($el, $link, $icon, $span, $attr, $search_box) {
    $items = array();
    foreach (array(
        array('explore', lang('Explore')),
        array('topics', lang('Topics')),
        array('people', lang('People')),
        array('blog', lang('Community News')),
        array('help', lang('Help')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom sticky-top', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-2', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $icon('bi-camera', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'fs-5 psc-brand', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#sc-nav'),
                    $attr('aria-controls', 'sc-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $search_box('sc-search', 'flex-grow-1 mx-xl-3 my-3 my-xl-0 psc-search'),
                    $el('ul', 'navbar-nav column-gap-xl-1 pb-3 pb-xl-0 small', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 ms-xl-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary btn-sm rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-3 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-plus-square'),
                            $span(lang('Share a Photo'), '', lang('Link Text')),
                        ), array('href' => '{{page:post_new}}')),
                    )),
                ), array('id' => 'sc-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The member's own menu, under the header of the member pages: one shared
// component. Every page it names is a registration page.
$member_bar = function () use ($el, $link, $icon, $span, $attr) {
    $item = function ($key, $name, $text) use ($el, $link, $icon, $span) {
        return $el('a', 'nav-link px-0 d-inline-flex align-items-center gap-2', $text, array(
            $icon($name), $span($text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $key . '}}'));
    };
    return $el('div', 'border-bottom small bg-body', lang('Member Bar'), array(
        $el('nav', 'container nav nav-underline gap-4 py-2', lang('Member Links'), array(
            $item('my_account', 'bi-person-circle', lang('My Account')),
            $item('my_posts', 'bi-grid-3x3', lang('My Posts')),
            $item('saved', 'bi-bookmark', lang('Saved Posts')),
            $item('my_profile', 'bi-person-badge', lang('My Profile')),
            $item('my_reports', 'bi-flag', lang('My Reports')),
        ), array('smartActive' => true, '_attrs' => array($attr('aria-label', lang('Member navigation'))))),
    ));
};

// The footer, on a dark band: the community in a sentence, the topics, the
// community's pages and the company.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $attr, $topics, $topic_url) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $column = function ($title, $links) use ($el, $heading, $list) {
        return $el('div', 'col-6 col-md-3 col-lg-2', $title, array(
            $heading('h2', $title, 'h6 text-uppercase small fw-semibold mb-3'),
            $list($links),
        ));
    };
    $topic_links = array();
    foreach (array_keys($topics) as $key) {
        $topic_links[] = $link($topics[$key]['title'], $topic_url($key), $plain);
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Community'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-bold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-camera', 'text-primary'),
                        $span('{{site_name}}', 'psc-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('A friendly place to share photos, tell the story behind them and meet people who love the same things.'), 'small text-body-secondary mb-0'),
                )),
                $column(lang('Topics'), $topic_links),
                $column(lang('Community'), array(
                    $link(lang('Explore'), '{{page:explore}}', $plain),
                    $link(lang('People'), '{{page:people}}', $plain),
                    $link(lang('Community Guidelines'), '{{page:guidelines}}', $plain),
                    $link(lang('Community News'), '{{page:blog}}', $plain),
                )),
                $column(lang('Help'), array(
                    $link(lang('Help'), '{{page:help}}', $plain),
                    $link(lang('FAQ'), '{{page:faq}}', $plain),
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                    $link(lang('Contact Us'), '{{page:contact}}', $plain),
                )),
                $column(lang('Company'), array(
                    $link(lang('About Us'), '{{page:about}}', $plain),
                    $link(lang('Terms of Use'), '{{page:terms}}', $plain),
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Every photo belongs to the member who shared it.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The join band: the invitation to sign up. Shared, so every page that ends
// with it carries the same words.
$join_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary-subtle psc-band', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Have a photo worth sharing?'), 'h3 fw-bold mb-2'),
                    $para(lang('Join the community: post your pictures, tell their story and find people who love the same things.'), 'text-body-secondary mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Join Now'), '{{page:register}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                        $link(lang('Explore'), '{{page:explore}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A member's picture in a round frame. The name beside it says who it is,
// so the picture's alternative text is empty unless $alt is given.
$avatar = function ($member_key, $size = 'psc-avatar', $alt = '') use ($image, $photo) {
    return $image($photo('avatar-' . $member_key, 160, 160), $alt, 'rounded-circle object-fit-cover flex-shrink-0 ' . $size);
};

// The tags of a post as small links, each opening its tag's page.
$tag_links = function ($tags) use ($el, $link, $tag_url) {
    $children = array();
    foreach ($tags as $tag) {
        $children[] = $link('#' . $tag, $tag_url($tag), 'link-primary text-decoration-none');
    }
    return $el('p', 'd-flex flex-wrap gap-2 small mb-0', lang('Tags'), $children);
};

// The comment count of a post, an icon and the figure in words.
$comment_count = function ($count) use ($el, $icon, $span) {
    return $el('span', 'd-inline-flex align-items-center gap-1 small text-body-secondary', lang('Comment Count'), array(
        $icon('bi-chat'),
        $span(sprintf(lang('%d comments'), (int)$count), '', lang('Value')),
    ));
};

// A post as a square tile of the explore grid: the photo, the comment count
// over it on hover. The sample tile opens its first tag's page.
$post_tile = function ($member_key, $n) use ($el, $image, $link, $col, $social_posts, $photo, $tag_url, $comment_count) {
    $p = $social_posts[$member_key][$n];
    return $col(array(
        $el('article', 'position-relative rounded-3 overflow-hidden psc-tile', lang('Post Tile'), array(
            $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('post-' . $member_key . '-' . ($n + 1), 600, 600), $p['alt'], 'object-fit-cover'),
            )),
            $el('div', 'position-absolute bottom-0 start-0 end-0 p-2 psc-tile-info', lang('Post Info'), array(
                $comment_count($p['comments']),
            )),
            $link($p['caption'], $tag_url($p['tags'][0]), 'stretched-link visually-hidden-focusable'),
        )),
    ), '4', '3', '', '6', 'psc-tile-col');
};

// A post as a card of the feed: who posted it and where, the photo, the
// caption, its tags and comment count. The name opens the member's
// profile.
$post_card = function ($member_key, $n) use ($el, $heading, $para, $link, $image, $span, $icon, $members, $social_posts, $photo, $avatar, $member_url, $tag_links, $comment_count) {
    $m = $members[$member_key];
    $p = $social_posts[$member_key][$n];
    return $el('article', 'card border-0 shadow-sm rounded-4 overflow-hidden mb-4 psc-post', lang('Member Post'), array(
        $el('div', 'card-header bg-body border-0 d-flex align-items-center gap-2 p-3', lang('Post Author'), array(
            $avatar($member_key),
            $el('div', 'lh-sm', lang('Author'), array(
                $link($m['name'], $member_url($member_key), 'fw-semibold link-body-emphasis text-decoration-none'),
                $el('p', 'd-flex align-items-center gap-1 small text-body-secondary mb-0', lang('Place'), array(
                    $icon('bi-geo-alt'), $span($p['place'], '', lang('Value')),
                )),
            )),
        )),
        $el('div', 'ratio ratio-1x1 bg-body-tertiary', lang('Picture Frame'), array(
            $image($photo('post-' . $member_key . '-' . ($n + 1), 1080, 1080), $p['alt'], 'object-fit-cover'),
        )),
        $el('div', 'card-body p-3', lang('Card Body'), array(
            $para($p['caption'], 'mb-2'),
            $tag_links($p['tags']),
            $el('div', 'mt-2', lang('Post Footer'), array($comment_count($p['comments']))),
        )),
    ));
};

// A member as a card of the people list: picture, name, city, topic and
// the words of the profile; it opens the member in the people list.
$profile_card = function ($member_key) use ($el, $heading, $para, $link, $span, $icon, $col, $members, $topics, $avatar, $member_url) {
    $m = $members[$member_key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 position-relative psc-lift', $m['name'], array(
            $el('div', 'card-body p-4 text-center', lang('Card Body'), array(
                $avatar($member_key, 'psc-avatar-lg mb-3'),
                $el('h3', 'h6 fw-bold mb-2 position-static', lang('Title'), array(
                    $link($m['name'], $member_url($member_key), 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $el('p', 'd-flex flex-wrap justify-content-center align-items-center gap-2 small text-body-secondary mb-2', lang('Place and Topic'), array(
                    $icon('bi-geo-alt'),
                    $span($m['city'], '', lang('City')),
                    $span($topics[$m['topic']]['title'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis', lang('Topic')),
                )),
                $para($m['bio'], 'small mb-0'),
            )),
        )),
    ), '6', '3');
};

// A topic as a tile: its picture, icon, title and line, opening the explore
// list filtered to it.
$topic_tile = function ($key) use ($el, $para, $link, $image, $icon, $col, $topics, $photo, $topic_url) {
    $t = $topics[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 rounded-4 overflow-hidden position-relative psc-lift', $t['title'], array(
            $el('div', 'ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('topic-' . $key, 800, 600), $t['title'], 'object-fit-cover'),
            )),
            $el('div', 'card-body p-3', lang('Card Body'), array(
                $el('h3', 'h6 fw-bold d-flex align-items-center gap-2 mb-1 position-static', lang('Title'), array(
                    $icon($t['icon'], 'text-primary'),
                    $link($t['title'], $topic_url($key), 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($t['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '4', '', '6', '6');
};

// The guidelines as a list: an icon, a title and the words of each rule.
$rule_list = function () use ($el, $heading, $para, $icon, $community_rules) {
    $items = array();
    foreach ($community_rules as $r) {
        $items[] = $el('li', 'd-flex gap-3 mb-4', $r['title'], array(
            $icon($r['icon'], 'fs-4 text-primary'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $r['title'], 'h6 fw-bold mb-1'),
                $para($r['text'], 'text-body-secondary mb-0'),
            )),
        ));
    }
    return $el('ul', 'list-unstyled mb-0', lang('Guidelines'), $items);
};

// A page: the account bar, the header, the page's own content, the join
// band (left off where it would be in the way: the forms, the member
// pages) and the shared footer.
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

// A page of the member's own: the member bar under the header, no band.
$member_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('account_bar'),
        $shared('header'),
        $shared('member_bar'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
    ));
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
            $heading('h1', $title, 'display-6 fw-semibold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'psc-eyebrow text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'h3 fw-semibold mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the help topics are marked.
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
$faq = function ($n, $question, $answer, $keywords = '', $parent = 'sc-faq') use ($el, $para, $attr, $searchable) {
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
    ), $searchable($id . '-item', $keywords));
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

// The comments of a record page (a post's comments, a report's notes, a
// conversation's replies), in the reading column: the page's Comments
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



// ── Staff, newsletter and Community News parts ──────────────────────────

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
                $item('staff_posts', lang('All Member Posts')),
                $item('staff_reports', lang('All Reports')),
                $item('staff_members', lang('All Profiles')),
                $item('blog_new', lang('New Community News Post')),
                $item('staff_entry', lang('Add to the Staff Directory')),
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
    return $agree($id, 'consent', lang('I would like to hear about community news, photo challenges and new features by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. A post's
// photo and a profile's picture take only pictures (a collection's image
// kind); a report may add a screenshot or a PDF.
$file = function ($id, $name, $folder, $accept = 'image/*') use ($el, $attr) {
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

// A Community News post card of the lists (the blog and the home page).
// $post_card is a member's post; this is the site's own news.
$news_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden', lang('News Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('news', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('This month\'s photo challenge: windows'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'card-text small text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold text-primary', lang('Read More')),
            )),
        )),
    ), '6', '4');
};

// A date field in the format given (date()'s letters), in place of the
// site's own date format.
$bind_date = function ($token, $format) {
    return array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => $format));
};
// A link to a template page that sends values of the record or list row it
// is drawn in: $params maps the address name to the token it takes (the
// renderer's __page_link, filled per row in a list).
$page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};
