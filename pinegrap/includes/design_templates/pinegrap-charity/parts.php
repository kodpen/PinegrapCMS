<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Charity", part 1 of its files: the node builders, the
 * charity itself (the causes it works for, its campaigns with their goals
 * and progress, the gift amounts and what each pays for, its impact and
 * where the money goes) and the parts every page repeats (account bar,
 * header with the Donate button, footer, donate band, page shells, section
 * headers, cause tiles, campaign cards with their progress bars, gift
 * cards, form fields, comment thread, staff bar). The page files of the
 * same folder are included after it in the same scope and build their
 * trees with these variables; includes/design_templates/pinegrap-charity.php
 * puts them together.
 *
 * A gift is a product of the shop with the donation selection type: the
 * visitor types the amount, and the cart, checkout and receipt are the
 * shop's. A campaign is a record of the Campaigns collection whose figures
 * the staff update. Every figure is written here once: the cards, the
 * campaign samples and the donation products all read it.
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
// opening until the site owner puts its own in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-charity-' . $seed . '/' . (int)$width . '/' . (int)$height;
};

// ── The charity: causes, campaigns, gifts, impact ───────────────────────

// The causes the charity works for, in the order of the menu and the cause
// tiles. Each has its section on the our-work page (#cause-<key>) and the
// words a visitor may search for; a campaign names one of them.
$causes = array(
    'water'     => array('title' => lang('Clean Water'), 'icon' => 'bi-droplet',
        'text'   => lang('Wells, rain tanks and filters that bring safe water within a short walk of home.'),
        'topics' => lang('wells, drinking water, sanitation, hygiene, filters')),
    'education' => array('title' => lang('Education'), 'icon' => 'bi-book',
        'text'   => lang('Classrooms, books and trained teachers, so that every child can stay in school.'),
        'topics' => lang('schools, children, teachers, books, scholarships, literacy')),
    'food'      => array('title' => lang('Food and Farming'), 'icon' => 'bi-basket',
        'text'   => lang('School meals, seeds and training for families who grow their own food.'),
        'topics' => lang('hunger, nutrition, school meals, farming, seeds, harvest')),
    'health'    => array('title' => lang('Health'), 'icon' => 'bi-heart-pulse',
        'text'   => lang('Village clinics, health workers and care for mothers and newborns.'),
        'topics' => lang('clinics, medicine, mothers, babies, vaccines, health workers')),
    'relief'    => array('title' => lang('Emergency Relief'), 'icon' => 'bi-house-heart',
        'text'   => lang('Shelter, water and food in the first weeks after a flood, a storm or an earthquake.'),
        'topics' => lang('disaster, emergency, flood, earthquake, shelter, aid')),
);

