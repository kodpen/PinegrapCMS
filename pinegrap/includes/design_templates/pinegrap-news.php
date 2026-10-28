<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "News Portal": a city newsroom. A front page with the
 * lead stories, the featured row, a block for each section, the columnists,
 * the newest articles and the most read; one page for each section (U.S.,
 * World, Politics, Economy, Tech, Science, Health, Sports, Arts) and one for
 * Opinion; the latest news with a search; the writers with each writer's
 * own articles (?author=); the article page with its comments; a breaking
 * news strip fed by the articles the editors flag; an author desk where
 * authors send articles the editors review, schedule and set live; the
 * newsroom desk; a tip line, a contact page whose messages become
 * conversations, the newsletter, the standards and the legal pages, the
 * sign-in and account pages, the e-mails the site sends and a site search.
 * Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * An article is a record of the News Articles form, owned by the Submit an
 * Article page in the private "contributors" folder: an author with view
 * access to that folder sends it, and an editor sets its Status, its
 * publication time, its front-page placement and its breaking flag in the
 * control panel (office-use-only fields). A record is public only while its
 * Status is "Live on the site" and its publication time is set and has come:
 * every public list and the article page carry those three filters ($live).
 *
 * The look ships in the template's User Styles: a few of the ready-made
 * blocks' rules (pg_design_template_user_styles()) and the template's own
 * rules, all named pn-. Fonts: Barlow Condensed, Public Sans and Source
 * Serif 4.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). There is no catalog.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components and 'requires'.
 *
 * Every structural node carries its role in '_label' (the designer's own
 * label, shown beside the element's tag in the layer tree).
 *
 * The version follows the template, not the software: raise it whenever the
 * pages change, so a design records which edition it started from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$panel_url = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/welcome.php';
$shop      = defined('ECOMMERCE') && ECOMMERCE;

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
// Words only a screen reader hears, next to an icon that says them.
$sr_only = function ($text) use ($span) {
    return $span($text, 'visually-hidden', lang('Text'));
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
// A date field in the format given (date()'s letters, or 'relative' for
// "2 hours ago"), in place of the site's own date format.
$bind_date = function ($token, $format) {
    return array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => $format));
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
// leaves it out while the site has a single language, so it can stay in the
// top bar of every site. Its button carries the language's name, so it is
// filled ($outline false): an outlined grey button with words is too faint
// on a dark band under the plain palette.
$lang_switcher = function ($class = '', $variant = 'secondary', $outline = false) {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => $outline, 'size' => 'sm',
        'align' => 'lg-end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};

// A sample portrait: the writer's initials on the palette's colour, until
// the newsroom puts a photo in its place. The initials follow the name in
// the site's language.
$portrait = function ($name, $size = 192) {
    $words = preg_split('/\s+/u', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . $size . 'x' . $size . '/e8eef8/1c3f94?text=' . rawurlencode($initials !== '' ? $initials : 'N');
};
// A sample photo, the same for a seed on every site.
$photo = function ($seed, $w, $h) {
    return 'https://picsum.photos/seed/pinegrap-news-' . $seed . '/' . $w . '/' . $h;
};

// ── One vocabulary ──────────────────────────────────────────────────────
// The values the form stores, the filters compare, the menus link and the
// sample articles carry, written once. Each is the lang() text of the
// language the template is opened in, so the options, the filters and the
// samples always match.

// The sections: key => array(title, description, page slug). The key is the
// section page's key (never translated); the title is the value the Section
// field stores, the filter value and the label everywhere.
$sections = array(
    'us'       => array(lang('U.S.'),     lang('News from cities and towns across the country.'),                      lang('us-news')),
    'world'    => array(lang('World'),    lang('Stories from around the world, told for readers at home.'),           lang('world')),
    'politics' => array(lang('Politics'), lang('City halls, statehouses and the decisions that shape daily life.'),   lang('politics')),
    'economy'  => array(lang('Economy'),  lang('Work, prices, small business and household money.'),                  lang('economy')),
    'tech'     => array(lang('Tech'),     lang('Gadgets, software and the people who make them.'),                    lang('tech')),
    'science'  => array(lang('Science'),  lang('Discoveries, space and the natural world.'),                          lang('science')),
    'health'   => array(lang('Health'),   lang('Care, clinics and everyday wellbeing.'),                              lang('health')),
    'sports'   => array(lang('Sports'),   lang('Local teams, big games and the people in the stands.'),               lang('sports')),
    'arts'     => array(lang('Arts'),     lang('Books, music, film and the city\'s stages.'),                         lang('arts')),
    'opinion'  => array(lang('Opinion'),  lang('Columns and commentary from our writers.'),                           lang('opinion')),
);
// The section titles in menu order: the Section field's options.
$section_titles = array();
foreach ($sections as $s) $section_titles[] = $s[0];
// The sections the desktop menu bar shows from xl only; below xl they move
// into its "More" menu.
$nav_wide_only = array('science', 'health');

// The section colours, light and dark (each reads at least 4.5:1 on the
// page background of its scheme). A block of one section copies its colour
// into --pn-cat through the .pn-sec-<key> class.
$section_colours = array(
    'us'       => array('#c8302a', '#ff7b72'),
    'world'    => array('#2453c2', '#86a8ff'),
    'politics' => array('#4152b8', '#9da9ff'),
    'economy'  => array('#0b7a6a', '#3cc8b2'),
    'tech'     => array('#6b3fc2', '#b69cff'),
    'science'  => array('#567514', '#a9cf55'),
    'health'   => array('#0a729a', '#52c3e8'),
    'sports'   => array('#177a3a', '#55c987'),
    'arts'     => array('#9a5b0a', '#edb25a'),
    'opinion'  => array('#4a5568', '#a9b4c6'),
);

// An article's Status, set by the editors. Only the fourth makes an article
// public (with a publication time that has come): $live below.
$statuses   = array(lang('Awaiting review'), lang('In review'), lang('Changes requested'),
                    lang('Live on the site'), lang('Not accepted'), lang('Withdrawn'));
$live_value = $statuses[3];
// Where an article stands on the front page: the three newest Lead Story
// articles fill the lead grid, the four newest Featured ones the featured row.
$placements = array(lang('Standard'), lang('Lead Story'), lang('Featured'));

// The writers: name => array(beat, one line). The one line carries no name:
// the byline prints right above it, and a name never sits inside a longer
// text to translate.
$writers = array(
    'Jane Cooper'    => array(lang('City hall and transportation'), lang('Covers city hall, transit and the way the city grows.')),
    'Michael Brooks' => array(lang('Economy and personal finance'), lang('Writes about work, prices and household money.')),
    'Emily Turner'   => array(lang('World affairs'),                lang('Reports on cities and people around the world.')),
    'Daniel Ross'    => array(lang('Sports'),                       lang('Follows local teams from the press box to the bleachers.')),
    'Samuel Reyes'   => array(lang('Science and technology'),       lang('Explains new science and technology in plain words.')),
    'Nora Whitfield' => array(lang('Health and the arts'),          lang('Covers health care and the arts.')),
);
// The columnists of the Opinion page, in this order.
$columnists = array('Nora Whitfield', 'Michael Brooks', 'Emily Turner');
// A writer's name as the byline stores it, and the address of the writer's
// own articles: the writers page reads ?author= and lists the live articles
// whose byline is exactly that name.
$writer_name = function ($key) {
    return lang($key);
};
$writer_url = function ($key) {
    return '{{page:author}}?author=' . rawurlencode(lang($key));
};

// ── The filters of the article lists ────────────────────────────────────
// Operators and dynamic values are the renderer's English constants, never
// translated (pg_sw_form_list_filter_sql()).
$f = function ($field, $operator, $value, $dynamic = '', $attribute = null) {
    $row = array('field' => $field, 'operator' => $operator, 'value' => $value, 'dynamic_value' => $dynamic);
    if ($attribute !== null) $row['dynamic_value_attribute'] = $attribute;
    return $row;
};
// What makes an article public; every row must match. The second row keeps
// an article with no publication time off the site: an empty value is not
// greater than or equal to a date, while it would pass "less than or equal
// to now". The third row holds a future time back until it comes.
$live = array(
    $f('status', 'is equal to', $live_value),
    $f('publish_date', 'is greater than or equal to', '2000-01-01 00:00:00'),
    $f('publish_date', 'is less than or equal to', '', 'current date and time'),
);
$with_live = function ($more = array()) use ($live) {
    return array_merge($live, $more);
};
$in_section = function ($title) use ($f) {
    return $f('section', 'is equal to', $title);
};
$not_lead = $f('placement', 'is not equal to', lang('Lead Story'));
$recent = function ($days) use ($f) {
    return $f('publish_date', 'is greater than or equal to', '', 'days ago', $days);
};
// The settings every public list of articles shares: the form, the article
// page its rows open, newest publication first.
$pub = array(
    'regionType'           => 'form_list_view',
    'custom_form_page_id'  => '{{tab:blog_new}}',
    'detail_page_id'       => '{{tab:blog_post}}',
    'order_by_field'       => 'publish_date',
    'order_by_direction'   => 'DESC',
    'order_by_2_field'     => 'submitted_date_and_time',
    'order_by_2_direction' => 'DESC',
);

// ── Look: fonts and the template's own rules ───────────────────────────
// The fonts the editor's font picker lists, weights only (no italics).
$news_fonts = array(
    array('family' => 'Barlow Condensed', 'weights' => array('600', '700', '800'), 'enabled' => true),
    array('family' => 'Public Sans', 'weights' => array('400', '600', '700'), 'enabled' => true),
    array('family' => 'Source Serif 4', 'weights' => array('400', '600'), 'enabled' => true),
);

// The template's own CSS, beside the blocks' rules in User Styles. Every
// class is named pn-; no block rule's class is named here (the editor's
// palette would then take its rule as present). Selectors use descendants
// and pseudo-classes only, so they hold on the editor's canvas too. A
// palette colour that only the colour palettes define (--pg-primary-fg)
// always carries a fallback for the plain Bootstrap palette.
$cat_light = '';
$cat_dark  = '';
$cat_sections = '';
foreach ($section_colours as $key => $c) {
    $cat_light    .= '--pn-cat-' . $key . ':' . $c[0] . ';';
    $cat_dark     .= '--pn-cat-' . $key . ':' . $c[1] . ';';
    $cat_sections .= '.pn-sec-' . $key . '{--pn-cat:var(--pn-cat-' . $key . ')}' . "\n";
}
$news_css = implode("\n", array(
    // Fonts and the accent colour.
    ':root,[data-bs-theme]{--pg-font-heading:"Barlow Condensed","Arial Narrow",Arial,sans-serif;--pg-font-body:"Public Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-heading-tracking:0;--pg-heading-line-height:1.1;--pn-serif:"Source Serif 4",Georgia,"Times New Roman",serif;'
        . '--pn-accent:var(--pg-primary-fg,var(--bs-primary-text-emphasis))}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-5,.display-6,.pn-cond{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The section colours.
    ':root,[data-bs-theme=light]{' . $cat_light . '}',
    '[data-bs-theme=dark]{' . $cat_dark . '}',
    rtrim($cat_sections),
    // Masthead: the name, the line under it, and the two bands that stay
    // on top (the masthead below lg, the menu bar from lg).
    '.pn-wordmark{max-width:100%;font-family:var(--pg-font-heading);font-weight:800;font-size:clamp(1.75rem,1.1rem + 2.6vw,3.125rem);line-height:.9;letter-spacing:.01em}',
    '.pn-tagline{margin-top:.3rem;font-family:var(--pn-serif);font-size:.875rem}',
    '@media (max-width:991.98px){.pn-masthead{position:sticky;top:0;z-index:1020}}',
    '@media (min-width:992px){.pn-nav{position:sticky;top:0;z-index:1020}}',
    // A focused link, the skip link's target and an in-page anchor scroll
    // clear of the band that stays on top.
    '@media (max-width:991.98px){html{scroll-padding-top:5rem}}',
    '@media (min-width:992px){html{scroll-padding-top:3.75rem}}',
    // A clear focus ring in the dark top bar, where the buttons' own rings
    // barely show, and on the masthead's menu and search buttons, which
    // have no colour variant and so no ring of their own.
    '.pn-topbar a:focus-visible,.pn-topbar .btn:focus-visible,.pn-masthead .btn:focus-visible{outline:2px solid var(--bs-emphasis-color);outline-offset:2px;box-shadow:none}',
    // The menu bar's links: a bar slides in under the current one.
    '.pn-navlink{position:relative;display:inline-flex;align-items:center;gap:.375rem;height:3rem;padding:0 .75rem;border:0;background:none;font-family:var(--pg-font-heading);font-weight:700;font-size:.95rem;letter-spacing:.02em;text-transform:uppercase;white-space:nowrap}',
    '.pn-navlink::after{content:"";position:absolute;left:.75rem;right:.75rem;bottom:0;height:3px;background:currentColor;transform:scaleX(0);transform-origin:left;transition:transform .2s ease}',
    '.pn-navlink:hover::after,.pn-navlink.active::after,.pn-navlink[aria-expanded=true]::after,.pn-navlink:focus-visible::after{transform:scaleX(1)}',
    '.pn-navlink:focus-visible{box-shadow:none;outline:2px solid currentColor;outline-offset:-4px}',
    // The phone's section pills, scrolled sideways.
    '.pn-strip{display:flex;gap:.5rem;overflow-x:auto;scrollbar-width:none;-webkit-mask-image:linear-gradient(90deg,#000 85%,transparent);mask-image:linear-gradient(90deg,#000 85%,transparent)}',
    '.pn-strip::-webkit-scrollbar{display:none}',
    // A spacer as wide as the fade, so the last pill scrolls out of it.
    '.pn-strip::after{content:"";flex:0 0 15%}',
    // Keyboard focus: Chromium leaves a partly visible pill where it is, so
    // the padding narrows the visible area to the middle fifth and a focused
    // pill outside it is scrolled to the centre (either Tab direction). The
    // fade drops while a pill has focus, so its ring is never faded out.
    '.pn-strip{scroll-padding-inline:40%}',
    '.pn-strip:focus-within{-webkit-mask-image:none;mask-image:none}',
    '.pn-pill{display:inline-flex;align-items:center;gap:.4375rem;min-height:2.25rem;padding:.25rem .875rem;border-radius:999px;background:var(--bs-tertiary-bg);color:var(--bs-emphasis-color);font-size:.875rem;font-weight:600;white-space:nowrap;text-decoration:none}',
    '.pn-pill::before{content:"";flex:none;width:.5rem;height:.5rem;border-radius:50%;background:var(--pn-cat,var(--pn-accent))}',
    '.pn-pill:hover{color:var(--bs-emphasis-color);background:var(--bs-secondary-bg)}',
    '.pn-pill.active{background:var(--bs-emphasis-color);color:var(--bs-body-bg)}',
    '.pn-pill.active::before{background:var(--bs-body-bg)}',
    // The menu drawer's rows.
    '.pn-drawer{display:flex;align-items:center;gap:.75rem;min-height:44px;padding:.25rem 0;border-bottom:1px solid var(--bs-border-color);color:var(--bs-emphasis-color);font-weight:700;text-decoration:none}',
    '.pn-drawer::before{content:"";flex:none;width:.5rem;height:.5rem;border-radius:50%;background:var(--pn-cat,var(--pn-accent))}',
    '.pn-drawer:hover{color:var(--bs-emphasis-color);text-decoration:underline}',
    '.pn-drawer.active{text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:.3em}',
    // The breaking strip: gone while no flagged article is live; one
    // headline still loops without a gap.
    '.pn-breaking:has(.pg-sw-empty){display:none}',
    '@media (prefers-reduced-motion:no-preference){.pn-breaking .pg-marquee-group{min-width:100vw}}',
    // Standing still (less motion asked for, or paused with the button) the
    // headlines are plain text: the edge fade would leave the first one
    // faint, so it goes.
    '@media (prefers-reduced-motion:reduce){.pn-breaking .pg-marquee-viewport{-webkit-mask-image:none;mask-image:none}}',
    '.pn-breaking:has(.pg-marquee-pause:checked) .pg-marquee-viewport{-webkit-mask-image:none;mask-image:none}',
    // A headline reached with the keyboard: the strip goes back to its
    // start, so the browser can scroll the focused link into view instead
    // of leaving it frozen outside the box. Keyboard focus only: a mouse
    // click would move the link from under the pointer. The moving box is
    // no scroll container otherwise (clip, with the zero minimum width a
    // scroll box has in a flex row), so the offset that scroll left behind
    // is dropped when the focus leaves and the loop has no gap.
    '.pn-breaking .pg-marquee-viewport:has(a:focus-visible){-webkit-mask-image:none;mask-image:none}',
    '.pn-breaking .pg-marquee-viewport:has(a:focus-visible) .pg-marquee-track{animation:none}',
    '@media (prefers-reduced-motion:no-preference){.pn-breaking .pg-marquee-viewport{overflow:clip;min-width:0}.pn-breaking .pg-marquee-viewport:has(a:focus-visible){overflow:hidden}}',
    // The pause and theme buttons hold an icon only: a small button's
    // font size would draw it a few pixels tall.
    '.pn-breaking .pg-marquee-toggle .bi,.pn-topbar [data-pg-theme-switcher] .bi{font-size:1rem}',
    '.pn-topbar [data-pg-theme-switcher] .btn{--bs-btn-border-color:var(--bs-border-color)}',
    '.pn-breaking-label{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.06em;text-transform:uppercase}',
    '.pn-breaking-time{color:var(--bs-danger-text-emphasis);font-weight:700;font-variant-numeric:tabular-nums}',
    '.pn-breaking-item:hover .pn-breaking-title{text-decoration:underline}',
    // A section's title over its block, with its colour's rule.
    '.pn-sec-title{margin:0 0 -1px;padding-bottom:.5rem;border-bottom:3px solid var(--pn-cat,var(--bs-emphasis-color));color:var(--pn-cat,var(--bs-emphasis-color));font-family:var(--pg-font-heading);font-weight:800;font-size:1.5rem;letter-spacing:.02em;line-height:1;text-transform:uppercase}',
    '.pn-sec-title.h5,.pn-sec-title.h6{font-size:1.125rem}',
    '.pn-sec-title.display-6{font-size:calc(1.375rem + 1.5vw)}',
    '@media (min-width:1200px){.pn-sec-title.display-6{font-size:2.5rem}}',
    '.pn-sec-title a{color:inherit;text-decoration:none}',
    '.pn-sec-title a:hover{text-decoration:underline}',
    // The words over a headline, and the headline.
    '.pn-kicker{position:relative;z-index:2;font-family:var(--pg-font-heading);font-weight:700;font-size:.75rem;letter-spacing:.08em;text-transform:uppercase;color:var(--pn-cat,var(--pn-accent))}',
    '.pn-headline{font-family:var(--pg-font-heading);font-weight:700;line-height:1.18;text-wrap:balance}',
    '.pn-headline a{color:inherit;text-decoration:none}',
    '.pn-story:hover .pn-headline a,.pn-headline a:focus-visible{text-decoration:underline;text-decoration-color:var(--pn-cat,currentColor);text-decoration-thickness:2px;text-underline-offset:.18em}',
    // The lead grid: the first story large, the next two beside it. The
    // first article, not the first child: an editor's edit button may
    // bring its own style element in first.
    '.pn-lead-grid{display:grid;gap:1rem}',
    '@media (min-width:576px){.pn-lead-grid{grid-template-columns:1fr 1fr}.pn-lead-grid article:first-of-type{grid-column:1 / -1}}',
    '@media (min-width:992px){.pn-lead-grid{grid-template-columns:2fr 1fr}.pn-lead-grid article:first-of-type{grid-column:auto;grid-row:span 2}}',
    '.pn-lead-grid article{align-self:start}',
    // From lg the large story fills the height of the two beside it.
    '@media (min-width:992px){.pn-lead-grid article:first-of-type{align-self:stretch}.pn-lead-grid article:first-of-type .ratio{height:100%}}',
    '.pn-lead-grid .pn-lead-title{font-size:1.1875rem}',
    '.pn-lead-grid article:first-of-type .pn-lead-title{font-size:clamp(1.5rem,1rem + 2.2vw,2.75rem);font-weight:800;line-height:1.04}',
    '.pn-lead-grid .pn-lead-dek{display:none;color:var(--bs-body-color)}',
    '.pn-lead-grid article:first-of-type .pn-lead-dek{display:block}',
    // On a phone the large story is one column wide: a taller frame and no
    // summary keep its words in the dark part of the gradient.
    '@media (max-width:575.98px){.pn-lead-grid article:first-of-type .ratio{--bs-aspect-ratio:75%}.pn-lead-grid article:first-of-type .pn-lead-dek{display:none}}',
    // The text over a lead photo sits on a dark gradient that keeps white
    // words readable even on a white picture.
    '.pn-scrim{background-image:linear-gradient(to top,rgba(0,0,0,.9),rgba(0,0,0,.72) 70%,rgba(0,0,0,.45))}',
    // The summary under a headline.
    '.pn-dek{font-family:var(--pn-serif);font-size:1.0625rem;line-height:1.5;color:var(--bs-secondary-color)}',
    '.pn-dek.lead{font-size:1.25rem;font-weight:400}',
    // A story's picture, which grows a little under the pointer.
    '.pn-media{overflow:hidden;border-radius:var(--bs-border-radius);background-color:var(--bs-secondary-bg)}',
    '.pn-media img{transition:transform .6s ease}',
    '.pn-story:hover .pn-media img{transform:scale(1.035)}',
    '.pn-ratio-3x2{--bs-aspect-ratio:66.6667%}',
    // The most read list: outlined numbers counted by the list, since every
    // row of a list is drawn alike.
    '.pn-rank{counter-reset:pn-rank}',
    '.pn-rank-item{counter-increment:pn-rank;border-top:var(--bs-border-width) solid var(--bs-border-color)}',
    '.pn-rank-item:first-of-type{border-top:0}',
    '.pn-rank-num::before{content:counter(pn-rank)}',
    '.pn-rank-num{flex:0 0 2.25rem;font:800 2.25rem/1 var(--pg-font-heading);text-align:center;color:transparent;-webkit-text-stroke:1.5px var(--pn-accent)}',
    '@supports not (-webkit-text-stroke:1px){.pn-rank-num{color:var(--pn-accent)}}',
    // The newest articles on a time line.
    '.pn-feed{position:relative}',
    '.pn-feed::before{content:"";position:absolute;top:.5rem;bottom:.5rem;left:4px;width:1px;background:var(--bs-border-color)}',
    '.pn-feed-item{padding-left:1.5rem}',
    '.pn-feed-item::before{content:"";position:absolute;top:.4rem;left:0;width:9px;height:9px;border-radius:50%;background:var(--pn-accent);box-shadow:0 0 0 3px var(--bs-body-bg)}',
    // A section's tile on the front page: the first story with its photo,
    // the others as headlines. A hidden lazy picture is not fetched.
    '.pn-stack .pn-stack-media{display:none}',
    // A rule between the tile's stories; the tile's head draws the first.
    '.pn-stack article{border-top:var(--bs-border-width) solid var(--bs-border-color)}',
    '.pn-stack article:first-of-type{border-top:0}',
    '.pn-stack article:first-of-type .pn-stack-media{display:block}',
    // A column's title.
    '.pn-column-title{font-family:var(--pn-serif);font-weight:600;font-size:1.125rem;line-height:1.3}',
    // The article text. Contained: what an author writes stays inside its
    // box (no link or layer laid over the page), and a wide table scrolls.
    '.pn-body{position:relative;contain:paint;overflow-wrap:anywhere;max-width:42rem;font-family:var(--pn-serif);font-size:1.125rem;line-height:1.7}',
    '.pn-body p,.pn-body ul,.pn-body ol{margin-bottom:1.25rem}',
    '.pn-body h2,.pn-body h3{margin:2rem 0 .75rem;font-family:var(--pg-font-heading)}',
    '.pn-body a{text-decoration:underline;text-underline-offset:.15em}',
    '.pn-body blockquote{margin:1.5rem 0;padding-left:1rem;border-left:3px solid var(--bs-primary)}',
    '.pn-body img{max-width:100%;height:auto}',
    '.pn-body figure{margin:1.5rem 0}',
    '.pn-body table{display:block;max-width:100%;overflow-x:auto}',
    // The correction under an article.
    '.pn-correction{border-left:3px solid var(--bs-warning);padding-left:1rem}',
    // A flex item that may shrink below its words (Bootstrap has no
    // min-width utility).
    '.pn-brand,.pn-min-0{min-width:0}',
    // A tip's words on the desk, whole. The text area's value comes with a
    // <br> at each line break, so the kept newlines must not break again.
    '.pn-tip{overflow-wrap:anywhere}',
    '@media (prefers-reduced-motion:reduce){.pn-navlink::after,.pn-media img{transition:none}.pn-story:hover .pn-media img{transform:none}}',
));

// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3') use ($el, $attr) {
    return $el('div', $class, $label, array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    ));
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    foreach ($more as $k => $v) $attrs[] = $attr($k, $v);
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
// $cf: the field's form settings ('wysiwyg' => true makes it a rich text
// box, 'office_use_only' => true a field only the editors fill).
$textarea = function ($id, $name, $rows, $required = true, $placeholder = '', $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    if ($placeholder !== '') $attrs[] = $attr('placeholder', $placeholder);
    foreach ($more as $k => $v) $attrs[] = $attr($k, $v);
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('textarea', 'form-control', lang('Field'), array(), $props);
};
// A pick list: the first, empty option is the prompt, not an answer. Each
// answer's value is its own words, so the e-mails and the lists read as the
// visitor chose. $selected is the answer chosen to begin with (no prompt
// then).
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
// A hint under a field, named by the field's aria-describedby. Never under
// an office-use-only field: the hint would stay on the page for an author
// after the field itself is taken out.
$help = function ($id, $text) use ($el) {
    return $el('div', 'form-text', lang('Help Text'), array(), array('id' => $id, 'text' => $text));
};
// An office-use-only field: the editors fill it in, an author never sees
// it, and a new record stores its selected option or nothing.
$office = function ($control) {
    $cf = isset($control['props']['_cf']) && is_array($control['props']['_cf']) ? $control['props']['_cf'] : array();
    $control['props']['_cf'] = array_merge($cf, array('office_use_only' => true));
    return $control;
};
$submit = function ($text, $class = 'btn btn-primary px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// A box the visitor must tick: what was agreed to is stored with the form.
$agree = function ($id, $name, $text) use ($el, $attr) {
    return $el('div', 'form-check small mb-3', lang('Consent'), array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
    ));
};
// The newsletter's fields in the order they are filled in: the e-mail
// address, the consent, the CAPTCHA, then the button, so Subscribe is the
// last step. The address is the contact's, so the sign-up becomes a contact
// of the newsletter's group.
$newsletter_fields = function ($id, $button_class = 'btn btn-primary px-4') use ($el, $attr, $submit, $agree, $captcha) {
    return array(
        $el('div', 'mb-3', lang('Email'), array(
            $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Email'), '_attrs' => array($attr('for', $id . '-email')))),
            $el('input', 'form-control', lang('Field'), array(), array('id' => $id . '-email', '_cf' => array('contact_field' => 'email_address'), '_attrs' => array(
                $attr('type', 'email'), $attr('name', 'email'), $attr('required', ''), $attr('autocomplete', 'email'),
                $attr('placeholder', lang('Your e-mail address')),
            ))),
        )),
        $agree($id . '-consent', 'consent', lang('I would like to receive the Morning Briefing by e-mail. I can unsubscribe at any time.')),
        $captcha(),
        $submit(lang('Subscribe'), $button_class),
    );
};
// A file field; what is sent is kept in the template folder named.
$file = function ($id, $name, $folder, $accept = 'image/*', $required = false) use ($el, $attr) {
    $attrs = array($attr('type', 'file'), $attr('name', $name), $attr('accept', $accept));
    if ($required) $attrs[] = $attr('required', '');
    return $el('input', 'form-control', lang('Field'), array(), array('id' => $id, '_attrs' => $attrs,
        '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')));
};
// The name, e-mail and phone of whoever sends a form; the contact's own
// fields, so the site knows them next time.
$person_fields = function ($prefix, $last_required = true, $phone_required = true) use ($row, $col, $field, $input) {
    return $row(array(
        $col(array($field($prefix . '-first-name', lang('First Name'), $input($prefix . '-first-name', 'text', 'first_name', 'given-name', true, array('contact_field' => 'first_name')))), '6'),
        $col(array($field($prefix . '-last-name', lang('Last Name'), $input($prefix . '-last-name', 'text', 'last_name', 'family-name', $last_required, array('contact_field' => 'last_name')))), '6'),
        $col(array($field($prefix . '-email', lang('Email'), $input($prefix . '-email', 'email', 'email', 'email', true, array('contact_field' => 'email_address')))), '6'),
        $col(array($field($prefix . '-phone', lang('Phone'), $input($prefix . '-phone', 'tel', 'phone', 'tel', $phone_required, array('contact_field' => 'mobile_phone')))), '6'),
    ), '3');
};

// ── Records ─────────────────────────────────────────────────────────────

// A picture that leaves with its row when the record has none (the
// pg-hide-if-empty row of the form widgets).
$optional_image = function ($token, $alt_token, $class, $frame, $sample, $alt, $extra = array()) use ($el, $image) {
    $bindings = array('src' => $token);
    if ($alt_token !== '') $bindings['alt'] = $alt_token;
    return $el('div', 'pg-hide-if-empty', lang('Picture'), array(
        $el('span', trim('pg-field-value d-block ' . $frame), lang('Picture Frame'), array(
            $image($sample, $alt, $class, array_merge(array('_bindings' => $bindings), $extra)),
        )),
    ));
};
// The record's Change Status button, shown to whoever may edit the
// submissions of its form; it opens the record in the control panel and
// comes back. A filled button: an outlined grey one is too faint on the
// dark scheme of the plain palette.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-secondary', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};
// Summarize the article with AI: with the visitor's own assistant (each
// link opens it with a question about the article's address) or with the
// browser's built-in model, whose button the browser shows only where it
// can summarize and whose points come into the box below. Shown for an
// article anybody may open.
$ai_summary = function () use ($el, $link, $icon, $span, $attr, $bind, $show_when) {
    $assistant = function ($text, $token) use ($link, $attr) {
        return $link($text, '#', 'btn btn-sm btn-secondary rounded-pill', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    // An aside, so its own words stay out of the summary; named for the
    // landmark list.
    return $el('aside', 'p-3 bg-body-tertiary mb-4', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars', 'text-primary'),
            $span(lang('Summarize this article with AI'), 'small fw-semibold me-1', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-primary rounded-pill', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the article, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), array_merge(array('_attrs' => array($attr('aria-label', lang('Summarize this article with AI')))), $show_when('has_ai_links')));
};
// The comments of a record page, on their own: the region alone, for a
// column the page lays out itself.
$comments_block = function () {
    return array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array());
};
// The comments of a record page in their own band, in the reading column.
$comments_section = function ($name) use ($el, $container, $row, $col, $comments_block) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array($comments_block()), '', '8'),
            ), '', 'center'),
        )),
    ));
};
// The notice a record page shows above the record once its form is sent,
// and the account opened for a visitor who had none.
$sent_notices = function ($thanks) use ($el, $icon, $span, $para, $link, $attr, $bind, $show_when) {
    return array(
        $el('div', 'alert alert-success d-flex gap-2', lang('Sent Notice'), array(
            $icon('bi-check-circle', 'mt-1'),
            $span($thanks, '', lang('Text')),
        ), array_merge(array('_attrs' => array($attr('role', 'status'))), $show_when('submitted_this_session'))),
        $el('div', 'alert alert-info', lang('New Account'), array(
            $el('p', 'fw-semibold mb-2', lang('Title'), array(), array('text' => lang('We opened an account for you'))),
            $el('p', 'mb-1', lang('Sign-in Email'), array(
                $span(lang('Email') . ': ', '', lang('Label')),
                $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
            )),
            // The single-use set password link, for the person who sent the form
            // while it is live; the sentence below covers a used or expired link.
            $link(lang('Set your password'), '#', 'btn btn-sm btn-primary', array(),
                array('_bindings' => array('href' => '__new_account_set_password_url', 'eo_visible_if' => 'has_set_password_link'))),
            $para(lang('You can set a password with "Forgot password" on the sign-in page.'), 'small mt-2 mb-0'),
        ), $show_when('has_new_account')),
    );
};
// A label and its value in a record's definition list; the row leaves when
// the value is empty.
$detail = function ($label, $sample, $token, $class = 'col-sm-8') use ($el, $bind) {
    return array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', $class, $label, array(), array_merge(array('text' => $sample), $bind($token))),
    );
};
// An article's Status as a badge.
$status_badge = function ($sample, $class = 'badge rounded-pill bg-primary-subtle text-primary-emphasis') use ($span, $bind) {
    return $span($sample, $class, lang('Status'), $bind('status'));
};

