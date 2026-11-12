<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Web Designer Portfolio", part 1 of its files: the node
 * builders, the designer's own data (the kinds of project, services and
 * packages, the way of working, skills, tools, experience, clients, what
 * clients say, the sample case studies and the answers of the brief form's
 * pick lists) and the parts every page repeats (account bar, header,
 * footer, brief band, project, package and testimonial cards, page shells,
 * section headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-portfolio.php puts them together.
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

// A sample picture with a fixed seed, so the same picture comes back on
// every opening until the designer puts their own work in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-portfolio-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: the person's initials on the palette's colour. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e2e8f0/1e293b?text=' . rawurlencode($initials !== '' ? $initials : 'P');
};

// ── The designer: their work, services and the way they work ────────────

// The person behind the site. The about page tells their story; the
// footer, the brief band and the contact lines use the address and phone.
$designer = array(
    'name'      => lang('Ada Yılmaz'),
    'role'      => lang('Independent web designer'),
    'city'      => lang('Istanbul, working with clients everywhere'),
    'bio'       => lang('I design and build websites for small companies that want to look as good as the work they do: clear pages, quick to load and easy for them to keep up to date.'),
    'available' => lang('Taking on two new projects a month'),
    'email'     => 'hello@example.com',
    'phone'     => '+90 555 000 00 00',
);

// The kinds of project, in the order the work page filters them. Each
// answer's value is its own words, so a filter link (?category=) reads as
// it shows and the brief form's Project type list starts from it.
$categories = array(
    'website' => array('icon' => 'bi-window', 'title' => lang('Website'),
        'text' => lang('Company sites of five to fifteen pages that say what you do and bring in enquiries.')),
    'shop'    => array('icon' => 'bi-bag', 'title' => lang('Online shop'),
        'text' => lang('A shop with your products, payments and shipping, simple enough to run every day.')),
    'brand'   => array('icon' => 'bi-palette', 'title' => lang('Brand identity'),
        'text' => lang('A logo, colours and type that work on the screen, on paper and on a shop window.')),
    'app'     => array('icon' => 'bi-phone', 'title' => lang('App design'),
        'text' => lang('Screens and a clickable prototype for a mobile or web app, ready for the developers.')),
    'landing' => array('icon' => 'bi-rocket-takeoff', 'title' => lang('Landing page'),
        'text' => lang('One page for one launch, campaign or event, built to turn visitors into sign-ups.')),
);

// What the designer does, in the order the services page lists them. Each
// opens the brief with its kind of project chosen.
$services = array(
    'design'  => array('icon' => 'bi-vector-pen', 'title' => lang('Web design'), 'category' => 'website',
        'text'   => lang('Pages designed around what your visitors come for, tested on a phone before anything else.'),
        'points' => array(lang('Site map and page plan'), lang('Design for phone, tablet and desktop'),
                          lang('A clickable prototype to try before the build'), lang('Two rounds of changes included'))),
    'build'   => array('icon' => 'bi-code-slash', 'title' => lang('Website build'), 'category' => 'website',
        'text'   => lang('The design built on a content management system, so you change the words and pictures yourself.'),
        'points' => array(lang('Fast pages and clean code'), lang('Search engine basics set up'),
                          lang('Accessible to keyboard and screen reader users'), lang('An hour of training at the handover'))),
    'shop'    => array('icon' => 'bi-bag-check', 'title' => lang('Online shops'), 'category' => 'shop',
        'text'   => lang('Product pages that sell, a checkout without surprises and the shipping rules set up for you.'),
        'points' => array(lang('Catalogue, variants and stock'), lang('Payment and shipping set up'),
                          lang('Order e-mails in your brand'), lang('Your first products added with you'))),
    'brand'   => array('icon' => 'bi-palette2', 'title' => lang('Brand identity'), 'category' => 'brand',
        'text'   => lang('A look that people remember, with a short guide so everyone uses it the same way.'),
        'points' => array(lang('Logo in three versions'), lang('Colours and fonts'),
                          lang('Business card and social media pictures'), lang('A brand guide of a few pages'))),
    'care'    => array('icon' => 'bi-shield-check', 'title' => lang('Care plan'), 'category' => 'website',
        'text'   => lang('Updates, backups and small changes every month, for a site that stays fast and safe.'),
        'points' => array(lang('Updates and backups'), lang('An hour of changes every month'),
                          lang('A short report on visits and speed'), lang('Answer within one working day'))),
);