// The campaigns: each is a sample record of the Campaigns collection and a
// donation product of the shop (SKU $campaign_sku). 'cause' is a key of the
// list above, 'goal' the amount it raises per currency in major units,
// 'percent' how much of it is raised (the staff update it on the record),
// 'featured' a campaign the home page puts first. Places and names are
// fictional.
$campaigns = array(
    'wells'     => array('title' => lang('Ten Wells for the Dry Valley'), 'cause' => 'water', 'place' => lang('The Dry Valley'),
        'featured' => true, 'percent' => 72,
        'summary'  => lang('Ten deep wells for villages that walk two hours a day for water.'),
        'story'    => lang('In the dry season the river bed of the valley is sand. Ten wells, each with a pump the village keeps itself, bring water to four thousand people all year round.'),
        'goal'     => array('USD' => 60000, 'EUR' => 56000, 'GBP' => 48000, 'TRY' => 2600000)),
    'classroom' => array('title' => lang('A Classroom for Every Grade'), 'cause' => 'education', 'place' => lang('Hill Country Schools'),
        'featured' => true, 'percent' => 45,
        'summary'  => lang('Four new classrooms, so that no grade learns under a tree.'),
        'story'    => lang('Three schools in the hills teach six grades in two rooms. Four classrooms with desks, books and a teacher\'s room let every grade have its own.'),
        'goal'     => array('USD' => 40000, 'EUR' => 37000, 'GBP' => 32000, 'TRY' => 1750000)),
    'meals'     => array('title' => lang('A Hot Meal at School'), 'cause' => 'food', 'place' => lang('River District'),
        'featured' => true, 'percent' => 88,
        'summary'  => lang('A warm lunch every school day for twelve hundred children.'),
        'story'    => lang('A child who is hungry cannot learn. The school kitchens cook with what the parents\' gardens grow, and a meal costs less than a bus ticket.'),
        'goal'     => array('USD' => 25000, 'EUR' => 23000, 'GBP' => 20000, 'TRY' => 1100000)),
    'seeds'     => array('title' => lang('Seeds and Tools for a Harvest'), 'cause' => 'food', 'place' => lang('The Plains'),
        'featured' => false, 'percent' => 30,
        'summary'  => lang('Seeds, tools and training for three hundred farming families.'),
        'story'    => lang('After two poor harvests, families ate the seed they had kept. Drought-hardy seed, tools and a season of training put them back on their own feet.'),
        'goal'     => array('USD' => 18000, 'EUR' => 17000, 'GBP' => 14000, 'TRY' => 790000)),
    'clinic'    => array('title' => lang('A Clinic Open Day and Night'), 'cause' => 'health', 'place' => lang('Lake Shore'),
        'featured' => false, 'percent' => 61,
        'summary'  => lang('Solar power, a delivery room and two nurses for the lake clinic.'),
        'story'    => lang('The only clinic on the lake shore closes at dark. Solar panels, a delivery room and two more nurses keep it open for the births that do not wait for morning.'),
        'goal'     => array('USD' => 50000, 'EUR' => 46000, 'GBP' => 40000, 'TRY' => 2200000)),
    'flood'     => array('title' => lang('Flood Relief Kits'), 'cause' => 'relief', 'place' => lang('Coastal Towns'),
        'featured' => false, 'percent' => 54,
        'summary'  => lang('Shelter, clean water and food for families who lost their homes to the flood.'),
        'story'    => lang('A kit holds a tarpaulin, a water filter, cooking pots and food for two weeks. Our teams on the coast hand them out with the town councils.'),
        'goal'     => array('USD' => 30000, 'EUR' => 28000, 'GBP' => 24000, 'TRY' => 1300000)),
);
$campaign_sku = function ($key) {
    return 'CH-' . strtoupper($key);
};
// The gift that goes where it is needed most: a product beside the
// campaigns' own.
$general_sku = 'CH-GENERAL';

// A price of the template as the shop writes it: the figure for the site's
// base currency, USD's figure for a currency not listed (the same choice
// _pg_tpl_catalog() makes for a product), with the base currency's symbol.
// A card and the product it stands for therefore show one amount.
$base_currency = defined('BASE_CURRENCY_CODE') ? strtoupper((string)BASE_CURRENCY_CODE) : 'USD';
$money = function (array $prices) use ($base_currency) {
    $amount = isset($prices[$base_currency]) ? $prices[$base_currency] : $prices['USD'];
    return pg_money_text($amount, defined('BASE_CURRENCY_SYMBOL') ? (string)BASE_CURRENCY_SYMBOL : '$');
};
// What a campaign has raised: its goal times its percent, whole units.
$raised = function (array $goal, $percent) {
    $out = array();
    foreach ($goal as $currency => $amount) $out[$currency] = (int)round($amount * $percent / 100);
    return $out;
};

// The gift amounts the donate page suggests, smallest first, and what each
// pays for. The amounts are the donation products' suggested prices; the
// visitor may type any other amount in the cart.
$gift_amounts = array(
    array('icon' => 'bi-journal-text', 'text' => lang('Books and a school bag for one child for a year.'),
        'price' => array('USD' => 25, 'EUR' => 25, 'GBP' => 20, 'TRY' => 1100)),
    array('icon' => 'bi-droplet', 'text' => lang('A water filter for a family of six.'),
        'price' => array('USD' => 50, 'EUR' => 50, 'GBP' => 40, 'TRY' => 2200)),
    array('icon' => 'bi-egg-fried', 'text' => lang('School lunches for ten children for a month.'),
        'price' => array('USD' => 100, 'EUR' => 100, 'GBP' => 80, 'TRY' => 4400)),
    array('icon' => 'bi-house-heart', 'text' => lang('Two flood relief kits for families who lost their homes.'),
        'price' => array('USD' => 250, 'EUR' => 250, 'GBP' => 200, 'TRY' => 11000)),
);