// ── The cards of the article lists ──────────────────────────────────────
// Each card is an article whose headline link stretches over the whole
// card; the section's name above the headline is raised over that link. A
// picture in a list has an empty alt: the headline beside it names the
// story. Below the lead grid every list picture loads lazily.

$lazy = array('_attrs' => array($attr('loading', 'lazy')));
// The headline: the article's title, linking to the article.
$card_headline = function ($tag, $class, $sample) use ($el, $link) {
    return $el($tag, trim('pn-headline ' . $class), lang('Headline'), array(
        $link($sample, '#', 'stretched-link text-reset text-decoration-none', array(),
            array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
    ));
};
// A card's sample value: the field of the sample article a list passes in
// (so the editor and the installer's preview show a story under its own
// section), else the card's own default.
$sample_value = function ($s, $key, $default) {
    return is_array($s) && isset($s[$key]) ? $s[$key] : $default;
};
// The article's section over its headline.
$card_kicker = function ($class = '', $sample = '') use ($span, $bind, $sections) {
    return $span($sample !== '' ? $sample : $sections['us'][0], trim('pn-kicker ' . $class), lang('Section'), $bind('section'));
};
// The article's dateline (its city) over its headline; gone when empty.
$card_dateline = function ($class = '', $sample = null) use ($el, $span, $bind) {
    return $el('span', trim('pn-kicker pg-hide-if-empty ' . $class), lang('Dateline'), array(
        $span($sample !== null ? $sample : lang('Denver'), 'pg-field-value', lang('Value'), $bind('dateline')),
    ));
};
// How long ago the article went live ("2 hours ago").
$card_time = function ($class = '') use ($span, $bind_date) {
    return $span(lang('2 hours ago'), $class, lang('Time'), $bind_date('publish_date', 'relative'));
};
// A dot between two details, silent to screen readers.
$dot = function () use ($span, $attr) {
    return $span('·', 'mx-1', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
// "· 4 min read", gone when the editors left the reading time empty: the
// words around the value do not keep it on the page. The number and its
// words stay on one line.
$reading_time = function ($sample = '4') use ($el, $span, $bind, $attr) {
    return $el('span', 'pg-hide-if-empty text-nowrap', lang('Reading Time'), array(
        $span('·', 'mx-1', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true')))),
        $span($sample, 'pg-field-value', lang('Value'), $bind('reading_time')),
        $span(lang('min read'), '', lang('Text')),
    ));
};
// The byline as the writer gave it.
$card_byline = function ($class = '', $sample = '') use ($span, $bind, $writer_name) {
    return $span($sample !== '' ? $sample : $writer_name('Jane Cooper'), $class, lang('Byline'), $bind('byline'));
};
// The cover picture in a frame that leaves with it; $src is the sample.
$cover = function ($src, $class, $frame, $lazy_load = true) use ($optional_image, $lazy) {
    return $optional_image('cover_image', '', $class, $frame, $src, '', $lazy_load ? $lazy : array());
};

// (a) A story of the lead grid: its picture with the headline over it, on a
// dark gradient that keeps the words readable on any picture. The summary
// shows on the large card only.
$overlay_card = function () use ($el, $para, $span, $bind, $attr, $photo, $cover, $card_headline, $card_byline, $card_time, $dot) {
    return $el('article', 'card border-0 overflow-hidden position-relative bg-body pn-story', lang('Lead Story'), array(
        $el('div', 'ratio ratio-16x9', lang('Picture Box'), array(
            $cover($photo('light-rail', 1200, 675), 'w-100 h-100 object-fit-cover', 'h-100', false),
        )),
        $el('div', 'card-img-overlay d-flex flex-column justify-content-end p-3 p-md-4 text-body pn-scrim', lang('Caption'), array(
            $span(lang('U.S.'), 'badge text-bg-primary align-self-start mb-2', lang('Section'), $bind('section')),
            $card_headline('h3', 'pn-lead-title mb-2', lang('New light-rail line opens nine stations across downtown')),
            $para(lang('Thousands rode the first trains on opening day, as the line links the river district with the university campus.'), 'pn-dek pn-lead-dek mb-2', $bind('summary')),
            $el('p', 'small mb-0', lang('Byline and Time'), array($card_byline(), $dot(), $card_time())),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};
// (b) A story card: the picture, the section, the headline, the time.
$story_card = function () use ($el, $photo, $cover, $card_headline, $card_time, $sections, $span, $bind) {
    return $el('article', 'pn-story position-relative', lang('Story Card'), array(
        $cover($photo('factory', 1200, 675), 'w-100 object-fit-cover', 'ratio pn-ratio-3x2 pn-media mb-2'),
        $span($sections['economy'][0], 'pn-kicker', lang('Section'), $bind('section')),
        $card_headline('h3', 'h6 mt-1 mb-1', lang('Small manufacturers report their busiest month in three years')),
        $el('p', 'small text-body-secondary mb-0', lang('Time'), array($card_time())),
    ));
};
// (c) A row story: a small square picture beside the dateline, the
// headline, the summary (from md) and the time. $s: the sample article.
$row_story = function ($s = array()) use ($el, $para, $bind, $photo, $cover, $card_dateline, $card_headline, $card_time, $sample_value) {
    return $el('article', 'pn-story d-flex gap-3 position-relative', lang('Story Card'), array(
        $cover($sample_value($s, 'cover_image', $photo('pocket-park', 1200, 675)), 'rounded object-fit-cover pg-thumb-100', 'flex-shrink-0'),
        $el('div', 'pn-min-0', lang('Words'), array(
            $card_dateline('', $sample_value($s, 'dateline', lang('Columbus'))),
            $card_headline('h3', 'h6 mb-1', $sample_value($s, 'title', lang('Twelve pocket parks open on former parking lots'))),
            $para($sample_value($s, 'summary', lang('Small green spaces with benches and shade trees now dot the city\'s east side.')), 'd-none d-md-block small text-body-secondary mb-1', $bind('summary')),
            $el('p', 'small text-body-secondary mb-0', lang('Time'), array($card_time())),
        )),
    ));
};
// (d) A column: the columnist's portrait and name, the column's title in
// the serif and the time.
$columnist_card = function () use ($el, $para, $bind, $optional_image, $portrait, $writer_name, $card_headline, $card_time) {
    return $el('article', 'pn-story position-relative h-100 p-3 bg-body border', lang('Column Card'), array(
        $el('div', 'd-flex align-items-center gap-3 mb-2', lang('Columnist'), array(
            $optional_image('byline_photo', '', 'pg-circle-60 rounded-circle object-fit-cover', 'flex-shrink-0',
                $portrait($writer_name('Nora Whitfield'), 192), '', array('_attrs' => array(array('name' => 'loading', 'value' => 'lazy')))),
            $el('div', '', lang('Name'), array(
                $para($writer_name('Nora Whitfield'), 'small fw-bold mb-0', $bind('byline')),
                $para(lang('Opinion'), 'pn-kicker mb-0'),
            )),
        )),
        $card_headline('h3', 'pn-column-title mb-2', lang('What a city remembers when the trains arrive')),
        $el('p', 'small text-body-secondary mb-0', lang('Time'), array($card_time())),
    ));
};
// (e) An item of the newest articles' time line.
$feed_item = function () use ($el, $card_time, $card_kicker, $card_headline, $dot) {
    return $el('li', 'pn-feed-item position-relative pb-3', lang('Story Card'), array(
        $el('p', 'small text-body-secondary mb-1', lang('Time and Section'), array($card_time(), $dot(), $card_kicker())),
        $card_headline('h3', 'h6 mb-0', lang('City council approves a plan to plant 10,000 street trees')),
    ));
};
// (f) A story of a long list: the picture on the left, the words on the
// right. $tag is the headline's level (h2 under a page's h1, h3 under a
// list's own h2); $kicker the field over it (the dateline on a section's
// page, where every story is of that section, the section elsewhere); $s
// the sample article. The rule under the story is on the article, not on
// its row, whose gutters reach past the column's edges.
$river = function ($tag, $kicker, $s = array()) use ($el, $para, $bind, $photo, $cover, $card_kicker, $card_dateline, $card_headline, $card_byline, $card_time, $dot, $reading_time, $sample_value) {
    return $el('article', 'pn-story position-relative py-4 border-bottom', lang('Story Card'), array(
        $el('div', 'row g-3', lang('Row'), array(
            $el('div', 'col-5 col-md-4', lang('Picture'), array(
                $cover($sample_value($s, 'cover_image', $photo('sea-wall', 1200, 675)), 'img-fluid w-100 rounded object-fit-cover pg-ratio-16x9', 'd-block'),
            )),
            $el('div', 'col-7 col-md-8', lang('Words'), array(
                $kicker === 'dateline' ? $card_dateline('', $sample_value($s, 'dateline', lang('London'))) : $card_kicker('', $sample_value($s, 'section', '')),
                $card_headline($tag, 'h5 mt-1 mb-2', $sample_value($s, 'title', lang('Coastal cities agree on a shared fund to repair sea walls'))),
                $para($sample_value($s, 'summary', lang('Twelve port cities will pool money and engineers to rebuild defenses before the next storm season.')), 'pn-dek d-none d-md-block mb-2', $bind('summary')),
                $el('p', 'small text-body-secondary mb-0', lang('Byline and Time'), array(
                    $card_byline('', $sample_value($s, 'byline', '')), $dot(), $card_time(), $reading_time($sample_value($s, 'reading_time', '4')),
                )),
            )),
        )),
    ));
};
// (g) A story of a section's tile on the front page: the first one with
// its picture (the template's rules show it), the others as headlines. $s:
// the sample article.
$stack_item = function ($s = array()) use ($el, $photo, $cover, $card_headline, $card_time, $sample_value) {
    return $el('article', 'pn-story position-relative py-2', lang('Story Card'), array(
        $el('div', 'pn-stack-media mb-2', lang('Picture'), array(
            $cover($sample_value($s, 'cover_image', $photo('library', 1200, 675)), 'w-100 object-fit-cover', 'ratio ratio-16x9 pn-media'),
        )),
        $card_headline('h3', 'h6 mb-1', $sample_value($s, 'title', lang('City council votes to keep branch libraries open until 9 p.m.'))),
        $el('p', 'small text-body-secondary mb-0', lang('Time'), array($card_time())),
    ));
};

// ── The desk's rows and record pages ────────────────────────────────────

// An article in a desk list: its headline and Status on top, then its
// section, the byline ($with_writer: the newsroom's lists), when it was
// sent, its publication time once an editor set one, and the messages under
// it. The row opens the list's detail page: the submission page on the
// desks, the public article in the newsroom's "Live now".
$desk_row = function ($with_writer) use ($el, $span, $icon, $bind, $bind_date, $status_badge, $sections, $writer_name, $statuses) {
    $detail = function ($icon_name, $name, $children, $class = 'd-inline-flex align-items-center gap-1') use ($el, $icon) {
        return $el('span', $class, $name, array_merge(array($icon($icon_name)), $children));
    };
    $details = array($detail('bi-bookmark', lang('Section'), array($span($sections['us'][0], '', lang('Section'), $bind('section')))));
    if ($with_writer) {
        $details[] = $detail('bi-person', lang('Byline'), array($span($writer_name('Jane Cooper'), '', lang('Byline'), $bind('byline'))));
    }
    $details[] = $detail('bi-send', lang('Sent'), array(
        $span(lang('Sent:'), '', lang('Label')),
        $span(lang('2 hours ago'), '', lang('Time'), $bind_date('submitted_date_and_time', 'relative')),
    ));
    $details[] = $detail('bi-calendar-check', lang('Goes live:'), array(
        $span(lang('Goes live:'), '', lang('Label')),
        $span(lang('Date'), 'pg-field-value', lang('Value'), $bind('publish_date')),
    ), 'pg-hide-if-empty d-inline-flex align-items-center gap-1');
    $details[] = $detail('bi-chat-left-text', lang('Messages'), array(
        $span('2', 'fw-semibold', lang('Number'), $bind('number_of_comments')),
        $span(lang('messages'), '', lang('Label')),
    ));
    return $el('a', 'list-group-item list-group-item-action d-flex flex-column gap-1 py-3', lang('News article'), array(
        $el('div', 'd-flex align-items-start gap-2', lang('Top Line'), array(
            $span(lang('City council approves a plan to plant 10,000 street trees'), 'fw-semibold', lang('Headline'), $bind('title')),
            $el('span', 'ms-auto flex-shrink-0', lang('Status'), array($status_badge($statuses[0]))),
        )),
        $el('div', 'small text-body-secondary d-flex flex-wrap column-gap-3 row-gap-1', lang('Details'), $details),
    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view')));
};
// A record page's toolbar: back to its list, and the editors' button.
$record_toolbar = function ($back_text, $back_page, $edit_text = '') use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-3', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-body-emphasis text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($back_text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $back_page . '}}')),
        $edit_button($edit_text),
    ));
};
// A label and its value in a record's details; $optional rows leave when
// the value is empty.
$record_detail = function ($label, $sample, $token, $optional = false, $value_class = '') use ($el, $bind) {
    return $el('div', 'row mb-1' . ($optional ? ' pg-hide-if-empty' : ''), $label, array(
        $el('dt', 'col-sm-4 fw-normal text-body-secondary', lang('Label'), array(), array('text' => $label)),
        $el('dd', trim('col-sm-8 mb-0 ' . ($optional ? 'pg-field-value ' : '') . $value_class), lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// A box of words from the record, which leaves when they are empty.
$record_words = function ($label, $sample, $token) use ($el, $para, $bind) {
    return $el('div', 'pg-hide-if-empty p-4 bg-body-tertiary border mb-4', $label, array(
        $para($label, 'small fw-semibold text-body-secondary mb-2'),
        $el('div', 'pg-field-value', lang('Value'), array(), array_merge(array('text' => $sample), $bind($token))),
    ));
};
// The name of whoever sent the contact form.
$person_name = function () use ($span, $bind) {
    return array(
        $span(lang('Jane'), '', lang('First Name'), $bind('first_name')),
        $span(' ', '', lang('Text')),
        $span(lang('Cooper'), '', lang('Last Name'), $bind('last_name')),
    );
};

// ── The parts every page repeats ────────────────────────────────────────

// The dark bar on top: the skip link, quick links, the theme toggle, the
// language switcher and the sign-in. A shared component.
$topbar = function () use ($el, $link, $icon, $span, $attr, $lang_switcher, $widget) {
    $quick = function ($icon_name, $text, $href) use ($el, $icon, $span) {
        $children = array();
        if ($icon_name !== '') $children[] = $icon($icon_name);
        $children[] = $span($text, '', lang('Link Text'));
        return $el('a', 'd-inline-flex align-items-center gap-1 py-1 link-body-emphasis text-decoration-none', $text, $children, array('href' => $href));
    };
    return $el('div', 'pn-topbar border-bottom small bg-body text-body', lang('Top Bar'), array(
        $link(lang('Skip to main content'), '#main', 'visually-hidden-focusable position-absolute p-2 bg-body text-body'),
        $el('div', 'container d-flex align-items-center gap-3 py-1', lang('Top Bar Container'), array(
            $el('nav', 'd-none d-md-flex gap-3', lang('Quick Links'), array(
                $quick('bi-envelope', lang('Newsletter'), '{{page:newsletter}}'),
                $quick('bi-shield-lock', lang('Send a tip'), '{{page:tips}}'),
                $quick('', lang('Our writers'), '{{page:author}}'),
            ), array('_attrs' => array($attr('aria-label', lang('Quick Links'))))),
            // A named region, so moving by landmark reaches the theme
            // switch, the language and the sign-in.
            $el('div', 'ms-auto d-flex align-items-center gap-2', lang('Account Links'), array(
                // The palette's Theme Switcher (Toggle): the page loads its
                // script because of the marker.
                $el('div', 'd-inline-block', lang('Theme Switcher'), array(
                    $el('button', 'btn btn-outline-secondary btn-sm rounded-pill', lang('Theme Toggle Button'), array(
                        $icon('bi-moon-stars-fill'),
                    ), array('_attrs' => array(
                        $attr('type', 'button'),
                        $attr('aria-label', lang('Switch the theme')),
                        $attr('aria-pressed', 'false'),
                    ))),
                ), array('_attrs' => array($attr('data-pg-theme-switcher', 'toggle')))),
                $lang_switcher(),
                $widget('login_region'),
            ), array('_attrs' => array($attr('role', 'region'), $attr('aria-label', lang('Account Links'))))),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The masthead: the menu button (phone), the site's name and its line, the
// search and the way to subscribe. Sticky below lg.
$search_form = function ($id, $class) use ($el, $icon, $attr) {
    return $el('form', $class, lang('Search Form'), array(
        $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Search news'), '_attrs' => array($attr('for', $id)))),
        $el('div', 'input-group flex-nowrap', lang('Search Box'), array(
            $el('span', 'input-group-text bg-body', lang('Search Icon'), array($icon('bi-search'))),
            $el('input', 'form-control', lang('Field'), array(), array('id' => $id, '_attrs' => array(
                $attr('type', 'search'),
                $attr('name', 'query'),
                $attr('placeholder', lang('Search news…')),
            ))),
            // A filled grey button: its words read on both schemes of every
            // palette.
            $el('button', 'btn btn-secondary', lang('Search Button'), array(), array('text' => lang('Search'), '_attrs' => array($attr('type', 'submit')))),
        )),
    ), array('_attrs' => array($attr('action', '{{page:search}}'), $attr('method', 'get'), $attr('role', 'search'))));
};
$masthead = function () use ($el, $icon, $span, $attr, $search_form) {
    return $el('header', 'pn-masthead bg-body border-bottom', lang('Masthead'), array(
        $el('div', 'container d-flex align-items-center gap-2 gap-lg-4 py-2 py-lg-3', lang('Masthead Container'), array(
            $el('button', 'btn d-lg-none p-2', lang('Menu Button'), array($icon('bi-list', 'fs-3')), array('_attrs' => array(
                $attr('type', 'button'),
                $attr('data-bs-toggle', 'offcanvas'),
                $attr('data-bs-target', '#pn-menu'),
                $attr('aria-controls', 'pn-menu'),
                $attr('aria-label', lang('Open the menu')),
            ))),
            $el('a', 'pn-brand d-flex flex-column align-items-center align-items-lg-start text-decoration-none link-body-emphasis mx-auto mx-lg-0', lang('Brand'), array(
                $span('{{site_name}}', 'pn-wordmark text-uppercase text-truncate', lang('Name')),
                $span(lang('News with a sense of direction'), 'pn-tagline d-none d-sm-block text-body-secondary', lang('Tagline')),
            ), array('href' => '{{page:home}}')),
            $search_form('pn-q', 'd-none d-lg-flex ms-auto'),
            $el('a', 'btn d-lg-none p-2', lang('Search Link'), array($icon('bi-search', 'fs-4')), array(
                'href' => '{{page:search}}', '_attrs' => array($attr('aria-label', lang('Search'))))),
            $el('a', 'btn btn-primary d-none d-lg-inline-flex align-items-center gap-2', lang('Subscribe Button'), array(
                $icon('bi-envelope-paper'),
                $span(lang('Subscribe'), '', lang('Link Text')),
            ), array('href' => '{{page:newsletter}}')),
        )),
    ));
};

// The sections: the menu bar in the palette's colour (from lg; sticky), the
// pills a phone scrolls sideways and the menu drawer the masthead's button
// opens. The link to the page being served is marked on the server (smart
// active state). The drawer sits here and not in the masthead: the sticky
// masthead would put it under the drawer's backdrop.
$sections_nav = function () use ($el, $link, $icon, $span, $attr, $sections, $nav_wide_only, $search_form, $sr_only) {
    $items = array($el('li', 'nav-item', lang('Nav Item'), array(
        $el('a', 'pn-navlink nav-link text-reset', lang('Homepage'), array($icon('bi-house-door'), $sr_only(lang('Homepage'))), array('href' => '{{page:home}}')),
    )));
    $more_sections = array();
    foreach ($sections as $key => $s) {
        $wide = in_array($key, $nav_wide_only, true);
        $items[] = $el('li', $wide ? 'nav-item d-none d-xl-block' : 'nav-item', lang('Nav Item'), array(
            $link($s[0], '{{page:' . $key . '}}', 'pn-navlink nav-link text-reset'),
        ));
        if ($wide) {
            $more_sections[] = $el('li', 'd-xl-none', lang('Menu Item'), array($link($s[0], '{{page:' . $key . '}}', 'dropdown-item')));
        }
    }
    $more_items = $more_sections;
    $more_items[] = $el('li', 'd-xl-none', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
    foreach (array(
        array(lang('Latest news'), '{{page:blog}}'),
        array(lang('Our writers'), '{{page:author}}'),
        array(lang('Newsletter'), '{{page:newsletter}}'),
        array(lang('Send a tip'), '{{page:tips}}'),
        array(lang('About Us'), '{{page:about}}'),
    ) as $m) {
        $more_items[] = $el('li', '', lang('Menu Item'), array($link($m[0], $m[1], 'dropdown-item')));
    }
    // The More button has no dropdown-toggle class: its caret would take
    // the place of the bar under the link.
    $items[] = $el('li', 'nav-item dropdown', lang('More'), array(
        $el('button', 'pn-navlink nav-link text-reset', lang('More Button'), array(
            $span(lang('More'), '', lang('Text')),
            $icon('bi-chevron-down', 'small'),
        ), array('_attrs' => array(
            $attr('type', 'button'),
            $attr('data-bs-toggle', 'dropdown'),
            $attr('aria-expanded', 'false'),
        ))),
        $el('ul', 'dropdown-menu', lang('More Menu'), $more_items),
    ));

    $pills = array($el('li', '', lang('Nav Item'), array($link(lang('Homepage'), '{{page:home}}', 'pn-pill'))));
    foreach ($sections as $key => $s) {
        $pills[] = $el('li', '', lang('Nav Item'), array($link($s[0], '{{page:' . $key . '}}', 'pn-pill pn-sec-' . $key)));
    }

    $drawer = array($el('li', '', lang('Nav Item'), array($link(lang('Homepage'), '{{page:home}}', 'pn-drawer'))));
    foreach ($sections as $key => $s) {
        $drawer[] = $el('li', '', lang('Nav Item'), array($link($s[0], '{{page:' . $key . '}}', 'pn-drawer pn-sec-' . $key)));
    }
    $drawer[] = $el('li', '', lang('Nav Item'), array($link(lang('Latest news'), '{{page:blog}}', 'pn-drawer')));
    $drawer[] = $el('li', '', lang('Nav Item'), array($link(lang('Our writers'), '{{page:author}}', 'pn-drawer')));
    $drawer_more = array();
    foreach (array(
        array(lang('Newsletter'), '{{page:newsletter}}'),
        array(lang('Send a tip'), '{{page:tips}}'),
        array(lang('About Us'), '{{page:about}}'),
        array(lang('Contact us'), '{{page:contact}}'),
        array(lang('My Account'), '{{page:my_account}}'),
    ) as $m) {
        $drawer_more[] = $el('li', '', lang('Nav Item'), array($link($m[0], $m[1], 'pn-drawer')));
    }

    return $el('nav', 'pn-nav', lang('Sections Menu'), array(
        $el('div', 'text-bg-primary d-none d-lg-block', lang('Menu Bar'), array(
            $el('div', 'container', lang('Menu Bar Container'), array(
                $el('ul', 'nav flex-nowrap', lang('Nav Menu'), $items, array('smartActive' => true)),
            )),
        )),
        $el('div', 'd-lg-none bg-body border-bottom', lang('Section Pills'), array(
            $el('div', 'container', lang('Section Pills Container'), array(
                $el('ul', 'pn-strip list-unstyled mb-0 py-2', lang('Pill List'), $pills, array('smartActive' => true)),
            )),
        )),
        $el('div', 'offcanvas offcanvas-start', lang('Menu Drawer'), array(
            $el('div', 'offcanvas-header', lang('Drawer Header'), array(
                // Not a heading: the drawer comes before the page's h1, and
                // the page's outline should start there. The dialog takes its
                // name from it all the same (aria-labelledby).
                $el('p', 'h5 offcanvas-title', lang('Drawer Title'), array(), array('id' => 'pn-menu-title', 'text' => '{{site_name}}')),
                $el('button', 'btn-close', lang('Close Button'), array(), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-dismiss', 'offcanvas'),
                    $attr('aria-label', lang('Close')),
                ))),
            )),
            $el('div', 'offcanvas-body', lang('Drawer Body'), array(
                $search_form('pn-q2', 'mb-3'),
                $el('ul', 'list-unstyled mb-0', lang('Drawer Sections'), $drawer, array('smartActive' => true)),
                $el('p', 'h6 text-uppercase mt-4 mb-1', lang('Heading'), array(), array('text' => lang('More from us'))),
                $el('ul', 'list-unstyled mb-0', lang('Drawer Links'), $drawer_more, array('smartActive' => true)),
            )),
        ), array('id' => 'pn-menu', '_attrs' => array(
            $attr('tabindex', '-1'),
            $attr('aria-labelledby', 'pn-menu-title'),
        ))),
    ), array('_attrs' => array($attr('aria-label', lang('Sections')))));
};

// The breaking strip: the live articles the editors flag as breaking news
// in the last two days, moving from right to left (the ready-made News
// Ticker block's rules). Two lists with the same settings: the second
// repeats the first so the loop has no seam, hidden from screen readers and
// out of the tab order. It pauses on hover, on focus and with its button,
// stands still when the visitor asks for less motion, and leaves the page
// while nothing is flagged.
$breaking = function () use ($el, $icon, $span, $attr, $widget, $sr_only) {
    return $el('section', 'pg-marquee pn-breaking bg-body border-bottom', lang('Breaking Strip'), array(
        // On a phone the badge keeps its icon and the headlines get the
        // room; the word stays for screen readers.
        $el('div', 'container d-flex align-items-center gap-2 gap-sm-3 py-2', lang('Breaking Container'), array(
            $el('span', 'badge text-bg-danger d-inline-flex align-items-center gap-1 flex-shrink-0 pn-breaking-label', lang('Breaking Badge'), array(
                $icon('bi-lightning-charge-fill'),
                $span(lang('Breaking'), 'd-none d-sm-inline', lang('Text')),
                $span(lang('Breaking'), 'visually-hidden d-sm-none', lang('Text')),
            )),
            $el('div', 'pg-marquee-viewport pg-marquee-fade flex-grow-1', lang('Marquee Viewport'), array(
                $el('div', 'pg-marquee-track', lang('Marquee Track'), array(
                    $widget('breaking_main'),
                    $widget('breaking_copy'),
                )),
            ), array('_attrs' => array(
                $attr('role', 'region'),
                $attr('aria-label', lang('Breaking news headlines')),
                $attr('tabindex', '0'),
            ))),
            $el('div', 'pg-marquee-toggle flex-shrink-0', lang('Pause Control'), array(
                $el('input', 'btn-check pg-marquee-pause', lang('Field'), array(), array('id' => 'pn-breaking-pause', '_attrs' => array(
                    $attr('type', 'checkbox'),
                    $attr('autocomplete', 'off'),
                ))),
                $el('label', 'btn btn-sm btn-outline-secondary', lang('Pause Button'), array(
                    $icon('bi-pause-fill'),
                    $icon('bi-play-fill'),
                    $sr_only(lang('Pause the headlines')),
                ), array('_attrs' => array($attr('for', 'pn-breaking-pause')))),
            )),
        )),
    ), array('_attrs' => array($attr('aria-label', lang('Breaking news')))));
};

// The footer, on a dark band: the newsroom in a sentence and its channels,
// the sections, the newsroom's pages, the reader's own pages and the legal
// pages; under them the copyright.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $attr, $sections, $sr_only) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $column = function ($title, $class, $links) use ($el, $heading, $list) {
        return $el('div', $class, $title, array(
            $heading('h2', $title, 'h6 text-uppercase small fw-bold mb-3'),
            $list($links),
        ));
    };
    $social = function ($name, $label, $href) use ($el, $icon, $sr_only) {
        return $el('a', 'link-body-emphasis fs-5 p-1', $label, array($icon($name), $sr_only($label)), array('href' => $href));
    };
    $section_links = array();
    foreach ($sections as $key => $s) $section_links[] = $link($s[0], '{{page:' . $key . '}}', $plain);

    // The top rule shows the band's edge when the page itself is dark.
    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body border-top', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-lg-4', lang('About the Newsroom'), array(
                    $el('a', 'pn-wordmark d-inline-block text-uppercase text-decoration-none link-body-emphasis', lang('Brand'), array(), array(
                        'href' => '{{page:home}}', 'text' => '{{site_name}}')),
                    $para(lang('News with a sense of direction'), 'pn-tagline text-body-secondary mb-3'),
                    $para(lang('An independent newsroom covering the city, the country and the world.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-2', lang('Social Links'), array(
                        $social('bi-facebook', 'Facebook', '#'),
                        $social('bi-instagram', 'Instagram', '#'),
                        $social('bi-youtube', 'YouTube', '#'),
                        $social('bi-rss', lang('RSS Feed'), '{{page:blog}}?rss=true'),
                    )),
                )),
                $column(lang('Sections'), 'col-6 col-md-3 col-lg-2', $section_links),
                $column(lang('The newsroom'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('About Us'), '{{page:about}}', $plain),
                    $link(lang('Our writers'), '{{page:author}}', $plain),
                    $link(lang('Standards & Corrections'), '{{page:standards}}', $plain),
                    $link(lang('Send a tip'), '{{page:tips}}', $plain),
                    $link(lang('Write for us'), '{{page:about}}#write-for-us', $plain),
                    $link(lang('Contact us'), '{{page:contact}}', $plain),
                )),
                $column(lang('Your account'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('My Account'), '{{page:my_account}}', $plain),
                    $link(lang('My Articles'), '{{page:my_articles}}', $plain),
                    $link(lang('Newsletter'), '{{page:newsletter}}', $plain),
                    $link(lang('RSS Feed'), '{{page:blog}}?rss=true', $plain),
                    $link(lang('Search'), '{{page:search}}', $plain),
                )),
                $column(lang('Legal'), 'col-6 col-md-3 col-lg-2', array(
                    $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    $link(lang('Terms of Use'), '{{page:terms}}', $plain),
                    $link(lang('Accessibility'), '{{page:accessibility}}', $plain),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-2 border-top mt-4 pt-3 small', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'text-body-secondary mb-0'),
                $para(lang('All rights reserved.'), 'text-body-secondary mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The newsroom's own menu, under the sections menu on the staff pages. Each
// link names a page of its own: the smart active state ignores the part
// after #, so two links to one page would both be marked current.
$staff_bar = function () use ($el, $link, $span, $attr) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small bg-body', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center column-gap-4 row-gap-2 py-2', lang('Staff Bar Container'), array(
            $el('nav', 'nav nav-underline align-items-center column-gap-4 row-gap-1', lang('Staff Links'), array(
                $span(lang('Newsroom'), 'badge text-bg-primary', lang('Staff Badge')),
                $item('staff', lang('Newsroom desk')),
                $item('blog_new', lang('Write an article')),
                $item('inbox', lang('Inbox')),
                $item('staff_directory', lang('Staff Directory')),
                $item('staff_entry', lang('Add to the Staff Directory')),
            ), array('smartActive' => true, '_attrs' => array($attr('aria-label', lang('Newsroom'))))),
        )),
    ));
};

// A page of the site: the top bar, the masthead, the sections menu, the
// breaking strip (left off on the account, desk, staff and error pages),
// the page's own content and the footer.
$page = function ($main, $breaking = true) use ($root, $shared, $el) {
    $children = array($shared('topbar'), $shared('masthead'), $shared('sections_nav'));
    if ($breaking) $children[] = $shared('breaking');
    $children[] = $el('main', '', lang('Main Content'), $main, array('id' => 'main'));
    $children[] = $shared('footer');
    return $root($children);
};
// A page of the newsroom: the staff bar under the sections menu, no
// breaking strip.
$staff_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('topbar'),
        $shared('masthead'),
        $shared('sections_nav'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main, array('id' => 'main')),
        $shared('footer'),
    ));
};

// The top of the inner pages: where the page sits, its title and one
// sentence, and whatever the page adds under them. $wrap adds classes to
// the header (a section's pn-sec-<key>), $title_class sets the title's.
$page_header = function ($title, $lead = '', $more = array(), $wrap = '', $title_class = 'display-6 mb-2') use ($el, $heading, $para, $link, $container, $attr) {
    $children = array(
        $el('nav', '', lang('Breadcrumb'), array(
            $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-body-emphasis link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover'))),
                $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb trail'))))),
        $heading('h1', $title, $title_class),
    );
    if ($lead !== '') $children[] = $para($lead, 'pn-dek mb-0');
    return $el('header', trim('py-4 border-bottom ' . $wrap), lang('Page Header'), array(
        $container(array_merge($children, $more)),
    ));
};

// The head of a block: its title with the section's rule, and a link to the
// rest. Inside a .pn-sec-<key> wrapper the title wears that section's
// colour.
$sec_head = function ($title, $more_text = '', $more_href = '', $tag = 'h2') use ($el, $heading, $icon, $span) {
    $children = array($heading($tag, $title, 'pn-sec-title'));
    if ($more_text !== '') {
        $children[] = $el('a', 'ms-auto d-inline-flex align-items-center gap-1 pb-2 small fw-bold link-body-emphasis text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex align-items-end gap-3 mb-3 border-bottom', lang('Section Header'), $children);
};

// ── E-mail pages ────────────────────────────────────────────────────────
// Sent as the body of an e-mail, never visited. E-mail programs drop linked
// stylesheets, so the few rules that shape them are written on the elements
// next to the Bootstrap classes; a palette colour is written twice, plain
// first, so a program that blocks the linked stylesheet still has one.
$mail_style = function ($css) use ($attr) {
    return array('_attrs' => array($attr('style', $css)));
};
$mail_page = function ($children) use ($el, $shared, $mail_style) {
    return array('type' => 'root', 'props' => array('cssClass' => ''), 'children' => array(
        $el('div', 'bg-body-tertiary py-4 px-3', lang('E-mail Frame'), array(
            $el('div', 'mx-auto bg-body border overflow-hidden', lang('E-mail Card'), array(
                $shared('email_header'),
                $el('div', 'p-4', lang('E-mail Body'), $children, $mail_style('padding:24px')),
                $shared('email_footer'),
            ), $mail_style('max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #d8dfe9;overflow:hidden')),
        ), $mail_style('background:#f1f4f9;padding:24px 12px;font-family:Public Sans,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#0e1730')),
    ));
};
$mail_heading = function ($text) use ($heading, $attr) {
    return $heading('h1', $text, 'h4 fw-bold mb-3', array('_attrs' => array($attr('style', 'font-size:22px;font-weight:700;margin:0 0 16px;color:#0e1730'))));
};
$mail_para = function ($text, $class = 'mb-3', $style = 'margin:0 0 16px') use ($para, $attr) {
    return $para($text, $class, array('_attrs' => array($attr('style', $style))));
};
// The small print at the end of an e-mail.
$mail_small = function ($text) use ($mail_para) {
    return $mail_para($text, 'small text-body-secondary mb-0', 'font-size:13px;color:#4a5568;margin:0');
};
$mail_button = function ($text, $href, $extra = array()) use ($el, $link, $mail_style, $attr) {
    return $el('p', 'my-4', lang('Button'), array(
        $link($text, $href, 'btn btn-primary px-4', array($attr('style',
            'display:inline-block;padding:10px 22px;font-weight:600;text-decoration:none;text-transform:none;'
            . 'background-color:#0047ab;background-color:var(--bs-primary,#0047ab);color:#ffffff;color:var(--pg-on-primary,#ffffff)')), $extra),
    ), $mail_style('margin:24px 0'));
};
$mail_line = function ($label, $values) use ($el, $span, $mail_style, $attr) {
    return $el('p', 'pg-hide-if-empty mb-1', lang('Line'), array(
        $span($label . ': ', 'text-body-secondary', lang('Label'), array('_attrs' => array($attr('style', 'color:#4a5568')))),
        $el('span', 'pg-field-value fw-semibold', lang('Value'), $values),
    ), $mail_style('margin:0 0 4px'));
};
$mail_box = function ($children, $name) use ($el, $mail_style) {
    return $el('div', 'bg-body-tertiary border p-3 my-3', $name, $children,
        $mail_style('background:#f1f4f9;border:1px solid #d8dfe9;padding:16px;margin:16px 0'));
};
$mail_reference = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'small text-body-secondary mt-4 mb-0', lang('Reference'), array(
        $span(lang('Reference Code') . ': ', '', lang('Label')),
        $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
    ), $mail_style('font-size:13px;color:#4a5568;margin:24px 0 0'));
};
// The member's first name after "Hello,", in an element of its own: a
// translated page matches whole texts.
$mail_account_greeting = function () use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello,'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
        $span(lang('Jane'), '', lang('First Name'), $bind('__user_first_name')),
    ), $mail_style('margin:0 0 16px'));
};
$mail_settings = array('folder' => 'private', 'search' => false, 'sitemap' => false, 'noindex' => true);

// ── Pages ───────────────────────────────────────────────────────────────

$pages = array();

// A band of a page: a section around a container.
$band = function ($name, $children, $class = 'py-4 py-lg-5', $extra = array()) use ($el, $container) {
    return $el('section', $class, $name, array($container($children)), $extra);
};
// The head of a block whose title links to the block's page.
$sec_link_head = function ($title, $href, $tag = 'h2') use ($el, $link) {
    return $el('div', 'd-flex align-items-end gap-3 mb-3 border-bottom', lang('Section Header'), array(
        $el($tag, 'pn-sec-title', lang('Title'), array($link($title, $href, ''))),
    ));
};
// A list with a check before each line.
$check_list = function ($items) use ($el, $icon, $span) {
    $children = array();
    foreach ($items as $text) {
        $children[] = $el('li', 'd-flex gap-2 mb-2', lang('List Item'), array(
            $icon('bi-check2', 'text-primary'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', 'list-unstyled mb-0', lang('Check List'), $children);
};
// A box of the side column: a title and what goes under it.
$side_card = function ($name, $title, $children, $class = 'p-4 border bg-body mb-4', $extra = array()) use ($el, $heading) {
    return $el('div', $class, $name, array_merge(array($heading('h2', $title, 'h5 fw-bold mb-3')), $children), $extra);
};
// A part of a static page: an anchor, a title and its paragraphs, then
// whatever else it holds.
$prose = function ($id, $name, $title, $paras, $more = array()) use ($el, $heading, $para) {
    $children = array($heading('h2', $title, 'h3 mb-3'));
    foreach ($paras as $text) $children[] = $para($text, 'mb-3');
    return $el('section', 'pb-4 mb-4 border-bottom', $name, array_merge($children, $more), array('id' => $id));
};
// A sample photo at the top of a static page's side column. It is a random
// picture, so it is decorative (no alt) until the newsroom puts its own in.
$side_photo = function ($seed) use ($el, $image, $photo, $lazy) {
    return $el('figure', 'mb-4', lang('Picture'), array(
        $image($photo($seed, 800, 450), '', 'img-fluid w-100 rounded object-fit-cover pg-ratio-16x9', $lazy),
    ));
};
// The note under the legal pages' sample text.
$sample_note = function () use ($el, $icon, $span, $attr) {
    return $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Sample Text Note'), array(
        $icon('bi-info-circle'),
        $span(lang('Sample text: adapt it to your newsroom and to the law that applies to you before you publish.'), '', lang('Text')),
    ), array('_attrs' => array($attr('role', 'note'))));
};
// A static page: its header, then its parts in the reading column and a
// side column beside them.
$static_page = function ($title, $lead, $parts, $side = array()) use ($page, $page_header, $band, $row, $col) {
    return $page(array(
        $page_header($title, $lead),
        $band(lang('Content'), array(
            $row(array(
                $col($parts, '', '8'),
                $col($side, '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    ));
};
// A writer's card: the portrait, the name linking to the writer's own
// articles ($href) and the beat; $with_line adds the writer's one line.
$writer_card = function ($key, $href, $with_line = false) use ($el, $image, $link, $para, $attr, $portrait, $writer_name, $writers) {
    $words = array(
        $el('h3', 'h6 fw-bold mb-1', lang('Name'), array($link($writer_name($key), $href, 'link-body-emphasis'))),
        $para($writers[$key][0], 'small text-body-secondary ' . ($with_line ? 'mb-1' : 'mb-0')),
    );
    if ($with_line) $words[] = $para($writers[$key][1], 'small mb-0');
    return $el('div', 'd-flex gap-3 h-100 p-3 bg-body border', lang('Writer Card'), array(
        $image($portrait($writer_name($key), 192), '', 'pg-circle-60 rounded-circle object-fit-cover flex-shrink-0', array('_attrs' => array($attr('loading', 'lazy')))),
        $el('div', 'pn-min-0', lang('Words'), $words),
    ));
};

// The front page: the lead stories, the featured row, the U.S. and World
// blocks beside the most read, the columnists, the newsletter, a tile for
// each other section and the newest articles. Its RSS feed is the lead
// stories' (the first list on the page); the full feed is linked below.
$grid_tiles = array();
foreach ($sections as $key => $s) {
    if (in_array($key, array('us', 'world', 'opinion'), true)) continue;
    $grid_tiles[] = $col(array(
        $sec_link_head($s[0], '{{page:' . $key . '}}'),
        $widget('home_' . $key),
    ), '', '3', '6', '12', 'pn-sec-' . $key);
}
$grid_tiles[] = $col(array(
    $el('div', 'p-4 bg-body-tertiary border', lang('Tip Tile'), array(
        $heading('h2', lang('Send us a tip'), 'h5 fw-bold mb-2'),
        $para(lang('Something we should look into? Our editors read every tip.'), 'small mb-3'),
        $link(lang('Send a tip'), '{{page:tips}}', 'btn btn-sm btn-primary'),
    )),
), '', '3', '6');
$pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('News, Analysis and Opinion'),
    'meta_description' => lang('The news of the city, the country and the world: the lead stories, every section, our columnists and the most read articles.'),
    // Neighbouring bands on the same white surface share one gap: the upper
    // one has no bottom padding.
    'tree'             => $page(array(
        $heading('h1', '{{site_name}}', 'visually-hidden'),
        $band(lang('Top Stories'), array(
            $heading('h2', lang('Top stories'), 'visually-hidden'),
            $widget('home_lead'),
        ), 'pt-3 pt-lg-4'),
        $band(lang('Featured'), array(
            $sec_head(lang('Featured')),
            $widget('home_featured'),
        ), 'pt-4 pt-lg-5'),
        $band(lang('U.S. and World'), array(
            $row(array(
                $col(array(
                    $el('div', 'pn-sec-us mb-5', lang('U.S.'), array(
                        $sec_link_head($sections['us'][0], '{{page:us}}'),
                        $widget('home_us'),
                    )),
                    $el('div', 'pn-sec-world', lang('World'), array(
                        $sec_link_head($sections['world'][0], '{{page:world}}'),
                        $widget('home_world'),
                    )),
                ), '', '8'),
                $col(array($widget('most_read')), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
        $band(lang('Columnists'), array(
            $el('div', 'pn-sec-opinion', lang('Opinion'), array(
                $sec_head(lang('Opinion'), lang('All opinion'), '{{page:opinion}}'),
                $widget('home_opinion'),
            )),
        ), 'py-4 py-lg-5 bg-body-tertiary'),
        $widget('newsletter_band'),
        $band(lang('Sections'), array(
            $row($grid_tiles, '4', '', 'g-lg-5'),
        ), 'pt-4 pt-lg-5'),
        $band(lang('Latest news'), array(
            $row(array(
                $col(array(
                    $sec_head(lang('Latest news'), lang('All the latest news'), '{{page:blog}}'),
                    $widget('home_posts'),
                ), '', '8'),
                $col(array(
                    $side_card(lang('Follow Us'), lang('Follow us'), array(
                        $para(lang('Read us the way that suits you.'), 'small text-body-secondary mb-3'),
                        $el('ul', 'list-unstyled mb-0', lang('Links'), array(
                            $el('li', 'mb-2', lang('List Item'), array($el('a', 'd-inline-flex align-items-center gap-2 link-body-emphasis', lang('Link'), array($icon('bi-rss'), $span(lang('RSS Feed'), '', lang('Link Text'))), array('href' => '{{page:blog}}?rss=true')))),
                            $el('li', 'mb-2', lang('List Item'), array($el('a', 'd-inline-flex align-items-center gap-2 link-body-emphasis', lang('Link'), array($icon('bi-envelope-paper'), $span(lang('Newsletter'), '', lang('Link Text'))), array('href' => '{{page:newsletter}}')))),
                            $el('li', '', lang('List Item'), array($el('a', 'd-inline-flex align-items-center gap-2 link-body-emphasis', lang('Link'), array($icon('bi-people'), $span(lang('Our writers'), '', lang('Link Text'))), array('href' => '{{page:author}}')))),
                        )),
                    ), 'p-4 border bg-body'),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// One page for each section: its title in the section's colour, its line,
// the section's articles (its RSS feed) and the most read beside them.
foreach ($sections as $key => $s) {
    if ($key === 'opinion') continue;
    $pages[] = array(
        'key'              => $key,
        'name'             => $s[2],
        'folder'           => 'public',
        'title'            => $s[0],
        'meta_description' => $s[1],
        'tree'             => $page(array(
            $page_header($s[0], $s[1], array(), 'pn-sec-' . $key, 'pn-sec-title display-6 mb-3'),
            $band(lang('News Stories'), array(
                $row(array(
                    $col(array($widget('section_' . $key)), '', '8', '', '12', 'pn-sec-' . $key),
                    $col(array($widget('most_read'), $widget('newsletter_box')), '', '4'),
                ), '4', '', 'g-lg-5'),
            ), 'py-3 py-lg-4'),
        )),
    );
}

// Opinion: the columnists, each name linking to the writer's own columns,
// then the newest columns.
$columnist_cards = array();
foreach ($columnists as $key) $columnist_cards[] = $col(array($writer_card($key, $writer_url($key))), '4');
$pages[] = array(
    'key'              => 'opinion',
    'name'             => $sections['opinion'][2],
    'folder'           => 'public',
    'title'            => $sections['opinion'][0],
    'meta_description' => $sections['opinion'][1],
    'tree'             => $page(array(
        $page_header($sections['opinion'][0], $sections['opinion'][1], array(), 'pn-sec-opinion', 'pn-sec-title display-6 mb-3'),
        $band(lang('Our columnists'), array(
            $heading('h2', lang('Our columnists'), 'h4 mb-3'),
            $row($columnist_cards, '3'),
        ), 'py-4 bg-body-tertiary', array('id' => 'columnists')),
        $band(lang('Latest columns'), array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Latest columns'), 'h4 mb-3'),
                    $widget('opinion_list'),
                ), '', '8', '', '12', 'pn-sec-opinion'),
                $col(array($widget('most_read'), $widget('newsletter_box')), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// The latest news: every live article, newest first, with a search; the
// site's full RSS feed.
$pages[] = array(
    'key'              => 'blog',
    'name'             => lang('latest-news'),
    'folder'           => 'public',
    'title'            => lang('Latest news'),
    'meta_description' => lang('Every story we have published, newest first.'),
    'tree'             => $page(array(
        $page_header(lang('Latest news'), lang('Every story we have published, newest first.')),
        $band(lang('News Stories'), array(
            $row(array(
                $col(array($widget('blog_list')), '', '8'),
                $col(array($widget('most_read')), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'py-3 py-lg-4'),
    )),
);

// The article (?r=): the article with the most read beside it (both in the
// record view's row), then its comments (signed in, held until an editor
// approves them) in the article's column, then the newest articles. The
// bare page shows "not found", so it stays out of the sitemap; its articles
// get there through the lists.
$pages[] = array(
    'key'              => 'blog_post',
    'name'             => lang('article'),
    'folder'           => 'public',
    'title'            => lang('News article'),
    'meta_description' => lang('An article from the newsroom.'),
    'sitemap'          => false,
    'comments'         => array('label' => lang('Comment'), 'allow_new' => true, 'auto_publish' => false, 'show_date' => true, 'login' => true,
                                'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new comment on an article you follow'),
                                'notify_email' => '{{site_email}}'),
    'tree'             => $page(array(
        $widget('blog_post'),
        // The comments in the article's column (their heading closes the
        // article widget, so it leaves with a missing article; the row's
        // gutter is the widget row's, so the columns line up), then the
        // newest articles across the page.
        $el('section', 'pb-5', lang('Comments'), array(
            $container(array(
                $row(array(
                    $col(array($comments_block()), '', '8'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $band(lang('Latest news'), array(
            $sec_head(lang('Latest news'), lang('All the latest news'), '{{page:blog}}'),
            $widget('article_more'),
        ), 'py-5 border-top'),
    )),
);

// The writers: with ?author=<name> the writer's own live articles (the
// list takes the name from the address); always the page's heading and the
// directory of the writers, each name linking to that address.
$writer_cards = array();
foreach (array_keys($writers) as $key) $writer_cards[] = $col(array($writer_card($key, $writer_url($key), true)), '6');
$pages[] = array(
    'key'              => 'author',
    'name'             => lang('writers'),
    'folder'           => 'public',
    'title'            => lang('Our writers'),
    'meta_description' => lang('Reporters, editors and columnists of the newsroom.'),
    'tree'             => $page(array(
        $page_header(lang('Our writers'), lang('Reporters, editors and columnists of the newsroom.')),
        $band(lang('Writers'), array(
            $row(array(
                $col(array(
                    $widget('author_list'),
                    $el('section', '', lang('All Our Writers'), array(
                        $heading('h2', lang('All our writers'), 'h4 mb-3'),
                        $row($writer_cards, '3'),
                    ), array('id' => 'writers')),
                ), '', '8'),
                $col(array($widget('most_read')), '', '4'),
            ), '4', '', 'g-lg-5'),
        ), 'py-3 py-lg-4'),
    )),
);

// Search: the whole site for ?query= (the masthead's search sends it here).
$browse = array();
foreach ($sections as $key => $s) {
    $browse[] = $el('li', '', lang('List Item'), array($link($s[0], '{{page:' . $key . '}}', 'pn-drawer pn-sec-' . $key)));
}
$pages[] = array(
    'key'              => 'search',
    'name'             => lang('search'),
    'folder'           => 'public',
    'title'            => lang('Search'),
    'meta_description' => lang('Search every article and page of the site.'),
    'tree'             => $page(array(
        $page_header(lang('Search'), lang('Search every article and page of the site.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('site_search')), '', '8'),
                $col(array(
                    $side_card(lang('Browse the Sections'), lang('Browse the sections'), array(
                        $el('ul', 'list-unstyled mb-0', lang('Links'), $browse),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// About us: who we are, the newsroom, how outside writers get access to
// the author desk, and the ways to reach us.
$about_writers = array();
foreach (array_keys($writers) as $key) $about_writers[] = $col(array($writer_card($key, '{{page:author}}#writers')), '6', '4');
$pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('An independent newsroom covering the city, the country and the world.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), lang('An independent newsroom covering the city, the country and the world.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $prose('mission', lang('Who We Are'), lang('Who we are'), array(
                        lang('We are a small, independent newsroom. We report on the decisions, the people and the places that shape daily life, and we explain what they mean for our readers.'),
                        lang('Our reporters work in the city, our editors check every article before it goes live, and our columnists write under their own names.'),
                    )),
                    $prose('newsroom', lang('The Newsroom'), lang('The newsroom'), array(
                        lang('Every section has its own reporters and an editor. These are the writers you meet most often on the site.'),
                    ), array(
                        $row($about_writers, '3', '', 'mb-3'),
                        $link(lang('Meet our writers'), '{{page:author}}#writers', 'fw-semibold link-body-emphasis'),
                    )),
                    $prose('write-for-us', lang('Write for Us'), lang('Write for us'), array(
                        lang('We publish articles from outside writers too. Write to us through the contact page with your idea and a few lines about yourself; when an editor opens the author desk for you, you send your articles from there.'),
                        lang('An editor reads every article, may ask for changes and decides when it goes live. You follow your article and talk with the desk on its own page.'),
                    ), array(
                        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Buttons'), array(
                            $link(lang('Write to the newsroom'), '{{page:contact}}', 'btn btn-primary'),
                            $link(lang('Submit an Article'), '{{page:blog_new}}', 'btn btn-secondary'),
                            $span(lang('The second button is for writers with access to the author desk.'), 'small text-body-secondary', lang('Note')),
                        )),
                    )),
                ), '', '8'),
                $col(array(
                    $side_photo('newsroom'),
                    $side_card(lang('Contact Strip'), lang('Talk to the newsroom'), array(
                        $para(lang('A question, a correction or a story idea? We read everything that reaches us.'), 'small mb-3'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                            $link(lang('Send a tip'), '{{page:tips}}', 'btn btn-sm btn-secondary'),
                        )),
                    ), 'p-4 bg-body-tertiary border'),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// Standards and corrections: independence, sources, and how a correction
// shows under the article it concerns.
$pages[] = array(
    'key'              => 'standards',
    'name'             => lang('standards-and-corrections'),
    'folder'           => 'public',
    'title'            => lang('Standards & Corrections'),
    'meta_description' => lang('How we report, how we choose our sources and how we correct our mistakes.'),
    'tree'             => $static_page(lang('Standards & Corrections'), lang('How we report, how we choose our sources and how we correct our mistakes.'), array(
        $prose('independence', lang('Independence'), lang('Independence'), array(
            lang('No advertiser, owner or official decides what we cover or how. Our reporters do not write about people or companies they have a personal stake in.'),
            lang('Opinion is kept apart from the news: columns are marked Opinion, and columnists do not comment on stories they report.'),
        )),
        $prose('sources', lang('Sources'), lang('Sources'), array(
            lang('We name our sources whenever we can. We keep a source\'s name out of an article only when the source would face real harm, when the information matters to the public and when an editor knows who the source is.'),
            lang('Every fact in an article is checked before it goes live, and we say so when something could not be confirmed.'),
        )),
        $prose('corrections', lang('Corrections'), lang('Corrections'), array(
            lang('When we get something wrong, we fix it and say so. A correction appears under the article it concerns, with what was wrong and what is right.'),
            lang('Tell us which article it is and what is wrong; an editor checks it and answers you.'),
        ), array(
            $link(lang('Report an error'), '{{page:contact}}', 'btn btn-primary'),
        )),
    ), array(
        $side_photo('standards'),
        // The about page's contact strip: the corrections section beside it
        // already has its own button.
        $side_card(lang('Contact Strip'), lang('Talk to the newsroom'), array(
            $para(lang('A question, a correction or a story idea? We read everything that reaches us.'), 'small mb-3'),
            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                $link(lang('Contact us'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                $link(lang('Send a tip'), '{{page:tips}}', 'btn btn-sm btn-secondary'),
            )),
        ), 'p-4 bg-body-tertiary border'),
    )),
);

// Contact: a message to the newsroom. What is sent opens the conversation
// page, and the answers follow there and in the inbox; a visitor without an
// account gets one.
$pages[] = array(
    'key'              => 'contact',
    'name'             => lang('contact-us'),
    'folder'           => 'public',
    'title'            => lang('Contact us'),
    'meta_description' => lang('A question for the newsroom, an error to report or an idea for a story: write to us, and our answer comes to your inbox.'),
    'form'             => array(
        'form_name'            => lang('Contact the Newsroom'),
        'confirmation_message' => lang('Thank you, your message has reached the newsroom. Our answer comes to your inbox.'),
        'confirmation_page_id' => '{{tab:conversation}}',
        'auto_registration'    => 1,
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New message to the newsroom: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_new_message}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('We received your message'),
        'confirm_page_id'      => '{{tab:email_message_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Contact us'), lang('A question for the newsroom, an error to report or an idea for a story: write to us, and our answer comes to your inbox.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'p-4 border bg-body', lang('Form Card'), array($widget('contact_form'))),
                ), '', '7'),
                $col(array(
                    $side_card(lang('Contact Details'), lang('The newsroom'), array(
                        $el('p', 'd-flex align-items-start gap-2 mb-2', lang('Address'), array(
                            $icon('bi-geo-alt', 'text-primary'),
                            $span(lang('100 Example Avenue, Suite 400, Denver, CO 80202'), '', lang('Text')),
                        )),
                        $el('p', 'd-flex align-items-center gap-2 mb-2', lang('Phone'), array(
                            $icon('bi-telephone', 'text-primary'),
                            $link('(303) 555-0142', 'tel:+13035550142', 'link-body-emphasis'),
                        )),
                        $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Email'), array(
                            $icon('bi-envelope', 'text-primary'),
                            $link('newsroom@example.com', 'mailto:newsroom@example.com', 'link-body-emphasis'),
                        )),
                    ), 'p-4 bg-body-tertiary border mb-4'),
                    $el('div', 'p-4 border', lang('Inbox Note'), array(
                        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                            $icon('bi-inbox', 'text-primary'),
                            $span(lang('Follow the answer in your inbox'), '', lang('Text')),
                        )),
                        $para(lang('Your message becomes a conversation: the page that opens after you send it, and your inbox, show our answers, and you can write back there. If you have no account yet, one is opened for you.'), 'small text-body-secondary mb-3'),
                        $link(lang('Open my inbox'), '{{page:inbox}}', 'btn btn-sm btn-secondary'),
                    )),
                ), '', '5'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// The newsletter: its page owns the form. A sign-up joins the Morning
// Briefing's contact group and gets the welcome e-mail. The box beside the
// section pages and the band on the front page send the same form.
$pages[] = array(
    'key'              => 'newsletter',
    'name'             => lang('newsletter'),
    'folder'           => 'public',
    'title'            => lang('Newsletter'),
    'meta_description' => lang('The Morning Briefing: the stories that matter today, in your inbox before breakfast.'),
    'form'             => array(
        'form_name'            => lang('Morning Briefing'),
        'confirmation_message' => lang('Thank you! The Morning Briefing is on its way to your inbox.'),
        'contact_group_id'     => '{{contact_group:newsletter}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Welcome to the Morning Briefing'),
        'confirm_page_id'      => '{{tab:email_welcome}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Newsletter'), lang('The Morning Briefing: the stories that matter today, in your inbox before breakfast.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'p-4 p-lg-5 border bg-body', lang('Form Card'), array($widget('newsletter_form'))),
                ), '', '7'),
                $col(array(
                    $side_card(lang('What You Get'), lang('What you get'), array(
                        $check_list(array(
                            lang('The top stories of the day, each with a short summary'),
                            lang('A note from our editors on what to watch'),
                            lang('One e-mail a day, and never your address to anyone else'),
                        )),
                    ), 'p-4 bg-body-tertiary border', array('id' => 'briefing')),
                ), '', '5'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// Tips: a separate form, with no account made for whoever sends it. A tip
// sent while signed in is tied to the account, and the page says so.
$signed_in_tip = lang('If you are signed in, this tip is linked to your account and our reply goes to your account\'s e-mail. To send it without your name, sign out first.');
$pages[] = array(
    'key'              => 'tips',
    'name'             => lang('send-a-tip'),
    'folder'           => 'public',
    'title'            => lang('Send a tip'),
    'meta_description' => lang('Something we should look into? Tell us what you know. Our editors read every tip.'),
    'form'             => array(
        'form_name'            => lang('News Tips'),
        'confirmation_message' => lang('Thank you. An editor reads every tip.'),
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New tip: ^^topic^^'),
        'notify_page_id'       => '{{tab:email_tip_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Thank you for your tip'),
        'confirm_page_id'      => '{{tab:email_tip_received}}',
    ),
    'tree'             => $page(array(
        $page_header(lang('Send a tip'), lang('Something we should look into? Tell us what you know. Our editors read every tip.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'alert alert-secondary d-flex gap-2 small', lang('Signed-in Note'), array(
                        $icon('bi-person-check'),
                        $span($signed_in_tip, '', lang('Text')),
                    ), array('_attrs' => array($attr('role', 'note')))),
                    $el('div', 'p-4 border bg-body', lang('Form Card'), array($widget('tips_form'))),
                ), '', '7'),
                $col(array(
                    $side_card(lang('How Tips Work'), lang('How tips work'), array(
                        $para(lang('Our editors read every tip and decide whether and how to follow it up. We write back when we need to know more.'), 'small'),
                        $para(lang('Your name and e-mail are optional when you are signed out. If you leave an e-mail address, we answer there.'), 'small'),
                        $para($signed_in_tip, 'small'),
                        $para(lang('We keep the people who send us tips out of our articles, but we cannot promise more than our own care. Do not send what you are not allowed to share.'), 'small mb-0'),
                    ), 'p-4 bg-body-tertiary border', array('id' => 'how-tips-work')),
                ), '', '5'),
            ), '4', '', 'g-lg-5'),
        )),
    )),
);

// The legal pages: sample text, each with a note to adapt it.
$pages[] = array(
    'key'              => 'privacy',
    'name'             => lang('privacy-policy'),
    'folder'           => 'public',
    'title'            => lang('Privacy Policy'),
    'meta_description' => lang('What we keep about you, why, and what you can ask of us.'),
    'tree'             => $static_page(lang('Privacy Policy'), lang('What we keep about you, why, and what you can ask of us.'), array(
        $sample_note(),
        $prose('your-data', lang('Your Data'), lang('Your data'), array(
            lang('When you open an account, we keep your name, your e-mail address and the settings you choose. When you comment, write to us or send a tip, we keep what you sent with your account or the address you gave.'),
            lang('We use your e-mail address to answer you, to send the newsletter you asked for and to tell you about replies you follow. We never sell it or give it to advertisers.'),
            lang('The site uses the cookies it needs to keep you signed in and to remember your choices. You can see and change your account data from My Account, and ask us to delete it.'),
        )),
    )),
);
$pages[] = array(
    'key'              => 'terms',
    'name'             => lang('terms-of-use'),
    'folder'           => 'public',
    'title'            => lang('Terms of Use'),
    'meta_description' => lang('The rules for reading, commenting and writing on this site.'),
    'tree'             => $static_page(lang('Terms of Use'), lang('The rules for reading, commenting and writing on this site.'), array(
        $sample_note(),
        $prose('using-the-site', lang('Using the Site'), lang('Using the site'), array(
            lang('You may read, share and link to our articles. Copying a whole article or its photos to another site needs our written permission.'),
            lang('Comments are read by an editor before they appear. We do not publish insults, threats, advertising or anything unlawful, and we may remove a comment or close an account that breaks these rules.'),
            lang('An article sent through the author desk stays its writer\'s own work; by sending it, the writer allows us to edit and publish it.'),
        )),
    )),
);
$pages[] = array(
    'key'              => 'accessibility',
    'name'             => lang('accessibility'),
    'folder'           => 'public',
    'title'            => lang('Accessibility'),
    'meta_description' => lang('How the site works with a keyboard, a screen reader, the dark theme and reduced motion.'),
    'tree'             => $static_page(lang('Accessibility'), lang('We want everyone to be able to read the news here.'), array(
        $sample_note(),
        $prose('accessibility-help', lang('How the Site Helps'), lang('How the site helps'), array(
            lang('Every page works with a keyboard alone: the first press of Tab shows a link that skips to the main content.'),
            lang('Words and buttons keep a readable contrast in the light and the dark theme; switch the theme with the button at the top of the page (a moon in the light theme, a sun in the dark one).'),
            lang('The breaking news strip stops while you point at it or move into it, has its own pause button, and stands still when your device asks for less motion.'),
            lang('If something on the site does not work for you, tell us and we will fix it.'),
        ), array(
            $link(lang('Contact us'), '{{page:contact}}', 'btn btn-primary'),
        )),
    )),
);

// Signing in and out (no breaking strip on the account pages).
$sign_page = function ($key, $name, $title, $widget_key) use ($page, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'public',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'tree'    => $page(array($widget($widget_key)), false),
    );
};
$pages[] = $sign_page('login', lang('login'), lang('Log In'), 'login');
$pages[] = $sign_page('register', lang('register'), lang('Sign Up'), 'register');
$pages[] = $sign_page('forgot_password', lang('forgot-password'), lang('Forgot Password'), 'forgot_password');
$pages[] = $sign_page('set_password', lang('set-password'), lang('Set Password'), 'set_password');
$pages[] = $sign_page('logout', lang('logout'), lang('Logout'), 'logout');

// My account: the reader's own pages, behind the registration folder.
$pages[] = array(
    'key'     => 'my_account',
    'name'    => lang('my-account'),
    'folder'  => 'registration',
    'title'   => lang('My Account'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array($widget('my_account'), $widget('account_invoices'), $widget('account_security')), false),
);
$pages[] = array_merge($sign_page('profile', lang('my-profile'), lang('My Profile'), 'profile'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('change_password', lang('change-password'), lang('Change Password'), 'change_password'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('email_preferences', lang('email-preferences'), lang('Email Preferences'), 'email_preferences'), array('folder' => 'registration'));
$pages[] = array_merge($sign_page('address_book', lang('address-book'), lang('Address Book'), 'address_book'), array('folder' => 'registration', 'requires' => 'ecommerce'));

// The inbox: the member's conversations with the newsroom (for the staff,
// everybody's), and the conversation page, where both sides answer as
// comments.
$pages[] = array(
    'key'     => 'inbox',
    'name'    => lang('inbox'),
    'folder'  => 'registration',
    'title'   => lang('Inbox'),
    'search'  => false,
    'sitemap' => false,
    'tree'    => $page(array(
        $page_header(lang('Inbox'), lang('The messages you sent the newsroom from the contact page, and our answers.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('inbox_list')), '', '8'),
                $col(array(
                    $el('div', 'p-4 bg-body-tertiary border mb-4', lang('Inbox Note'), array(
                        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                            $icon('bi-chat-left-text', 'text-primary'),
                            $span(lang('A question for the newsroom?'), '', lang('Text')),
                        )),
                        $para(lang('Write to us from the contact page; your message and our answers stay here, in one conversation.'), 'small text-body-secondary mb-3'),
                        $link(lang('New message'), '{{page:contact}}', 'btn btn-sm btn-primary'),
                    )),
                    $el('div', 'p-4 border', lang('Author Note'), array(
                        $heading('h2', lang('Writing for us?'), 'h6 fw-bold mb-2'),
                        $para(lang('Messages about an article you sent to the desk are written under the article itself, in My Articles.'), 'small text-body-secondary mb-3'),
                        $link(lang('My Articles'), '{{page:my_articles}}', 'btn btn-sm btn-secondary'),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    ), false),
);
$pages[] = array(
    'key'      => 'conversation',
    'name'     => lang('conversation'),
    'folder'   => 'registration',
    'title'    => lang('Conversation'),
    'search'   => false,
    'sitemap'  => false,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_reply}}', 'email_subject' => lang('There is a new reply to your conversation'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('conversation'),
        $comments_section(lang('Replies')),
    ), false),
);

// The author desk, in the contributors folder: the author's own articles
// (an editor's list is the newsroom desk) and the page of one article,
// where the author and the editors write to each other under it.
$pages[] = array(
    'key'     => 'my_articles',
    'name'    => lang('my-articles'),
    'folder'  => 'contributors',
    'title'   => lang('My Articles'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array(
        $page_header(lang('My Articles'), lang('The articles you sent to the desk, their status and the editors\' messages, newest first.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array($widget('my_articles_list')), '', '8'),
                $col(array(
                    $el('div', 'p-4 bg-body-tertiary border mb-4', lang('Write Another'), array(
                        $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Title'), array(
                            $icon('bi-pencil-square', 'text-primary'),
                            $span(lang('Another story?'), '', lang('Text')),
                        )),
                        $para(lang('Send it to the desk the same way; each article has its own page and its own messages.'), 'small text-body-secondary mb-3'),
                        $link(lang('Submit an Article'), '{{page:blog_new}}', 'btn btn-sm btn-primary'),
                    )),
                    $el('div', 'p-4 border', lang('Live on the site'), array(
                        $heading('h2', lang('Live on the site'), 'h6 fw-bold mb-2'),
                        $widget('my_articles_live'),
                    )),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    ), false),
);
// One article on the desk: what the author sent, its status, and the
// messages between the author and the editors. The form's confirmation
// page: an author lands here (?r=<code>) right after sending.
$pages[] = array(
    'key'      => 'submission',
    'name'     => lang('article-submission'),
    'folder'   => 'contributors',
    'title'    => lang('Article Submission'),
    'search'   => false,
    'sitemap'  => false,
    'noindex'  => true,
    'comments' => array('label' => lang('Message'), 'allow_new' => true, 'auto_publish' => true, 'show_date' => true, 'login' => true,
                        'email_page' => '{{tab:email_desk_message}}', 'email_subject' => lang('New message about an article on the desk'),
                        'notify_email' => '{{site_email}}'),
    'tree'     => $page(array(
        $widget('submission_view'),
        $comments_section(lang('Messages with the desk')),
    ), false),
);

// The newsroom: the desk's lists of articles and tips, the staff directory
// and its form, behind the private folder.
$staff_shortcut = function ($icon_name, $title, $text, $href) use ($el, $heading, $para, $icon, $col) {
    return $col(array(
        $el('a', 'd-flex align-items-start gap-3 h-100 p-4 bg-body-tertiary border text-decoration-none link-body-emphasis', $title, array(
            $icon($icon_name, 'fs-3 text-primary'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 fw-bold mb-1'),
                $para($text, 'small text-body-secondary mb-0'),
            )),
        ), array('href' => $href)),
    ), '6', '4');
};
// A list of the desk under its heading, with the anchor the jump list and
// the e-mails point at.
$desk_section = function ($id, $title, $text, $widget_key, $lg = '6') use ($el, $heading, $para, $widget) {
    return $el('section', 'col-12' . ($lg !== '' ? ' col-lg-' . $lg : ''), $title, array(
        $heading('h2', $title, 'h5 pn-sec-title mb-2'),
        $para($text, 'small text-body-secondary mb-3'),
        $widget($widget_key),
    ), array('id' => $id));
};
$pages[] = array(
    'key'     => 'staff',
    'name'    => lang('staff'),
    'folder'  => 'private',
    'title'   => lang('Newsroom desk'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $el('section', 'py-5 bg-body-tertiary border-bottom', lang('Staff Welcome'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $span(lang('Newsroom'), 'badge text-bg-primary mb-3', lang('Badge')),
                        $heading('h1', lang('Newsroom desk'), 'display-6 mb-3'),
                        $para(lang('Every article sent to the desk, from the newest arrival to the pieces live on the site, and the tips readers send. Open an article to read it; press Change Status there to move it on.'), 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Open the control panel'), $panel_url, 'btn btn-primary px-4'),
                            $link(lang('Write an article'), '{{page:blog_new}}', 'btn btn-secondary'),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 bg-body border', lang('Access Note'), array(
                            $heading('h2', lang('How the desk works'), 'h6 fw-bold mb-2'),
                            $el('ul', 'small mb-0 ps-3', lang('Notes'), array(
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('Authors: open the account under Users, then under Private Content Access tick the contributors folder.'))),
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('Editors: under Content and Forms, tick the private folder and switch on Reach the data submitted through forms. Administrators, designers and managers need neither.'))),
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('Tips and contact messages belong to the public folder. Editors read tips and open their attachments on this page; to open a tip in the control panel or to answer messages in the inbox, an editor also needs the public folder ticked, which lets them change the public pages too.'))),
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('To publish: open the article, press Change Status, choose "Live on the site" and a publication time, save, then write a message under the article. That message is the e-mail the author gets.'))),
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('Before you set an article live, check its text and the name on the byline: the byline decides which writer\'s page and feed the article joins.'))),
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('The newest Lead Story is the large one on the front page.'))),
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('If you rename a status in the control panel, change the list filters with it; otherwise articles leave the site.'))),
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('Site search changes only when the index is rebuilt: after a scheduled article goes live, and after you take an article off the site, run Update Search Index in the control panel. Until then search shows the old state, and an old headline also matches every page that showed it in the breaking strip, the most read box or the newest articles.'))),
                                $el('li', 'mb-2', lang('Note'), array(), array('text' => lang('The article\'s structured data and the RSS feeds name the time it reached the desk, not the publication time, and feeds order articles by that time; the structured data also names the account that sent it, not the byline.'))),
                                $el('li', '', lang('Note'), array(), array('text' => lang('A photo uploaded with an article opens only for the newsroom while the article is not live. Pictures that an author\'s text loads from other sites are fetched when you open the piece.'))),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        // The jump list: plain anchors, outside any smart active menu (it
        // would mark every one of them as the current page).
        $el('nav', 'border-bottom', lang('On this page'), array(
            $el('div', 'container', lang('Container'), array(
                $el('ul', 'nav nav-underline column-gap-4 small', lang('Links'), array(
                    $el('li', 'nav-item', lang('Menu Item'), array($link(lang('Waiting for review'), '#waiting', 'nav-link'))),
                    $el('li', 'nav-item', lang('Menu Item'), array($link(lang('News Tips'), '#tips', 'nav-link'))),
                    $el('li', 'nav-item', lang('Menu Item'), array($link(lang('Every article'), '#every-article', 'nav-link'))),
                )),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('On this page'))))),
        $band(lang('Desk'), array(
            $row(array(
                $desk_section('waiting', lang('Waiting for review'), lang('New articles, the oldest first.'), 'staff_queue'),
                $desk_section('being-edited', lang('Being edited'), lang('Articles in review or waiting for the author\'s changes.'), 'staff_in_progress'),
                $desk_section('scheduled', lang('Scheduled to go live'), lang('Live articles whose publication time has not come yet, the next first.'), 'staff_scheduled'),
                $desk_section('no-time', lang('Live, but no publication time'), lang('Set live without a publication time, so readers do not see them yet. Give each one a time.'), 'staff_no_time'),
                $desk_section('live-now', lang('Live now'), lang('What readers see, the newest first. A row opens the article as readers see it.'), 'staff_published'),
                $desk_section('tips', lang('News Tips'), lang('What readers sent from the tip page, the newest first, each with its whole text and any attachment.'), 'staff_tips'),
                $desk_section('every-article', lang('Every article'), lang('Every article on the desk in any status, with a search.'), 'staff_all', ''),
            ), '4', '', 'g-lg-5'),
        )),
        $band(lang('Shortcuts'), array(
            $sec_head(lang('Shortcuts')),
            $row(array(
                $staff_shortcut('bi-pencil-square', lang('Write an article'), lang('The author desk\'s form; what you send waits for review like any article.'), '{{page:blog_new}}'),
                $staff_shortcut('bi-inbox', lang('Inbox'), lang('Every message from the contact page. Answer below each one.'), '{{page:inbox}}'),
                $staff_shortcut('bi-person-lines-fill', lang('Staff Directory'), lang('The newsroom\'s people and how to reach them.'), '{{page:staff_directory}}'),
                $staff_shortcut('bi-person-plus', lang('Add to the Staff Directory'), lang('Add a colleague to the newsroom\'s directory.'), '{{page:staff_entry}}'),
                $staff_shortcut('bi-speedometer2', lang('Control panel'), lang('Statuses, publication times, the newsletter and every form\'s records.'), $panel_url),
            ), '4'),
        ), 'pb-5'),
    )),
);
// The newsroom's directory, for the staff only (it lists e-mails and
// phones), and the form that adds a person to it.
$pages[] = array(
    'key'     => 'staff_directory',
    'name'    => lang('staff-directory'),
    'folder'  => 'private',
    'title'   => lang('Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $staff_page(array(
        $page_header(lang('Staff Directory'), lang('The newsroom\'s editors, reporters and staff, and how to reach them. Only the newsroom sees this page.')),
        $band(lang('Directory'), array($widget('staff_directory'))),
    )),
);
$pages[] = array(
    'key'     => 'staff_entry',
    'name'    => lang('add-to-staff-directory'),
    'folder'  => 'private',
    'title'   => lang('Add to the Staff Directory'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('Newsroom Directory'),
        'confirmation_message' => lang('The person is in the staff directory.'),
        'confirmation_page_id' => '{{tab:staff_directory}}',
    ),
    'tree'    => $staff_page(array(
        $page_header(lang('Add to the Staff Directory'), lang('Add a colleague to the newsroom\'s directory. Only the newsroom sees it.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'p-4 border bg-body', lang('Form Card'), array($widget('staff_form'))),
                ), '', '8'),
            ), '', 'center'),
        )),
    )),
);

// ── The sample articles ─────────────────────────────────────────────────
// Twenty-four live articles the News Articles form opens with, newest
// first, so the front page, every section, the writers and the strip are
// filled on the first day. Each is a record like any other, edited or
// deleted in the control panel. The seeder dates record i (4i + 1) days
// back; the publication time follows it by three hours, and the newest is
// two hours old, so it shows in the breaking strip for about two days. The
// texts name no month, season or weekday: the dates move with the install.
// The pictures are random sample photos, so every credit says so and only
// one caption shows, asking for a real one.
$article_body = function ($paragraphs) {
    return implode("\n\n", $paragraphs);
};
$sample_article = function ($i, $section, $placement, $breaking, $dateline, $writer, $minutes, $title, $summary, $paragraphs, $seed, $tags, $more = array())
    use ($sections, $placements, $live_value, $writers, $writer_name, $portrait, $photo, $article_body) {
    $publish = $i === 0 ? time() - 2 * 3600 : time() - (4 * $i + 1) * 86400 + 3 * 3600;
    return array_merge(array(
        'section'         => $sections[$section][0],
        'title'           => $title,
        'summary'         => $summary,
        'dateline'        => $dateline,
        'body'            => $article_body($paragraphs),
        'cover_image'     => $photo($seed, 1200, 675),
        'image_caption'   => '',
        'image_credit'    => lang('Sample photo'),
        'tags'            => $tags,
        'byline'          => $writer_name($writer),
        'byline_bio'      => $writers[$writer][1],
        'byline_photo'    => $portrait($writer_name($writer), 192),
        'rights'          => 'yes',
        'status'          => $live_value,
        'publish_date'    => date('Y-m-d H:i', $publish),
        'placement'       => $placements[$placement],
        'breaking'        => $breaking ? lang('Yes') : lang('No'),
        'reading_time'    => (string)$minutes,
    ), $more);
};
$sample_articles = array(
    $sample_article(0, 'us', 0, true, lang('Denver'), 'Jane Cooper', 3,
        lang('City council approves a plan to plant 10,000 street trees'),
        lang('Planting begins in the neighborhoods with the least shade, and residents can ask for a tree in front of their home.'),
        array(
            lang('The city council approved a plan to plant 10,000 street trees over the next five years, starting in the neighborhoods with the least shade.'),
            lang('Residents can ask for a tree in front of their home through the city\'s website. Crews will water each new tree for its first two years.'),
            lang('The plan is paid for from the parks budget and a state grant, council members said.'),
        ), 'street-trees', lang('trees, city council')),
    $sample_article(1, 'us', 1, false, lang('Denver'), 'Jane Cooper', 5,
        lang('New light-rail line opens nine stations across downtown'),
        lang('Thousands rode the first trains on opening day, as the line links the river district with the university campus.'),
        array(
            lang('The first trains on the new light-rail line left the river district station at 5 a.m. on opening day, and by noon riders had filled every car.'),
            lang('The line adds nine stations and a park-and-ride lot. It cuts the trip from the university campus to downtown to 14 minutes, transit officials said.'),
            lang('Fares are the same as on the city\'s other lines for the first year. Trains run every eight minutes at rush hour and every fifteen minutes in the evening.'),
            lang('Shop owners near the new stations said they had already seen more foot traffic during the test runs.'),
        ), 'light-rail', lang('transit, downtown, city budget'),
        array('image_caption' => lang('Replace this picture with your own photo and caption.'))),
    $sample_article(2, 'world', 1, false, lang('London'), 'Emily Turner', 4,
        lang('Coastal cities agree on a shared fund to repair sea walls'),
        lang('Twelve port cities will pool money and engineers to rebuild defenses before the next storm season.'),
        array(
            lang('Mayors of twelve coastal cities signed an agreement to share a repair fund and a team of engineers for their sea walls and harbor gates.'),
            lang('The fund starts with contributions from each city and will be spent where inspections find the most urgent damage first.'),
            lang('Engineers said the shared approach lets smaller towns borrow equipment they could not afford on their own.'),
        ), 'sea-wall', lang('coasts, storms')),
    $sample_article(3, 'sports', 1, false, lang('Seattle'), 'Daniel Ross', 3,
        lang('Harbor Hawks rally after halftime for a 3–1 win'),
        lang('Two goals in six minutes turned the game in front of a sold-out home crowd.'),
        array(
            lang('The Harbor Hawks trailed at halftime but scored twice in six minutes after the break and added a third late to beat their rivals 3–1.'),
            lang('The win moves the club into second place with four games left in the regular season.'),
            lang('"We changed nothing at halftime except how fast we moved the ball," the head coach said after the game.'),
        ), 'hawks', lang('soccer, Harbor Hawks')),
    $sample_article(4, 'politics', 0, false, lang('Raleigh'), 'Jane Cooper', 4,
        lang('City council votes to keep branch libraries open until 9 p.m.'),
        lang('The longer hours begin next month at all eight branches after a unanimous vote.'),
        array(
            lang('The city council voted unanimously to extend evening hours at all eight branch libraries to 9 p.m. on weekdays.'),
            lang('Council members said the change answers a survey in which most residents asked for more time after work and school.'),
            lang('The library system will hire part-time staff for the extra hours. The cost is covered within this year\'s budget.'),
        ), 'library', lang('libraries, city council')),
    $sample_article(5, 'tech', 0, false, lang('Austin'), 'Samuel Reyes', 4,
        lang('Lab battery could double phone run time, researchers say'),
        lang('A university team reports a new electrode design that lasted twice as long in early tests.'),
        array(
            lang('Researchers at a state university lab say a new electrode design kept test batteries running about twice as long as today\'s phone batteries.'),
            lang('The team cautioned that the cells have only been tested in the lab and that larger trials are planned for next year.'),
            lang('If the results hold, they said, the design could also make batteries for electric bikes lighter.'),
        ), 'battery', lang('batteries, research')),
    $sample_article(6, 'health', 0, false, lang('Portland'), 'Nora Whitfield', 3,
        lang('Clinics add weekend hours as flu-shot demand climbs'),
        lang('Community clinics will open on weekend mornings for the next three months to shorten waits.'),
        array(
            lang('Community clinics across the city will open on weekend mornings for the next three months, after a rise in requests for flu shots.'),
            lang('No appointment is needed. Clinic staff recommend bringing an insurance card if you have one, though shots are offered either way.'),
            lang('Health officials said early vaccination is the simplest way to protect people at higher risk during flu season.'),
        ), 'clinic', lang('flu shots, clinics')),
    $sample_article(7, 'arts', 0, false, lang('Chicago'), 'Nora Whitfield', 4,
        lang('Book fair opens with 600 publishers and ten days of readings'),
        lang('Authors, small presses and children\'s workshops fill the convention center through next weekend.'),
        array(
            lang('The city\'s book fair opened its doors with 600 publishers, the most in its history, and ten days of readings and talks.'),
            lang('Small presses have a hall of their own this year, and the children\'s floor offers drawing and writing workshops every morning.'),
            lang('Entry is free on weekdays before noon.'),
        ), 'book-fair', lang('books, festivals')),
    $sample_article(8, 'opinion', 0, false, '', 'Nora Whitfield', 5,
        lang('What a city remembers when the trains arrive'),
        lang('A new rail line is a timetable, but also a promise about who gets to be on time.'),
        array(
            lang('Every new transit line starts as a map with dots and ends as a habit: the stop where you buy coffee, the platform where you meet a friend.'),
            lang('The test of the new line will not be its opening day but its ordinary weekdays. Will the trains come when the schedule says, and will the stations feel safe at night?'),
            lang('If the city gets those details right, the line will change more than travel times. It will change where people feel they can live and work.'),
        ), 'trains-column', lang('transit, cities')),
    $sample_article(9, 'economy', 2, false, lang('Pittsburgh'), 'Michael Brooks', 4,
        lang('Small manufacturers report their busiest month in three years'),
        lang('New orders rose for a second month in a row, a regional survey of factory owners found.'),
        array(
            lang('Small manufacturers in the region had their busiest month in three years, according to a monthly survey of factory owners.'),
            lang('New orders rose for the second month in a row, led by makers of building parts and kitchen equipment.'),
            lang('Owners said finding skilled workers remains their biggest challenge, and several plan to expand training programs.'),
        ), 'factory', lang('manufacturing, jobs'),
        array('correction' => lang('An earlier version of this article gave the wrong month for the survey\'s start.'))),
    $sample_article(10, 'world', 2, false, lang('Tokyo'), 'Emily Turner', 3,
        lang('Late-night trains added on festival weekends'),
        lang('Extra departures will run until 1 a.m. while the city\'s light festival lasts.'),
        array(
            lang('Rail operators will add late-night departures on festival weekends, running trains until 1 a.m.'),
            lang('The extra service follows record crowds at last year\'s light displays, when many visitors left early to catch the last train.'),
            lang('Station staff will hand out maps in several languages at the busiest stops.'),
        ), 'night-trains', lang('trains, festivals')),
    $sample_article(11, 'us', 2, false, lang('Columbus'), 'Samuel Reyes', 3,
        lang('Twelve pocket parks open on former parking lots'),
        lang('Small green spaces with benches and shade trees now dot the city\'s east side.'),
        array(
            lang('The city opened twelve pocket parks on former parking lots, each with benches, shade trees and a drinking fountain.'),
            lang('Neighbors chose the designs at public meetings last year. Several parks include small play areas.'),
            lang('The parks department said volunteers will help water the new trees during their first two years.'),
        ), 'pocket-park', lang('parks, neighborhoods')),
    $sample_article(12, 'opinion', 0, false, '', 'Michael Brooks', 5,
        lang('Reading the fine print on your next lease'),
        lang('Three clauses deserve a second look before you sign.'),
        array(
            lang('Most people read a lease the way they read a phone contract: quickly, and only the price.'),
            lang('Before you sign, look at who pays for repairs, how much notice either side must give, and what happens to your deposit when you move out.'),
            lang('Ask for any change in writing. A friendly promise at the viewing is easy to forget a year later.'),
        ), 'lease', lang('housing, renting')),
    $sample_article(13, 'science', 0, false, lang('Boise'), 'Samuel Reyes', 3,
        lang('Volunteer observers log a record 40,000 meteor sightings'),
        lang('A citizen-science project counted more shooting stars than ever during this year\'s big showers.'),
        array(
            lang('Volunteers logged more than 40,000 meteor sightings this year, a record for the citizen-science project that collects them.'),
            lang('Clear skies over the western states and a new phone app that times each sighting helped push the count higher.'),
            lang('Astronomers use the reports to map how meteor streams change from year to year.'),
        ), 'meteors', lang('astronomy, volunteers')),
    $sample_article(14, 'economy', 0, false, lang('Omaha'), 'Michael Brooks', 3,
        lang('Credit unions add weekend hours at 40 branches'),
        lang('Members can open accounts and get loan advice without taking time off work.'),
        array(
            lang('A group of local credit unions will open 40 branches on weekend mornings starting next month.'),
            lang('Members can open accounts, ask about loans and get help with online banking during the new hours.'),
            lang('The change follows member surveys in which weekday hours were the most common complaint.'),
        ), 'credit-union', lang('banking, weekends')),
    $sample_article(15, 'world', 0, false, lang('Berlin'), 'Emily Turner', 4,
        lang('Rooftop gardens now grow a fifth of a market\'s greens'),
        lang('Growers on twenty roofs supply lettuce and herbs to a neighborhood market.'),
        array(
            lang('Growers on twenty city rooftops now supply about a fifth of the lettuce and herbs sold at a neighborhood market.'),
            lang('The gardens use rainwater collected on the same roofs and sell to the market directly, so the produce travels only a few streets.'),
            lang('Organizers hope to add ten more roofs next year.'),
        ), 'rooftop', lang('gardens, food')),
    $sample_article(16, 'sports', 2, false, '', 'Daniel Ross', 3,
        lang('City marathon adds 5,000 spots and a second start wave'),
        lang('Registration for next year\'s race opens next week.'),
        array(
            lang('The city marathon will add 5,000 places and a second start wave when registration opens next week.'),
            lang('Organizers widened two turns on the course and added aid stations, so the larger field can spread out.'),
            lang('Volunteers are needed at every mile marker, and sign-ups are open on the race\'s website.'),
        ), 'marathon', lang('running, marathon')),
    $sample_article(17, 'opinion', 0, false, '', 'Emily Turner', 4,
        lang('The quiet logistics of a first year abroad'),
        lang('Living somewhere new is less about landmarks than about learning when the bakery closes.'),
        array(
            lang('The postcards show the landmarks. The real work of living abroad is smaller: learning which line moves fastest at the post office.'),
            lang('The first months teach patience, and a few words of the local language go further than any guidebook.'),
            lang('By the end of the year the city feels less like a destination and more like a neighborhood, which is the point.'),
        ), 'year-abroad', lang('travel, living abroad')),
    $sample_article(18, 'politics', 0, false, lang('Madison'), 'Jane Cooper', 3,
        lang('County board moves budget hearings to the evening'),
        lang('Residents who work during the day can now speak before the board votes.'),
        array(
            lang('The county board will hold its budget hearings at 6 p.m. instead of 10 a.m., so residents who work during the day can take part.'),
            lang('Speakers can sign up online or at the door, and each gets three minutes.'),
            lang('Board members said attendance at daytime hearings had dropped to a handful of people in recent years.'),
        ), 'hearing', lang('county board, budget')),
    $sample_article(19, 'tech', 0, false, lang('Austin'), 'Samuel Reyes', 3,
        lang('Community college opens a free repair lab for old phones and laptops'),
        lang('Students fix donated devices and pass them on to families who need one.'),
        array(
            lang('A community college opened a repair lab where students fix donated phones and laptops as part of their coursework.'),
            lang('Repaired devices go to families and seniors referred by local libraries, along with a short lesson on using them.'),
            lang('Anyone can drop off an old device at the lab. The students wipe its data before any repair starts.'),
        ), 'repair-lab', lang('repair, recycling')),
    $sample_article(20, 'science', 0, false, lang('Tucson'), 'Samuel Reyes', 3,
        lang('Students count 85 bird species along the restored river trail'),
        lang('A school project shows wildlife returning to a stretch of river cleaned up three years ago.'),
        array(
            lang('High school students counted 85 bird species along the restored river trail in their first year of surveys.'),
            lang('Their teacher said several of the species had not been recorded on that stretch of river for decades.'),
            lang('The counts are shared with a state university lab that tracks how restored rivers recover.'),
        ), 'river-birds', lang('birds, citizen science')),
    $sample_article(21, 'health', 0, false, lang('Milwaukee'), 'Nora Whitfield', 3,
        lang('Hospital opens a walk-in clinic for children until midnight'),
        lang('Parents can bring a sick child in the evening without an appointment.'),
        array(
            lang('The children\'s hospital opened a walk-in clinic that stays open until midnight every day.'),
            lang('Nurses see children with fevers, coughs, small cuts and sprains. Anything serious goes straight to the emergency room.'),
            lang('Hospital staff said the clinic should shorten waits in the emergency room, where many evening visits are not emergencies.'),
        ), 'kids-clinic', lang('children, clinics')),
    $sample_article(22, 'sports', 0, false, lang('Seattle'), 'Daniel Ross', 3,
        lang('Harbor Hawks open their training sessions to young fans'),
        lang('Youth teams can watch practice and meet the players twice a month.'),
        array(
            lang('The Harbor Hawks will open two training sessions a month to local youth teams, who can watch from the stands and meet the players afterward.'),
            lang('The club said the visits are free and that each team can bring its coaches and parents.'),
            lang('Places are given out in the order teams sign up on the club\'s website.'),
        ), 'hawks-training', lang('soccer, youth sports')),
    $sample_article(23, 'arts', 0, false, lang('Nashville'), 'Nora Whitfield', 3,
        lang('Neighborhood choir fills the old train depot for its first concert'),
        lang('Two hundred singers, from teenagers to retirees, performed for a full house.'),
        array(
            lang('A neighborhood choir of two hundred singers performed its first concert in the restored train depot to a full house.'),
            lang('The choir started a year ago with a sign-up sheet at the library and now rehearses every week.'),
            lang('Organizers plan a second concert and are looking for more tenors.'),
        ), 'choir', lang('music, choirs')),
);

// What each Status means to the author, in the words of the Status field.
$status_notes = array(
    lang('It reached the desk; no editor has opened it yet.'),
    lang('An editor is reading and editing it.'),
    lang('The editor wrote under your article what to change.'),
    lang('Readers see it from its publication time.'),
    lang('The desk decided not to publish it.'),
    lang('It was taken off the desk or the site, for example at your request.'),
);
$status_list = array();
foreach ($statuses as $i => $status) {
    $status_list[] = $el('dt', 'fw-semibold', lang('Status'), array(), array('text' => $status));
    $status_list[] = $el('dd', 'mb-2', lang('Text'), array(), array('text' => $status_notes[$i]));
}

// The first sample article of a section ($skip_lead: not a Lead Story),
// the sample a list's card shows in the editor and the installer's preview.
$sample_of = function ($key, $skip_lead = false) use ($sample_articles, $sections, $placements) {
    foreach ($sample_articles as $a) {
        if ($a['section'] === $sections[$key][0] && (!$skip_lead || $a['placement'] !== $placements[1])) {
            return $a;
        }
    }
    return $sample_articles[0];
};

// Submit an Article: the author desk's form, in the private contributors
// folder (an author has view access to it). Its page owns the News Articles
// form; an article is sent as Awaiting review, and only an editor sets it
// live. Addresses are ?r=<reference code> (no pretty URLs): the code never
// changes, while a pretty address would follow every headline edit.
$pages[] = array(
    'key'     => 'blog_new',
    'name'    => lang('submit-an-article'),
    'folder'  => 'contributors',
    'title'   => lang('Submit an Article'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'form'    => array(
        'form_name'            => lang('News Articles'),
        'confirmation_message' => lang('Thank you. Your article has reached the desk; an editor will write to you on its page.'),
        'confirmation_page_id' => '{{tab:submission}}',
        'notify_email'         => '{{site_email}}',
        'notify_subject'       => lang('New article to review: ^^title^^'),
        'notify_page_id'       => '{{tab:email_article_new}}',
        'confirm_email'        => 1,
        'confirm_subject'      => lang('Your article reached the desk: ^^title^^'),
        'confirm_page_id'      => '{{tab:email_article_received}}',
        'pretty_urls'          => 0,
        'sample_records'       => $sample_articles,
        // The BlogPosting author is the byline readers see, not the account
        // that sent the story (the sample stories have none).
        'schema_author'        => 'field:byline',
    ),
    'tree'    => $page(array(
        $page_header(lang('Submit an Article'), lang('Write your article and send it to the desk. An editor reads it, may ask for changes and decides when it goes live.')),
        $band(lang('Content'), array(
            $row(array(
                $col(array(
                    $el('div', 'card card-body p-4', lang('Form Card'), array($widget('article_form'))),
                ), '', '8'),
                $col(array(
                    $side_card(lang('What the Desk Looks For'), lang('What the desk looks for'), array(
                        $check_list(array(
                            lang('A clear headline and a summary of one or two sentences'),
                            lang('Facts you checked yourself, with your sources named in the text'),
                            lang('Your own words and photos you may publish'),
                            lang('The name readers should see on the byline'),
                        )),
                    ), 'p-4 bg-body-tertiary border mb-4'),
                    $side_card(lang('How Review Works'), lang('How review works'), array(
                        $el('dl', 'small mb-3', lang('Statuses'), $status_list),
                        $para(lang('Nothing you send is shown to readers until an editor sets it live.'), 'small fw-semibold'),
                        $para(lang('Follow your article under My Articles. The editors write to you under the article, and each message also comes by e-mail. To change your article or take it back, write to the desk there.'), 'small'),
                        $para(lang('When an editor sets your article live, the editor tells you so under it: that message is the e-mail you get, and no other e-mail is sent when it goes live.'), 'small'),
                        $para(lang('The name on the byline decides which writer\'s page and feed the article joins, and the editors check it.'), 'small'),
                        $para(lang('A photo you upload is not shown to readers while your article waits for review.'), 'small'),
                        $para(lang('An article taken off the site can still show in the site search until the editors update the search index.'), 'small'),
                        $link(lang('My Articles'), '{{page:my_articles}}', 'btn btn-sm btn-secondary'),
                    ), 'p-4 border'),
                ), '', '4'),
            ), '4', '', 'g-lg-5'),
        )),
    ), false),
);

// The site's error page.
$pages[] = array(
    'key'     => 'error',
    'name'    => lang('page-not-found'),
    'folder'  => 'public',
    'title'   => lang('Page Not Found'),
    'search'  => false,
    'sitemap' => false,
    'noindex' => true,
    'tree'    => $page(array($widget('error_page')), false),
);

// The forms' e-mails: each form's notification to the newsroom and its
// reply to whoever sent it. Their widgets show the record just sent.
$mail_pages = array(
    // The News Articles form: to the editors, and to the signed-in author.
    array('email_article_new', lang('email-new-article'), lang('New Article E-mail'), 'email_article_admin'),
    array('email_article_received', lang('email-article-received'), lang('Article Received E-mail'), 'email_article_author'),
    // The tips form: to the editors, and to the tipster (the account's
    // address when signed in, else the address typed, else none).
    array('email_tip_new', lang('email-new-tip'), lang('New Tip E-mail'), 'email_tip_admin'),
    array('email_tip_received', lang('email-tip-received'), lang('Tip Received E-mail'), 'email_tip_sender'),
    // The contact form: to the newsroom, and to the sender.
    array('email_new_message', lang('email-new-message'), lang('New Message E-mail'), 'email_message_admin'),
    array('email_message_received', lang('email-message-received'), lang('Message Received E-mail'), 'email_message_sender'),
);
foreach ($mail_pages as $m) {
    $pages[] = array_merge($mail_settings, array(
        'key'   => $m[0],
        'name'  => $m[1],
        'title' => $m[2],
        'tree'  => $mail_page(array($widget($m[3]))),
    ));
}
// The comment e-mails. The System region carries the comment and its "View
// or Reply" button. A message on an article on the desk goes to its author
// and to every editor who follows it, with the same words for both.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_desk_message',
    'name'  => lang('email-desk-message'),
    'title' => lang('Desk Message E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('New message about an article')),
        $mail_para(lang('There is a new message on an article on the desk.')),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));
// A new comment on an article the reader follows, or a new reply in a
// conversation with the newsroom.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_reply',
    'name'  => lang('email-new-reply'),
    'title' => lang('New Reply E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('There is a new message')),
        $mail_para(lang('Something new was written on a page you follow: an article you commented on, or your conversation with the newsroom. Read it below and answer on the page.')),
        array('type' => 'region', 'props' => array('regionType' => 'system', 'regionName' => ''), 'children' => array()),
    )),
));
// The newsletter's welcome, sent to a new subscriber.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_welcome',
    'name'  => lang('email-newsletter-welcome'),
    'title' => lang('Newsletter Welcome E-mail'),
    'tree'  => $mail_page(array(
        $mail_heading(lang('Welcome to the Morning Briefing')),
        $mail_para(lang('Thank you for subscribing. Every morning you get the top stories of the day, each with a short summary, and a note from our editors on what to watch.')),
        $mail_para(lang('Changed your mind? Every e-mail we send has a link to unsubscribe.'), 'small text-body-secondary mb-3', 'font-size:13px;color:#4a5568;margin:0 0 16px'),
        $mail_button(lang('Read today\'s news'), '{{page:home}}'),
    )),
));

// The account e-mails: the welcome after a sign-up and the password reset
// link. Their Account E-mail widgets fill in the member's name and links.
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_account_welcome',
    'name'  => lang('email-account-welcome'),
    'title' => lang('Account Welcome E-mail'),
    'tree'  => $mail_page(array($widget('account_email_welcome'))),
));
$pages[] = array_merge($mail_settings, array(
    'key'   => 'email_password_reset',
    'name'  => lang('email-password-reset'),
    'title' => lang('Password Reset E-mail'),
    'tree'  => $mail_page(array($widget('account_email_reset'))),
));

// ── Widgets ─────────────────────────────────────────────────────────────

$login_item = function ($text, $token, $flag = '') use ($el, $link) {
    $extra = ($flag !== '') ? array('_bindings' => array('eo_visible_if' => $flag)) : array();
    return $el('li', '', lang('Menu Item'), array(
        $link($text, '#', 'dropdown-item', array(), array('_bindings' => array('href' => $token))),
    ), $extra);
};
$login_page_item = function ($text, $href) use ($el, $link) {
    return $el('li', '', lang('Menu Item'), array($link($text, $href, 'dropdown-item')));
};
$login_divider = function () use ($el) {
    return $el('li', '', lang('Divider'), array($el('hr', 'dropdown-divider', lang('Divider Line'))));
};
// $flag shows the link only while that widget flag is on.
$account_link = function ($icon_name, $text, $href, $token = '', $flag = '') use ($el, $icon, $span) {
    $extra = array('href' => $href);
    if ($token !== '') $extra['_bindings'] = array('href' => $token);
    if ($flag !== '') $extra['_bindings']['eo_visible_if'] = $flag;
    return $el('a', 'list-group-item list-group-item-action d-flex align-items-center gap-2', lang('Menu Item'), array(
        $icon($icon_name, 'text-primary'),
        $span($text, '', lang('Link Text')),
    ), $extra);
};
// The reader's account menu. The staff directory is not in it: it lists
// the newsroom's e-mails and phones and is for the staff only.
$account_links = array(
    $account_link('bi-inbox', lang('Inbox'), '{{page:inbox}}'),
    $account_link('bi-envelope-paper', lang('Newsletter'), '{{page:newsletter}}'),
    $account_link('bi-person-vcard', lang('My Profile'), '#', '__profile_url'),
);
if ($shop) $account_links[] = $account_link('bi-journal-bookmark', lang('Address Book'), '#', '__address_book_url');
$account_links[] = $account_link('bi-shield-lock', lang('Change Password'), '#', '__change_password_url');
$account_links[] = $account_link('bi-envelope-check', lang('Email Preferences'), '#', '__email_preferences_url');

// A card of the account page: an icon, a title, a line and its buttons.
$account_card = function ($icon_name, $title, $text, $buttons, $extra = array()) use ($el, $heading, $para, $icon) {
    return $el('div', 'p-4 border mb-4', $title, array(
        $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-3', lang('Title'), array(
            $el('div', 'd-flex align-items-center gap-3', lang('Words'), array(
                $icon($icon_name, 'fs-2 text-primary'),
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'h5 fw-bold mb-1'),
                    $para($text, 'small text-body-secondary mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), $buttons),
        )),
    ), $extra);
};

// A list of the desk: the News Articles form's records, opening the
// submission page. These lists sit on private pages and carry no live rule:
// they show the articles in their state on the desk.
$desk_list = function ($page_key, $ipp, $max, $order, $direction, $filters, $empty, $tree, $extra = array()) use ($widget_root) {
    $config = array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:blog_new}}',
        'detail_page_id'      => '{{tab:submission}}',
        'items_per_page'      => $ipp,
        'max_results'         => $max,
        'order_by_field'      => $order,
        'order_by_direction'  => $direction,
    );
    if ($filters) $config['filters'] = $filters;
    $config['empty_message'] = $empty;
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => array_merge($config, $extra), 'tree' => $widget_root($tree));
};
// My Articles shows each author their own articles only: an editor's view
// of everybody's is the newsroom desk.
$own_articles = array(
    'viewer_filter'           => true,
    'viewer_filter_submitter' => true,
    'viewer_filter_watcher'   => false,
    'viewer_filter_editor'    => false,
);

$widgets = array(
    // The sign-in in the top bar: two links for a visitor, the account menu
    // for a member. "Newsroom desk" is drawn for whoever the staff page's
    // private folder lets in.
    'login_region' => array(
        'page'   => '',
        'slug'   => lang('login-region'),
        'config' => array(
            'regionType'         => 'login_region',
            'login_page_id'      => '{{tab:login}}',
            'register_page_id'   => '{{tab:register}}',
            'show_register_link' => true,
            'account_page_id'    => '{{tab:my_account}}',
            'staff_page_id'      => '{{tab:staff}}',
        ),
        'tree'   => $widget_root(array(
            $el('div', 'd-flex align-items-center gap-2', lang('Signed Out'), array(
                $link(lang('Log In'), '#', 'btn btn-sm btn-link link-body-emphasis text-decoration-none text-nowrap', array(), array('_bindings' => array('href' => '__login_url'))),
                $link(lang('Sign Up'), '#', 'btn btn-sm btn-primary text-nowrap', array(), array('_bindings' => array('href' => '__register_url'))),
            ), $show_when('is_signed_out')),
            $el('div', 'dropdown', lang('Signed In'), array(
                $el('button', 'btn btn-link d-flex align-items-center gap-2 px-0 py-1 border-0 link-body-emphasis text-decoration-none dropdown-toggle', lang('Account Button'), array(
                    $image('https://placehold.co/64x64/1c3f94/ffffff?text=JC', '', 'rounded-circle object-fit-cover',
                        array('width' => '24', 'height' => '24', '_bindings' => array('src' => '__user_avatar_url'))),
                    $span(lang('Jane'), 'fw-semibold', lang('First Name'), $bind('__user_first_name')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'dropdown'),
                    $attr('aria-expanded', 'false'),
                ))),
                $el('ul', 'dropdown-menu dropdown-menu-end shadow-sm', lang('Account Menu'), array(
                    $el('li', 'px-3 py-2', lang('Account Summary'), array(
                        $span(lang('Jane Cooper'), 'd-block fw-semibold', lang('Full Name'), $bind('__user_name')),
                        $span(lang('jane.cooper@example.com'), 'd-block small text-body-secondary', lang('Email'), $bind('__user_email')),
                    )),
                    $login_divider(),
                    $login_item(lang('My Account'), '__my_account_url'),
                    $login_page_item(lang('My Articles'), '{{page:my_articles}}'),
                    $login_page_item(lang('Inbox'), '{{page:inbox}}'),
                    $login_item(lang('Edit my profile'), '__profile_url'),
                    $login_item(lang('Newsroom desk'), '__staff_url', 'is_staff'),
                    $login_item(lang('Control panel'), '__panel_url', 'has_panel_access'),
                    $login_divider(),
                    $login_item(lang('Log out'), '__logout_url'),
                )),
            ), $show_when('is_signed_in')),
            $loop(array()),
        )),
    ),

    // My Articles: the author's own articles in every status, newest first.
    'my_articles_list' => $desk_list('my_articles', 20, 0, 'submitted_date_and_time', 'DESC', array(),
        lang('Nothing sent yet. Your first article is one form away.'),
        array($el('div', 'list-group', lang('News Article List'), array($loop(array($desk_row(false)))))),
        $own_articles),
    // Beside it: the author's articles that readers can see now, each
    // opening the public article.
    'my_articles_live' => array(
        'page'   => 'my_articles',
        'slug'   => lang('form-list'),
        'config' => array_merge($pub, $own_articles, array(
            'items_per_page' => 5,
            'max_results'    => 5,
            'filters'        => $live,
            'empty_message'  => lang('Nothing of yours is live yet.'),
        )),
        'tree'   => $widget_root(array(
            $el('div', '', lang('News Stories'), array($loop(array(
                $el('article', 'pn-story position-relative py-2 border-top', lang('Story Card'), array(
                    $card_headline('h3', 'h6 mb-1', lang('New light-rail line opens nine stations across downtown')),
                    $el('p', 'small text-body-secondary mb-0', lang('Time'), array($card_time())),
                )),
            )))),
        )),
    ),

    // One article on the desk, for its author (the browser that sent it, or
    // the account) and the editors: everything the author sent, the
    // editors' note fields, the status, and the rich text in a box of its
    // own (what an author writes cannot reach outside it).
    'submission_view' => array(
        'page'   => 'submission',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:blog_new}}',
            'access_control'      => 'submitter_only',
            'title_field'         => 'title',
            'title_suffix'        => lang('| Newsroom desk'),
            'not_found_message'   => lang('This article could not be found, or it is not yours to see.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-file-earmark-text', 'display-5 text-body-secondary'),
                            $heading('h1', lang('Article not found'), 'h3 mt-3'),
                            $para(lang('This article could not be found, or it is not yours to see.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('My Articles'), '{{page:my_articles}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('News article'), array_merge(
                            $sent_notices(lang('Thank you. Your article reached the desk.')),
                            array(
                                $record_toolbar(lang('My Articles'), 'my_articles', lang('Change Status')),
                                $el('div', 'card mb-4', lang('Record Card'), array(
                                    $el('div', 'card-body p-4', lang('Card Body'), array(
                                        $el('div', 'd-flex flex-wrap align-items-start justify-content-between gap-2 mb-3', lang('Title'), array(
                                            $el('div', 'pn-min-0', lang('Words'), array(
                                                $para($sections['us'][0], 'pn-kicker mb-1', $bind('section')),
                                                $heading('h1', lang('City council approves a plan to plant 10,000 street trees'), 'h3 pn-headline mb-0', $bind('title')),
                                            )),
                                            $status_badge($statuses[0], 'badge rounded-pill bg-primary-subtle text-primary-emphasis fs-6'),
                                        )),
                                        $el('dl', 'small mb-0', lang('Details'), array(
                                            $record_detail(lang('Name on the byline'), $writer_name('Jane Cooper'), 'byline', false, 'fw-semibold'),
                                            $record_detail(lang('Dateline (city)'), lang('Denver'), 'dateline', true),
                                            $record_detail(lang('Publication date and time'), lang('Date'), 'publish_date', true, 'fw-semibold'),
                                            $record_detail(lang('Sent'), lang('Date'), 'submitted_date_and_time'),
                                            $record_detail(lang('Reference Code'), 'ABC123', 'reference_code', false, 'font-monospace'),
                                        )),
                                    )),
                                )),
                                $record_words(lang('Summary'), lang('Planting begins in the neighborhoods with the least shade, and residents can ask for a tree in front of their home.'), 'summary'),
                                $record_words(lang('A note for the editors'), lang('I can add a photo of the council vote if you need one.'), 'note_to_editors'),
                                $record_words(lang('Correction'), lang('An earlier version of this article gave the wrong month for the survey\'s start.'), 'correction'),
                                $para(lang('Article text'), 'small fw-semibold text-body-secondary mb-2'),
                                $el('div', 'pn-body mw-100 p-4 border mb-4', lang('Article text'), array(), array_merge(
                                    array('text' => lang('The article text appears here.')), $bind('body'))),
                                $el('div', 'pg-hide-if-empty mb-4', lang('Cover Image'), array(
                                    $para(lang('Cover Image'), 'small fw-semibold text-body-secondary mb-2'),
                                    $el('span', 'pg-field-value d-block', lang('Picture Frame'), array(
                                        $image($photo('street-trees', 1200, 675), lang('City council approves a plan to plant 10,000 street trees'),
                                            'img-fluid w-100 object-fit-cover pg-ratio-16x9', array('_bindings' => array('src' => 'cover_image', 'alt' => 'title'))),
                                    )),
                                )),
                                // The heading of the messages the page puts
                                // right below; gone with a missing article.
                                $heading('h2', lang('Messages with the desk'), 'h4 mb-2'),
                                $el('div', 'd-flex gap-2 small text-body-secondary', lang('Note'), array(
                                    $icon('bi-chat-left-text', 'text-primary mt-1'),
                                    $el('div', '', lang('Text'), array(
                                        $para(lang('Write to the desk below. Editors answer here and by e-mail.'), 'mb-1'),
                                        $para(lang('To change your article or take it back, write to the desk below; an editor makes the change.'), 'mb-0'),
                                    )),
                                )),
                            )
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'pt-5 pb-3'),
        )),
    ),

    // The newsroom desk's lists. Only the last one, every article, pages
    // through; the others show a fixed number.
    'staff_queue' => $desk_list('staff', 20, 20, 'submitted_date_and_time', 'ASC',
        array($f('status', 'is equal to', $statuses[0])),
        lang('Nothing is waiting for review.'),
        array($el('div', 'list-group', lang('News Article List'), array($loop(array($desk_row(true))))))),
    // In review and Changes requested: every status but the other four.
    'staff_in_progress' => $desk_list('staff', 20, 20, 'submitted_date_and_time', 'ASC',
        array(
            $f('status', 'is not equal to', $statuses[0]),
            $f('status', 'is not equal to', $statuses[3]),
            $f('status', 'is not equal to', $statuses[4]),
            $f('status', 'is not equal to', $statuses[5]),
        ),
        lang('No article is being edited.'),
        array($el('div', 'list-group', lang('News Article List'), array($loop(array($desk_row(true))))))),
    'staff_scheduled' => $desk_list('staff', 10, 10, 'publish_date', 'ASC',
        array(
            $f('status', 'is equal to', $live_value),
            $f('publish_date', 'is greater than', '', 'current date and time'),
        ),
        lang('Nothing is scheduled to go live.'),
        array($el('div', 'list-group', lang('News Article List'), array($loop(array($desk_row(true))))))),
    // Live with an empty publication time: kept off the site by the live
    // rule until an editor gives it a time.
    'staff_no_time' => $desk_list('staff', 10, 10, 'submitted_date_and_time', 'ASC',
        array(
            $f('status', 'is equal to', $live_value),
            $f('publish_date', 'is equal to', ''),
        ),
        lang('Every live article has a publication time.'),
        array($el('div', 'list-group', lang('News Article List'), array($loop(array($desk_row(true))))))),
    // What readers see now; a row opens the public article.
    'staff_published' => array(
        'page'   => 'staff',
        'slug'   => lang('form-list'),
        'config' => array_merge($pub, array(
            'items_per_page' => 10,
            'max_results'    => 10,
            'filters'        => $live,
            'empty_message'  => lang('Nothing is live yet.'),
        )),
        'tree'   => $widget_root(array($el('div', 'list-group', lang('News Article List'), array($loop(array($desk_row(true))))))),
    ),
    // The tips readers sent, newest first, each with its whole text and a
    // link to its attachment. Editors read them here: the tip form sits in
    // the public folder (a visitor must be able to send it), so its edit
    // button and the control panel's screen open only for whoever may edit
    // that folder. The attachment is a file in the private folder, which
    // every editor may open.
    'staff_tips' => array(
        'page'   => 'staff',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:tips}}',
            'items_per_page'      => 10,
            'max_results'         => 10,
            'order_by_field'      => 'submitted_date_and_time',
            'order_by_direction'  => 'DESC',
            'empty_message'       => lang('No tips yet.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Tip List'), array($loop(array(
                $el('div', 'list-group-item py-3', lang('Tip'), array(
                    $el('div', 'd-flex align-items-start gap-2 mb-1', lang('Top Line'), array(
                        $span(lang('Local news'), 'fw-semibold', lang('Topic'), $bind('topic')),
                        $span(lang('2 hours ago'), 'ms-auto small text-body-secondary text-nowrap', lang('Time'), $bind_date('submitted_date_and_time', 'relative')),
                    )),
                    $el('div', 'small text-body-secondary d-flex flex-wrap column-gap-3 row-gap-1 mb-2', lang('Details'), array(
                        $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-1', lang('Name'), array(
                            $icon('bi-person'),
                            $span(lang('Jane Cooper'), 'pg-field-value', lang('Value'), $bind('name')),
                        )),
                        $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-1 text-break', lang('Email'), array(
                            $icon('bi-envelope'),
                            $span(lang('jane.cooper@example.com'), 'pg-field-value', lang('Value'), $bind('email')),
                        )),
                        // Sent while signed in: the account the reply goes to.
                        $el('span', 'pg-hide-if-empty d-inline-flex align-items-center gap-1 text-break', lang('Account'), array(
                            $icon('bi-person-check'),
                            $sr_only(lang('Account')),
                            $span(lang('jane.cooper'), 'pg-field-value', lang('Value'), $bind('submitter')),
                        )),
                    )),
                    $el('p', 'pn-tip text-break mb-0', lang('Tip'), array(), array_merge(
                        array('text' => lang('The bridge on the river road has been closed for repairs for weeks, but nobody is working on it.')), $bind('tip'))),
                    // Gone when the reader attached nothing: the link's own
                    // words are the file's address.
                    $el('p', 'pg-hide-if-empty small d-flex align-items-center gap-1 mt-2 mb-0', lang('Attachment'), array(
                        $icon('bi-paperclip'),
                        $sr_only(lang('Attachment')),
                        $link(lang('bridge-photo.jpg'), '#', 'pg-field-value link-body-emphasis text-break', array(), array(
                            '_bindings' => array('text' => 'attachment', 'href' => 'attachment'))),
                    )),
                )),
            )))),
        )),
    ),
    // Every article on the desk in any status, newest first, with a search.
    'staff_all' => $desk_list('staff', 20, 0, 'submitted_date_and_time', 'DESC', array(),
        lang('No articles yet.'),
        array($el('div', 'list-group', lang('News Article List'), array($loop(array($desk_row(true)))))),
        array(
            'search_enabled' => true,
            'search_fields'  => array('title', 'byline', 'section', 'status', 'tags'),
            'search_width'   => 'full',
            'search_label'   => lang('Search by headline, writer, section or status…'),
        )),

    // The staff directory's form: one person.
    'staff_form' => array(
        'page'   => 'staff_entry',
        'slug'   => 'form',
        'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
        'tree'   => $widget_root(array(
            $row(array(
                $col(array($field('nw-staff-name', lang('Full Name'), $input('nw-staff-name', 'text', 'full_name', 'off'))), '6'),
                $col(array($field('nw-staff-title', lang('Job Title'), $input('nw-staff-title', 'text', 'job_title', 'off'))), '6'),
                $col(array($field('nw-staff-department', lang('Department'), $input('nw-staff-department', 'text', 'department', 'off', false))), '6'),
                $col(array($field('nw-staff-photo', lang('Photo'), $file('nw-staff-photo', 'photo', 'private'))), '6'),
                $col(array($field('nw-staff-email', lang('Email'), $input('nw-staff-email', 'email', 'email', 'off', false))), '6'),
                $col(array($field('nw-staff-phone', lang('Phone'), $input('nw-staff-phone', 'tel', 'phone', 'off', false))), '6'),
            ), '3'),
            $field('nw-staff-bio', lang('A few words'), $textarea('nw-staff-bio', 'bio', 3, false)),
            $submit(lang('Add')),
            $loop(array()),
        )),
    ),
    // The directory, for the newsroom only.
    'staff_directory' => array(
        'page'   => 'staff_directory',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'          => 'form_list_view',
            'custom_form_page_id' => '{{tab:staff_entry}}',
            'items_per_page'      => 50,
            'max_results'         => 0,
            'order_by_field'      => 'full_name',
            'order_by_direction'  => 'ASC',
            'search_enabled'      => true,
            'search_fields'       => array('full_name', 'job_title', 'department'),
            'search_width'        => 'half',
            'search_label'        => lang('Search by name or department...'),
            'empty_message'       => lang('The directory is empty for now.'),
        ),
        'tree'   => $widget_root(array(
            $row(array($loop(array(
                $col(array(
                    $el('div', 'd-flex align-items-start gap-3 h-100 p-4 bg-body-tertiary border', lang('Person Card'), array(
                        $optional_image('photo', '', 'rounded-circle object-fit-cover pg-circle-60', 'flex-shrink-0',
                            $portrait($writer_name('Jane Cooper'), 128), '', $lazy),
                        $el('div', 'pn-min-0', lang('Details'), array(
                            $heading('h2', $writer_name('Jane Cooper'), 'h6 fw-bold mb-0', $bind('full_name')),
                            $para(lang('Managing Editor'), 'small fw-semibold mb-2 text-body-secondary', $bind('job_title')),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Department'), array(
                                $icon('bi-diagram-3', 'text-primary me-1'),
                                $span(lang('City desk'), 'pg-field-value', lang('Value'), $bind('department')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1 text-break', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary me-1'),
                                $span(lang('jane.cooper@example.com'), 'pg-field-value', lang('Value'), $bind('email')),
                            )),
                            $el('p', 'pg-hide-if-empty small mb-1', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary me-1'),
                                $span('(303) 555-0142', 'pg-field-value', lang('Value'), $bind('phone')),
                            )),
                            $el('p', 'pg-hide-if-empty small text-body-secondary mt-2 mb-0', lang('A few words'), array(
                                $span(lang('Edits the city and transportation coverage.'), 'pg-field-value', lang('Value'), $bind('bio')),
                            )),
                        )),
                    )),
                ), '6', '4'),
            ))), '4'),
        )),
    ),

    // The inbox: conversations the member started (a signed-in message from
    // the contact page) or follows; the staff see them all. A conversation's
    // subject is its topic.
    'inbox_list' => array(
        'page'   => 'inbox',
        'slug'   => lang('form-list'),
        'config' => array(
            'regionType'              => 'form_list_view',
            'custom_form_page_id'     => '{{tab:contact}}',
            'detail_page_id'          => '{{tab:conversation}}',
            'items_per_page'          => 20,
            'order_by_field'          => 'submitted_date_and_time',
            'order_by_direction'      => 'DESC',
            'viewer_filter'           => true,
            'viewer_filter_submitter' => true,
            'viewer_filter_watcher'   => true,
            'viewer_filter_editor'    => true,
            'empty_message'           => lang('No conversations yet. Start one from the contact page.'),
        ),
        'tree'   => $widget_root(array(
            $el('div', 'list-group', lang('Conversation List'), array(
                $loop(array(
                    $el('a', 'list-group-item list-group-item-action py-3', lang('Conversation'), array(
                        $el('div', 'd-flex w-100 justify-content-between gap-3', lang('Top Line'), array(
                            $span(lang('A question for the newsroom'), 'fw-semibold text-truncate', lang('Subject'), $bind('topic')),
                            $span(lang('2 hours ago'), 'small text-body-secondary text-nowrap', lang('Date'), $bind_date('submitted_date_and_time', 'relative')),
                        )),
                        $el('div', 'd-flex align-items-center gap-2 small text-body-secondary mt-1', lang('Replies'), array(
                            $icon('bi-chat-left-text'),
                            $span('0', 'fw-semibold', lang('Reply Count'), $bind('number_of_comments')),
                            $span(lang('replies'), '', lang('Label')),
                        )),
                    ), array('href' => '#', '_bindings' => array('href' => 'form_item_view'))),
                )),
            )),
        )),
    ),

    // One conversation: the first message; the answers follow as comments.
    'conversation' => array(
        'page'   => 'conversation',
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:contact}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => lang('This conversation could not be found, or it is not yours to read.'),
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'text-center py-5', lang('Not Found'), array(
                            $icon('bi-chat-left-dots', 'display-5 text-body-secondary'),
                            $heading('h1', lang('Conversation not found'), 'h3 mt-3'),
                            $para(lang('This conversation could not be found, or it is not yours to read.'), 'text-body-secondary mb-4', $bind('not_found')),
                            $link(lang('Back to the inbox'), '{{page:inbox}}', 'btn btn-primary px-4'),
                        ), $show_when('record_not_found')),
                        $el('div', '', lang('Conversation'), array_merge(
                            $sent_notices(lang('Thank you, your message has reached the newsroom. Our answers appear on this page and in your inbox, and you can write back below.')),
                            array(
                                $record_toolbar(lang('Inbox'), 'inbox'),
                                $heading('h1', lang('A question for the newsroom'), 'h3 mb-1', $bind('topic')),
                                $el('p', 'd-flex flex-wrap align-items-center gap-2 small text-body-secondary mb-4', lang('Conversation Details'), array(
                                    $icon('bi-calendar3'),
                                    $span(lang('Date'), '', lang('Date'), $bind('submitted_date_and_time')),
                                    $icon('bi-hash', 'ms-2'),
                                    $span('ABC123', '', lang('Reference Code'), $bind('reference_code')),
                                )),
                                $el('div', 'p-4 bg-body-tertiary border', lang('First Message'), array(
                                    $el('p', 'd-flex align-items-center gap-2 fw-semibold mb-2', lang('Sender'), array_merge(
                                        array($icon('bi-person-circle', 'fs-5 text-primary')), $person_name())),
                                    $el('div', '', lang('Message'), array(), array_merge(
                                        array('text' => lang('Hello, the photo caption in your article about the new pocket parks names the wrong street.')), $bind('message'))),
                                )),
                            )
                        ), $show_when('record_found')),
                    ), '', '8'),
                ), '', 'center'),
            ), 'py-5'),
        )),
    ),

    // The account page: who is signed in, the account menu, and, for whoever
    // the author desk's folder lets in, the way to the author desk (its
    // staff page is My Articles: the flag is_staff follows that folder).
    'my_account' => array(
        'page'   => 'my_account',
        'slug'   => lang('my-account'),
        'config' => array(
            'regionType'       => 'my_account',
            'login_page_id'    => '{{tab:login}}',
            'show_submissions' => false,
            'staff_page_id'    => '{{tab:my_articles}}',
        ),
        'tree'   => $widget_root(array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'p-4 bg-primary-subtle mb-3', lang('Account Card'), array(
                            $para(lang('Welcome back'), 'small text-body-secondary mb-1'),
                            $heading('h1', lang('Jane Cooper'), 'h4 fw-bold mb-1', $bind('__display_name')),
                            $para(lang('jane.cooper@example.com'), 'small text-body-secondary mb-3', $bind('email_address')),
                            $link(lang('Log out'), '#', 'btn btn-sm btn-secondary', array(), array('_bindings' => array('href' => '__logout_url'))),
                        )),
                        $el('nav', 'list-group', lang('Account Menu'), $account_links, array('_attrs' => array($attr('aria-label', lang('My Account'))))),
                    ), '', '4'),
                    $col(array(
                        $account_card('bi-pencil-square', lang('Your author desk'), lang('Send an article to the editors and follow it until it goes live.'), array(
                            $link(lang('My Articles'), '{{page:my_articles}}', 'btn btn-primary'),
                            $link(lang('Submit an Article'), '{{page:blog_new}}', 'btn btn-secondary'),
                        ), $show_when('is_staff')),
                        $account_card('bi-chat-left-text', lang('Comments and conversations'), lang('Comment on articles, follow your conversations with the newsroom and choose the e-mails you get.'), array(
                            $link(lang('Inbox'), '{{page:inbox}}', 'btn btn-primary'),
                            $link(lang('Newsletter'), '{{page:newsletter}}', 'btn btn-secondary'),
                        )),
                        $heading('h2', lang('My Orders'), 'h5 fw-bold mb-3', $show_when('has_orders')),
                        $el('div', '', lang('Order History'), array(), array('_bindings' => array('section' => 'order_history'))),
                    ), '', '8'),
                ), '4', '', 'g-lg-5'),
                $loop(array()),
            ), 'py-5'),
        )),
    ),
    'account_invoices' => array(
        'page'   => 'my_account',
        'slug'   => lang('invoices'),
        'config' => array('regionType' => 'account_invoices', 'max_results' => 50),
        'tree'   => 'starter',
    ),
    // Google connection, remembered devices and two-step verification,
    // under the account overview on the same page.
    'account_security' => array(
        'page'   => 'my_account',
        'slug'   => lang('security'),
        'config' => array('regionType' => 'account_security', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),

    // Signing in, up and out, and the password: the layouts their widgets
    // get from the palette.
    'login' => array(
        'page'   => 'login',
        'slug'   => lang('login'),
        'config' => array(
            'regionType'              => 'login_form',
            'show_remember_me'        => true,
            'register_page_id'        => '{{tab:register}}',
            'show_register_link'      => true,
            'forgot_password_page_id' => '{{tab:forgot_password}}',
        ),
        'tree'   => 'starter',
    ),
    'register' => array(
        'page'   => 'register',
        'slug'   => lang('registration'),
        'config' => array(
            'regionType'            => 'registration',
            'show_remember_me'      => true,
            'login_page_id'         => '{{tab:login}}',
            'redirect_page_id'      => '{{tab:my_account}}',
            'welcome_email_page_id' => '{{tab:email_account_welcome}}',
            'welcome_email_subject' => lang('Welcome! Your account is ready'),
        ),
        'tree'   => 'starter',
    ),
    'forgot_password' => array(
        'page'   => 'forgot_password',
        'slug'   => lang('forgot-password'),
        'config' => array(
            'regionType'          => 'forgot_password',
            'login_page_id'       => '{{tab:login}}',
            'reset_email_page_id' => '{{tab:email_password_reset}}',
            'reset_email_subject' => lang('Reset your password'),
        ),
        'tree'   => 'starter',
    ),
    // The account e-mails' widgets, drawn for the member the e-mail is
    // sent to. The reset e-mail's button carries the set password link: a
    // page without it is not sent (the built-in e-mail goes instead).
    'account_email_welcome' => array(
        'page'   => 'email_account_welcome',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Welcome!')),
            $mail_account_greeting(),
            $mail_para(lang('Your reader account is ready. Comment on articles, follow your conversations and manage the newsletter from your account.')),
            $mail_button(lang('Go to my account'), '#', array('_bindings' => array('href' => '__my_account_url'))),
            $mail_small(lang('Thank you for reading us.')),
        )),
    ),
    'account_email_reset' => array(
        'page'   => 'email_password_reset',
        'slug'   => lang('account-email'),
        'config' => array('regionType' => 'account_email'),
        'tree'   => $widget_root(array(
            $mail_heading(lang('Reset your password')),
            $mail_account_greeting(),
            $mail_para(lang('We received a request to reset your password.')),
            $mail_button(lang('Set a new password'), '#', array('_bindings' => array('href' => '__set_password_url'))),
            $mail_small(lang('The link works for 24 hours. If you did not ask for it, ignore this e-mail; your password stays the same.')),
        )),
    ),
    'set_password' => array(
        'page'   => 'set_password',
        'slug'   => lang('set-password'),
        'config' => array('regionType' => 'set_password', 'redirect_page_id' => '{{tab:my_account}}', 'forgot_password_page_id' => '{{tab:forgot_password}}'),
        'tree'   => 'starter',
    ),
    'logout' => array(
        'page'   => 'logout',
        'slug'   => lang('logout'),
        'config' => array('regionType' => 'logout', 'login_page_id' => '{{tab:login}}'),
        'tree'   => 'starter',
    ),
    'profile' => array(
        'page'   => 'profile',
        'slug'   => lang('profile'),
        'config' => array('regionType' => 'account_profile', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'change_password' => array(
        'page'   => 'change_password',
        'slug'   => lang('change-password'),
        'config' => array('regionType' => 'change_password', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'email_preferences' => array(
        'page'   => 'email_preferences',
        'slug'   => lang('email-preferences'),
        'config' => array('regionType' => 'email_preferences', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'   => 'starter',
    ),
    'address_book' => array(
        'page'     => 'address_book',
        'requires' => 'ecommerce',
        'slug'     => lang('address-book'),
        'config'   => array('regionType' => 'address_book', 'my_account_page_id' => '{{tab:my_account}}'),
        'tree'     => 'starter',
    ),
    'error_page' => array(
        'page'   => 'error',
        'slug'   => lang('error'),
        'config' => array('regionType' => 'error_page'),
        'tree'   => 'starter',
    ),
);

// The breaking strip's two lists, in the shared breaking component: the
// live articles flagged as breaking news in the last two days, newest
// first. Identical settings; the copy's group is hidden from screen readers
// and its links are out of the tab order. An empty list prints its message
// in a .pg-sw-empty box, which takes the whole strip off the page.
$breaking_cfg = array_merge($pub, array(
    'items_per_page' => 8,
    'max_results'    => 8,
    'filters'        => $with_live(array($f('breaking', 'is equal to', lang('Yes')), $recent(2))),
    'empty_message'  => lang('No breaking news right now.'),
));
unset($breaking_cfg['order_by_2_field'], $breaking_cfg['order_by_2_direction']);
$ticker_tree = function ($copy) use ($el, $span, $icon, $attr, $bind, $bind_date, $widget_root, $loop) {
    $item_extra = array('href' => '#', '_bindings' => array('href' => 'form_item_view'));
    if ($copy) $item_extra['_attrs'] = array($attr('tabindex', '-1'));
    $group_extra = $copy ? array('_attrs' => array($attr('aria-hidden', 'true'))) : array();
    return $widget_root(array(
        $el('div', 'pg-marquee-group gap-4 pe-4' . ($copy ? ' pg-marquee-copy' : ''), $copy ? lang('Marquee Copy') : lang('Marquee Group'), array(
            $loop(array(
                $el('a', 'pn-breaking-item small fw-semibold text-nowrap text-reset text-decoration-none', lang('Headline Link'), array(
                    $span(lang('2 hours ago'), 'pn-breaking-time me-2', lang('Time'), $bind_date('publish_date', 'relative')),
                    $span(lang('City council approves a plan to plant 10,000 street trees'), 'pn-breaking-title', lang('Headline'), $bind('title')),
                ), $item_extra),
                $icon('bi-dot', 'text-body-secondary'),
            )),
        ), $group_extra),
    ));
};
$widgets['breaking_main'] = array('page' => '', 'slug' => lang('breaking-news'), 'config' => $breaking_cfg, 'tree' => $ticker_tree(false));
$widgets['breaking_copy'] = array('page' => '', 'slug' => lang('breaking-news-copy'), 'config' => $breaking_cfg, 'tree' => $ticker_tree(true));

// ── The lists of articles ───────────────────────────────────────────────
// Every public list carries the live rule ($live) before its own filters.
// Lists on one page share the page_number and query parameters, so only
// one list of a page pages through its articles (max_results 0); every
// other list shows a fixed number.
$article_list = function ($page_key, $ipp, $max, $more, $empty, $tree, $extra = array()) use ($pub, $with_live, $widget_root) {
    $config = array_merge($pub, array('items_per_page' => $ipp, 'max_results' => $max, 'filters' => $with_live($more)), $extra);
    if ($empty !== '') $config['empty_message'] = $empty;
    return array('page' => $page_key, 'slug' => lang('form-list'), 'config' => $config, 'tree' => $widget_root($tree));
};

// The front page. The lead grid is one list: the three newest Lead Story
// articles, the first drawn large by the template's rules.
$widgets['home_lead'] = $article_list('home', 3, 3, array($f('placement', 'is equal to', lang('Lead Story'))),
    lang('Mark live articles as Lead Story and the newest three appear here.'),
    array($el('div', 'pn-lead-grid', lang('Lead Grid'), array($loop(array($overlay_card()))))));
$widgets['home_featured'] = $article_list('home', 4, 4, array($f('placement', 'is equal to', lang('Featured'))),
    lang('Mark live articles as Featured and they appear here.'),
    array($row(array($loop(array($col(array($story_card()), '', '3', '', '6')))), '3', '', 'g-lg-4')));
// The section blocks leave the lead stories out: they are already in the
// grid right above.
foreach ($sections as $key => $s) {
    if ($key === 'opinion') continue;
    if ($key === 'us' || $key === 'world') {
        $widgets['home_' . $key] = $article_list('home', 4, 4, array($in_section($s[0]), $not_lead),
            lang('No live articles in this section yet.'),
            array($row(array($loop(array($col(array($row_story($sample_of($key, true))), '6')))), '4')));
    } else {
        $widgets['home_' . $key] = $article_list('home', 3, 3, array($in_section($s[0]), $not_lead),
            lang('No live articles in this section yet.'),
            array($el('div', 'pn-stack', lang('News Stories'), array($loop(array($stack_item($sample_of($key, true))))))));
    }
}
$widgets['home_opinion'] = $article_list('home', 3, 3, array($in_section($sections['opinion'][0])),
    lang('No columns yet.'),
    array($row(array($loop(array($col(array($columnist_card()), '6', '4')))), '3', '', 'g-lg-4')));
$widgets['home_posts'] = $article_list('home', 8, 8, array(),
    lang('No live articles yet.'),
    array($el('ol', 'pn-feed list-unstyled mb-0', lang('Time Line'), array($loop(array($feed_item()))), array('_attrs' => array($attr('role', 'list'))))));

// The most read: the live articles of the last 30 days that were opened
// most in the last 7 (the views the article page counts), the newest first
// among equals. One list, placed beside the front page, the sections, the
// latest news, the article and the writers.
$widgets['most_read'] = array(
    'page'   => '',
    'slug'   => lang('most-read'),
    'config' => array_merge($pub, array(
        'items_per_page'       => 5,
        'max_results'          => 5,
        'order_by_field'       => 'recent_views',
        'order_by_direction'   => 'DESC',
        'order_recent_days'    => 7,
        'order_by_2_field'     => 'publish_date',
        'order_by_2_direction' => 'DESC',
        'filters'              => $with_live(array($recent(30))),
        'empty_message'        => lang('The most read articles of the week appear here.'),
    )),
    'tree'   => $widget_root(array(
        $el('aside', 'p-4 border bg-body mb-4', lang('Most read'), array(
            $el('h2', 'h5 pn-sec-title mb-2', lang('Title'), array(), array('id' => 'pn-most-read', 'text' => lang('Most read'))),
            $el('ol', 'pn-rank list-unstyled mb-0', lang('Ranking'), array(
                $loop(array(
                    $el('li', 'pn-rank-item pn-story d-flex gap-3 py-3 position-relative', lang('Story Card'), array(
                        $el('span', 'pn-rank-num', lang('Number'), array(), array('_attrs' => array($attr('aria-hidden', 'true')))),
                        $el('div', 'pn-min-0', lang('Words'), array(
                            $card_kicker('', $sections['sports'][0]),
                            $card_headline('h3', 'h6 mb-0', lang('Harbor Hawks rally after halftime for a 3–1 win')),
                        )),
                    )),
                )),
            ), array('_attrs' => array($attr('role', 'list')))),
        ), array('_attrs' => array($attr('aria-labelledby', 'pn-most-read')))),
    )),
);

// A page for each section: the section's live articles, twelve to a page.
foreach ($sections as $key => $s) {
    if ($key === 'opinion') continue;
    $widgets['section_' . $key] = $article_list($key, 12, 0, array($in_section($s[0])),
        lang('No live articles in this section yet.'),
        array($el('div', '', lang('News Stories'), array($loop(array($river('h2', 'dateline', $sample_of($key))))))));
}
$widgets['opinion_list'] = $article_list('opinion', 12, 0, array($in_section($sections['opinion'][0])),
    lang('No columns yet.'),
    array($row(array($loop(array($col(array($columnist_card()), '6')))), '4')));
// The latest news: every live article, with a search over the headline,
// the summary, the byline and the topics.
$widgets['blog_list'] = $article_list('blog', 20, 0, array(),
    lang('Nothing matched. Try other words.'),
    array($el('div', '', lang('News Stories'), array($loop(array($river('h2', 'section', $sample_articles[0])))))),
    array(
        'search_enabled' => true,
        'search_fields'  => array('title', 'summary', 'byline', 'tags'),
        'search_width'   => 'full',
        'search_label'   => lang('Search the latest news…'),
    ));
// A writer's own articles: the byline must equal ?author= exactly. Without
// a value the block and its list stay off the page (the page keeps its
// heading and the directory). With a value no live article carries, the
// same, and the page is not indexed. While a value matches, the page title
// reads "Articles by <name>".
$widgets['author_list'] = $article_list('author', 12, 0, array($f('byline', 'is equal to', '', 'url parameter', 'author')),
    '',
    array($el('div', 'mb-5', lang('Writer\'s Articles'), array(
        $para(lang('Author'), 'pn-kicker mb-1'),
        $heading('h2', $writer_name('Jane Cooper'), 'h3 mb-3', $bind('__filter_value')),
        $el('div', '', lang('News Stories'), array($loop(array($river('h3', 'section', $sample_articles[0]))))),
    ), $show_when('has_filter_value'))),
    array('filter_title' => lang('Articles by {value}')));
// Under the article: the four newest live articles (the open one may be
// among them: a list cannot leave the record of the page out).
$widgets['article_more'] = $article_list('blog_post', 4, 4, array(),
    lang('No other articles yet.'),
    array($row(array($loop(array($col(array($story_card()), '', '3', '', '6')))), '3', '', 'g-lg-4')));

// The article: shown while it is live (the same three filters as the
// lists: anything else is "not found" and not indexed). Its title is the
// page's title; its summary, cover and section give the description, the
// picture and the category of the head and the feed.
$widgets['blog_post'] = array(
    'page'   => 'blog_post',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:blog_new}}',
        'access_control'      => 'public',
        'filters'             => $live,
        'title_field'         => 'title',
        'not_found_message'   => lang('This article is not available. It may not be published yet, or it was removed.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $row(array(
                $el('div', 'col-lg-8 mx-auto text-center py-5', lang('Not Found'), array(
                    $icon('bi-newspaper', 'display-5 text-body-secondary'),
                    $heading('h1', lang('Article not available'), 'h3 mt-3'),
                    $para(lang('This article is not available. It may not be published yet, or it was removed.'), 'text-body-secondary mb-4', $bind('not_found')),
                    $link(lang('Back to the latest news'), '{{page:blog}}', 'btn btn-primary px-4'),
                ), $show_when('record_not_found')),
                $el('article', 'col-lg-8 pn-story', lang('News article'), array(
                    $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Toolbar'), array(
                        $el('nav', '', lang('Breadcrumb'), array(
                            $el('ol', 'breadcrumb small mb-0', lang('Breadcrumb List'), array(
                                $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-body-emphasis link-offset-2 link-underline-opacity-25 link-underline-opacity-100-hover'))),
                                $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(
                                    $span($sections['us'][0], '', lang('Section'), $bind('section')),
                                ), array('_attrs' => array($attr('aria-current', 'page')))),
                            )),
                        ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb trail'))))),
                        $el('div', 'ms-auto', lang('Edit'), array($edit_button(lang('Change Status')))),
                    )),
                    $span($sections['us'][0], 'badge bg-primary-subtle text-primary-emphasis mb-3', lang('Section'), $bind('section')),
                    $heading('h1', lang('New light-rail line opens nine stations across downtown'), 'display-5 fw-bold mb-3 pn-headline', $bind('title')),
                    $para(lang('Thousands rode the first trains on opening day, as the line links the river district with the university campus.'), 'lead pn-dek mb-4', $bind('summary')),
                    // The words wrap beside the picture, never under it.
                    $el('div', 'd-flex align-items-center gap-3 py-3 mb-4 border-top border-bottom', lang('Article Details'), array(
                        $optional_image('byline_photo', '', 'rounded-circle object-fit-cover pg-circle-40', 'flex-shrink-0', $portrait($writer_name('Jane Cooper'), 96), ''),
                        $el('div', 'small pn-min-0', lang('Byline and Time'), array(
                            $span(lang('Written by'), 'visually-hidden', lang('Label')),
                            $span($writer_name('Jane Cooper'), 'fw-semibold', lang('Byline'), $bind('byline')),
                            $el('span', 'pg-hide-if-empty', lang('Dateline'), array(
                                $span('·', 'mx-1', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true')))),
                                $span(lang('Denver'), 'pg-field-value text-uppercase', lang('Value'), $bind('dateline')),
                            )),
                            $dot(),
                            $span(lang('Date'), 'text-body-secondary', lang('Date'), $bind('publish_date')),
                            $reading_time(),
                        )),
                    )),
                    $el('figure', 'mb-4 pg-hide-if-empty', lang('Cover Image'), array(
                        $el('span', 'pg-field-value d-block', lang('Picture Frame'), array(
                            $image($photo('light-rail', 1200, 675), lang('New light-rail line opens nine stations across downtown'),
                                'img-fluid w-100 rounded object-fit-cover pg-ratio-16x9', array('_bindings' => array('src' => 'cover_image', 'alt' => 'title'))),
                        )),
                        $el('figcaption', 'small text-body-secondary mt-2', lang('Caption'), array(
                            $el('span', 'pg-hide-if-empty', lang('Photo caption'), array(
                                $span(lang('Replace this picture with your own photo and caption.'), 'pg-field-value', lang('Value'), $bind('image_caption')),
                            )),
                            $el('span', 'pg-hide-if-empty ms-2', lang('Photo credit'), array(
                                $span(lang('Photo:'), 'me-1', lang('Label')),
                                $span(lang('Sample photo'), 'pg-field-value', lang('Value'), $bind('image_credit')),
                            )),
                        )),
                    )),
                    $ai_summary(),
                    // The article text is rich text: a div holds what the
                    // editor wrote, contained by the template's rules.
                    $el('div', 'pn-body', lang('Article text'), array(), array_merge(
                        array('text' => lang('The article text appears here.')), $bind('body'))),
                    $el('aside', 'pn-correction pg-hide-if-empty my-4', lang('Correction'), array(
                        $para(lang('Correction'), 'fw-bold mb-1'),
                        $el('div', 'pg-field-value', lang('Value'), array(), array_merge(
                            array('text' => lang('An earlier version of this article gave the wrong month for the survey\'s start.')), $bind('correction'))),
                    ), array('_attrs' => array($attr('aria-label', lang('Correction'))))),
                    $el('p', 'small mt-4 pg-hide-if-empty', lang('Topics'), array(
                        $span(lang('Topics:'), 'fw-semibold me-1', lang('Label')),
                        $span(lang('transit, downtown, city budget'), 'pg-field-value', lang('Value'), $bind('tags')),
                    )),
                    $el('p', 'small text-body-secondary mt-3', lang('Corrections Note'), array(
                        $span(lang('Spotted an error?'), 'me-1', lang('Text')),
                        $link(lang('Ask for a correction'), '{{page:contact}}', ''),
                        $span('·', 'mx-1', lang('Separator'), array('_attrs' => array($attr('aria-hidden', 'true')))),
                        $link(lang('Our corrections policy'), '{{page:standards}}#corrections', ''),
                    )),
                    $el('aside', 'card mt-5', lang('About the Writer'), array(
                        $el('div', 'card-body d-flex gap-3', lang('Card Body'), array(
                            $optional_image('byline_photo', '', 'pg-circle-60 rounded-circle object-fit-cover', 'flex-shrink-0', $portrait($writer_name('Jane Cooper'), 192), '', $lazy),
                            $el('div', 'pn-min-0', lang('Words'), array(
                                $para(lang('About the writer'), 'pn-kicker mb-1'),
                                $para($writer_name('Jane Cooper'), 'fw-bold mb-1', $bind('byline')),
                                $para($writers['Jane Cooper'][1], 'small text-body-secondary pg-hide-if-empty', $bind('byline_bio')),
                                $link(lang('Meet our writers'), '{{page:author}}#writers', 'small link-body-emphasis'),
                            )),
                        )),
                    ), array('_attrs' => array($attr('aria-label', lang('About the writer'))))),
                ), $show_when('record_found')),
                // The most read beside the article, so the right third of
                // the page is not empty from lg; on a phone it follows the
                // article. A missing article keeps its message alone.
                $el('div', 'col-lg-4', lang('Most read'), array($widget('most_read')), $show_when('record_found')),
                // The heading of the comments the page puts right below,
                // in the article's column.
                $el('div', 'col-lg-8', lang('Comments'), array(
                    $heading('h2', lang('Comments'), 'h4 mb-0'),
                ), $show_when('record_found')),
            ), '4', '', 'g-lg-5'),
        ), 'pt-4 pt-lg-5 pb-3'),
    )),
);