// The three packages of the services page and the home. 'price' is the
// words the cards show; the brief form's Package list starts from ?package=.
$packages = array(
    'starter'  => array('title' => lang('Starter'), 'price' => lang('From $2,400'), 'featured' => false,
        'text'   => lang('A one-page site or a landing page, for a new business or a single launch.'),
        'points' => array(lang('One long page with up to six sections'), lang('Contact form and map'),
                          lang('Ready in two to three weeks'))),
    'business' => array('title' => lang('Company Site'), 'price' => lang('From $5,800'), 'featured' => true,
        'text'   => lang('A company site with up to fifteen pages, a blog and the words written with you.'),
        'points' => array(lang('Up to fifteen pages and a blog'), lang('Copy editing and photo selection'),
                          lang('Search engine basics and analytics'), lang('Ready in five to seven weeks'))),
    'shop'     => array('title' => lang('Shop'), 'price' => lang('From $8,900'), 'featured' => false,
        'text'   => lang('An online shop with the catalogue, payments and shipping, and the company pages around it.'),
        'points' => array(lang('Up to 100 products added'), lang('Payment and shipping set up'),
                          lang('Order and stock e-mails'), lang('Ready in seven to ten weeks'))),
);

// The way of working, in order: what happens and how long it takes.
$process = array(
    array('icon' => 'bi-chat-dots', 'title' => lang('Discover'), 'time' => lang('Week 1'),
        'text' => lang('We talk about your business, your visitors and the sites you like; I send a proposal with a fixed price.')),
    array('icon' => 'bi-pencil', 'title' => lang('Design'), 'time' => lang('Weeks 2–3'),
        'text' => lang('You see the home page first, then the other pages, as a prototype you can click through on your phone.')),
    array('icon' => 'bi-code-square', 'title' => lang('Build'), 'time' => lang('Weeks 3–5'),
        'text' => lang('I build the site, add your words and pictures and test it on phones, tablets and screen readers.')),
    array('icon' => 'bi-rocket', 'title' => lang('Launch'), 'time' => lang('Week 6'),
        'text' => lang('The site goes live on your address; you get an hour of training and a month of free changes.')),
);

// The skills of the about page and the tools the case studies name.
$skills = array(lang('User research'), lang('Information architecture'), lang('Interface design'), lang('Responsive layout'),
                lang('Accessibility'), lang('Copy editing'), lang('Search engine basics'), lang('Brand identity'));
$tools = array('Figma', 'Adobe Illustrator', 'Adobe Photoshop', 'Pinegrap', 'HTML & CSS', 'Bootstrap');

// The experience of the about page, newest first.
$experience = array(
    array('years' => '2021 –', 'title' => lang('Independent web designer'), 'place' => lang('Own studio')),
    array('years' => '2017 – 2021', 'title' => lang('Senior designer'), 'place' => lang('A digital agency in Istanbul')),
    array('years' => '2014 – 2017', 'title' => lang('Interface designer'), 'place' => lang('An online travel company')),
);

// The designer in four figures (the home and about pages).
$figures = array(
    array('value' => '12', 'label' => lang('Years of designing for the web')),
    array('value' => '140+', 'label' => lang('Websites and shops launched')),
    array('value' => '9', 'label' => lang('Countries clients work from')),
    array('value' => '5.0/5', 'label' => lang('Client rating')),
);

// The clients whose names the home shows in a row (sample names).
$clients = array('Harbour Roasters', 'Atelier Nord', 'Bloom & Co.', 'Mova Fitness', 'Summit Days', 'Lumen Dental');

// What clients say, each about one of the sample projects.
$testimonials = array(
    array('project' => 'harbour', 'name' => lang('Mert Arslan'), 'role' => lang('Founder, Harbour Roasters'),
        'quote' => lang('Our online orders doubled in three months, and I can add a new coffee myself in two minutes.')),
    array('project' => 'atelier', 'name' => lang('Elif Koç'), 'role' => lang('Partner, Atelier Nord'),
        'quote' => lang('She understood our work better than we could explain it. The site finally looks like our buildings.')),
    array('project' => 'lumen', 'name' => lang('Dr. Can Öztürk'), 'role' => lang('Lumen Dental'),
        'quote' => lang('Patients book from their phones now, and the questions we answered every day are on the site.')),
);