// What the charity has done, as figures for the home and impact pages:
// sample figures the site owner changes to its own.
$impact = array(
    array('figure' => '48,000', 'text' => lang('people with safe water close to home')),
    array('figure' => '6,500', 'text' => lang('children back in school')),
    array('figure' => '120', 'text' => lang('villages we work with')),
    array('figure' => '900', 'text' => lang('volunteers who give their time')),
);

// Where each gift goes, in percent of what the charity spends; the
// transparency page draws it as bars. The parts add up to 100.
$spending = array(
    array('title' => lang('Programmes in the field'), 'percent' => 84, 'text' => lang('Wells, classrooms, meals, clinics and relief, and the teams who run them.')),
    array('title' => lang('Raising funds'), 'percent' => 10, 'text' => lang('Campaigns, events and the care of our donors.')),
    array('title' => lang('Running the charity'), 'percent' => 6, 'text' => lang('Offices, audits, insurance and the systems that keep gifts safe.')),
);

// What every donor can count on, on the home and donate pages: sample
// terms the site owner changes to its own.
$donor_promises = array(
    array('icon' => 'bi-shield-check', 'title' => lang('Safe giving'), 'text' => lang('Your gift is paid through the site\'s secure checkout.')),
    array('icon' => 'bi-pie-chart', 'title' => lang('84% of every gift'), 'text' => lang('go to the programmes in the field; see where the rest goes.')),
    array('icon' => 'bi-receipt', 'title' => lang('A receipt for every gift'), 'text' => lang('Sent by e-mail as soon as your gift arrives.')),
    array('icon' => 'bi-geo-alt', 'title' => lang('News from the field'), 'text' => lang('Hear what your gift built, from the people who built it.')),
);

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the campaign that needs help most. A shared
// component.
$notice = function () use ($el, $icon, $span, $link) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-heart-fill', 'text-primary'),
        $span(lang('Every gift comes with a report on what it built.'), '', lang('Text')),
        $link(lang('See our campaigns'), '{{page:campaigns}}', 'link-primary fw-semibold text-nowrap'),
    ));
};

// The bar above the header: the notice on the left, the language switcher,
// the login region and the cart (with the shop) on the right. Every page
// places it as one shared component, so a change to it is made once.
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