// ── The forms ───────────────────────────────────────────────────────────

// A field with a hint under it, which the field names as its description.
$field_help = function ($id, $label, $control, $help_text) use ($el, $attr, $help) {
    $help_id = $id . '-help';
    $control['props']['_attrs'][] = $attr('aria-describedby', $help_id);
    return $el('div', 'mb-3', $label, array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
        $help($help_id, $help_text),
    ));
};
// A group of fields under its legend.
$fieldset = function ($legend, $children) use ($el) {
    return $el('fieldset', 'mb-4', $legend, array_merge(array(
        $el('legend', 'h5 fw-bold mb-3 pb-2 border-bottom', lang('Legend'), array(), array('text' => $legend)),
    ), $children));
};

// The News Articles form, on the author desk. What an author fills in sits
// in four groups; the fields after them are for the editors only (office
// use): each in a box of its own with its label and no hint, so an author's
// form keeps nothing of them. A new article stores their selected option or
// nothing: Awaiting review, Standard, No, no publication time.
$widgets['article_form'] = array(
    'page'   => 'blog_new',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $fieldset(lang('News article'), array(
            $field('nw-section', lang('Section'), pg_cf_with_role($select('nw-section', 'section', lang('Choose a section…'), $section_titles), 'category')),
            $field('nw-title', lang('Headline'), pg_cf_with_role($input('nw-title', 'text', 'title', 'off', true, array(), array('maxlength' => '140')), 'title')),
            $field_help('nw-summary', lang('Summary'), pg_cf_with_role($textarea('nw-summary', 'summary', 3, true, '', array(), array('maxlength' => '300')), 'description'),
                lang('One or two sentences under the headline.')),
            $field('nw-dateline', lang('Dateline (city)'), $input('nw-dateline', 'text', 'dateline', 'off', false, array(), array('placeholder' => lang('e.g. Denver')))),
            $field('nw-body', lang('Article text'), $textarea('nw-body', 'body', 18, true, '', array('wysiwyg' => true))),
            $field_help('nw-tags', lang('Topics'), $input('nw-tags', 'text', 'tags', 'off', false), lang('Separate them with commas.')),
        )),
        $fieldset(lang('Photo'), array(
            $field('nw-cover', lang('Cover Image'), pg_cf_with_role($file('nw-cover', 'cover_image', 'public', 'image/*'), 'media')),
            $field('nw-caption', lang('Photo caption'), $input('nw-caption', 'text', 'image_caption', 'off', false)),
            $field('nw-credit', lang('Photo credit'), $input('nw-credit', 'text', 'image_credit', 'off', false, array(), array('placeholder' => lang('Who took the photo')))),
        )),
        $fieldset(lang('About you'), array(
            $field('nw-byline', lang('Name on the byline'), $input('nw-byline', 'text', 'byline', 'name', true, array(), array('placeholder' => lang('Your name as readers see it')))),
            $field('nw-byline-bio', lang('One line about you (optional)'), $input('nw-byline-bio', 'text', 'byline_bio', 'off', false, array(), array('maxlength' => '160'))),
            $field('nw-byline-photo', lang('Your photo for the byline (optional)'), $file('nw-byline-photo', 'byline_photo', 'public', 'image/*')),
        )),
        $fieldset(lang('For the editors'), array(
            $field('nw-note', lang('A note for the editors (not published)'), $textarea('nw-note', 'note_to_editors', 3, false)),
        )),
        $agree('nw-rights', 'rights', lang('I wrote this article myself, I may publish its photos, and the editors may edit it.')),
        // For the editors only.
        $field('nw-status', lang('Status'), $office($select('nw-status', 'status', '', $statuses, false, $statuses[0]))),
        $field('nw-publish', lang('Publication date and time (a future time schedules it)'), $office($input('nw-publish', 'datetime-local', 'publish_date', '', false))),
        $field('nw-placement', lang('Front page placement'), $office($select('nw-placement', 'placement', '', $placements, false, $placements[0]))),
        $field('nw-breaking', lang('Breaking news (shown in the strip for two days)'), $office($select('nw-breaking', 'breaking', '', array(lang('No'), lang('Yes')), false, lang('No')))),
        $field('nw-reading-time', lang('Reading time (minutes)'), $office($input('nw-reading-time', 'text', 'reading_time', 'off', false, array(), array('inputmode' => 'numeric')))),
        $field('nw-correction', lang('Correction (shown under the article)'), $office($textarea('nw-correction', 'correction', 2, false))),
        $captcha(),
        $submit(lang('Send to the desk'), 'btn btn-primary btn-lg px-4'),
        $loop(array()),
    )),
);