// The sample case studies: the records the Projects form starts with. The
// work list, the case study page and the home read them; the home shows
// the first three. Each names a kind of project of the list above; 'year'
// is the number the lists sort on.
$projects = array(
    'harbour' => array('title' => lang('An online coffee shop for a local roaster'), 'category' => 'shop',
        'client' => 'Harbour Roasters', 'industry' => lang('Food and drink'), 'year' => 2026, 'role' => lang('Design, build and product photos'),
        'summary'   => lang('A shop that sells fresh beans by subscription and tells the story of every farm.'),
        'challenge' => lang('Orders came in by phone and messages; nobody knew which beans were still in stock.'),
        'approach'  => lang('Short product pages with the taste in three words, a subscription that can be paused and stock kept by the shop.'),
        'results'   => lang('Online orders doubled in three months; half of them are now subscriptions.'),
        'tools'     => 'Figma, Pinegrap', 'url' => 'https://example.com', 'featured' => true),
    'atelier' => array('title' => lang('A quiet website for an architecture studio'), 'category' => 'website',
        'client' => 'Atelier Nord', 'industry' => lang('Architecture'), 'year' => 2026, 'role' => lang('Design and build'),
        'summary'   => lang('Large pictures, few words and a project archive the partners update themselves.'),
        'challenge' => lang('The old site hid the buildings behind slides and loaded for ten seconds on a phone.'),
        'approach'  => lang('One picture per screen, a plain list of projects by year and pages that load in under a second.'),
        'results'   => lang('Three times as many enquiries through the site in the first half year.'),
        'tools'     => 'Figma, Adobe Photoshop, Pinegrap', 'url' => 'https://example.com', 'featured' => true),
    'bloom'   => array('title' => lang('A fresh brand for a flower shop'), 'category' => 'brand',
        'client' => 'Bloom & Co.', 'industry' => lang('Retail'), 'year' => 2025, 'role' => lang('Brand identity'),
        'summary'   => lang('A hand-drawn logo, a soft palette and wrapping paper customers photograph.'),
        'challenge' => lang('A second shop was opening, and the first one had no logo but its sign.'),
        'approach'  => lang('Three sketches from the shop\'s own flowers, one chosen, then colours, cards, bags and the sign.'),
        'results'   => lang('Both shops open with one look; the wrapping paper is now sold on its own.'),
        'tools'     => 'Adobe Illustrator', 'url' => '', 'featured' => false),
    'mova'    => array('title' => lang('Booking app screens for a fitness studio'), 'category' => 'app',
        'client' => 'Mova Fitness', 'industry' => lang('Sport and health'), 'year' => 2025, 'role' => lang('Research and interface design'),
        'summary'   => lang('A class timetable, booking in two taps and a waiting list members actually use.'),
        'challenge' => lang('Members booked through a chat group, and a third of the places stayed empty.'),
        'approach'  => lang('Five interviews with members, a prototype tested in the studio and screens handed to the developers.'),
        'results'   => lang('Classes are 85% full; the chat group is now only for news.'),
        'tools'     => 'Figma', 'url' => '', 'featured' => true),
    'summit'  => array('title' => lang('A landing page for a design conference'), 'category' => 'landing',
        'client' => 'Summit Days', 'industry' => lang('Events'), 'year' => 2025, 'role' => lang('Design, build and copy'),
        'summary'   => lang('One page with the speakers, the programme and the ticket button always in sight.'),
        'challenge' => lang('Tickets went on sale in four weeks and the programme was still changing.'),
        'approach'  => lang('A page the organisers could update every day, with the speakers added as they said yes.'),
        'results'   => lang('Sold out eleven days before the conference.'),
        'tools'     => 'Figma, Bootstrap, Pinegrap', 'url' => 'https://example.com', 'featured' => false),
    'lumen'   => array('title' => lang('A friendly website for a dental clinic'), 'category' => 'website',
        'client' => 'Lumen Dental', 'industry' => lang('Health'), 'year' => 2024, 'role' => lang('Design, build and copy editing'),
        'summary'   => lang('Treatments explained in plain words, prices shown and an appointment request on every page.'),
        'challenge' => lang('Patients were afraid to call and asked the same questions every day.'),
        'approach'  => lang('A page per treatment answering those questions, real photos of the team and a short request form.'),
        'results'   => lang('Half the appointments now start on the site; calls with questions fell by a third.'),
        'tools'     => 'Figma, Pinegrap', 'url' => 'https://example.com', 'featured' => false),
);