// The header: the charity's name, the menu, Volunteer and the Donate
// button. The links to the current page are marked active on the server
// (smart active state), which is what lets one header serve every page.
// The menu names only pages that stand without the shop: the donate page
// stands on its own.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('about', lang('About Us')),
        array('our_work', lang('Our Work')),
        array('campaigns', lang('Campaigns')),
        array('events', lang('Events')),
        array('blog', lang('News')),
        array('transparency', lang('Transparency')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom sticky-top', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-2', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $icon('bi-heart-fill', 'fs-4 text-primary'),
                    $span('{{site_name}}', 'fs-5 pch-brand', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#ch-nav'),
                    $attr('aria-controls', 'ch-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav mx-xl-auto column-gap-xl-1 pt-3 pt-xl-0 pb-3 pb-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $link(lang('Volunteer'), '{{page:volunteer}}', 'btn btn-outline-primary btn-sm rounded-pill px-3 text-nowrap'),
                        $el('a', 'btn btn-primary btn-sm rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-3 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-heart'),
                            $span(lang('Donate'), '', lang('Link Text')),
                        ), array('href' => '{{page:donate}}')),
                    )),
                ), array('id' => 'ch-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the charity in a sentence, the causes, ways
// to help and the charity itself. Every link names a page that stands
// without the shop.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $attr, $causes) {
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
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $cause_links = array();
    foreach ($causes as $key => $c) {
        $cause_links[] = $link($c['title'], '{{page:our_work}}#cause-' . $key, $plain);
    }

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Charity'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-bold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-heart-fill', 'text-primary'),
                        $span('{{site_name}}', 'pch-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('We work with villages and towns for clean water, schooling, food, health and help when disaster strikes, and we report on every gift.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-facebook', 'Facebook'),
                        $social('bi-youtube', 'YouTube'),
                        $social('bi-linkedin', 'LinkedIn'),
                    )),
                )),
                $column(lang('Our Work'), $cause_links),
                $column(lang('Get Involved'), array(
                    $link(lang('Donate'), '{{page:donate}}', $plain),
                    $link(lang('Volunteer'), '{{page:volunteer}}', $plain),
                    $link(lang('Events'), '{{page:events}}', $plain),
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                )),
                $column(lang('The Charity'), array(
                    $link(lang('About Us'), '{{page:about}}', $plain),
                    $link(lang('Transparency'), '{{page:transparency}}', $plain),
                    $link(lang('FAQ'), '{{page:faq}}', $plain),
                    $link(lang('Contact Us'), '{{page:contact}}', $plain),
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('A registered charity. Our accounts are audited and published every year.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The donate band: the invitation to give or to help. Shared, so every page
// that ends with it carries the same words.
$donate_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary-subtle pch-band', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Every gift reaches someone by name.'), 'h3 fw-bold mb-2'),
                    $para(lang('Give once, give to a campaign, or give your time: each one is put to work in the field.'), 'text-body-secondary mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Donate'), '{{page:donate}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                        $link(lang('Volunteer'), '{{page:volunteer}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A progress bar of a campaign: how much of its goal is raised. $value is a
// figure (a sample card) or '{{percent}}' (a record of the Campaigns form,
// filled as the page is drawn); the words beside it say the same for a
// screen reader.
$progress = function ($value, $label) use ($el, $attr) {
    return $el('progress', 'w-100 pch-progress', lang('Progress Bar'), array(), array('text' => $value . '%', '_attrs' => array(
        $attr('value', (string)$value), $attr('max', '100'), $attr('aria-label', $label),
    )));
};

// The address of the campaign list filtered to one cause, the way a cause's
// "See the campaigns" link opens it (the list reads ?cause=).
$campaigns_in = function ($cause_key) use ($causes) {
    return '{{page:campaigns}}?cause=' . rawurlencode($causes[$cause_key]['title']);
};

// A cause as a tile: its picture, icon, title and line, opening its section
// of the our-work page.
$cause_tile = function ($key) use ($el, $para, $link, $image, $icon, $col, $causes, $photo) {
    $c = $causes[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 rounded-4 overflow-hidden position-relative pch-lift', $c['title'], array(
            $el('div', 'ratio ratio-4x3 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('cause-' . $key, 800, 600), $c['title'], 'object-fit-cover'),
            )),
            $el('div', 'card-body p-3', lang('Card Body'), array(
                $el('h3', 'h6 fw-bold d-flex align-items-center gap-2 mb-1 position-static', lang('Title'), array(
                    $icon($c['icon'], 'text-primary'),
                    $link($c['title'], '{{page:our_work}}#cause-' . $key, 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($c['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '4', '', '6', '6');
};

// A campaign as a card: its picture, cause, title, line, progress and what
// is raised of its goal. The card is the sample shown before the campaign
// list's own records; it opens the campaigns of its cause, which stand
// without the shop.
$campaign_card = function ($key) use ($el, $para, $link, $image, $span, $col, $campaigns, $causes, $photo, $money, $raised, $progress, $campaigns_in) {
    $c = $campaigns[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative pch-lift', $c['title'], array(
            $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                $image($photo('campaign-' . $key, 800, 450), $c['title'], 'object-fit-cover'),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($causes[$c['cause']]['title'] . ' · ' . $c['place'], 'small text-body-secondary mb-1', lang('Cause')),
                $el('h3', 'h5 fw-bold mb-2 position-static', lang('Title'), array(
                    $link($c['title'], $campaigns_in($c['cause']), 'stretched-link link-body-emphasis text-decoration-none'),
                )),
                $para($c['summary'], 'small text-body-secondary mb-3'),
                $el('div', 'mt-auto', lang('Progress'), array(
                    $progress($c['percent'], $c['title']),
                    $el('p', 'd-flex justify-content-between small mt-2 mb-0', lang('Figures'), array(
                        $span($money($raised($c['goal'], $c['percent'])), 'fw-bold pch-figure', lang('Raised')),
                        $span($money($c['goal']), 'text-body-secondary pch-figure', lang('Goal')),
                    )),
                )),
            )),
        )),
    ), '6', '4');
};

// A suggested gift: its amount and what it pays for, opening the donate
// page (which stands without the shop).
$gift_card = function ($n) use ($el, $para, $link, $icon, $col, $gift_amounts, $money) {
    $g = $gift_amounts[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-body-tertiary rounded-4 position-relative pch-lift', lang('Gift Amount'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon($g['icon'], 'fs-3 text-primary d-block mb-2'),
                $el('h3', 'h4 fw-bold mb-2 position-static', lang('Amount'), array(
                    $link($money($g['price']), '{{page:donate}}', 'stretched-link link-body-emphasis text-decoration-none pch-figure'),
                )),
                $para($g['text'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '3', '', '6');
};

// The impact figures as a row of four.
$impact_row = function () use ($el, $para, $span, $row, $col, $impact) {
    $cols = array();
    foreach ($impact as $i) {
        $cols[] = $col(array(
            $el('div', 'text-center', lang('Figure'), array(
                $span($i['figure'], 'd-block display-6 fw-bold text-primary pch-figure', lang('Figure')),
                $para($i['text'], 'small text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// What every donor can count on, as a row of four.
$promise_row = function () use ($el, $heading, $para, $icon, $row, $col, $donor_promises) {
    $cols = array();
    foreach ($donor_promises as $p) {
        $cols[] = $col(array(
            $el('div', 'd-flex gap-3', $p['title'], array(
                $icon($p['icon'], 'fs-3 text-primary'),
                $el('div', '', lang('Text'), array(
                    $heading('h3', $p['title'], 'h6 fw-bold mb-1'),
                    $para($p['text'], 'small text-body-secondary mb-0'),
                )),
            )),
        ), '6', '3');
    }
    return $row($cols, '4');
};

// Where the money goes: a bar for each part of what the charity spends.
$spending_bars = function () use ($el, $heading, $para, $span, $progress, $spending) {
    $items = array();
    foreach ($spending as $s) {
        $items[] = $el('li', 'mb-4', $s['title'], array(
            $el('div', 'd-flex justify-content-between align-items-baseline gap-2 mb-1', lang('Title'), array(
                $heading('h3', $s['title'], 'h6 fw-bold mb-0'),
                $span($s['percent'] . '%', 'fw-bold text-primary pch-figure', lang('Percent')),
            )),
            $progress($s['percent'], $s['title']),
            $para($s['text'], 'small text-body-secondary mt-1 mb-0'),
        ));
    }
    return $el('ul', 'list-unstyled mb-0', lang('Spending'), $items);
};

// A page: the account bar, the header, the page's own content, the
// newsletter band (left off where it would be in the way: the forms, the
// account pages) and the shared footer.
$page = function ($main, $newsletter = true) use ($root, $shared, $el, $widget) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($newsletter) $children[] = $widget('newsletter_band');
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
            $heading('h1', $title, 'display-6 fw-semibold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'pch-eyebrow text-uppercase fw-semibold text-primary mb-1'),
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

// An icon in a soft round tile, the way the ways to help are marked.
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'ch-faq') use ($el, $para, $attr, $searchable) {
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

// The comments of a record page (a campaign's updates, a volunteer's or a
// pledge's messages, a conversation's replies, a post's comments), in
// the reading column: the page's Comments
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



// ── Staff, newsletter and News parts ────────────────────────────────────

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
                $item('campaign_new', lang('New Campaign')),
                $item('staff_volunteers', lang('All Volunteers')),
                $item('event_new', lang('New Event')),
                $item('blog_new', lang('New Post')),
                $item('report_new', lang('New Report')),
                $item('staff_entry', lang('Add to the Staff Directory')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no newsletter.
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
    return $agree($id, 'consent', lang('I would like to hear about our campaigns, events and news from the field by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. The
// volunteer form takes a CV or a certificate, so the default
// accepts PDFs and pictures; one that takes only pictures is a
// collection's image kind (a campaign's cover).
$file = function ($id, $name, $folder, $accept = '.pdf,.jpg,.jpeg,.png,.heic') use ($el, $attr) {
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

// A News post card of the lists (the News page and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind, $photo) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                $photo('post', 800, 450), lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('The first water flows in the Dry Valley'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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