// The newsletter's own form, on its page: the address and the consent.
$widgets['newsletter_form'] = array(
    'page'   => 'newsletter',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array_merge(array(
        $icon('bi-envelope-paper', 'fs-1 text-primary d-block mb-2'),
        $heading('h2', lang('The Morning Briefing'), 'h4 mb-2'),
        $para(lang('Leave your e-mail address and get the top stories of the day every morning.'), 'text-body-secondary mb-4'),
    ), $newsletter_fields('nw-news'), array(
        $loop(array()),
    ))),
);
// The newsletter page's form again, beside the section pages (a box) and
// on the front page (a dark band), so every sign-up lands in one list. Each
// carries the CAPTCHA: a visitor's sign-up is checked against it whenever
// it is on.
$newsletter_existing = array(
    'regionType'          => 'custom_form',
    'form_source'         => 'existing',
    'form_id'             => '{{tab:newsletter}}',
    'custom_form_page_id' => '{{tab:newsletter}}',
);
$widgets['newsletter_box'] = array(
    'page'   => '',
    'slug'   => lang('newsletter'),
    'config' => $newsletter_existing,
    'tree'   => $widget_root(array(
        $el('aside', 'p-4 text-bg-primary', lang('Newsletter'), array_merge(array(
            $heading('h2', lang('The Morning Briefing'), 'h5 mb-2'),
            $para(lang('The top stories of the day, in your inbox every morning.'), 'small mb-3'),
        ), $newsletter_fields('nw-box', 'btn btn-warning')), array('_attrs' => array($attr('aria-label', lang('Newsletter'))))),
        $loop(array()),
    )),
);
$widgets['newsletter_band'] = array(
    'page'   => '',
    'slug'   => lang('newsletter'),
    'config' => $newsletter_existing,
    'tree'   => $widget_root(array(
        // Rules above and below show the band's edges when the page itself
        // is dark.
        $el('section', 'py-5 bg-body text-body border-top border-bottom', lang('Newsletter Band'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('The Morning Briefing'), 'h3 mb-2'),
                        $para(lang('The top stories of the day and a note from our editors, in your inbox every morning. Unsubscribe whenever you like.'), 'text-body-secondary mb-0'),
                    ), '', '5'),
                    $col($newsletter_fields('nw-band'), '', '7'),
                ), '4', '', 'align-items-center'),
            )),
        ), array('_attrs' => array($attr('data-bs-theme', 'dark')))),
        $loop(array()),
    )),
);