// The answers of the brief form's pick lists. Each answer's value is its
// own words, so the e-mails and the lists read as the client chose.
$category_titles = array();
foreach ($categories as $c) $category_titles[] = $c['title'];
$package_choices = array(lang('Not sure yet'));
foreach ($packages as $p) $package_choices[] = $p['title'];
$budget_choices = array(lang('Under $3,000'), lang('$3,000–$6,000'), lang('$6,000–$12,000'), lang('Over $12,000'), lang('Not sure yet'));
$start_choices = array(lang('As soon as possible'), lang('Within a month'), lang('In one to three months'), lang('Just exploring'));
$page_count_choices = array(lang('One page'), lang('2–5 pages'), lang('6–15 pages'), lang('More than 15 pages'));
$brand_choices = array(
    array(lang('Yes, a logo and colours'), lang('Yes, a logo and colours')),
    array(lang('Partly'), lang('Partly')),
    array(lang('No, I need them too'), lang('No, I need them too')),
);
$contact_choices = array(
    array(lang('E-mail'), lang('E-mail')),
    array(lang('Phone'), lang('Phone')),
    array(lang('Video call'), lang('Video call')),
);
// The states of a brief, set by the designer in the panel.
$brief_statuses = array(lang('Brief received'), lang('Proposal sent'), lang('In progress'), lang('Delivered'), lang('Declined'));
// The query string of a link that opens the work list with a filter set
// (?category=) or the brief with a choice made (?type=, ?package=): the
// form's fields start with these values (_cf.url_param).
$query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// A page: the account bar, the header, the page's own content (the lists,
// the forms, the account pages) and the shared footer.
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
        $para($eyebrow, 'pdp-eyebrow text-uppercase fw-semibold text-primary mb-1'),
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

// An icon in a soft round tile, the way the categories are marked.
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

// The line in the account bar. A shared component.
$notice = function () use ($el, $icon, $span, $designer) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-circle-fill', 'text-success small'),
        $span($designer['available'] . ' — ' . lang('tell me about yours.'), '', lang('Text')),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site.
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