// A tip: no account is made for whoever sends it, and the attachment is
// kept in the private folder, for the staff only.
$widgets['tips_form'] = array(
    'page'   => 'tips',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $row(array(
            $col(array($field('nw-tip-name', lang('Your name (optional)'), $input('nw-tip-name', 'text', 'name', 'name', false))), '6'),
            $col(array($field('nw-tip-email', lang('Your e-mail (optional, for our answer)'), $input('nw-tip-email', 'email', 'email', 'email', false))), '6'),
        ), '3'),
        $field('nw-tip-topic', lang('Topic'), $select('nw-tip-topic', 'topic', lang('Choose a topic…'), array(
            lang('Local news'),
            lang('Government and politics'),
            lang('Business and work'),
            lang('Something else'),
        ))),
        $field('nw-tip-text', lang('What should we know?'), $textarea('nw-tip-text', 'tip', 6)),
        $field('nw-tip-file', lang('A document or photo (optional)'), $file('nw-tip-file', 'attachment', 'private', 'image/*,application/pdf')),
        $agree('nw-tip-consent', 'consent', lang('I understand that the newsroom decides whether and how to use this tip.')),
        $captcha(),
        $submit(lang('Send the tip')),
        $loop(array()),
    )),
);

// Contact: the fields are the page's form.
$widgets['contact_form'] = array(
    'page'   => 'contact',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $person_fields('nw-contact', false, false),
        $field('nw-contact-topic', lang('What is it about?'), $select('nw-contact-topic', 'topic', lang('Choose a topic…'), array(
            lang('A question for the newsroom'),
            lang('Report an error'),
            lang('Write for us'),
            lang('Advertising'),
            lang('Something else'),
        ))),
        $field('nw-contact-message', lang('Your Message'), $textarea('nw-contact-message', 'message', 6)),
        $captcha(),
        $submit(lang('Send the message')),
        $loop(array()),
    )),
);

// The site search: the whole site for ?query=, ten results a page. A
// result that matches a part of a page links to that part.
$widgets['site_search'] = array(
    'page'   => 'search',
    'slug'   => lang('search'),
    'config' => array(
        'regionType'       => 'search_results',
        'search_scope'     => 'all',
        'results_per_page' => 10,
        'empty_message'    => lang('Nothing matched your search. Try fewer or different words.'),
    ),
    'tree'   => $widget_root(array(
        $el('form', 'mb-4', lang('Search Form'), array(
            $el('div', 'input-group input-group-lg', lang('Search Box'), array(
                $el('input', 'form-control', lang('Field'), array(), array(
                    '_attrs'    => array($attr('type', 'search'), $attr('placeholder', lang('Search news…')), $attr('aria-label', lang('Search news'))),
                    '_bindings' => array('value' => 'search_query'),
                )),
                $el('button', 'btn btn-primary px-4', lang('Search Button'), array($icon('bi-search')), array(
                    '_attrs'    => array($attr('type', 'submit'), $attr('aria-label', lang('Search'))),
                    '_bindings' => array('action' => 'submit_search'),
                )),
            )),
        ), array('_bindings' => array('action' => 'search_form'))),
        $para(lang('Results heading'), 'fw-semibold pb-3 mb-0 border-bottom', $bind('__results_heading')),
        $el('div', '', lang('Results'), array(
            $loop(array(
                $el('article', 'pn-story py-3 border-bottom position-relative', lang('Result Row'), array(
                    $span(lang('Page'), 'badge bg-secondary-subtle text-secondary-emphasis mb-2', lang('Result Type'), $bind('__result_type_label')),
                    $el('h2', 'h5 pn-headline mb-1', lang('Result Title'), array(
                        $link(lang('Title'), '#', 'stretched-link text-reset text-decoration-none', array(),
                            array('_bindings' => array('href' => '__result_url', 'text' => '__result_title'))),
                    )),
                    $para(lang('Corrections'), 'pn-kicker mb-1', array_merge(array('customName' => lang('Result Section')), $bind('__result_section'))),
                    $para(lang('Excerpt'), 'small text-body-secondary mb-0', $bind('__result_excerpt')),
                )),
            )),
        )),
        $el('div', 'mt-4', lang('Pagination'), array(), array('_bindings' => array('section' => 'pagination'))),
    )),
);