// The header: the designer's name, the menu and the way to start a
// project. The links to the current page are marked active on the server
// (smart active state), which is what lets one header serve every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('work', lang('Portfolio')),
        array('services', lang('Services')),
        array('process', lang('How I Work')),
        array('about', lang('About')),
        array('blog', lang('Journal')),
        array('contact', lang('Get in Touch')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-lg py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-bold', lang('Brand'), array(
                    $el('span', 'd-inline-flex p-2 rounded-circle bg-primary text-white lh-1', lang('Icon Box'), array($icon('bi-vector-pen', 'fs-5'))),
                    $span('{{site_name}}', 'pdp-brand fs-4', lang('Name')),
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
                    $el('ul', 'navbar-nav ms-lg-auto me-lg-3 column-gap-lg-2 py-3 py-lg-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2 pb-2 pb-lg-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $span(lang('Start a Project'), '', lang('Link Text')),
                            $icon('bi-arrow-up-right'),
                        ), array('href' => '{{page:brief}}')),
                    )),
                ), array('id' => 'pd-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The footer, on a dark band: the designer in a sentence and the profiles,
// the kinds of work, the pages for clients, the studio's pages and how to
// get in touch; at the bottom the year and the site's name.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $categories, $designer, $query, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $work_links = array();
    foreach ($categories as $c) $work_links[] = $link($c['title'], '{{page:work}}?' . $query(array('category' => $c['title'])), $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Designer'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-bold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-vector-pen', 'text-primary'),
                        $span('{{site_name}}', 'pdp-brand', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para($designer['bio'], 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-dribbble', 'Dribbble'),
                        $social('bi-behance', 'Behance'),
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-linkedin', 'LinkedIn'),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Work Links'), array(
                    $heading('h2', lang('Portfolio'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array_merge(array($link(lang('All Work'), '{{page:work}}', $plain)), $work_links)),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Clients'), array(
                    $heading('h2', lang('Clients'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Start a Project'), '{{page:brief}}', $plain),
                        $link(lang('My Briefs'), '{{page:my_briefs}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Help Center'), '{{page:search}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Studio'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Services'), '{{page:services}}', $plain),
                        $link(lang('How I Work'), '{{page:process}}', $plain),
                        $link(lang('About'), '{{page:about}}', $plain),
                        $link(lang('FAQ'), '{{page:faq}}', $plain),
                        $link(lang('Journal'), '{{page:blog}}', $plain),
                        $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-3 col-lg-2', lang('Contact Details'), array(
                    $heading('h2', lang('Say hello'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-envelope', $link($designer['email'], 'mailto:' . $designer['email'], $plain . ' text-break'), lang('Email')),
                    $contact_line('bi-telephone', $link($designer['phone'], 'tel:' . preg_replace('/[^0-9+]/', '', $designer['phone']), $plain), lang('Phone')),
                    $contact_line('bi-geo-alt', $span($designer['city'], '', lang('Text')), lang('Address')),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $link(lang('Get in Touch'), '{{page:contact}}', 'small ' . $plain),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The brief band: a full-width band in the palette's colour with the way
// to start a project. Shared, so every page that ends with it carries the
// same offer.
$brief_band = function () use ($el, $heading, $para, $link, $container, $row, $col, $designer) {
    return $el('section', 'py-5 text-bg-primary', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Have a project in mind?'), 'pdp-display h2 fw-bold mb-2'),
                    $para(lang('Tell me about it in a few lines; you get an answer and a first idea of the price within two working days.'), 'mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Start a Project'), '{{page:brief}}', 'btn btn-secondary btn-lg rounded-pill px-4'),
                        $link(lang('E-mail me'), 'mailto:' . $designer['email'], 'btn btn-link btn-lg text-reset'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A case study's card of the lists (the home and the work page): the
// cover, the kind of project and the year, the title linking the case
// study, the client and the summary. Every value is bound to the Projects
// form's field of the same name; the sample fills the canvas.
$project_card = function ($sample_key = 'harbour') use ($el, $para, $span, $link, $image, $col, $bind, $projects, $categories, $photo) {
    $s = $projects[$sample_key];
    return $col(array(
        $el('article', 'card h-100 border-0 bg-transparent pdp-lift', lang('Project Card'), array(
            $el('span', 'pg-field-value d-block ratio ratio-4x3 rounded-4 overflow-hidden bg-body-tertiary mb-3', lang('Picture Frame'), array(
                $image($photo($sample_key, 900, 675), $s['title'], 'object-fit-cover',
                    array('_bindings' => array('src' => 'cover', 'alt' => 'title'))),
            )),
            $el('div', 'd-flex flex-wrap align-items-center gap-2 small mb-2', lang('Meta'), array(
                $span($categories[$s['category']]['title'], 'badge rounded-pill text-bg-light border', lang('Category'), $bind('category')),
                $span((string)$s['year'], 'text-body-secondary', lang('Year'), $bind('year')),
            )),
            $el('h3', 'h5 fw-bold position-static mb-1', lang('Project Title'), array(
                $link($s['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                    array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
            )),
            $para($s['client'], 'small fw-semibold text-primary mb-2', $bind('client')),
            $para($s['summary'], 'small text-body-secondary mb-0', $bind('summary')),
        )),
    ), '6', '4');
};

// A package's card (the services page and the home): the name, the price,
// a line, what it holds and a link that opens the brief with it chosen.
$package_card = function ($key) use ($el, $heading, $para, $link, $span, $col, $check_list, $packages, $query) {
    $p = $packages[$key];
    $children = array();
    if ($p['featured']) $children[] = $span(lang('Most chosen'), 'badge rounded-pill text-bg-primary align-self-start mb-3', lang('Badge'));
    $children[] = $heading('h3', $p['title'], 'h5 fw-bold mb-1');
    $children[] = $para($p['price'], 'pdp-display h3 fw-bold mb-2');
    $children[] = $para($p['text'], 'small text-body-secondary mb-3');
    $children[] = $check_list($p['points'], 'list-unstyled small mb-4');
    $children[] = $link(lang('Start with this package'), '{{page:brief}}?' . $query(array('package' => $p['title'])),
        'btn ' . ($p['featured'] ? 'btn-primary' : 'btn-outline-primary') . ' rounded-pill mt-auto');
    return $col(array(
        $el('article', 'card h-100 rounded-4 ' . ($p['featured'] ? 'border-primary border-2 shadow' : 'border-0 shadow-sm'), lang('Package Card'), array(
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), $children),
        )),
    ), '6', '4');
};

// What a client says: the quote, the stars, the person and their company.
$testimonial_card = function ($n) use ($el, $para, $span, $col, $image, $attr, $stars, $portrait, $testimonials) {
    $t = $testimonials[$n];
    return $col(array(
        $el('figure', 'card h-100 border-0 shadow-sm rounded-4 p-4 mb-0', lang('Client Quote'), array(
            $stars(),
            $el('blockquote', 'mb-4', lang('Quote'), array($para($t['quote'], 'mb-0'))),
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

// The comments of a record page (a brief's messages, a conversation's
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
                $item('project_new', lang('New Case Study')),
                $item('staff_briefs', lang('All Briefs')),
                $item('blog_new', lang('New Article')),
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
    return $agree($id, 'consent', lang('I would like to receive new case studies and Journal posts by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. The
// brief takes documents (a brief, a content plan) and pictures (a logo,
// screenshots), so the default accepts PDFs, office files and pictures; one
// that takes only pictures is a collection's image kind (a case study's).
$file = function ($id, $name, $folder, $accept = '.pdf,.doc,.docx,.odt,.jpg,.jpeg,.png') use ($el, $attr) {
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
                    $link(lang('What to prepare before your website project starts'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
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