// ── The e-mails' widgets ────────────────────────────────────────────────
// Drawn for the record just sent, which the server names when it sends the
// e-mail. Opened by its address instead (?r=), the page shows the record
// only to whoever sent it and to the editors.
$mail_widget = function ($page_key, $form_page, $not_found, $children) use ($widget_root) {
    return array(
        'page'   => $page_key,
        'slug'   => lang('form-detail'),
        'config' => array(
            'regionType'          => 'form_item_view',
            'custom_form_page_id' => '{{tab:' . $form_page . '}}',
            'access_control'      => 'submitter_only',
            'not_found_message'   => $not_found,
        ),
        'tree'   => $widget_root($children),
    );
};
// "Hello," and the name the record carries, in an element of its own: a
// translated page matches whole texts.
$mail_greeting = function ($token, $sample) use ($el, $span, $bind, $mail_style) {
    return $el('p', 'mb-3', lang('Greeting'), array(
        $span(lang('Hello,'), 'me-1', lang('Label'), $mail_style('margin-right:4px')),
        $span($sample, '', lang('Name'), $bind($token)),
    ), $mail_style('margin:0 0 16px'));
};
// One value of the record on a line of an e-mail's box.
$mail_value = function ($label, $sample, $token) use ($mail_line, $span, $bind) {
    return $mail_line($label, array($span($sample, '', $label, $bind($token))));
};
// Whoever sent the contact form.
$mail_person = function ($label) use ($mail_box, $mail_line, $person_name, $mail_value) {
    return $mail_box(array(
        $mail_line($label, $person_name()),
        $mail_value(lang('Email'), lang('jane.cooper@example.com'), 'email'),
        $mail_value(lang('Phone'), '(303) 555-0142', 'phone'),
    ), $label);
};
// The account the contact form opened for a visitor who had none: the
// address it signs in with, and how to choose a password (the set
// password link is shown on the conversation's page, not mailed).
$mail_new_account = function () use ($el, $span, $link, $bind, $mail_style, $show_when) {
    return $el('div', 'border-top pt-3', lang('New Account'), array(
        $el('p', 'mb-2', lang('Sign-in Email'), array(
            $span(lang('We opened an account for you with this address:') . ' ', '', lang('Label')),
            $span('sample@example.com', 'fw-semibold', lang('Value'), $bind('__new_account_email')),
        ), $mail_style('margin:0 0 8px')),
        $el('p', 'mb-0', lang('Password'), array(
            $span(lang('To choose your password, use') . ' ', '', lang('Label')),
            $link(lang('Forgot Password'), '{{page:forgot_password}}', ''),
        ), $mail_style('margin:0')),
    ), array_merge($mail_style('border-top:1px solid #d8dfe9;padding-top:16px'), $show_when('has_new_account')));
};
// The article's lines: all of them for the editors, the headline and the
// section for the author.
$mail_article = function ($full) use ($mail_box, $mail_value, $sections, $writer_name) {
    $lines = array(
        $mail_value(lang('Headline'), lang('City council approves a plan to plant 10,000 street trees'), 'title'),
        $mail_value(lang('Section'), $sections['us'][0], 'section'),
    );
    if ($full) {
        $lines[] = $mail_value(lang('Name on the byline'), $writer_name('Jane Cooper'), 'byline');
        $lines[] = $mail_value(lang('Summary'), lang('Planting begins in the neighborhoods with the least shade, and residents can ask for a tree in front of their home.'), 'summary');
        $lines[] = $mail_value(lang('A note for the editors'), lang('I can add a photo of the council vote if you need one.'), 'note_to_editors');
    }
    return $mail_box($lines, lang('News article'));
};
$widgets['email_article_admin'] = $mail_widget('email_article_new', 'blog_new', lang('The article could not be found.'), array(
    $mail_heading(lang('A new article is waiting for review')),
    $mail_para(lang('It came from the Submit an Article page. Read it on the desk, change its status in the control panel and write to the author under it.')),
    $mail_article(true),
    $mail_button(lang('Open it on the desk'), '{{page:submission}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_article_author'] = $mail_widget('email_article_received', 'blog_new', lang('The article could not be found.'), array(
    $mail_heading(lang('Your article reached the desk')),
    $mail_greeting('byline', $writer_name('Jane Cooper')),
    $mail_para(lang('An editor reads every article. Follow its status under My Articles; we answer your messages on its page.')),
    $mail_article(false),
    $mail_para(lang('Nothing you send is shown to readers until an editor sets it live.')),
    $mail_button(lang('Follow my article'), '{{page:submission}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_tip_admin'] = $mail_widget('email_tip_new', 'tips', lang('The tip could not be found.'), array(
    $mail_heading(lang('A new tip')),
    $mail_para(lang('It came from the tip page. Whoever sent it may have left no name.')),
    $mail_box(array(
        $mail_value(lang('Topic'), lang('Local news'), 'topic'),
        $mail_value(lang('Name'), lang('Jane Cooper'), 'name'),
        $mail_value(lang('Email'), lang('jane.cooper@example.com'), 'email'),
        $mail_value(lang('Account'), lang('jane.cooper'), 'submitter'),
    ), lang('Tip')),
    $el('div', 'mb-3', lang('Tip'), array(), array_merge(
        array('text' => lang('The bridge on the river road has been closed for repairs for weeks, but nobody is working on it.')),
        $bind('tip'), $mail_style('margin:0 0 16px'))),
    $mail_small(lang('The attachment, if any, opens from the tip on the newsroom desk.')),
    $mail_button(lang('Open the newsroom desk'), '{{page:staff}}#tips'),
    $mail_reference(),
));
$widgets['email_tip_sender'] = $mail_widget('email_tip_received', 'tips', lang('The tip could not be found.'), array(
    $mail_heading(lang('Thank you for your tip')),
    $mail_para(lang('An editor reads every tip. We write back when we need to know more.')),
    $mail_box(array(
        $el('p', 'fw-semibold mb-2', lang('Topic'), array(), array_merge(
            array('text' => lang('Local news'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
        $el('div', '', lang('Tip'), array(), array_merge(
            array('text' => lang('The bridge on the river road has been closed for repairs for weeks, but nobody is working on it.')), $bind('tip'))),
    ), lang('Your tip')),
));
$widgets['email_message_admin'] = $mail_widget('email_new_message', 'contact', lang('The message could not be found.'), array(
    $mail_heading(lang('A new message has arrived')),
    $mail_para(lang('It came from the contact page. Answer it on the conversation page: the answer reaches the sender by e-mail and in their inbox.')),
    $mail_person(lang('Sender')),
    $mail_box(array(
        $mail_value(lang('Subject'), lang('Report an error'), 'topic'),
    ), lang('Subject')),
    $el('div', 'mb-3', lang('Message'), array(), array_merge(
        array('text' => lang('Hello, the photo caption in your article about the new pocket parks names the wrong street.')),
        $bind('message'), $mail_style('margin:0 0 16px'))),
    $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
    $mail_reference(),
));
$widgets['email_message_sender'] = $mail_widget('email_message_received', 'contact', lang('The message could not be found.'), array(
    $mail_heading(lang('Your message has reached us')),
    $mail_greeting('first_name', lang('Jane')),
    $mail_para(lang('Thank you for writing to the newsroom. Our answer will be on the conversation page and in your inbox, and we will let you know by e-mail when it is there.')),
    $mail_box(array(
        $el('p', 'fw-semibold mb-2', lang('Subject'), array(), array_merge(
            array('text' => lang('Report an error'), '_attrs' => array($attr('style', 'font-weight:600;margin:0 0 8px'))), $bind('topic'))),
        $el('div', '', lang('Message'), array(), array_merge(
            array('text' => lang('Hello, the photo caption in your article about the new pocket parks names the wrong street.')), $bind('message'))),
    ), lang('Your Message')),
    $mail_button(lang('Open the conversation'), '{{page:conversation}}?r=^^reference_code^^'),
    $mail_new_account(),
));

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// Parts inside a shared component or a widget belong to that, not to the
// page, so only the page's own parts are listed here.
$search_sections = array(
    'about' => array(
        '#mission'      => array('mission', lang('about us, mission, independent newsroom, who we are')),
        '#newsroom'     => array('newsroom', lang('editors, reporters, newsroom, staff')),
        '#write-for-us' => array('write-for-us', lang('write for us, contribute, submit an article, freelance, author access')),
    ),
    'standards' => array(
        '#independence' => array('independence', lang('ethics, independence, ownership')),
        '#sources'      => array('sources', lang('sources, anonymous sources, fact checking')),
        '#corrections'  => array('corrections', lang('corrections, report an error, mistakes')),
    ),
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, newsroom')),
    ),
    'tips' => array(
        '#how-tips-work' => array('how-tips-work', lang('tip, story idea, documents, confidential')),
    ),
    'newsletter' => array(
        '#briefing' => array('briefing', lang('newsletter, morning briefing, e-mail')),
    ),
    'author' => array(
        '#writers' => array('writers', lang('writers, reporters, columnists, staff writers')),
    ),
    'opinion' => array(
        '#columnists' => array('columnists', lang('columnists, opinion, op-ed')),
    ),
    'privacy' => array(
        '#your-data' => array('your-data', lang('privacy, personal data, cookies')),
    ),
    'terms' => array(
        '#using-the-site' => array('using-the-site', lang('terms, rules, comments policy')),
    ),
    'accessibility' => array(
        '#accessibility-help' => array('accessibility-help', lang('accessibility, screen reader, keyboard')),
    ),
);
$searchable_tree = function ($node, $marks, &$used) use (&$searchable_tree) {
    if (!is_array($node)) return $node;
    if (isset($node['props']) && is_array($node['props'])) {
        $p = $node['props'];
        $name = isset($p['customName']) ? (string)$p['customName'] : (isset($p['_label']) ? (string)$p['_label'] : '');
        $key = null;
        if (!empty($p['id']) && isset($marks['#' . $p['id']]) && !isset($used['#' . $p['id']])) {
            $key = '#' . $p['id'];
        } elseif ($name !== '' && isset($marks[$name]) && !isset($used[$name])) {
            $key = $name;
        }
        if ($key !== null) {
            $used[$key] = true;
            if (empty($p['id'])) $node['props']['id'] = $marks[$key][0];
            $node['props']['searchSection'] = true;
            if ($marks[$key][1] !== '') $node['props']['searchKeywords'] = $marks[$key][1];
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) $node['children'][$i] = $searchable_tree($child, $marks, $used);
    }
    return $node;
};
foreach ($pages as $i => $p) {
    if (!isset($search_sections[$p['key']])) continue;
    $used = array();
    $pages[$i]['tree'] = $searchable_tree($p['tree'], $search_sections[$p['key']], $used);
}

return array(
    'name'        => lang('News Portal'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 70,
    'icon'        => 'bi-newspaper',
    // The picture on the template's card and beside the designs made from
    // it: a news site's front page (pg_design_thumb_svg()).
    'thumb'       => 'news',
    'description' => lang('A city news site: a front page with a lead story grid, section pages, writers, most read and a breaking strip, with author submissions that editors review and publish.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'modern-sharp',
    'palette'     => 'cobalt',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('A front page with the lead stories, a featured row and a block for every section, a page for each section and the writers with their own articles.'),
        lang('Authors send articles from their desk; editors review, schedule and set them live, and talk to the author under each article.'),
        lang('Most read, a breaking news strip and RSS feeds, all from the site\'s own articles.'),
        lang('Reader accounts with comments and an inbox, a newsroom desk, a tip line and the Morning Briefing newsletter.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The author desk lives in contributors, a private folder under the
    // staff's: authors get view access to it, editors edit access to private.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_news', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'contributors' => array('name' => 'contributors', 'parent' => 'private', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Morning Briefing'),
            'description'  => lang('The addresses left on the newsletter form of the news site.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'topbar'       => array('name' => lang('News Top Bar'), 'tree' => $topbar()),
        'masthead'     => array('name' => lang('News Masthead'), 'tree' => $masthead()),
        'sections_nav' => array('name' => lang('News Sections Menu'), 'tree' => $sections_nav()),
        'breaking'     => array('name' => lang('News Breaking Strip'), 'tree' => $breaking()),
        'footer'       => array('name' => lang('News Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Newsroom Bar'), 'tree' => $staff_bar()),
        'email_header' => array('name' => lang('News E-mail Header'), 'tree' =>
            $el('div', 'px-4 py-3 border-bottom', lang('E-mail Header'), array(
                $link('{{site_name}}', '{{page:home}}', 'fs-5 fw-bold text-decoration-none link-primary',
                    array($attr('style', 'font-size:20px;font-weight:800;letter-spacing:.02em;text-decoration:none;color:#0047ab;color:var(--bs-primary,#0047ab)'))),
            ), $mail_style('padding:16px 24px;border-bottom:1px solid #d8dfe9'))),
        'email_footer' => array('name' => lang('News E-mail Footer'), 'tree' =>
            $el('div', 'px-4 py-3 border-top small text-body-secondary', lang('E-mail Footer'), array(
                $para('© {{year}} {{site_name}}', 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $para(lang('An independent newsroom covering the city, the country and the world.'), 'mb-1', array('_attrs' => array($attr('style', 'margin:0 0 4px')))),
                $link(lang('Visit our website'), '{{page:home}}', 'link-secondary',
                    array($attr('style', 'color:#4a5568'))),
            ), $mail_style('padding:16px 24px;border-top:1px solid #d8dfe9;font-size:13px;color:#4a5568'))),
    ),

    // The template's own look: the blocks' rules it uses and its pn- rules
    // in one User Styles entry, and its three fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(
            array('news-ticker', 'pg-marquee-fade', 'pg-ratio-16x9', 'pg-thumb-100', 'pg-circle-40', 'pg-circle-60'),
            $news_css),
        'fonts' => json_encode($news_fonts),
    ),
);
